<?php

namespace App\Exceptions\Inventory;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Design doc §8: a negative quantity_delta is rejected server-side if it would take the computed
 * on-hand balance below zero — recomputed from the actual ledger at write time, never trusting a
 * client-sent running total (same discipline as PaymentRefundExceedsRemainingBalanceException).
 */
class InsufficientStockException extends RuntimeException
{
    public function __construct(public readonly int $quantityOnHand)
    {
        parent::__construct("This movement would take the supply's on-hand quantity below zero (currently {$quantityOnHand}).");
    }

    /**
     * 422 — a business-rule validation failure on the request as submitted.
     */
    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'code' => 'inventory_insufficient_stock',
        ], 422);
    }
}
