<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Exceptions\Payment\InvalidPaymentOperationException;
use App\Exceptions\Payment\PaymentRefundExceedsRemainingBalanceException;
use App\Models\BillingSetting;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class PaymentService
{
    /**
     * @param  array<string, mixed>  $data  Must include `patient_id` (set by the controller from
     *                                      the nested route). `invoice_id` optional — null records
     *                                      an unapplied/advance credit (design doc §3/§4).
     */
    public function record(array $data, User $actor): Payment
    {
        $amount = (string) $data['amount'];

        if (bccomp($amount, '0', 2) <= 0) {
            throw new InvalidPaymentOperationException('Payment amount must be greater than zero.');
        }

        $invoiceId = $data['invoice_id'] ?? null;

        if ($invoiceId !== null) {
            $this->assertIssuedInvoiceBelongsToPatient($invoiceId, $data['patient_id']);
        }

        $method = $data['method'] instanceof PaymentMethod ? $data['method'] : PaymentMethod::from($data['method']);

        return Payment::create([
            'patient_id' => $data['patient_id'],
            'invoice_id' => $invoiceId,
            'refunded_payment_id' => null,
            'method' => $method,
            'amount' => $amount,
            // Snapshot at creation, mirrors InvoiceService::createDraft()'s identical reasoning —
            // never assume a single global currency.
            'currency_code' => BillingSetting::query()->value('currency_code') ?? 'USD',
            'reference' => $data['reference'] ?? null,
            'notes' => $data['notes'] ?? null,
            'received_at' => $data['received_at'] ?? now()->toDateString(),
            'created_by_id' => $actor->id,
        ]);
    }

    /**
     * Design doc §8: reference/notes/received_at are the only editable fields — amount, method,
     * currency_code, patient_id are immutable after creation. A genuine correction is a refund
     * (real money) or, for a data-entry error, a soft delete — never an in-place edit.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateMetadata(Payment $payment, array $data): Payment
    {
        $payment->update(array_intersect_key($data, array_flip(['reference', 'notes', 'received_at'])));

        return $payment;
    }

    /**
     * Sets `invoice_id` on a currently-unapplied payment (design doc §3/§8) — allowed exactly once,
     * full-amount only, and only onto an `issued` invoice belonging to the same patient.
     *
     * Row-locked and transactional, mirroring InvoiceService::issue()'s identical reasoning: without
     * locking, two concurrent `apply()` calls against the same unapplied payment could both read
     * `invoice_id === null` before either commits, both pass validation, and race to a lost-update
     * outcome for an invariant this method's own docs promise holds "exactly once."
     */
    public function apply(Payment $payment, string $invoiceId): Payment
    {
        return DB::transaction(function () use ($payment, $invoiceId) {
            $locked = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($locked->invoice_id !== null) {
                throw new InvalidPaymentOperationException('This payment has already been applied to an invoice.');
            }

            if ($locked->is_refund) {
                throw new InvalidPaymentOperationException('A refund cannot be applied to an invoice.');
            }

            $this->assertIssuedInvoiceBelongsToPatient($invoiceId, $locked->patient_id);

            $locked->update(['invoice_id' => $invoiceId]);

            return $locked;
        });
    }

    /**
     * Creates the linked negative Payment row (design doc §4/§8) — the original is never edited.
     * `$amount` is the positive amount staff entered; negated here before storing.
     *
     * Row-locked and transactional, mirroring InvoiceService::issue()'s identical reasoning: the
     * remaining-balance check is otherwise a read-check-then-write race — two concurrent refund
     * requests against the same payment could both read the same `remaining_refundable_amount`
     * before either commits, both pass validation, and together over-refund it. Locking this row
     * makes a second concurrent caller block until the first transaction commits, then re-read the
     * already-updated remaining balance once unblocked — genuine serialization, not a race caught
     * only by luck.
     */
    public function refund(Payment $payment, string $amount, ?string $notes, User $actor): Payment
    {
        return DB::transaction(function () use ($payment, $amount, $notes, $actor) {
            // withTrashed(): a soft-deleted payment must still be fetched here so the trashed()
            // check below can throw the intended, friendly InvalidPaymentOperationException —
            // Payment::query()'s default SoftDeletingScope would otherwise make firstOrFail()
            // throw an unrelated ModelNotFoundException for this exact case instead.
            $locked = Payment::withTrashed()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($locked->trashed()) {
                throw new InvalidPaymentOperationException('A deleted payment cannot be refunded.');
            }

            $remaining = $locked->remaining_refundable_amount;

            if (bccomp($amount, '0', 2) <= 0 || bccomp($amount, $remaining, 2) > 0) {
                throw new PaymentRefundExceedsRemainingBalanceException($remaining);
            }

            return Payment::create([
                'patient_id' => $locked->patient_id,
                'invoice_id' => $locked->invoice_id,
                'refunded_payment_id' => $locked->id,
                'method' => $locked->method,
                'amount' => bcmul($amount, '-1', 2),
                'currency_code' => $locked->currency_code,
                'reference' => $locked->reference,
                'notes' => $notes,
                'received_at' => now()->toDateString(),
                'created_by_id' => $actor->id,
            ]);
        });
    }

    /**
     * Data-entry-error correction only (design doc §3/§8) — a payment with any refund against it
     * can never be soft-deleted; the refund is the audit trail, and deleting the original out from
     * under it would orphan that trail's meaning.
     */
    public function delete(Payment $payment): void
    {
        if ($payment->refunds()->exists()) {
            throw new InvalidPaymentOperationException('A payment that has been refunded cannot be deleted — issue a further refund instead.');
        }

        $payment->delete();
    }

    private function assertIssuedInvoiceBelongsToPatient(string $invoiceId, string $patientId): void
    {
        $invoice = Invoice::query()->find($invoiceId);

        if ($invoice === null || $invoice->patient_id !== $patientId) {
            throw new InvalidPaymentOperationException("The selected invoice does not belong to this payment's patient.");
        }

        if ($invoice->status !== InvoiceStatus::Issued) {
            throw new InvalidPaymentOperationException('Payments can only be recorded or applied against an issued invoice.');
        }
    }
}
