<?php

namespace App\Http\Requests\MedicalHistory;

use App\Enums\AllergySeverity;
use App\Models\PatientAllergy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAllergyRequest extends FormRequest
{
    /**
     * `patient_id` is not a field here — the parent Patient comes from the nested route
     * (`patients/{patient}/allergies`), same pattern as `StoreDentalChartEntryRequest`.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', PatientAllergy::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'allergen' => ['required', 'string', 'max:255'],
            'severity' => ['nullable', Rule::enum(AllergySeverity::class)],
            'reaction' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
