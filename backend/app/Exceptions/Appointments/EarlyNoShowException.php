<?php

namespace App\Exceptions\Appointments;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Soft warning (design doc §5.9/§13): marking no-show before `start_at` has passed is
 * unusual but allowed for staff backfilling records. Overridable via `override_early_no_show`.
 */
class EarlyNoShowException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct("This appointment's start time has not passed yet.");
    }

    /**
     * 422 — a business-rule validation failure on the request as submitted.
     */
    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'code' => 'early_no_show',
            'overridable' => true,
            'override_field' => 'override_early_no_show',
        ], 422);
    }
}
