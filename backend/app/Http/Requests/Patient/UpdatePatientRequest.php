<?php

namespace App\Http\Requests\Patient;

use App\Enums\BloodType;
use App\Enums\PatientGender;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePatientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('patient'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'first_name' => ['sometimes', 'required', 'string', 'max:100'],
            'last_name' => ['sometimes', 'required', 'string', 'max:100'],
            'date_of_birth' => ['sometimes', 'required', 'date', 'before:today'],
            'gender' => ['sometimes', 'required', Rule::enum(PatientGender::class)],
            'phone' => ['sometimes', 'required', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'national_id' => [
                'nullable', 'string', 'max:50',
                Rule::unique('patients', 'national_id')->ignore($this->route('patient')),
            ],
            'emergency_contact_name' => ['nullable', 'string', 'max:150'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:30'],
            'blood_type' => ['nullable', Rule::enum(BloodType::class)],
            'allergies' => ['nullable', 'string', 'max:2000'],
            'medical_history' => ['nullable', 'string', 'max:5000'],
            'insurance_provider' => ['nullable', 'string', 'max:150'],
            'insurance_number' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
