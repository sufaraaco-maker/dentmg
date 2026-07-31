<?php

namespace App\Http\Resources;

use App\Models\ClinicSetting;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ClinicSetting */
class ClinicSettingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone' => $this->phone,
            'address' => $this->address,
            'email' => $this->email,
            'ai_assistant_enabled' => $this->ai_assistant_enabled,
            'ai_assistant_phi_features_acknowledged' => $this->ai_assistant_phi_features_acknowledged,
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
