<?php

namespace App\Notifications;

use App\Enums\UserRole;
use App\Models\Payment;
use App\Services\RecipientResolver;
use Illuminate\Database\Eloquent\Collection;

/**
 * Type 7 of the 8 whitelisted V1 types (design doc §5.1). Admins only — financial control.
 *
 * Note the asymmetry with `payment.recorded`, which is deliberately NOT notified: recording
 * payments is routine, high-frequency front-desk work, and notifying on it would bury everything
 * else. Refunds are the exception worth surfacing. Same reasoning as `invoice.voided` vs
 * `invoice.issued` (design doc §5.1's exclusions table).
 *
 * The subject is the signed-negative refund row, not the original payment — PaymentService never
 * edits the original (see `docs/modules/payments-design.md`).
 */
class PaymentRefundedNotification extends BaseNotification
{
    public function type(): string
    {
        return 'payment.refunded';
    }

    public function category(): string
    {
        return 'payments';
    }

    public function params(): array
    {
        /** @var Payment $refund */
        $refund = $this->subject;

        return [
            'patientName' => $refund->patient?->full_name,
            // Raw, unformatted (design doc §11.2) — the frontend formats with the clinic's
            // configured currency at render time.
            'amount' => abs((float) $refund->amount),
            'currencyCode' => $refund->currency_code,
        ];
    }

    public function route(): array
    {
        /** @var Payment $refund */
        $refund = $this->subject;

        // A refund may be unapplied (nullable invoice_id — advance credits, see the Payments
        // design doc), in which case there is no invoice page to open; fall back to the patient's
        // Billing tab, which lists payments either way.
        if ($refund->invoice_id !== null) {
            return [
                'name' => 'invoice-detail',
                'params' => ['id' => $refund->patient_id, 'invoiceId' => $refund->invoice_id],
            ];
        }

        return [
            'name' => 'patient-detail',
            'params' => ['id' => $refund->patient_id],
            'query' => ['tab' => 'billing'],
        ];
    }

    public function recipients(RecipientResolver $resolver): Collection
    {
        return $resolver->byRoles([UserRole::Admin]);
    }
}
