<?php

namespace App\Http\Resources;

use App\Enums\InvoiceItemKind;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/** @mixin Invoice */
class InvoiceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'patient_id' => $this->patient_id,
            'created_by_id' => $this->created_by_id,
            'sequence_number' => $this->sequence_number,
            'invoice_number' => $this->invoice_number,
            'currency_code' => $this->currency_code,
            'status' => $this->status->value,
            'notes' => $this->notes,
            'issue_date' => $this->issue_date?->toDateString(),
            'due_date' => $this->due_date?->toDateString(),
            'issued_at' => $this->issued_at?->toIso8601String(),
            'voided_at' => $this->voided_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
            // Invoice-level total (design doc §4/§6): SUM(charge) + SUM(tax) - SUM(discount) over
            // the already-eager-loaded `items` relation, mirroring
            // TreatmentPlanResource::sumEstimatedCost() — never stored/cached, and deliberately not
            // a call into InvoiceService::total() (which re-queries) since the items are already
            // in hand here. Only present when items are loaded (every endpoint loads them, §9/§13).
            'total' => $this->whenLoaded('items', fn () => $this->sumTotal($this->items)),
            // Payments design doc §4/§6/§9: purely additive, computed from the eager-loaded
            // `payments` relation exactly like `total` above is computed from `items` — no new
            // stored column, no reshape of this response's envelope. Present only when `payments`
            // is loaded (every Invoice endpoint loads it, mirroring `items`).
            'amount_paid' => $this->whenLoaded('payments', fn () => $this->sumAmountPaid($this->payments)),
            'balance_due' => $this->when(
                $this->relationLoaded('items') && $this->relationLoaded('payments'),
                fn () => bcsub($this->sumTotal($this->items), $this->sumAmountPaid($this->payments), 2)
            ),
            'payment_status' => $this->when(
                $this->relationLoaded('items') && $this->relationLoaded('payments'),
                fn () => $this->resolvePaymentStatus($this->sumTotal($this->items), $this->sumAmountPaid($this->payments))
            ),
            'created_by' => $this->whenLoaded('createdBy', fn () => [
                'id' => $this->createdBy->id,
                'name' => $this->createdBy->name,
            ]),
            // Only meaningful on the clinic-wide list (frontend-ux-redesign design doc §5.1/§11) —
            // every patient-scoped Invoice endpoint already has the patient in context and doesn't
            // load this relation, so it's simply absent there (`whenLoaded`, same convention as
            // `created_by` above).
            'patient' => $this->whenLoaded('patient', fn () => [
                'id' => $this->patient->id,
                'first_name' => $this->patient->first_name,
                'last_name' => $this->patient->last_name,
            ]),
            'items' => InvoiceItemResource::collection($this->whenLoaded('items')),
        ];
    }

    /**
     * @param  Collection<int, InvoiceItem>  $items
     */
    private function sumTotal(Collection $items): string
    {
        return $items->reduce(
            fn (string $carry, InvoiceItem $item) => match ($item->kind) {
                InvoiceItemKind::Charge, InvoiceItemKind::Tax => bcadd($carry, $item->amount, 2),
                InvoiceItemKind::Discount => bcsub($carry, $item->amount, 2),
            },
            '0.00'
        );
    }

    /**
     * `SUM(payments.amount)`, refunds (negative rows) included — a full refund nets back to `0`
     * (Payments design doc §4).
     *
     * @param  Collection<int, Payment>  $payments
     */
    private function sumAmountPaid(Collection $payments): string
    {
        return $payments->reduce(fn (string $carry, Payment $payment) => bcadd($carry, (string) $payment->amount, 2), '0.00');
    }

    /**
     * `unpaid` / `partially_paid` / `paid` (Payments design doc §4) — meaningful only for `issued`
     * invoices in practice, but the formula itself needs no status branching: a draft invoice's
     * `amount_paid` is always `0` (payments can only apply to an issued invoice, PaymentService),
     * and a void invoice simply freezes whatever it was at void time.
     */
    private function resolvePaymentStatus(string $total, string $amountPaid): string
    {
        if (bccomp($amountPaid, '0.00', 2) <= 0) {
            return 'unpaid';
        }

        if (bccomp($amountPaid, $total, 2) >= 0) {
            return 'paid';
        }

        return 'partially_paid';
    }
}
