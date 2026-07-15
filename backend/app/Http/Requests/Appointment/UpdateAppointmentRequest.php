<?php

namespace App\Http\Requests\Appointment;

use App\Enums\UserRole;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('appointment'));
    }

    /**
     * Covers the "full edit" and in-place reschedule flows (design doc §11). `patient_id` is
     * deliberately not editable here — booking the wrong patient entirely is a soft-delete
     * case (§4), not a correction via update. `status` is not editable here either — it only
     * moves through the dedicated transition endpoints (confirm/check-in/start/.../cancel).
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'dentist_id' => [
                'sometimes', 'required', 'uuid',
                Rule::exists('users', 'id')->where(
                    fn (Builder $query) => $query->where('role', UserRole::Dentist->value)->whereNull('deleted_at')
                ),
            ],
            'appointment_type_id' => [
                'sometimes', 'required', 'uuid',
                Rule::exists('appointment_types', 'id')->where('is_active', true),
            ],
            'start_at' => ['sometimes', 'required', 'date'],
            'duration_minutes' => ['sometimes', 'required', 'integer', 'min:5', 'max:480'],
            'reason' => ['nullable', 'string', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'override_patient_conflict' => ['sometimes', 'boolean'],
            'override_outside_working_hours' => ['sometimes', 'boolean'],
        ];
    }
}
