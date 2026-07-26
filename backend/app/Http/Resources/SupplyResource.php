<?php

namespace App\Http\Resources;

use App\Models\Supply;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Supply */
class SupplyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'category_id' => $this->category_id,
            'default_supplier_id' => $this->default_supplier_id,
            'name' => $this->name,
            'sku' => $this->sku,
            'unit_of_measure' => $this->unit_of_measure,
            'unit_cost' => $this->unit_cost !== null ? (string) $this->unit_cost : null,
            'reorder_level' => $this->reorder_level,
            'reorder_quantity' => $this->reorder_quantity,
            'is_active' => $this->is_active,
            // Computed, never stored (design doc §0/§4/§6) — always the live SUM over stock_movements.
            'quantity_on_hand' => $this->quantity_on_hand,
            'is_low_stock' => $this->is_low_stock,
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
            'category' => $this->whenLoaded('category', fn () => [
                'id' => $this->category->id,
                'name' => $this->category->name,
            ]),
            'default_supplier' => $this->whenLoaded('defaultSupplier', fn () => $this->defaultSupplier === null ? null : [
                'id' => $this->defaultSupplier->id,
                'name' => $this->defaultSupplier->name,
            ]),
        ];
    }
}
