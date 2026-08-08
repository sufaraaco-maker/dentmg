<?php

namespace App\Http\Requests\MedicalHistory;

use App\Enums\MedicalConditionStatus;
use App\Models\PatientMedicalCondition;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMedicalConditionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', PatientMedicalCondition::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'condition_name' => ['required', 'string', 'max:255'],
            'status' => ['nullable', Rule::enum(MedicalConditionStatus::class)],
            'diagnosed_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
