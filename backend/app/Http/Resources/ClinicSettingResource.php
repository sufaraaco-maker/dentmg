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
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
