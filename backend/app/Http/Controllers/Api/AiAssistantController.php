<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AiAssistant\ClinicalNoteDraftRequest;
use App\Http\Requests\AiAssistant\DashboardInsightRequest;
use App\Http\Requests\AiAssistant\ReportNarrativeRequest;
use App\Http\Requests\AiAssistant\SmartSearchRequest;
use App\Http\Requests\AiAssistant\TreatmentSuggestionRequest;
use App\Http\Resources\AiInteractionLogResource;
use App\Models\AiInteractionLog;
use App\Models\ClinicalNote;
use App\Models\TreatmentPlan;
use App\Services\AiAssistantService;
use Illuminate\Http\Request;

/**
 * Every action: validate/authorize via a dedicated Form Request → call `AiAssistantService` →
 * return JSON (design doc §6). No prompt building, tool dispatch, or Claude API calls live here —
 * same "thin controller" convention as every other module.
 */
class AiAssistantController extends Controller
{
    public function __construct(private readonly AiAssistantService $aiAssistantService) {}

    public function dashboardInsight(DashboardInsightRequest $request)
    {
        $log = $this->aiAssistantService->dashboardInsight($request->validated('question'), $request->user());

        return response()->json(['answer' => $log->response_summary, 'interaction_id' => $log->id]);
    }

    public function smartSearch(SmartSearchRequest $request)
    {
        $log = $this->aiAssistantService->smartSearch($request->validated('query'), $request->user());

        return response()->json(['answer' => $log->response_summary, 'interaction_id' => $log->id]);
    }

    public function reportNarrative(ReportNarrativeRequest $request)
    {
        $log = $this->aiAssistantService->reportNarrative(
            $request->validated('report_type'),
            $request->safe()->except('report_type'),
            $request->user(),
        );

        return response()->json(['narrative' => $log->response_summary, 'interaction_id' => $log->id]);
    }

    public function draftClinicalNote(ClinicalNoteDraftRequest $request, ClinicalNote $clinical_note)
    {
        $result = $this->aiAssistantService->draftClinicalNote(
            $clinical_note,
            $request->validated('shorthand'),
            $request->user(),
        );

        return response()->json([
            'subjective' => $result['subjective'],
            'objective' => $result['objective'],
            'assessment' => $result['assessment'],
            'plan' => $result['plan'],
            'interaction_id' => $result['log']->id,
        ]);
    }

    public function suggestTreatmentItems(TreatmentSuggestionRequest $request, TreatmentPlan $treatment_plan)
    {
        $result = $this->aiAssistantService->suggestTreatmentItems($treatment_plan, $request->user());

        return response()->json([
            'candidates' => $result['candidates'],
            'interaction_id' => $result['log']->id,
        ]);
    }

    /**
     * Records the accept/edit/reject decision on a PHI-bearing suggestion (design doc §5's
     * auditability requirement) — restricted to the user who made the original request, since this
     * is recording *their* review decision, not an oversight action (unlike `index` below, which
     * admins may view across every user).
     */
    public function recordDecision(Request $request, AiInteractionLog $ai_interaction_log)
    {
        if ($ai_interaction_log->user_id !== $request->user()->id) {
            abort(403);
        }

        $validated = $request->validate([
            'accepted' => ['required', 'boolean'],
            'response_summary' => ['nullable', 'string'],
        ]);

        $log = $this->aiAssistantService->recordAcceptance(
            $ai_interaction_log,
            $validated['accepted'],
            $validated['response_summary'] ?? null,
        );

        return (new AiInteractionLogResource($log))->response()->setStatusCode(200);
    }

    /**
     * Self-service by default (design doc §7); admins may pass `?all=1` to see every user's
     * interactions, the same cross-user oversight precedent as every other module.
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', AiInteractionLog::class);

        $query = AiInteractionLog::query()->latest('created_at');

        if (! ($request->boolean('all') && $request->user()->isAdmin())) {
            $query->where('user_id', $request->user()->id);
        }

        return AiInteractionLogResource::collection($query->paginate(20));
    }
}
