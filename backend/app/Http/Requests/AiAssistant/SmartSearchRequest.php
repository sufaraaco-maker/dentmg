<?php

namespace App\Http\Requests\AiAssistant;

use App\Models\Appointment;
use App\Models\Patient;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Mirrors `PatientPolicy`/`AppointmentPolicy`'s existing `viewAny` — every role (design doc §7).
 */
class SmartSearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', Patient::class) && $this->user()->can('viewAny', Appointment::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'query' => ['required', 'string', 'max:1000'],
        ];
    }
}
