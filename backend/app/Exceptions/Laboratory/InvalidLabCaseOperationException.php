<?php

namespace App\Exceptions\Laboratory;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Business-rule violations on a Lab Case operation (design doc §4): an invalid status transition,
 * sending a case with no lab set, editing a case that has left draft, or cancelling a case that
 * already has a receipt against it (received_at is set).
 */
class InvalidLabCaseOperationException extends RuntimeException
{
    /**
     * 422 — a business-rule validation failure on the request as submitted.
     */
    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'code' => 'invalid_lab_case_operation',
        ], 422);
    }
}
