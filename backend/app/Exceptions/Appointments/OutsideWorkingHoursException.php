<?php

namespace App\Exceptions\Appointments;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Soft warning (design doc §5.5): the requested slot falls outside the dentist's working
 * hours or during their time-off. Overridable via `override_outside_working_hours`.
 */
class OutsideWorkingHoursException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct("This time falls outside the dentist's working hours or during their time-off.");
    }

    /**
     * 422 — a business-rule validation failure on the request as submitted (not a resource
     * conflict), same family as EarlyNoShowException/InvalidStatusTransitionException.
     */
    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'code' => 'outside_working_hours',
            'overridable' => true,
            'override_field' => 'override_outside_working_hours',
        ], 422);
    }
}
