<?php

namespace App\Http\Requests\Appointment;

use Illuminate\Foundation\Http\FormRequest;

class MarkNoShowAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('markNoShow', $this->route('appointment'));
    }

    /**
     * `no_show` is normally only settable after `start_at` has passed — a soft rule enforced
     * in AppointmentService, overridable via this flag for staff backfilling records (§5.9/§13).
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'override_early_no_show' => ['sometimes', 'boolean'],
        ];
    }
}
