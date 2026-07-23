<?php

namespace App\Exceptions\TreatmentPlan;

use App\Enums\TreatmentPlanItemStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Enforces the state machine in TreatmentPlanItemStatus::transitionsFrom() — never trust status
 * changes from the client (design doc §5).
 */
class InvalidTreatmentPlanItemStatusTransitionException extends RuntimeException
{
    public function __construct(TreatmentPlanItemStatus $from, TreatmentPlanItemStatus $to)
    {
        parent::__construct("Cannot transition a treatment plan item from \"{$from->value}\" to \"{$to->value}\".");
    }

    /**
     * 422 — a business-rule validation failure on the request as submitted (`api-guidelines.md`).
     */
    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'code' => 'invalid_treatment_plan_item_status_transition',
        ], 422);
    }
}
