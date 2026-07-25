<?php

namespace App\Exceptions\Payment;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * `original.amount + SUM(existing refunds against it) - requested >= 0` (design doc §8) — enforced
 * here as a hard Service-layer backstop, never trusting a client-computed running balance.
 */
class PaymentRefundExceedsRemainingBalanceException extends RuntimeException
{
    public function __construct(public readonly string $remainingRefundableAmount)
    {
        parent::__construct("The refund amount exceeds this payment's remaining refundable balance of {$remainingRefundableAmount}.");
    }

    /**
     * 422 — a business-rule validation failure on the request as submitted.
     */
    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'code' => 'payment_refund_exceeds_remaining_balance',
        ], 422);
    }
}
