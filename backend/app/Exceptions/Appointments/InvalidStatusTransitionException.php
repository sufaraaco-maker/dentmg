<?php

namespace App\Exceptions\Appointments;

use App\Enums\AppointmentStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Enforces the state machine in design doc §3 — never trust status changes from the client.
 */
class InvalidStatusTransitionException extends RuntimeException
{
    public function __construct(AppointmentStatus $from, AppointmentStatus $to)
    {
        parent::__construct("Cannot transition an appointment from \"{$from->value}\" to \"{$to->value}\".");
    }

    /**
     * 422 — a business-rule validation failure on the request as submitted. No `overridable`
     * key: an invalid transition isn't something the frontend can force past, unlike the soft
     * warnings above.
     */
    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'code' => 'invalid_status_transition',
        ], 422);
    }
}
