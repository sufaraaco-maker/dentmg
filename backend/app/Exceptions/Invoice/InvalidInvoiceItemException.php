<?php

namespace App\Exceptions\Invoice;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Business-rule violations on an invoice item's shape that don't fit InvoiceItemLockedException
 * (which is specifically about draft-only editability): a discount/tax-kind item carrying a
 * `treatment_plan_item_id`, a `treatment_plan_item_id` that belongs to a different patient than
 * the invoice, or a missing description/unit_amount for a manual item (design doc §7/§8).
 * Enforced here in the Service layer as a hard backstop reachable from every call path, not just
 * HTTP requests — Step 3's Form Requests add the friendlier field-level validation UX on top.
 */
class InvalidInvoiceItemException extends RuntimeException
{
    /**
     * 422 — a business-rule validation failure on the request as submitted.
     */
    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'code' => 'invalid_invoice_item',
        ], 422);
    }
}
