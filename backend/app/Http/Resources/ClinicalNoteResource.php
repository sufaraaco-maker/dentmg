<?php

namespace App\Http\Resources;

use App\Models\ClinicalNote;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ClinicalNote */
class ClinicalNoteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'patient_id' => $this->patient_id,
            'appointment_id' => $this->appointment_id,
            'dentist_id' => $this->dentist_id,
            'note_type' => $this->note_type->value,
            'chief_complaint' => $this->chief_complaint,
            'subjective' => $this->subjective,
            'objective' => $this->objective,
            'assessment' => $this->assessment,
            'plan' => $this->plan,
            'status' => $this->status->value,
            'signed_at' => $this->signed_at?->toIso8601String(),
            'signed_by_id' => $this->signed_by_id,
            'created_by_id' => $this->created_by_id,
            'updated_by_id' => $this->updated_by_id,
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
            'dentist' => $this->whenLoaded('dentist', fn () => [
                'id' => $this->dentist->id,
                'name' => $this->dentist->name,
            ]),
            'signed_by' => $this->whenLoaded('signedBy', fn () => $this->signedBy ? [
                'id' => $this->signedBy->id,
                'name' => $this->signedBy->name,
            ] : null),
            'created_by' => $this->whenLoaded('createdBy', fn () => [
                'id' => $this->createdBy->id,
                'name' => $this->createdBy->name,
            ]),
            'addendums' => ClinicalNoteAddendumResource::collection($this->whenLoaded('addendums')),
        ];
    }
}
