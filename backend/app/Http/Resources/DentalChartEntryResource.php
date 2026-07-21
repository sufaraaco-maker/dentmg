<?php

namespace App\Http\Resources;

use App\Models\DentalChartEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin DentalChartEntry */
class DentalChartEntryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'patient_id' => $this->patient_id,
            'tooth_number' => $this->tooth_number,
            'dentition_type' => $this->dentition_type,
            'surfaces' => $this->surfaces,
            'status' => $this->status->value,
            'notes' => $this->notes,
            'recorded_at' => $this->recorded_at->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
            'dental_condition' => $this->whenLoaded('dentalCondition', fn () => [
                'id' => $this->dentalCondition->id,
                'name' => $this->dentalCondition->name,
                'category' => $this->dentalCondition->category->value,
                'default_color' => $this->dentalCondition->default_color,
                'icon_key' => $this->dentalCondition->icon_key,
            ]),
            'dentist' => $this->whenLoaded('dentist', fn () => [
                'id' => $this->dentist->id,
                'name' => $this->dentist->name,
            ]),
            'created_by' => $this->whenLoaded('createdBy', fn () => [
                'id' => $this->createdBy->id,
                'name' => $this->createdBy->name,
            ]),
            'updated_by' => $this->whenLoaded('updatedBy', fn () => $this->updatedBy ? [
                'id' => $this->updatedBy->id,
                'name' => $this->updatedBy->name,
            ] : null),
        ];
    }
}
