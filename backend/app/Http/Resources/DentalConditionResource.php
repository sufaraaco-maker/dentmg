<?php

namespace App\Http\Resources;

use App\Models\DentalCondition;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin DentalCondition */
class DentalConditionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'category' => $this->category->value,
            'applies_to_surface' => $this->applies_to_surface,
            'default_color' => $this->default_color,
            'icon_key' => $this->icon_key,
            // Added by the Treatment Plans module (docs/modules/treatment-plans-design.md §6/§21
            // Step 8) — the Add/Edit Item dialog prefills unit_cost from this once a procedure is
            // selected, so it must be readable, not just server-side-only.
            'default_cost' => $this->default_cost === null ? null : (string) $this->default_cost,
            'description' => $this->description,
            'is_active' => $this->is_active,
            'sort_order' => $this->sort_order,
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
