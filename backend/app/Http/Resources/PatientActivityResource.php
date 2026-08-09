<?php

namespace App\Http\Resources;

use App\Models\PatientActivity;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Never joins back to the subject table (design doc §6/§9.3) — `summary` was precomputed at
 * dispatch time specifically so this resource doesn't need to.
 *
 * @mixin PatientActivity
 */
class PatientActivityResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'patient_id' => $this->patient_id,
            'event_type' => $this->event_type,
            'category' => $this->category,
            'subject_type' => $this->subject_type,
            'subject_id' => $this->subject_id,
            'actor_id' => $this->actor_id,
            'summary' => $this->summary,
            'metadata' => $this->metadata,
            'occurred_at' => $this->occurred_at->toIso8601String(),
            'actor' => $this->whenLoaded('actor', fn () => $this->actor ? [
                'id' => $this->actor->id,
                'name' => $this->actor->name,
            ] : null),
        ];
    }
}
