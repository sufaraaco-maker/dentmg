<?php

namespace App\Http\Requests\Appointment;

use App\Enums\UserRole;
use App\Models\Appointment;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Appointment::class);
    }

    /**
     * Shape/reference validation only. Availability, conflict detection, and slot
     * computation are AppointmentService concerns (design doc §17), not this layer.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'patient_id' => [
                'required', 'uuid',
                Rule::exists('patients', 'id')->whereNull('deleted_at'),
            ],
            'dentist_id' => [
                'required', 'uuid',
                Rule::exists('users', 'id')->where(
                    fn (Builder $query) => $query->where('role', UserRole::Dentist->value)->whereNull('deleted_at')
                ),
            ],
            'appointment_type_id' => [
                'required', 'uuid',
                Rule::exists('appointment_types', 'id')->where('is_active', true),
            ],
            'start_at' => ['required', 'date'],
            'duration_minutes' => ['required', 'integer', 'min:5', 'max:480'],
            'reason' => ['nullable', 'string', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:2000'],

            // Explicit client acknowledgement of the AppointmentService soft-warning checks
            // (patient double-booking, outside working hours) — design doc §5.4/§5.5.
            'override_patient_conflict' => ['sometimes', 'boolean'],
            'override_outside_working_hours' => ['sometimes', 'boolean'],
        ];
    }
}
