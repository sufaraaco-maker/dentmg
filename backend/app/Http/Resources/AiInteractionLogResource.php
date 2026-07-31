<?php

namespace App\Http\Resources;

use App\Models\AiInteractionLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AiInteractionLog */
class AiInteractionLogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'feature' => $this->feature->value,
            'patient_id' => $this->patient_id,
            'prompt_summary' => $this->prompt_summary,
            'response_summary' => $this->response_summary,
            'accepted' => $this->accepted,
            'model' => $this->model,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
