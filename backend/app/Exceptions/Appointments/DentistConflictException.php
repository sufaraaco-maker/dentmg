<?php

namespace App\Exceptions\Appointments;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Hard block, never overridable (design doc §5.3, §10): the dentist already has an
 * overlapping, time-occupying appointment.
 */
class DentistConflictException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('The dentist already has an appointment that overlaps this time slot.');
    }

    /**
     * 409 Conflict — framed the same way as PatientConflictException (both describe an
     * overlapping-appointment conflict), but with no `overridable` key: this one is a hard
     * block, so the frontend has nothing to offer the user except picking a different slot.
     */
    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'code' => 'dentist_conflict',
        ], 409);
    }
}
