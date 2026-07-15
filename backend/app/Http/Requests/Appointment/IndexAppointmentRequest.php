<?php

namespace App\Http\Requests\Appointment;

use App\Enums\AppointmentStatus;
use App\Enums\UserRole;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexAppointmentRequest extends FormRequest
{
    /**
     * Open to any authenticated role (design doc §14 — clinic-wide read visibility); the
     * controller still calls `AppointmentPolicy::viewAny()` for consistency with the rest of
     * the API, per `api-guidelines.md`.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Bounded by a required date range instead of classic pagination (design doc §16/§19).
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from'],
            'dentist_id' => [
                'nullable', 'uuid',
                Rule::exists('users', 'id')->where(
                    fn (Builder $query) => $query->where('role', UserRole::Dentist->value)->whereNull('deleted_at')
                ),
            ],
            'patient_id' => [
                'nullable', 'uuid',
                Rule::exists('patients', 'id')->whereNull('deleted_at'),
            ],
            'status' => ['nullable', Rule::enum(AppointmentStatus::class)],
        ];
    }
}
