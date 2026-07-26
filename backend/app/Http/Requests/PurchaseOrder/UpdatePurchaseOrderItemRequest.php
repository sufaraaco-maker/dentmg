<?php

namespace App\Http\Requests\PurchaseOrder;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePurchaseOrderItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('purchase_order_item')->purchaseOrder);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'description' => ['sometimes', 'required', 'string', 'max:255'],
            'quantity_ordered' => ['sometimes', 'required', 'integer', 'min:1'],
            'unit_cost' => ['sometimes', 'required', 'numeric', 'min:0'],
        ];
    }
}
