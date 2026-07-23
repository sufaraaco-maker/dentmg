<?php

namespace App\Http\Resources;

use App\Models\TreatmentPlanItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin TreatmentPlanItem */
class TreatmentPlanItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'treatment_plan_id' => $this->treatment_plan_id,
            'dental_condition_id' => $this->dental_condition_id,
            'procedure_name' => $this->procedure_name,
            'procedure_description' => $this->procedure_description,
            'diagnosis_entry_id' => $this->diagnosis_entry_id,
            'tooth_number' => $this->tooth_number,
            'surfaces' => $this->surfaces,
            'quantity' => $this->quantity,
            'unit_cost' => $this->unit_cost,
            'estimated_cost' => $this->estimated_cost,
            'phase' => $this->phase,
            'sequence' => $this->sequence,
            'status' => $this->status->value,
            'appointment_id' => $this->appointment_id,
            'notes' => $this->notes,
            'completed_at' => $this->completed_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
            'dental_condition' => $this->whenLoaded('dentalCondition', fn () => [
                'id' => $this->dentalCondition->id,
                'name' => $this->dentalCondition->name,
                'category' => $this->dentalCondition->category->value,
            ]),
            'diagnosis_entry' => $this->whenLoaded('diagnosisEntry', fn () => $this->diagnosisEntry ? [
                'id' => $this->diagnosisEntry->id,
                'tooth_number' => $this->diagnosisEntry->tooth_number,
                'status' => $this->diagnosisEntry->status->value,
            ] : null),
            'appointment' => $this->whenLoaded('appointment', fn () => $this->appointment ? [
                'id' => $this->appointment->id,
                'start_at' => $this->appointment->start_at->toIso8601String(),
                'status' => $this->appointment->status->value,
            ] : null),
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
