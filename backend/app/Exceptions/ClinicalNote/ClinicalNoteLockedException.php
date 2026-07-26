<?php

namespace App\Exceptions\ClinicalNote;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Design doc §8 rule 3: once a note is `signed`, every content field (`note_type`,
 * `chief_complaint`/`subjective`/`objective`/`assessment`/`plan`, `appointment_id`) is immutable —
 * a legal clinical record must never silently change after signing. The only correction path is an
 * Addendum (`ClinicalNoteService::addAddendum()`), never an edit. Mirrors
 * `TreatmentPlan\TreatmentPlanItemLockedException`/`Invoice\InvoiceItemLockedException`'s identical
 * enforcement pattern.
 */
class ClinicalNoteLockedException extends RuntimeException
{
    public function __construct(string $message = 'This clinical note is signed and can no longer be edited. Add an addendum instead.')
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
            'code' => 'clinical_note_locked',
        ], 422);
    }
}
