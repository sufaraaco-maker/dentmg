<?php

namespace App\Exceptions\TreatmentPlan;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Design doc §5/§8: a plan can only be marked `completed` once every item is `completed` or
 * `cancelled` — staff must resolve every still-`planned` item first. No "force complete" override
 * for V1.
 */
class TreatmentPlanHasOpenItemsException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('This treatment plan still has items that are not completed or cancelled.');
    }

    /**
     * 422 — a business-rule validation failure on the request as submitted.
     */
    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'code' => 'treatment_plan_has_open_items',
        ], 422);
    }
}
