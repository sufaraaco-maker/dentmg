<?php

namespace App\Exceptions\TreatmentPlan;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Two independent locks share this one exception (design doc §5/§8, Decision 2):
 *
 * 1. A terminal item (`completed`/`cancelled`) rejects every field but `notes` — mirrors
 *    `DentalChart\EntryLockedException` exactly.
 * 2. A non-terminal item whose *parent plan* has left `draft` rejects only the "commercial offer"
 *    fields (`dental_condition_id`/`tooth_number`/`surfaces`/`quantity`/`unit_cost`, and the
 *    `procedure_name`/`procedure_description` derived from them) — the plan's presented price and
 *    procedure must never silently change underneath an ongoing patient conversation, even though
 *    `notes`/`appointment_id`/`diagnosis_entry_id`/`phase`/`sequence` remain editable.
 */
class TreatmentPlanItemLockedException extends RuntimeException
{
    public function __construct(string $message = 'This treatment plan item is locked and cannot be edited as requested.')
    {
        parent::__construct($message);
    }

    /**
     * 422 — a business-rule validation failure on the request as submitted.
     */
    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'code' => 'treatment_plan_item_locked',
        ], 422);
    }
}
