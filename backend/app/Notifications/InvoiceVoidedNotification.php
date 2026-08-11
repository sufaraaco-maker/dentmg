<?php

namespace App\Notifications;

use App\Enums\UserRole;
use App\Models\Invoice;
use App\Services\RecipientResolver;
use Illuminate\Database\Eloquent\Collection;

/**
 * Type 8 of the 8 whitelisted V1 types (design doc §5.1). Admins only — financial control.
 * `invoice.issued` is deliberately silent for the same reason `payment.recorded` is: issuing is
 * routine, voiding is the exception.
 */
class InvoiceVoidedNotification extends BaseNotification
{
    public function type(): string
    {
        return 'invoice.voided';
    }

    public function category(): string
    {
        return 'billing';
    }

    public function params(): array
    {
        /** @var Invoice $invoice */
        $invoice = $this->subject;

        return [
            'patientName' => $invoice->patient?->full_name,
            'invoiceNumber' => $invoice->invoice_number,
        ];
    }

    public function route(): array
    {
        return [
            'name' => 'invoice-detail',
            'params' => [
                'id' => $this->subject->getAttribute('patient_id'),
                'invoiceId' => $this->subject->getKey(),
            ],
        ];
    }

    public function recipients(RecipientResolver $resolver): Collection
    {
        return $resolver->byRoles([UserRole::Admin]);
    }
}
