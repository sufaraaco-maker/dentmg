<?php

namespace App\Exceptions\ClinicalNote;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Business-rule violations on a clinical note operation that aren't "the note is locked"
 * (that's the dedicated ClinicalNoteLockedException, for a friendlier typed frontend message):
 * signing a note with every content section blank (design doc §8 rule 2), or adding an addendum to
 * a note that hasn't been signed yet (design doc §8 rule 4). Mirrors
 * `Payment\InvalidPaymentOperationException`'s identical "catch-all for this module's other invalid
 * states" role.
 */
class InvalidClinicalNoteOperationException extends RuntimeException
{
    /**
     * 422 — a business-rule validation failure on the request as submitted.
     */
    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'code' => 'invalid_clinical_note_operation',
        ], 422);
    }
}
