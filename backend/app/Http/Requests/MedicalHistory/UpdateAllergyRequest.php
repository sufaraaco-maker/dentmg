<?php

namespace App\Http\Requests\MedicalHistory;

use App\Enums\AllergySeverity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAllergyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('allergy'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'allergen' => ['sometimes', 'required', 'string', 'max:255'],
            'severity' => ['nullable', Rule::enum(AllergySeverity::class)],
            'reaction' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
