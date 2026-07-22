<?php

namespace App\Exceptions\DentalChart;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Design draft §4: `completed` and `cancelled` are terminal/historical — a chart entry in either
 * status can no longer have its clinical fields (condition, tooth, surfaces, dentist, status)
 * edited, only `notes`. Mirrors "can't edit a completed Appointment's core fields".
 */
class EntryLockedException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('This dental chart entry is locked and can no longer be edited, except for notes.');
    }

    /**
     * 422 — a business-rule validation failure on the request as submitted.
     */
    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'code' => 'dental_chart_entry_locked',
        ], 422);
    }
}
