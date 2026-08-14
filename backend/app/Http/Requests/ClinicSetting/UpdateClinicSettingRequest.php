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
            // `sometimes` — a caller updating only `logo_disk`/`logo_path` (or another subset of
            // fields) must not be forced to resend (and re-validate as non-blank) a practice name
            // it never touches; Practice Settings' own form still always sends `name`, so
            // `required` still applies whenever the field is actually present.
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:2000'],
            'email' => ['nullable', 'string', 'email', 'max:255'],
        ];
    }
}
