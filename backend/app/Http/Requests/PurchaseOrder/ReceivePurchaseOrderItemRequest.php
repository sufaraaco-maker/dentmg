<?php

namespace App\Http\Requests\PurchaseOrder;

use Illuminate\Foundation\Http\FormRequest;

class ReceivePurchaseOrderItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('receive', $this->route('purchase_order_item')->purchaseOrder);
    }

    /**
     * Whether `quantity` would exceed the item's remaining quantity_ordered is a
     * PurchaseOrderService concern (design doc §8/§15 Decision 5), not this layer's — never trust a
     * client-computed remaining balance.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'quantity' => ['required', 'integer', 'min:1'],
            'expiration_date' => ['nullable', 'date'],
        ];
    }
}
