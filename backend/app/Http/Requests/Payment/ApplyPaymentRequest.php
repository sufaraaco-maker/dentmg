<?php

namespace App\Http\Requests\Payment;

use Illuminate\Foundation\Http\FormRequest;

class ApplyPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('apply', $this->route('payment'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'invoice_id' => ['required', 'uuid', 'exists:invoices,id'],
        ];
    }
}
