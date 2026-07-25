<?php

namespace App\Exceptions\Payment;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Business-rule violations on a payment operation that aren't the refund-balance case (its own
 * PaymentRefundExceedsRemainingBalanceException, for a friendlier typed frontend message): recording
 * or applying against an invoice that isn't issued or belongs to a different patient, applying a
 * payment that's already applied or is itself a refund row, refunding a soft-deleted payment, or
 * deleting a payment that has any refund against it (design doc §8).
 */
class InvalidPaymentOperationException extends RuntimeException
{
    /**
     * 422 — a business-rule validation failure on the request as submitted.
     */
    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'code' => 'invalid_payment_operation',
        ], 422);
    }
}
