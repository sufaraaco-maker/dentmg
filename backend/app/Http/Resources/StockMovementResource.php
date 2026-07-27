<?php

namespace App\Http\Resources;

use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin StockMovement */
class StockMovementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'supply_id' => $this->supply_id,
            'quantity_delta' => $this->quantity_delta,
            'reason' => $this->reason->value,
            'purchase_order_item_id' => $this->purchase_order_item_id,
            'expiration_date' => $this->expiration_date?->toDateString(),
            'notes' => $this->notes,
            'occurred_at' => $this->occurred_at->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
            'performed_by' => $this->whenLoaded('performedBy', fn () => [
                'id' => $this->performedBy->id,
                'name' => $this->performedBy->name,
            ]),
        ];
    }
}
