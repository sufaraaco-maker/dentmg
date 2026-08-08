<?php

namespace App\Http\Resources;

use App\Models\PatientDocument;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Deliberately never exposes `disk`/`path` (internal storage details) — only the authenticated,
 * policy-checked streaming URL. Mirrors PatientImageResource.
 *
 * @mixin PatientDocument
 */
class PatientDocumentResource extends JsonResource
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
            'category' => $this->category->value,
            'title' => $this->title,
            'original_filename' => $this->original_filename,
            'mime_type' => $this->mime_type,
            'file_size' => $this->file_size,
            'notes' => $this->notes,
            'file_url' => route('patient-documents.file', $this->id),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
            'uploaded_by_user' => $this->whenLoaded('uploadedBy', fn () => $this->uploadedBy ? [
                'id' => $this->uploadedBy->id,
                'name' => $this->uploadedBy->name,
            ] : null),
        ];
    }
}
