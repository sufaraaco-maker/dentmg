<?php

namespace App\Http\Requests\DentistWorkingHour;

use App\Models\DentistWorkingHour;
use Illuminate\Foundation\Http\FormRequest;

class StoreDentistWorkingHourRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', [DentistWorkingHour::class, $this->route('user')]);
    }

    /**
     * Multiple rows per `day_of_week` are valid (lunch-break split shifts) — no uniqueness
     * rule here (design doc §6). Overlap-within-the-same-day sanity checking, if any, belongs
     * to AppointmentService, not this layer.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'day_of_week' => ['required', 'integer', 'between:0,6'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
