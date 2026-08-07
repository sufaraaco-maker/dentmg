<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Models\BillingSetting;
use App\Models\Invoice;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Patient-level Billing tab hero/summary stat (design doc §6.3/§8/§11.4, Phase 2.2). Deliberately
 * computed entirely via SQL aggregates (`SUM`/`COUNT`/`MAX`/`EXISTS`) rather than this codebase's
 * usual "load the collection, reduce in PHP" convention (`InvoiceResource`, `ReportService`) — the
 * design doc explicitly calls this out as a hard requirement (§11.4) since, unlike a report run on
 * demand, this endpoint loads on every Billing tab visit.
 *
 * A standalone service rather than a new method on InvoiceService/PaymentService: the aggregate
 * spans both domains equally and neither service depends on the other today, so bolting it onto
 * either would add a new asymmetric coupling that doesn't otherwise exist.
 */
class BillingSummaryService
{
    /**
     * @return array{total_invoiced: string, total_paid: string, invoice_count: int, last_payment_date: ?string, outstanding_balance: string, status: string, currency_code: string}
     */
    public function forPatient(string $patientId): array
    {
        // Only `issued` invoices count toward totals — draft is not yet a real financial
        // commitment, void is cancelled and frozen at void time. Matches ReportService::arAging()'s
        // existing precedent of scoping balance calculations to issued invoices only.
        // `DB::table()`, not `InvoiceItem::query()`: this is a pure aggregate over an alias shape
        // that doesn't correspond to any real model attribute — a plain query builder returns a
        // stdClass here, not a typed (and therefore falsely-property-checked) `InvoiceItem`.
        $itemTotals = DB::table('invoice_items')
            ->join('invoices', 'invoices.id', '=', 'invoice_items.invoice_id')
            ->where('invoices.patient_id', $patientId)
            ->where('invoices.status', InvoiceStatus::Issued->value)
            ->whereNull('invoice_items.deleted_at')
            ->selectRaw(
                "COALESCE(SUM(CASE WHEN invoice_items.kind IN ('charge', 'tax') THEN invoice_items.unit_amount * invoice_items.quantity ELSE 0 END), 0) as charges,
                 COALESCE(SUM(CASE WHEN invoice_items.kind = 'discount' THEN invoice_items.unit_amount * invoice_items.quantity ELSE 0 END), 0) as discounts"
            )
            ->first();

        $totalInvoiced = bcsub((string) $itemTotals->charges, (string) $itemTotals->discounts, 2);

        $invoiceCount = Invoice::query()
            ->forPatient($patientId)
            ->withStatus(InvoiceStatus::Issued)
            ->count();

        $hasOverdueInvoice = Invoice::query()
            ->forPatient($patientId)
            ->withStatus(InvoiceStatus::Issued)
            ->whereNotNull('due_date')
            ->where('due_date', '<', now()->toDateString())
            ->exists();

        $totalPaid = (string) Payment::query()->forPatient($patientId)->sum('amount');
        $lastPaymentDateRaw = Payment::query()->forPatient($patientId)->max('received_at');
        // `max()` returns the raw DB value, not cast through the model's `date` cast — normalize
        // to a plain date string (`received_at`'s own cast shape) rather than leaking whatever
        // format the driver happens to return.
        $lastPaymentDate = $lastPaymentDateRaw !== null ? Carbon::parse($lastPaymentDateRaw)->toDateString() : null;

        $outstandingBalance = bcsub($totalInvoiced, $totalPaid, 2);

        $status = match (true) {
            $invoiceCount === 0 => 'no_activity',
            bccomp($outstandingBalance, '0.00', 2) <= 0 => 'paid',
            $hasOverdueInvoice => 'overdue',
            default => 'partial',
        };

        return [
            'total_invoiced' => $totalInvoiced,
            'total_paid' => bcadd($totalPaid, '0.00', 2),
            'invoice_count' => $invoiceCount,
            'last_payment_date' => $lastPaymentDate,
            'outstanding_balance' => $outstandingBalance,
            'status' => $status,
            'currency_code' => BillingSetting::query()->value('currency_code') ?? 'USD',
        ];
    }
}
