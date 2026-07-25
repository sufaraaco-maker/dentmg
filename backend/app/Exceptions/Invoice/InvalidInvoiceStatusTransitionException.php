<?php

namespace App\Exceptions\Invoice;

use App\Enums\InvoiceStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Enforces the state machine in InvoiceStatus::transitionsFrom() — never trust status changes
 * from the client (design doc §5). In particular, `void` is reachable only from `issued`:
 * attempting to void a draft, re-issue an already-issued invoice, or act on a void invoice at all
 * throws this.
 */
class InvalidInvoiceStatusTransitionException extends RuntimeException
{
    public function __construct(InvoiceStatus $from, InvoiceStatus $to)
    {
        parent::__construct("Cannot transition an invoice from \"{$from->value}\" to \"{$to->value}\".");
    }

    /**
     * 422 — a business-rule validation failure on the request as submitted (`api-guidelines.md`).
     */
    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'code' => 'invalid_invoice_status_transition',
        ], 422);
    }
}
