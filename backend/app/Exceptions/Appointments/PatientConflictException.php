<?php

namespace App\Exceptions\Appointments;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Soft warning (design doc §5.4): the patient already has a genuinely overlapping
 * appointment (not just another one the same day). Overridable via `override_patient_conflict`.
 */
class PatientConflictException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('This patient already has an overlapping appointment.');
    }

    /**
     * 409 Conflict — same status family as DentistConflictException (both describe an
     * overlapping-appointment conflict), but `overridable`/`override_field` let the frontend
     * distinguish this soft warning and offer a "Book Anyway" confirmation.
     */
    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'code' => 'patient_conflict',
            'overridable' => true,
            'override_field' => 'override_patient_conflict',
        ], 409);
    }
}
