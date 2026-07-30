<?php

namespace App\Http\Requests\BillingSetting;

use App\Models\BillingSetting;
use Illuminate\Foundation\Http\FormRequest;

/**
 * `next_invoice_sequence` is deliberately absent — system-managed by `InvoiceService`'s
 * lock-and-increment logic, never user-editable (design doc §4.2/§8 decision 3).
 */
class UpdateBillingSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', BillingSetting::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'currency_code' => ['required', 'string', 'size:3'],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'invoice_number_prefix' => ['required', 'string', 'max:255'],
        ];
    }
}
