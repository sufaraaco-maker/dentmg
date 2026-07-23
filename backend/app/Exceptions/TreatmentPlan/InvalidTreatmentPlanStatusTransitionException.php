<?php

namespace App\Exceptions\TreatmentPlan;

use App\Enums\TreatmentPlanStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Enforces the state machine in TreatmentPlanStatus::transitionsFrom() — never trust status
 * changes from the client (design doc §5).
 */
class InvalidTreatmentPlanStatusTransitionException extends RuntimeException
{
    public function __construct(TreatmentPlanStatus $from, TreatmentPlanStatus $to)
    {
        parent::__construct("Cannot transition a treatment plan from \"{$from->value}\" to \"{$to->value}\".");
    }

    /**
     * 422 — a business-rule validation failure on the request as submitted (`api-guidelines.md`).
     */
    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'code' => 'invalid_treatment_plan_status_transition',
        ], 422);
    }
}
