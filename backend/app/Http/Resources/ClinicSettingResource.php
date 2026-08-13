<?php

namespace App\Http\Resources;

use App\Models\ClinicSetting;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

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
            'logo_url' => $this->logo_path
                ? Storage::disk($this->logo_disk)->url($this->logo_path)
                : null,
            'ai_assistant_enabled' => $this->ai_assistant_enabled,
            'ai_assistant_phi_features_acknowledged' => $this->ai_assistant_phi_features_acknowledged,
            // The raw key is never returned (ai-assistant-settings-api-key-design.md §5/§8) — only
            // whether one is set and its last 4 characters, so an admin can recognize the active
            // key without it ever crossing the network again after the initial save.
            'ai_assistant_api_key_configured' => (bool) $this->ai_assistant_api_key,
            'ai_assistant_api_key_last4' => $this->ai_assistant_api_key
                ? substr($this->ai_assistant_api_key, -4)
                : null,
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
