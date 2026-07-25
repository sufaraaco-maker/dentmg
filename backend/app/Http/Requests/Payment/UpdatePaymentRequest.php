<?php

namespace App\Http\Requests\Payment;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePaymentRequest extends FormRequest
{
    /**
     * Only administrative metadata is editable (design doc §8) — amount/method/currency_code/
     * patient_id are immutable after creation; a real correction is a refund or a soft delete.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('payment'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reference' => ['sometimes', 'nullable', 'string', 'max:255'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'received_at' => ['sometimes', 'date'],
        ];
    }
}
