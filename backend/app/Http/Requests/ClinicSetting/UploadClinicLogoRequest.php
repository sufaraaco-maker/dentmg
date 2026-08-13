<?php

namespace App\Http\Requests\ClinicSetting;

use App\Models\ClinicSetting;
use Illuminate\Foundation\Http\FormRequest;

class UploadClinicLogoRequest extends FormRequest
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
            // No `svg` — an uploaded SVG can embed a `<script>`, and this file is later rendered
            // directly as a plain <img src> with no sanitization step, unlike the app's own
            // hand-authored `public/favicon.svg`. `image` (GD-based) rejects anything that isn't a
            // real decodable raster image, not just a spoofed extension/mime.
            'logo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ];
    }
}
