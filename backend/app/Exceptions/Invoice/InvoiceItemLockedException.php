<?php

namespace App\Exceptions\Invoice;

use App\Models\Invoice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * The single centralized lock for "no silent mutation of an issued invoice" (design doc §8,
 * Decision Log item 2) — thrown by InvoiceService::assertEditable() for both invoice-level edits
 * (notes/dates, delete) and item-level edits (add/update/delete) the instant the parent invoice
 * has left `draft`. Named constructors keep the message accurate for either case without needing
 * two exception classes.
 */
class InvoiceItemLockedException extends RuntimeException
{
    public function __construct(string $message = 'This invoice is no longer editable outside its draft status.')
    {
        parent::__construct($message);
    }

    public static function forInvoice(Invoice $invoice): self
    {
        return new self("Invoice {$invoice->id} is not in draft status and can no longer be edited or deleted.");
    }

    /**
     * 422 — a business-rule validation failure on the request as submitted.
     */
    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'code' => 'invoice_item_locked',
        ], 422);
    }
}
