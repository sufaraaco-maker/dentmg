<?php

namespace App\Services;

use Anthropic\Client;
use App\Enums\AiInteractionFeature;
use App\Enums\AppointmentStatus;
use App\Enums\ClinicalNoteStatus;
use App\Enums\DentalChartEntryStatus;
use App\Enums\PaymentMethod;
use App\Enums\TreatmentPlanStatus;
use App\Exceptions\AiAssistant\AiAssistantDisabledException;
use App\Exceptions\AiAssistant\AiAssistantPhiNotAcknowledgedException;
use App\Exceptions\AiAssistant\AiAssistantUnavailableException;
use App\Exceptions\ClinicalNote\ClinicalNoteLockedException;
use App\Exceptions\TreatmentPlan\TreatmentPlanItemLockedException;
use App\Models\AiInteractionLog;
use App\Models\ClinicalNote;
use App\Models\DentalChartEntry;
use App\Models\DentalCondition;
use App\Models\Patient;
use App\Models\TreatmentPlan;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Orchestrates every AI Assistant feature (design doc §6/§4) — the single place that talks to the
 * Claude API. Two shapes: a bounded tool-use loop for Dashboard Insights/Smart Search (Claude picks
 * an existing Service method as a "tool," this class executes the real PHP method — the model never
 * runs its own query), and a single structured-output completion for Writing Reports narratives/
 * Clinical Notes draft-assist/Treatment Suggestions. No conversation history is retained server-side
 * between requests (design doc §5) — every call here is self-contained.
 */
class AiAssistantService
{
    /**
     * Hard ceiling on the tool-use loop (design doc §2: "no long-running agentic loop"). Every
     * named use case is a single bounded request — a runaway tool-selection loop is a bug, not a
     * feature, so this is a safety rail, not a tuning knob.
     */
    private const MAX_TOOL_ITERATIONS = 3;

    public function __construct(
        private readonly ClinicSettingService $clinicSettingService,
        private readonly ReportService $reportService,
        private readonly PatientService $patientService,
        private readonly AppointmentService $appointmentService,
    ) {}

    /**
     * Dashboard Insights (design doc §4.1) — Claude picks one of `ReportService`'s six methods as a
     * tool; only the `summary` block (never `rows`, which contain patient names) is fed back to the
     * model. The tool list itself is filtered to what `$actor` may see per the existing
     * `view-financial-reports`/`view-operational-reports` Gates, so a receptionist's question about
     * Production/Collections/A-R Aging never reaches the model as an available tool at all.
     */
    public function dashboardInsight(string $question, User $actor): AiInteractionLog
    {
        $this->assertFeatureAvailable(AiInteractionFeature::DashboardInsight);

        $tools = $this->reportTools($actor);

        $system = 'You answer questions about this dental clinic\'s operational and financial data by '
            .'calling exactly one of the provided report tools, then phrasing the returned summary as '
            .'one or two plain-language sentences. Never invent a number that wasn\'t returned by a '
            .'tool call. If the question needs a date range and none is given, assume the last 30 days. '
            .'If no available tool can answer the question, say so plainly instead of guessing.';

        [$answer, $toolCall] = $this->runToolLoop($system, $tools, $question, fn (string $name, array $input) => $this->executeReportTool($name, $input));

        return $this->log(AiInteractionFeature::DashboardInsight, $actor, null, $question, $answer);
    }

