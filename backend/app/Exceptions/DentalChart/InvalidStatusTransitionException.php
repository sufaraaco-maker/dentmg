<?php

namespace App\Exceptions\DentalChart;

use App\Enums\DentalChartEntryStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Enforces the state machine in DentalChartEntryStatus::transitionsFrom() — never trust status
 * changes from the client. Not the Appointments InvalidStatusTransitionException: that class's
 * constructor is hard-typed to AppointmentStatus, so it isn't reusable here.
 */
class InvalidStatusTransitionException extends RuntimeException
{
    public function __construct(DentalChartEntryStatus $from, DentalChartEntryStatus $to)
    {
        parent::__construct("Cannot transition a dental chart entry from \"{$from->value}\" to \"{$to->value}\".");
    }

    /**
     * 422 — a business-rule validation failure on the request as submitted, matching the
     * Appointments sibling exception's shape exactly (`api-guidelines.md`).
     */
    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'code' => 'invalid_status_transition',
        ], 422);
    }
}
