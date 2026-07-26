<?php

namespace App\Http\Resources;

use App\Models\ClinicalNoteAddendum;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ClinicalNoteAddendum */
class ClinicalNoteAddendumResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'clinical_note_id' => $this->clinical_note_id,
            'body' => $this->body,
            'created_at' => $this->created_at->toIso8601String(),
            'author' => $this->whenLoaded('author', fn () => [
                'id' => $this->author->id,
                'name' => $this->author->name,
            ]),
        ];
    }
}
