<?php

namespace App\Http\Resources;

use App\Models\PurchaseOrderItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PurchaseOrderItem */
class PurchaseOrderItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'purchase_order_id' => $this->purchase_order_id,
            'supply_id' => $this->supply_id,
            'description' => $this->description,
            'quantity_ordered' => $this->quantity_ordered,
            'quantity_received' => $this->quantity_received,
            'quantity_remaining' => $this->quantity_remaining,
            'unit_cost' => (string) $this->unit_cost,
            'subtotal' => $this->subtotal,
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
            'supply' => $this->whenLoaded('supply', fn () => [
                'id' => $this->supply->id,
                'name' => $this->supply->name,
                'unit_of_measure' => $this->supply->unit_of_measure,
            ]),
        ];
    }
}
