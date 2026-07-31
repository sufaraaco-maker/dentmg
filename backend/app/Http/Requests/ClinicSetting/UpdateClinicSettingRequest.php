<?php

namespace App\Http\Requests\ClinicSetting;

use App\Models\ClinicSetting;
use Illuminate\Foundation\Http\FormRequest;

class UpdateClinicSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', ClinicSetting::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:2000'],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            // AI Assistant toggles (design doc §3/§7) — admin-only, same gate as every other field
            // on this singleton; kept separate from each other so the zero-PHI features can be
            // enabled without also acknowledging the PHI-bearing features' BAA prerequisite.
            'ai_assistant_enabled' => ['sometimes', 'boolean'],
            'ai_assistant_phi_features_acknowledged' => ['sometimes', 'boolean'],
        ];
    }
}
