<?php

namespace App\Http\Requests\AppointmentType;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAppointmentTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('appointment_type'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'default_duration_minutes' => ['sometimes', 'required', 'integer', 'min:5', 'max:480'],
            'color' => ['sometimes', 'required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
