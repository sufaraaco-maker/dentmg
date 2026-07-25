<?php

namespace App\Http\Requests\Payment;

use App\Enums\PaymentMethod;
use App\Models\Payment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePaymentRequest extends FormRequest
{
    /**
     * `patient_id` is not a field — the parent Patient comes from the nested route
     * (`patients/{patient}/payments`, design doc §9), mirroring StoreInvoiceRequest. `currency_code`
     * is not a field either — PaymentService::record() snapshots it, never the client.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', Payment::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Optional — the unapplied/advance-credit flow (design doc §3/§4) leaves this null.
            // Whether an invoice_id (when given) is actually issued and belongs to this patient is
            // a PaymentService concern (assertIssuedInvoiceBelongsToPatient()), not this layer's.
            'invoice_id' => ['nullable', 'uuid', 'exists:invoices,id'],
            'method' => ['required', Rule::enum(PaymentMethod::class)],
            'amount' => ['required', 'numeric', 'gt:0'],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'received_at' => ['nullable', 'date'],
        ];
    }
}
