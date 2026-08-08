<?php

namespace App\Http\Requests\MedicalHistory;

use App\Enums\MedicalConditionStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMedicalConditionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('medical_condition'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'condition_name' => ['sometimes', 'required', 'string', 'max:255'],
            'status' => ['nullable', Rule::enum(MedicalConditionStatus::class)],
            'diagnosed_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
