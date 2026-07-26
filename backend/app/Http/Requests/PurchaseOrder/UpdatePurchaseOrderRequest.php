<?php

namespace App\Http\Requests\PurchaseOrder;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('purchase_order'));
    }

    /**
     * `supplier_id` is not editable (immutable after creation, design doc §6) — notes/expected_at
     * only, and only while draft (PurchaseOrderService::update() enforces the draft-only gate).
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'notes' => ['nullable', 'string', 'max:2000'],
            'expected_at' => ['nullable', 'date'],
        ];
    }
}
