<?php

namespace Database\Seeders;

use App\Enums\AiInteractionFeature;
use App\Enums\AppointmentStatus;
use App\Enums\BloodType;
use App\Enums\ClinicalNoteType;
use App\Enums\DentalChartEntryStatus;
use App\Enums\DentalConditionCategory;
use App\Enums\ImageType;
use App\Enums\InvoiceItemKind;
use App\Enums\LabCaseStatus;
use App\Enums\PatientGender;
use App\Enums\PaymentMethod;
use App\Enums\PurchaseOrderStatus;
use App\Enums\StockMovementReason;
use App\Enums\UserRole;
use App\Models\AiInteractionLog;
use App\Models\AppointmentType;
use App\Models\DentalChartEntry;
use App\Models\DentalCondition;
use App\Models\Invoice;
use App\Models\Lab;
use App\Models\LabCase;
use App\Models\Patient;
use App\Models\PatientImage;
use App\Models\Supplier;
use App\Models\Supply;
use App\Models\TreatmentPlan;
use App\Models\TreatmentPlanItem;
use App\Models\User;
use App\Services\AppointmentService;
use App\Services\ClinicalNoteService;
use App\Services\DentalChartService;
use App\Services\DentistWorkingHourService;
use App\Services\InvoiceService;
use App\Services\LabCaseService;
use App\Services\LabService;
use App\Services\PaymentService;
use App\Services\PurchaseOrderService;
use App\Services\StockMovementService;
use App\Services\SupplierService;
use App\Services\SupplyCategoryService;
use App\Services\SupplyService;
use App\Services\TreatmentPlanService;
use Carbon\Carbon;
use Faker\Factory;
use Faker\Generator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Large-scale Arabic-named demo dataset (110 patients) spanning every module's status/scenario
 * space, requested explicitly (2026-08-01) once Frontend UX Phase 1 shipped — local/dev only,
 * called from DatabaseSeeder's environment-gated block alongside the smaller original demo set
 * (never reachable outside `APP_ENV=local`, same gate every other demo-data block in this codebase
 * uses; see the Seed Data Gate note in DatabaseSeeder's own doc comment).
 *
 * Every state-machine-backed record is driven through its module's own Service — never a raw
 * `::create()` with a hardcoded status — mirroring `TreatmentPlanSeeder`'s established
 * "production-safe" convention: seeded data can never represent a state the app itself considers
 * invalid. The two exceptions (`PatientImage`, `AiInteractionLog`) have no state machine to
 * protect — both are documented inline at their seeding method.
 */
class DemoDataSeeder extends Seeder
{
    private const PATIENT_COUNT = 110;

    private Generator $arFaker;

    public function __construct(
        private AppointmentService $appointmentService,
        private DentistWorkingHourService $workingHourService,
        private DentalChartService $dentalChartService,
        private ClinicalNoteService $clinicalNoteService,
        private TreatmentPlanService $treatmentPlanService,
        private InvoiceService $invoiceService,
        private PaymentService $paymentService,
        private LabService $labService,
        private LabCaseService $labCaseService,
        private SupplierService $supplierService,
        private SupplyCategoryService $supplyCategoryService,
        private SupplyService $supplyService,
        private PurchaseOrderService $purchaseOrderService,
        private StockMovementService $stockMovementService,
    ) {
        $this->arFaker = Factory::create('ar_SA');
    }

    public function run(): void
    {
        $dentists = $this->seedDentists();
        $this->seedWorkingHours($dentists);

        $admin = User::query()->where('role', UserRole::Admin)->firstOrFail();
        $patients = $this->seedPatients();

        // Rich case studies (appointment + chart + note + invoice, some also a treatment plan) —
        // shows a patient's full cross-module history end to end.
        $rich = $patients->slice(0, 25)->values();
        $this->seedAppointments($rich, $dentists);
        $this->seedDentalChart($rich, $dentists, $admin);
        $this->seedClinicalNotes($rich->slice(0, 15)->values(), $dentists, $admin);
        $this->seedInvoicesAndPayments($rich, $admin);
        $this->seedTreatmentPlans($rich->slice(0, 12)->values(), $dentists, $admin);

        // Appointment + chart only — mid-history patients.
        $midHistory = $patients->slice(25, 25)->values();
        $this->seedAppointments($midHistory, $dentists, indexOffset: $rich->count());
        $this->seedDentalChart($midHistory, $dentists, $admin);

        // Lab + imaging focus.
        $labImaging = $patients->slice(50, 20)->values();
        $labs = $this->seedLabs();
        $this->seedLabCases($labImaging, $labs, $dentists);
        $this->seedPatientImages($labImaging->slice(0, 15)->values(), $admin);

        // Billing-only patients.
        $billingOnly = $patients->slice(70, 20)->values();
        $this->seedInvoicesAndPayments($billingOnly, $admin);

        // Remaining ~20 patients (index 90-109): freshly registered, no extra data — a realistic
        // baseline, since not every real patient has a full treatment history yet.

        $this->seedAiInteractionLogs($patients, $admin);
        $this->seedInventory($admin);
    }

    /**
     * @return Collection<int, User>
     */
    private function seedDentists(): Collection
    {
        $dentists = User::query()->where('role', UserRole::Dentist)->get();

        foreach ([
            ['name' => 'Dr. Ahmed Al-Farsi', 'email' => 'dentist2@example.com'],
            ['name' => 'Dr. Noura Al-Qahtani', 'email' => 'dentist3@example.com'],
        ] as $data) {
            if (User::query()->where('email', $data['email'])->exists()) {
                continue;
            }
            $dentists->push(User::factory()->dentist()->create($data));
        }

        return $dentists->values();
    }

    /**
     * @param  Collection<int, User>  $dentists
     */
    private function seedWorkingHours(Collection $dentists): void
    {
        // Deliberately unconditional (no "skip if this dentist already has any working-hour row"
        // guard) — a real bug found while first running this seeder: the original demo dentist
        // already had a narrow, pre-existing 09:00–10:00 Sunday-only row from earlier manual/E2E
        // testing in this same dev database, and an "already configured" skip based on mere
        // *presence* of any row left every other day fully unconfigured for that dentist, so every
        // scheduled appointment outside that one-hour window threw `OutsideWorkingHoursException`.
        // `dentist_working_hours` has no unique constraint (multiple rows per day are expected —
        // e.g. a lunch-break split, per its own migration comment), so adding a wide 09:00–17:00
        // row alongside any narrow pre-existing one is harmless: `isOutsideWorkingHours()` passes
        // as soon as *any* row for that day covers the requested range.
        foreach ($dentists as $dentist) {
            // Sunday(0)–Thursday(4): the standard Gulf/Middle East clinic work week, matching the
            // Arabic-named demo dataset's setting.
            foreach (range(0, 4) as $day) {
                $this->workingHourService->create($dentist, [
                    'day_of_week' => $day,
                    'start_time' => '09:00',
                    'end_time' => '17:00',
                ]);
            }
        }
    }

    /**
     * @return Collection<int, Patient>
     */
    private function seedPatients(): Collection
    {
        $patients = collect();
        // `PatientFactory`'s own auto-increment `static::$sequence` resets to 0 every fresh PHP
        // process — fine when this seeder runs as part of the full `DatabaseSeeder` chain right
        // after the original 8 patients in the *same* process, but `sequence_number` has a unique
        // DB constraint, so running this seeder standalone (`db:seed --class=DemoDataSeeder`)
        // against a database that already has patients would immediately collide on patient #1.
        // Basing the starting point on the DB's actual current max makes this safe either way.
        $nextSequence = (int) (Patient::max('sequence_number') ?? 0) + 1;

        for ($i = 0; $i < self::PATIENT_COUNT; $i++) {
            $gender = $i % 2 === 0 ? PatientGender::Male : PatientGender::Female;
            $firstName = $gender === PatientGender::Male
                ? $this->arFaker->firstNameMale()
                : $this->arFaker->firstNameFemale();

            $patients->push(Patient::factory()->create([
                'sequence_number' => $nextSequence + $i,
                'first_name' => $firstName,
                'last_name' => $this->arFaker->lastName(),
                'gender' => $gender,
                'date_of_birth' => fake()->dateTimeBetween('-85 years', '-2 years')->format('Y-m-d'),
                'blood_type' => fake()->optional(0.6)->randomElement(BloodType::cases()),
            ]));
        }

        return $patients;
    }

    /**
     * Deterministic, per-dentist unique (round, hour, day, week) slot assignment — guarantees no
     * two generated appointments for the same dentist ever collide, without needing to run the
     * Service's own conflict-detection logic in a loop. Slots are 2 hours apart (09/11/13/15), not
     * hourly — a real bug found while first running this seeder: hourly slots left less than an
     * hour of margin, and the longest seeded appointment type (Root Canal, 90 min) starting at one
     * slot genuinely overlapped the very next hourly slot for the same dentist. A 2-hour cadence
     * safely covers any single-type duration up to 90 min (ends by 16:30, still inside the
     * 09:00–17:00 working hours seeded above) with real margin to spare.
     *
     * @param  Collection<int, User>  $dentists
     * @return array{0: User, 1: Carbon}
     */
    private function scheduleSlot(int $index, Collection $dentists, bool $past): array
    {
        $dentistCount = $dentists->count();
        $dentistIndex = $index % $dentistCount;
        $round = intdiv($index, $dentistCount);

        $hour = 9 + ($round % 4) * 2;
        $dayIndex = intdiv($round, 4) % 5;
        $weekOffset = intdiv($round, 20) + 1;

        $base = Carbon::now()->startOfWeek(Carbon::SUNDAY);
        $base = $past ? $base->subWeeks($weekOffset) : $base->addWeeks($weekOffset);
        $date = $base->copy()->addDays($dayIndex)->setTime($hour, 0);

        return [$dentists[$dentistIndex], $date];
    }

    /**
     * `$indexOffset` matters: this is called twice (once for the "rich" patient group, once for
     * "mid-history"), and `scheduleSlot()` deterministically maps a plain 0-based index to a
     * (dentist, hour, day, week) slot — a real bug found while first running this seeder: without
     * an offset, the second call's index 0..24 recomputed the *exact same* slots as the first
     * call's index 0..24, so every appointment in the second group collided with one already
     * booked for the same dentist in the first.
     *
     * @param  Collection<int, Patient>  $patients
     * @param  Collection<int, User>  $dentists
     */
    private function seedAppointments(Collection $patients, Collection $dentists, int $indexOffset = 0): void
    {
        $types = AppointmentType::query()->get();
        $statuses = AppointmentStatus::cases();

        foreach ($patients as $index => $patient) {
            $index += $indexOffset;
            $status = $statuses[$index % count($statuses)];
            $isPast = in_array($status, [
                AppointmentStatus::Completed,
                AppointmentStatus::Cancelled,
                AppointmentStatus::NoShow,
                AppointmentStatus::CheckedIn,
                AppointmentStatus::InProgress,
            ], true);

            [$dentist, $startAt] = $this->scheduleSlot($index, $dentists, $isPast);
            $type = $types[$index % $types->count()];

            $appointment = $this->appointmentService->create([
                'patient_id' => $patient->id,
                'dentist_id' => $dentist->id,
                'appointment_type_id' => $type->id,
                'start_at' => $startAt,
                'duration_minutes' => $type->default_duration_minutes,
                'reason' => 'Routine visit',
            ]);

            match ($status) {
                AppointmentStatus::Scheduled => null,
                AppointmentStatus::Confirmed => $this->appointmentService->confirm($appointment),
                AppointmentStatus::CheckedIn => $this->appointmentService->checkIn(
                    $this->appointmentService->confirm($appointment)
                ),
                AppointmentStatus::InProgress => $this->appointmentService->start(
                    $this->appointmentService->checkIn($this->appointmentService->confirm($appointment))
                ),
                AppointmentStatus::Completed => $this->appointmentService->complete(
                    $this->appointmentService->start(
                        $this->appointmentService->checkIn($this->appointmentService->confirm($appointment))
                    )
                ),
                AppointmentStatus::Cancelled => $this->appointmentService->cancel($appointment, 'Patient requested cancellation'),
                AppointmentStatus::NoShow => $this->appointmentService->markNoShow($appointment),
            };
        }
    }

    /**
     * @param  Collection<int, Patient>  $patients
     * @param  Collection<int, User>  $dentists
     */
    private function seedDentalChart(Collection $patients, Collection $dentists, User $admin): void
    {
        $findings = DentalCondition::query()->where('category', DentalConditionCategory::Finding)->get();
        $procedures = DentalCondition::query()->where('category', DentalConditionCategory::Procedure)->get();
        $statuses = DentalChartEntryStatus::cases();
        $teeth = ['11', '12', '13', '21', '22', '23', '16', '26', '36', '46', '14', '24', '34', '44'];

        foreach ($patients as $index => $patient) {
            $status = $statuses[$index % count($statuses)];
            $dentist = $dentists[$index % $dentists->count()];
            $tooth = $teeth[$index % count($teeth)];

            $usesFinding = in_array($status, [DentalChartEntryStatus::Existing, DentalChartEntryStatus::Active], true);
            $condition = $usesFinding
                ? $findings[$index % $findings->count()]
                : $procedures[$index % $procedures->count()];

            $entry = $this->dentalChartService->create([
                'patient_id' => $patient->id,
                'dental_condition_id' => $condition->id,
                'dentist_id' => $dentist->id,
                'tooth_number' => $tooth,
                'surfaces' => $condition->applies_to_surface ? ['O', 'M'] : null,
                'status' => $status === DentalChartEntryStatus::Existing
                    ? DentalChartEntryStatus::Existing
                    : DentalChartEntryStatus::Active,
            ], $admin);

            $this->advanceDentalChartEntry($entry, $status, $admin);
        }
    }

    private function advanceDentalChartEntry(DentalChartEntry $entry, DentalChartEntryStatus $target, User $admin): void
    {
        match ($target) {
            DentalChartEntryStatus::Existing, DentalChartEntryStatus::Active => null,
            DentalChartEntryStatus::Planned => $this->dentalChartService->update(
                $entry, ['status' => DentalChartEntryStatus::Planned], $admin
            ),
            DentalChartEntryStatus::Completed => $this->dentalChartService->complete(
                $this->dentalChartService->update($entry, ['status' => DentalChartEntryStatus::Planned], $admin),
                $admin
            ),
            DentalChartEntryStatus::Cancelled => $this->dentalChartService->cancel(
                $this->dentalChartService->update($entry, ['status' => DentalChartEntryStatus::Planned], $admin),
                $admin
            ),
        };
    }

    /**
     * @param  Collection<int, Patient>  $patients
     * @param  Collection<int, User>  $dentists
     */
    private function seedClinicalNotes(Collection $patients, Collection $dentists, User $admin): void
    {
        $types = ClinicalNoteType::cases();

        foreach ($patients as $index => $patient) {
            $dentist = $dentists[$index % $dentists->count()];

            $note = $this->clinicalNoteService->create([
                'patient_id' => $patient->id,
                'dentist_id' => $dentist->id,
                'note_type' => $types[$index % count($types)],
                'chief_complaint' => 'Tooth pain, upper right quadrant.',
                'subjective' => 'Patient reports intermittent sharp pain when biting.',
                'objective' => 'Visible caries on tooth #16, sensitive to percussion.',
                'assessment' => 'Dental caries, tooth #16.',
                'plan' => 'Composite filling scheduled.',
            ], $admin);

            // Cycle: draft-only / signed / signed + addendum — every clinical-note scenario.
            $scenario = $index % 3;
            if ($scenario >= 1) {
                $note = $this->clinicalNoteService->sign($note, $admin);
            }
            if ($scenario === 2) {
                $this->clinicalNoteService->addAddendum(
                    $note,
                    'Follow-up: patient reports no further pain after the filling.',
                    $admin
                );
            }
        }
    }

    /**
     * @param  Collection<int, Patient>  $patients
     * @param  Collection<int, User>  $dentists
     */
    private function seedTreatmentPlans(Collection $patients, Collection $dentists, User $admin): void
    {
        $procedures = DentalCondition::query()->where('category', DentalConditionCategory::Procedure)->get();
        $scenarios = ['draft', 'presented', 'in_progress', 'completed', 'cancelled', 'rejected_revised'];

        foreach ($patients as $index => $patient) {
            $dentist = $dentists[$index % $dentists->count()];
            $scenario = $scenarios[$index % count($scenarios)];
            $condition = $procedures[$index % $procedures->count()];

            $plan = $this->treatmentPlanService->createPlan([
                'patient_id' => $patient->id,
                'dentist_id' => $dentist->id,
                'title' => $condition->name.' Plan',
            ], $admin);

            $item = $this->treatmentPlanService->addItem($plan, [
                'dental_condition_id' => $condition->id,
                'tooth_number' => '11',
                'quantity' => 1,
                'phase' => 1,
            ], $admin);

            match ($scenario) {
                'draft' => null,
                'presented' => $this->treatmentPlanService->present($plan, $admin),
                'in_progress' => $this->progressTreatmentPlan($plan, $admin),
                'completed' => $this->completeTreatmentPlan($plan, $item, $admin),
                'cancelled' => $this->cancelTreatmentPlan($plan, $admin),
                'rejected_revised' => $this->rejectAndReviseTreatmentPlan($plan, $admin),
            };
        }
    }

    private function progressTreatmentPlan(TreatmentPlan $plan, User $admin): void
    {
        $this->treatmentPlanService->present($plan, $admin);
        $this->treatmentPlanService->accept($plan, $admin);
        $this->treatmentPlanService->start($plan, $admin);
    }

    private function completeTreatmentPlan(TreatmentPlan $plan, TreatmentPlanItem $item, User $admin): void
    {
        $this->treatmentPlanService->present($plan, $admin);
        $this->treatmentPlanService->accept($plan, $admin);
        $this->treatmentPlanService->start($plan, $admin);
        $this->treatmentPlanService->completeItem($item, $admin);
        $this->treatmentPlanService->complete($plan, $admin);
    }

    private function cancelTreatmentPlan(TreatmentPlan $plan, User $admin): void
    {
        $this->treatmentPlanService->present($plan, $admin);
        $this->treatmentPlanService->accept($plan, $admin);
        $this->treatmentPlanService->cancel($plan, $admin);
    }

    private function rejectAndReviseTreatmentPlan(TreatmentPlan $plan, User $admin): void
    {
        $this->treatmentPlanService->present($plan, $admin);
        $this->treatmentPlanService->reject($plan, $admin);
        $this->treatmentPlanService->createSupersedingPlan($plan, ['title' => $plan->title.' (Revised)'], $admin);
    }

    /**
     * @param  Collection<int, Patient>  $patients
     */
    private function seedInvoicesAndPayments(Collection $patients, User $admin): void
    {
        $scenarios = ['draft', 'unpaid', 'partially_paid', 'fully_paid', 'void', 'refunded', 'unapplied_credit'];

        foreach ($patients as $index => $patient) {
            $scenario = $scenarios[$index % count($scenarios)];

            $invoice = $this->invoiceService->createDraft(['patient_id' => $patient->id], $admin);
            $this->invoiceService->addItem($invoice, [
                'kind' => InvoiceItemKind::Charge,
                'description' => 'Composite Filling',
                'unit_amount' => 150,
                'quantity' => 1,
            ], $admin);

            // Every third invoice also carries a Discount and a Tax item — full InvoiceItemKind
            // coverage, not just Charge.
            if ($index % 3 === 0) {
                $this->invoiceService->addItem($invoice, [
                    'kind' => InvoiceItemKind::Discount,
                    'description' => 'Loyalty discount',
                    'unit_amount' => 10,
                    'quantity' => 1,
                ], $admin);
                $this->invoiceService->addItem($invoice, [
                    'kind' => InvoiceItemKind::Tax,
                    'description' => 'VAT',
                    'unit_amount' => 15,
                    'quantity' => 1,
                ], $admin);
            }

            if ($scenario !== 'draft') {
                $invoice = $this->invoiceService->issue($invoice);
            }

            $total = (float) $this->invoiceService->total($invoice);

            match ($scenario) {
                'draft', 'unpaid' => null,
                'partially_paid' => $this->paymentService->record([
                    'patient_id' => $patient->id,
                    'invoice_id' => $invoice->id,
                    'amount' => round($total / 2, 2),
                    'method' => PaymentMethod::Cash,
                ], $admin),
                'fully_paid' => $this->paymentService->record([
                    'patient_id' => $patient->id,
                    'invoice_id' => $invoice->id,
                    'amount' => $total,
                    'method' => PaymentMethod::Card,
                ], $admin),
                'void' => $this->invoiceService->void($invoice),
                'refunded' => $this->refundScenario($invoice, $patient, $total, $admin),
                'unapplied_credit' => $this->paymentService->record([
                    'patient_id' => $patient->id,
                    'amount' => 100,
                    'method' => PaymentMethod::BankTransfer,
                    'notes' => 'Advance payment toward future treatment.',
                ], $admin),
            };
        }
    }

    private function refundScenario(Invoice $invoice, Patient $patient, float $total, User $admin): void
    {
        $payment = $this->paymentService->record([
            'patient_id' => $patient->id,
            'invoice_id' => $invoice->id,
            'amount' => $total,
            'method' => PaymentMethod::Cash,
        ], $admin);

        // PaymentService::refund() takes $amount as a bcmath-formatted string, not a float — every
        // other money value in this codebase (InvoiceResource, etc.) follows the same convention.
        $refundAmount = number_format($total / 4, 2, '.', '');
        $this->paymentService->refund($payment, $refundAmount, 'Partial refund — overcharged item corrected.', $admin);
    }

    /**
     * @return Collection<int, Lab>
     */
    private function seedLabs(): Collection
    {
        return collect([
            ['name' => 'Precision Dental Lab', 'contact_name' => 'Yousef Al-Harbi', 'default_turnaround_days' => 7],
            ['name' => 'CrownCraft Laboratory', 'contact_name' => 'Huda Al-Zahrani', 'default_turnaround_days' => 10],
        ])->map(fn (array $data) => $this->labService->create($data));
    }

    /**
     * @param  Collection<int, Patient>  $patients
     * @param  Collection<int, Lab>  $labs
     * @param  Collection<int, User>  $dentists
     */
    private function seedLabCases(Collection $patients, Collection $labs, Collection $dentists): void
    {
        $statuses = LabCaseStatus::cases();

        foreach ($patients as $index => $patient) {
            $status = $statuses[$index % count($statuses)];
            $lab = $labs[$index % $labs->count()];
            $dentist = $dentists[$index % $dentists->count()];

            $labCase = $this->labCaseService->create([
                'patient_id' => $patient->id,
                'lab_id' => $lab->id,
                'dentist_id' => $dentist->id,
                'tooth_numbers' => ['26'],
                'case_type' => 'Crown',
                'shade' => 'A2',
                'material' => 'Zirconia',
                'instructions' => 'Standard crown fabrication.',
                'fee' => 250,
            ]);

            $this->advanceLabCase($labCase, $status);
        }
    }

    private function advanceLabCase(LabCase $labCase, LabCaseStatus $target): void
    {
        match ($target) {
            LabCaseStatus::Draft => null,
            LabCaseStatus::Sent => $this->labCaseService->send($labCase),
            LabCaseStatus::Received => $this->labCaseService->receive($this->labCaseService->send($labCase)),
            LabCaseStatus::QualityChecked => $this->labCaseService->qualityCheck(
                $this->labCaseService->receive($this->labCaseService->send($labCase))
            ),
            LabCaseStatus::Cancelled => $this->labCaseService->cancel($labCase),
        };
    }

    /**
     * `PatientImage` has no state machine and `PatientImageService::upload()` is upload-specific
     * (requires a real `UploadedFile`) — creating the row directly here mirrors the project's own
     * established convention for non-upload-path seeding (`PatientImageFactory` does the same).
     * Unlike that factory's fabricated, nonexistent path, this writes real placeholder JPEG bytes
     * to the configured disk at the same path the row references, so clicking through to view an
     * image in the running demo app doesn't 404/500.
     *
     * @param  Collection<int, Patient>  $patients
     */
    private function seedPatientImages(Collection $patients, User $admin): void
    {
        $disk = config('filesystems.default');
        $types = ImageType::cases();
        $imageBytes = $this->generatePlaceholderJpeg();

        foreach ($patients as $index => $patient) {
            $type = $types[$index % count($types)];
            $id = (string) Str::uuid();
            $path = "patient-images/{$patient->id}/{$id}.jpg";
            $thumbnailPath = "patient-images/{$patient->id}/{$id}-thumb.jpg";

            Storage::disk($disk)->put($path, $imageBytes);
            Storage::disk($disk)->put($thumbnailPath, $imageBytes);

            $isXray = in_array($type, [
                ImageType::XrayPeriapical,
                ImageType::XrayBitewing,
                ImageType::XrayPanoramic,
                ImageType::XrayCephalometric,
            ], true);

            PatientImage::create([
                'patient_id' => $patient->id,
                'uploaded_by' => $admin->id,
                'image_type' => $type,
                'tooth_number' => $isXray ? '16' : null,
                'taken_at' => now()->subDays($index)->toDateString(),
                'disk' => $disk,
                'path' => $path,
                'thumbnail_path' => $thumbnailPath,
                'mime_type' => 'image/jpeg',
                'file_size' => strlen($imageBytes),
                'width' => 400,
                'height' => 300,
                'notes' => 'Demo image.',
            ]);
        }
    }

    private function generatePlaceholderJpeg(): string
    {
        $image = imagecreatetruecolor(400, 300);
        $background = imagecolorallocate($image, 226, 232, 240);
        imagefill($image, 0, 0, $background);
        $text = imagecolorallocate($image, 71, 85, 105);
        imagestring($image, 5, 140, 140, 'Demo Image', $text);

        ob_start();
        imagejpeg($image, null, 80);
        $contents = ob_get_clean();
        imagedestroy($image);

        return $contents ?: '';
    }

    /**
     * `AiInteractionLog` is a flat, append-only audit table with no state machine to protect
     * (unlike every other module seeded above) — `AiAssistantService` itself is not something a
     * seeder should call (it makes a real Claude API request and requires `ANTHROPIC_API_KEY`), so
     * creating a handful of plausible-looking rows directly is the only practical option.
     *
     * @param  Collection<int, Patient>  $patients
     */
    private function seedAiInteractionLogs(Collection $patients, User $admin): void
    {
        $zeroPhi = [
            [AiInteractionFeature::DashboardInsight, 'How did collections trend this month?', 'Collections rose 12% compared to last month.'],
            [AiInteractionFeature::SmartSearch, 'overdue patients', 'Found 4 patients with overdue balances.'],
            [AiInteractionFeature::ReportNarrative, 'production report summary', 'Production was steady across all dentists this quarter.'],
        ];

        foreach ($zeroPhi as [$feature, $prompt, $response]) {
            AiInteractionLog::create([
                'user_id' => $admin->id,
                'feature' => $feature,
                'patient_id' => null,
                'prompt_summary' => $prompt,
                'response_summary' => $response,
                'accepted' => null,
                'model' => 'claude-sonnet-5',
            ]);
        }

        foreach ($patients->slice(0, 5) as $index => $patient) {
            AiInteractionLog::create([
                'user_id' => $admin->id,
                'feature' => $index % 2 === 0 ? AiInteractionFeature::ClinicalNoteDraft : AiInteractionFeature::TreatmentSuggestion,
                'patient_id' => $patient->id,
                'prompt_summary' => 'pt c/o pain, upper right',
                'response_summary' => 'Draft generated for review.',
                'accepted' => $index % 2 === 0,
                'model' => 'claude-sonnet-5',
            ]);
        }
    }

    private function seedInventory(User $admin): void
    {
        $restorative = $this->supplyCategoryService->create(['name' => 'Restorative Materials']);
        $ppe = $this->supplyCategoryService->create(['name' => 'PPE & Disposables']);
        $supplierA = $this->supplierService->create(['name' => 'Gulf Dental Supplies', 'contact_name' => 'Faisal Al-Otaibi']);
        $supplierB = $this->supplierService->create(['name' => 'MedSource Trading', 'contact_name' => 'Reem Al-Ghamdi']);

        $supplies = collect([
            ['category_id' => $restorative->id, 'name' => 'Composite Resin A2', 'unit_of_measure' => 'syringe', 'default_supplier_id' => $supplierA->id, 'unit_cost' => 25, 'reorder_level' => 10],
            ['category_id' => $restorative->id, 'name' => 'Dental Cement', 'unit_of_measure' => 'box', 'default_supplier_id' => $supplierA->id, 'unit_cost' => 40, 'reorder_level' => 5],
            ['category_id' => $ppe->id, 'name' => 'Nitrile Gloves (M)', 'unit_of_measure' => 'box', 'default_supplier_id' => $supplierB->id, 'unit_cost' => 8, 'reorder_level' => 20],
            ['category_id' => $ppe->id, 'name' => 'Surgical Masks', 'unit_of_measure' => 'box', 'default_supplier_id' => $supplierB->id, 'unit_cost' => 5, 'reorder_level' => 30],
        ])->map(fn (array $data) => $this->supplyService->create($data));

        foreach ($supplies as $supply) {
            $this->stockMovementService->record($supply, [
                'reason' => StockMovementReason::InitialStock,
                'quantity_delta' => 50,
            ], $admin);
            $this->stockMovementService->record($supply, [
                'reason' => StockMovementReason::Used,
                'quantity_delta' => -5,
            ], $admin);
        }

        $this->seedPurchaseOrder($supplierA, $supplies->first(), PurchaseOrderStatus::Draft, $admin);
        $this->seedPurchaseOrder($supplierA, $supplies->first(), PurchaseOrderStatus::Placed, $admin);
        $this->seedPurchaseOrder($supplierA, $supplies->first(), PurchaseOrderStatus::PartiallyReceived, $admin);
        $this->seedPurchaseOrder($supplierA, $supplies->first(), PurchaseOrderStatus::Received, $admin);
        $this->seedPurchaseOrder($supplierB, $supplies->last(), PurchaseOrderStatus::Cancelled, $admin);
    }

    private function seedPurchaseOrder(Supplier $supplier, Supply $supply, PurchaseOrderStatus $target, User $admin): void
    {
        $order = $this->purchaseOrderService->create(['supplier_id' => $supplier->id], $admin);
        $item = $this->purchaseOrderService->addItem($order, ['supply_id' => $supply->id, 'quantity_ordered' => 20]);

        if ($target === PurchaseOrderStatus::Draft) {
            return;
        }

        $order = $this->purchaseOrderService->place($order);

        // A real bug found while first running this seeder: `Placed` fell through to the
        // `receive()` call below unconditionally (only `Draft`/`Cancelled` had an early return),
        // so every "Placed" scenario was immediately promoted to `Received` — the counts came back
        // with two `received` orders and zero `placed`, not the intended one-of-each.
        if ($target === PurchaseOrderStatus::Placed) {
            return;
        }

        if ($target === PurchaseOrderStatus::Cancelled) {
            $this->purchaseOrderService->cancel($order);

            return;
        }

        $quantity = $target === PurchaseOrderStatus::PartiallyReceived ? 10 : 20;
        $this->purchaseOrderService->receive($item, $quantity, null, $admin);
    }
}