    /**
     * Smart Search (design doc §4.2) — Claude translates the query into parameters for the existing
     * `PatientService::paginate()`/`AppointmentService::search()`; the model never receives patient
     * PHI as input, only the searcher's own typed text, and the results returned are exactly what
     * `PatientPolicy`/`AppointmentPolicy` already allow.
     */
    public function smartSearch(string $query, User $actor): AiInteractionLog
    {
        $this->assertFeatureAvailable(AiInteractionFeature::SmartSearch);

        $tools = [
            [
                'name' => 'search_patients',
                'description' => 'Search patients by name, phone, national ID, or email substring match.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'search' => ['type' => 'string', 'description' => 'Substring to match against patient name/phone/national_id/email.'],
                    ],
                    'required' => ['search'],
                ],
            ],
            [
                'name' => 'search_appointments',
                'description' => 'Search appointments within a date range, optionally filtered by dentist, patient, or status.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'date_from' => ['type' => 'string', 'format' => 'date'],
                        'date_to' => ['type' => 'string', 'format' => 'date'],
                        'dentist_id' => ['type' => 'string', 'description' => 'UUID of the dentist, if the query names one.'],
                        'status' => ['type' => 'string', 'enum' => array_map(fn ($case) => $case->value, AppointmentStatus::cases())],
                    ],
                    'required' => ['date_from', 'date_to'],
                ],
            ],
        ];

        $system = 'You translate a plain-language search request into exactly one call to '
            .'search_patients or search_appointments, then summarize what was found in one or two '
            .'sentences. Never fabricate a patient or appointment that wasn\'t in the tool result.';

        [$answer] = $this->runToolLoop($system, $tools, $query, fn (string $name, array $input) => $this->executeSearchTool($name, $input));

        return $this->log(AiInteractionFeature::SmartSearch, $actor, null, $query, $answer);
    }

    /**
     * Writing Reports narrative (design doc §4.3) — recomputes the report itself via
     * `ReportService` (identical data boundary to Dashboard Insights: only `summary`, never
     * `rows`, is ever sent to the model) rather than trusting a client-supplied summary, so every
     * number the model narrates is one this server actually computed. Single prompt-to-prose
     * completion, no tool use — the report type is already chosen by the "Explain this report"
     * button, not something the model picks.
     *
     * @param  array<string, mixed>  $params
     */
    public function reportNarrative(string $reportType, array $params, User $actor): AiInteractionLog
    {
        $this->assertFeatureAvailable(AiInteractionFeature::ReportNarrative);

        $summary = $this->executeReportTool('report_'.$reportType, $params);

        $system = 'You turn a dental clinic report\'s aggregate summary numbers into a short, plain-'
            .'language narrative (2-4 sentences) suitable for a practice-owner memo. Only reference '
            .'numbers present in the given summary — never invent or estimate a figure.';

        $userInput = "Report: {$reportType}\nSummary data: ".json_encode($summary);

        $response = $this->client()->messages->create(
            model: config('services.anthropic.model'),
            maxTokens: 1024,
            system: $system,
            outputConfig: ['effort' => 'low'],
            messages: [['role' => 'user', 'content' => $userInput]],
        );

        $answer = $this->extractText($response);

        return $this->log(AiInteractionFeature::ReportNarrative, $actor, null, "Explain {$reportType} report", $answer);
    }

    /**
     * Clinical Notes draft-assist (design doc §4.4, PHI-bearing) — expands the dentist's own typed
     * shorthand into SOAP-structured prose via structured outputs. Only ever available on a
     * **draft** note — reuses the exact same `ClinicalNoteLockedException` gate every other edit
     * path already respects (design doc §4.4). The drafted text is returned to the caller for
     * review — it is never written to the database by this method (design doc §0's
     * review-and-accept constraint).
     *
     * @return array{subjective: string, objective: string, assessment: string, plan: string, log: AiInteractionLog}
     */
    public function draftClinicalNote(ClinicalNote $note, string $shorthand, User $actor): array
    {
        $this->assertFeatureAvailable(AiInteractionFeature::ClinicalNoteDraft);

        if ($note->status === ClinicalNoteStatus::Signed) {
            throw new ClinicalNoteLockedException;
        }

        $schema = [
            'type' => 'object',
            'properties' => [
                'subjective' => ['type' => 'string'],
                'objective' => ['type' => 'string'],
                'assessment' => ['type' => 'string'],
                'plan' => ['type' => 'string'],
            ],
            'required' => ['subjective', 'objective', 'assessment', 'plan'],
            'additionalProperties' => false,
        ];

        $system = 'You are a dental clinical documentation assistant. Expand a dentist\'s rough '
            .'shorthand notes into SOAP-structured prose (Subjective/Objective/Assessment/Plan). Use '
            .'only the clinical content the dentist provided — never invent findings, diagnoses, or '
            .'treatments not implied by the shorthand. Write in a clinical, professional register.';

        $response = $this->client()->messages->create(
            model: config('services.anthropic.model'),
            maxTokens: 2048,
            system: $system,
            outputConfig: ['effort' => 'medium', 'format' => ['type' => 'json_schema', 'schema' => $schema]],
            messages: [['role' => 'user', 'content' => "Dentist's shorthand notes:\n{$shorthand}"]],
        );

        $draft = json_decode($this->extractText($response), true);

        $log = $this->log(
            AiInteractionFeature::ClinicalNoteDraft,
            $actor,
            $note->patient_id,
            $shorthand,
            json_encode($draft),
        );

        return [
            'subjective' => $draft['subjective'],
            'objective' => $draft['objective'],
            'assessment' => $draft['assessment'],
            'plan' => $draft['plan'],
            'log' => $log,
        ];
    }

    /**
     * Treatment Suggestions (design doc §4.5, PHI-bearing) — reads the patient's active (non-
     * cancelled) Dental Chart findings plus the active `dental_conditions` catalog and proposes
     * candidate treatment plan items. Only ever available on a **draft** plan — reuses the exact
     * same lock `TreatmentPlanService::addItem()` itself enforces. No `TreatmentPlanItem` is ever
     * created by this method — the returned candidates are added one-by-one through the existing
     * `TreatmentPlanService::addItem()` only once the dentist explicitly accepts each one.
     *
     * @return array{candidates: list<array{dental_condition_id: string, tooth_number: ?string, surfaces: ?list<string>, rationale: string}>, log: AiInteractionLog}
     */
    public function suggestTreatmentItems(TreatmentPlan $plan, User $actor): array
    {
        $this->assertFeatureAvailable(AiInteractionFeature::TreatmentSuggestion);

        if ($plan->status !== TreatmentPlanStatus::Draft) {
            throw new TreatmentPlanItemLockedException(
                'Treatment suggestions are only available while the treatment plan is still a draft.'
            );
        }

        $findings = DentalChartEntry::query()
            ->where('patient_id', $plan->patient_id)
            ->where('status', '!=', DentalChartEntryStatus::Cancelled->value)
            ->with('dentalCondition')
            ->get()
            ->map(fn (DentalChartEntry $entry) => [
                'finding' => $entry->dentalCondition->name,
                'tooth_number' => $entry->tooth_number,
                'surfaces' => $entry->surfaces,
                'status' => $entry->status->value,
            ]);

        $catalog = DentalCondition::query()
            ->where('is_active', true)
            ->get(['id', 'name', 'category', 'applies_to_surface'])
            ->map(fn (DentalCondition $condition) => [
                'dental_condition_id' => $condition->id,
                'name' => $condition->name,
                'category' => $condition->category,
            ]);

        $schema = [
            'type' => 'object',
            'properties' => [
                'candidates' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'dental_condition_id' => ['type' => 'string', 'description' => 'Must be one of the provided catalog IDs.'],
                            'tooth_number' => ['type' => ['string', 'null'], 'description' => 'FDI/Universal tooth number as it appears in the chart findings, e.g. "14".'],
                            'surfaces' => [
                                'type' => ['array', 'null'],
                                'items' => ['type' => 'string', 'enum' => ['M', 'D', 'F', 'L', 'O', 'I']],
                                'description' => 'Tooth surfaces this procedure applies to (Mesial/Distal/Facial/Lingual/Occlusal/Incisal); null when not tooth-specific.',
                            ],
                            'rationale' => ['type' => 'string'],
                        ],
                        'required' => ['dental_condition_id', 'tooth_number', 'surfaces', 'rationale'],
                        'additionalProperties' => false,
                    ],
                ],
            ],
            'required' => ['candidates'],
            'additionalProperties' => false,
        ];

        $system = 'You are a dental treatment-planning assistant. Given a patient\'s current chart '
            .'findings and the clinic\'s procedure catalog, propose candidate treatment plan items a '
            .'dentist might consider adding. Only use dental_condition_id values from the given '
            .'catalog. This is decision support only — the dentist reviews and selects every item; '
            .'never propose more than a handful of the most clinically relevant candidates.';

        $userInput = 'Chart findings: '.json_encode($findings).
            "\nProcedure catalog: ".json_encode($catalog);

        $response = $this->client()->messages->create(
            model: config('services.anthropic.model'),
            maxTokens: 2048,
            system: $system,
            outputConfig: ['effort' => 'medium', 'format' => ['type' => 'json_schema', 'schema' => $schema]],
            messages: [['role' => 'user', 'content' => $userInput]],
        );

        $result = json_decode($this->extractText($response), true);

        $log = $this->log(
            AiInteractionFeature::TreatmentSuggestion,
            $actor,
            $plan->patient_id,
            'Suggest treatment items for plan '.$plan->id,
            json_encode($result['candidates']),
        );

        return ['candidates' => $result['candidates'], 'log' => $log];
    }

    /**
     * Records the user's accept/edit/reject decision on a PHI-bearing suggestion (design doc §5's
     * auditability requirement) — the same request that performs the accept keeps this log row in
     * sync, per §3's `response_summary` note.
     */
    public function recordAcceptance(AiInteractionLog $log, bool $accepted, ?string $finalResponse = null): AiInteractionLog
    {
        $log->accepted = $accepted;

        if ($finalResponse !== null) {
            $log->response_summary = $finalResponse;
        }

        $log->save();

        return $log;
    }

    public function assertFeatureAvailable(AiInteractionFeature $feature): void
    {
        $settings = $this->clinicSettingService->current();

        if (! $settings->ai_assistant_enabled) {
            throw new AiAssistantDisabledException;
        }

        if ($feature->isPhiBearing() && ! $settings->ai_assistant_phi_features_acknowledged) {
            throw new AiAssistantPhiNotAcknowledgedException;
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function reportTools(User $actor): array
    {
        $tools = [];

        if (Gate::forUser($actor)->allows('view-operational-reports')) {
            $tools[] = [
                'name' => 'report_appointment_analytics',
                'description' => 'Appointment volume, status breakdown, no-show/cancellation rates for a date range.',
                'input_schema' => $this->dateRangeSchema(withDentist: true),
            ];
            $tools[] = [
                'name' => 'report_treatment_plan_acceptance',
                'description' => 'Treatment plan presentation/acceptance/rejection counts and rate for a date range.',
                'input_schema' => $this->dateRangeSchema(withDentist: true),
            ];
            $tools[] = [
                'name' => 'report_new_patients',
                'description' => 'New patient registration counts for a date range.',
                'input_schema' => $this->dateRangeSchema(withDentist: false),
            ];
        }

        if (Gate::forUser($actor)->allows('view-financial-reports')) {
            $tools[] = [
                'name' => 'report_production',
                'description' => 'Total production (charges) and breakdown by dentist for a date range.',
                'input_schema' => $this->dateRangeSchema(withDentist: true),
            ];
            $tools[] = [
                'name' => 'report_collections',
                'description' => 'Total payments collected and breakdown by method for a date range.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'date_from' => ['type' => 'string', 'format' => 'date'],
                        'date_to' => ['type' => 'string', 'format' => 'date'],
                        'method' => ['type' => 'string', 'enum' => array_map(fn ($case) => $case->value, PaymentMethod::cases())],
                    ],
                    'required' => ['date_from', 'date_to'],
                ],
            ];
            $tools[] = [
                'name' => 'report_ar_aging',
                'description' => 'Current accounts-receivable aging snapshot (as of today), bucketed by days overdue.',
                'input_schema' => ['type' => 'object', 'properties' => new \stdClass, 'required' => []],
            ];
        }

        return $tools;
    }

    /**
     * @return array<string, mixed>
     */
    private function dateRangeSchema(bool $withDentist): array
    {
        $properties = [
            'date_from' => ['type' => 'string', 'format' => 'date'],
            'date_to' => ['type' => 'string', 'format' => 'date'],
        ];

        if ($withDentist) {
            $properties['dentist_id'] = ['type' => 'string', 'description' => 'UUID of the dentist, if the query names one.'];
        }

        return [
            'type' => 'object',
            'properties' => $properties,
            'required' => ['date_from', 'date_to'],
        ];
    }

    /**
     * Executes the real `ReportService` method the model picked and returns only the `summary`
     * block — `rows` (patient-identified detail) never leaves the server (design doc §4.1).
     *
     * @param  array<string, mixed>  $input
     */
    private function executeReportTool(string $name, array $input): mixed
    {
        $data = match ($name) {
            'report_production' => $this->reportService->production($input['date_from'], $input['date_to'], $input['dentist_id'] ?? null),
            'report_collections' => $this->reportService->collections($input['date_from'], $input['date_to'], isset($input['method']) ? PaymentMethod::from($input['method']) : null),
            'report_ar_aging' => $this->reportService->arAging(),
            'report_appointment_analytics' => $this->reportService->appointmentAnalytics($input['date_from'], $input['date_to'], $input['dentist_id'] ?? null),
            'report_treatment_plan_acceptance' => $this->reportService->treatmentPlanAcceptance($input['date_from'], $input['date_to'], $input['dentist_id'] ?? null),
            'report_new_patients' => $this->reportService->newPatients($input['date_from'], $input['date_to']),
            default => throw new \InvalidArgumentException("Unknown report tool: {$name}"),
        };

        return $data['summary'];
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function executeSearchTool(string $name, array $input): mixed
    {
        return match ($name) {
            'search_patients' => $this->patientService->paginate($input['search'] ?? null)
                ->getCollection()
                ->map(fn (Patient $patient) => ['id' => $patient->id, 'name' => $patient->full_name, 'patient_code' => $patient->patient_code])
                ->values(),
            'search_appointments' => $this->appointmentService->search($input)
                ->map(fn ($appointment) => [
                    'id' => $appointment->id,
                    'patient' => $appointment->patient->full_name,
                    'dentist' => $appointment->dentist->name,
                    'start_at' => $appointment->start_at->toIso8601String(),
                    'status' => $appointment->status->value,
                ])
                ->values(),
            default => throw new \InvalidArgumentException("Unknown search tool: {$name}"),
        };
    }

    /**
     * A bounded (design doc §2) tool-use loop: Claude either answers directly or picks one tool;
     * the caller's `$executor` runs the real Service method; the result is fed back and the loop
     * repeats until Claude stops calling tools or `MAX_TOOL_ITERATIONS` is hit.
     *
     * @param  list<array<string, mixed>>  $tools
     * @return array{0: string, 1: ?string} [answer text, last tool name called]
     */
    private function runToolLoop(string $system, array $tools, string $userInput, \Closure $executor): array
    {
        $messages = [['role' => 'user', 'content' => $userInput]];
        $lastTool = null;

        for ($i = 0; $i < self::MAX_TOOL_ITERATIONS; $i++) {
            $response = $this->client()->messages->create(
                model: config('services.anthropic.model'),
                maxTokens: 1024,
                system: $system,
                outputConfig: ['effort' => 'low'],
                tools: $tools,
                messages: $messages,
            );

            if ($response->stopReason !== 'tool_use') {
                return [$this->extractText($response), $lastTool];
            }

            $messages[] = ['role' => 'assistant', 'content' => $response->content];

            $toolResults = [];

            foreach ($response->content as $block) {
                if ($block->type !== 'tool_use') {
                    continue;
                }

                $lastTool = $block->name;
                $result = $executor($block->name, (array) $block->input);

                $toolResults[] = [
                    'type' => 'tool_result',
                    'tool_use_id' => $block->id,
                    'content' => json_encode($result),
                ];
            }

            $messages[] = ['role' => 'user', 'content' => $toolResults];
        }

        return ['I wasn\'t able to find an answer to that within the available tools.', $lastTool];
    }

    private function extractText(mixed $response): string
    {
        foreach ($response->content as $block) {
            if ($block->type === 'text') {
                return $block->text;
            }
        }

        return '';
    }

    private function log(
        AiInteractionFeature $feature,
        User $actor,
        ?string $patientId,
        string $promptSummary,
        string $responseSummary,
    ): AiInteractionLog {
        return AiInteractionLog::create([
            'user_id' => $actor->id,
            'feature' => $feature,
            'patient_id' => $patientId,
            'prompt_summary' => $promptSummary,
            'response_summary' => $responseSummary,
            'accepted' => null,
            'model' => config('services.anthropic.model'),
        ]);
    }

    private function client(): Client
    {
        $apiKey = config('services.anthropic.key');

        if (blank($apiKey)) {
            throw new AiAssistantUnavailableException;
        }

        return new Client(apiKey: $apiKey);
    }
}
