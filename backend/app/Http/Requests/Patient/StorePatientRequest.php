<?php

namespace App\Http\Requests\Patient;

use App\Enums\BloodType;
use App\Enums\PatientGender;
use App\Models\Patient;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePatientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Patient::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'date_of_birth' => ['required', 'date', 'before:today'],
            'gender' => ['required', Rule::enum(PatientGender::class)],
            'phone' => ['required', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'national_id' => ['nullable', 'string', 'max:50', Rule::unique('patients', 'national_id')],
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
