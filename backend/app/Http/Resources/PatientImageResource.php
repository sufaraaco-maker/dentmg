<?php

namespace App\Http\Resources;

use App\Models\PatientImage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Deliberately never exposes `disk`/`path` (internal storage details) — only the authenticated,
 * policy-checked streaming URLs (design doc §9). Never serve images from a public/static URL.
 *
 * @mixin PatientImage
 */
class PatientImageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'patient_id' => $this->patient_id,
            'uploaded_by' => $this->uploaded_by,
            'image_type' => $this->image_type->value,
            'tooth_number' => $this->tooth_number,
            'surfaces' => $this->surfaces,
            'taken_at' => $this->taken_at->toDateString(),
            'treatment_plan_item_id' => $this->treatment_plan_item_id,
            'appointment_id' => $this->appointment_id,
            'mime_type' => $this->mime_type,
            'file_size' => $this->file_size,
            'width' => $this->width,
            'height' => $this->height,
            'notes' => $this->notes,
            'file_url' => route('patient-images.file', $this->id),
            'thumbnail_url' => $this->thumbnail_path ? route('patient-images.thumbnail', $this->id) : null,
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
            'uploaded_by_user' => $this->whenLoaded('uploadedBy', fn () => $this->uploadedBy ? [
                'id' => $this->uploadedBy->id,
                'name' => $this->uploadedBy->name,
            ] : null),
        ];
    }
}
