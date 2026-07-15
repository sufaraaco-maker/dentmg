<?php

namespace App\Http\Requests\AppointmentType;

use App\Models\AppointmentType;
use Illuminate\Foundation\Http\FormRequest;

class StoreAppointmentTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', AppointmentType::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'default_duration_minutes' => ['required', 'integer', 'min:5', 'max:480'],
            'color' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
