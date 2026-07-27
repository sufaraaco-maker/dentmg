<?php

namespace App\Exceptions\Inventory;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Business-rule violations on a Purchase Order operation (design doc §8): editing/adding/removing
 * an item once the order has left draft, placing an order that isn't draft, receiving more than
 * quantity_ordered against an item, cancelling an order that already has a receipt against it, or
 * deleting an order that isn't draft.
 */
class InvalidPurchaseOrderOperationException extends RuntimeException
{
    /**
     * 422 — a business-rule validation failure on the request as submitted.
     */
    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'code' => 'invalid_purchase_order_operation',
        ], 422);
    }
}
