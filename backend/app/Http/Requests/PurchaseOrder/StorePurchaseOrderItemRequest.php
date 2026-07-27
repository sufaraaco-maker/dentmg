<?php

namespace App\Http\Requests\PurchaseOrder;

use Illuminate\Foundation\Http\FormRequest;

class StorePurchaseOrderItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('purchase_order'));
    }

    /**
     * `description`/`unit_cost` are optional — PurchaseOrderService::addItem() defaults them from
     * the Supply's own name/SKU/unit_cost when omitted (design doc §6 snapshot convention).
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'supply_id' => ['required', 'uuid', 'exists:supplies,id'],
            'description' => ['nullable', 'string', 'max:255'],
            'quantity_ordered' => ['required', 'integer', 'min:1'],
            'unit_cost' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
