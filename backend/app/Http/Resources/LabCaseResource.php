<?php

namespace App\Http\Resources;

use App\Models\LabCase;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin LabCase */
class LabCaseResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sequence_number' => $this->sequence_number,
            'case_number' => $this->case_number,
            'patient_id' => $this->patient_id,
            'lab_id' => $this->lab_id,
            'dentist_id' => $this->dentist_id,
            'treatment_plan_item_id' => $this->treatment_plan_item_id,
            'appointment_id' => $this->appointment_id,
            'tooth_numbers' => $this->tooth_numbers,
            'case_type' => $this->case_type,
            'shade' => $this->shade,
            'material' => $this->material,
            'instructions' => $this->instructions,
            'fee' => $this->fee !== null ? (float) $this->fee : null,
            'tracking_number' => $this->tracking_number,
            'status' => $this->status->value,
            'sent_at' => $this->sent_at?->toIso8601String(),
            'due_at' => $this->due_at?->toIso8601String(),
            'received_at' => $this->received_at?->toIso8601String(),
            'quality_checked_at' => $this->quality_checked_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
            'patient' => $this->whenLoaded('patient', fn () => [
                'id' => $this->patient->id,
                'patient_code' => $this->patient->patient_code,
                'full_name' => $this->patient->full_name,
            ]),
            'lab' => $this->whenLoaded('lab', fn () => [
                'id' => $this->lab->id,
                'name' => $this->lab->name,
            ]),
            'dentist' => $this->whenLoaded('dentist', fn () => $this->dentist ? [
                'id' => $this->dentist->id,
                'name' => $this->dentist->name,
            ] : null),
        ];
    }
}
