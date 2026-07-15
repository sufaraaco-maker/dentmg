<?php

namespace App\Http\Resources;

use App\Models\Appointment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Appointment */
class AppointmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'patient_id' => $this->patient_id,
            'dentist_id' => $this->dentist_id,
            'appointment_type_id' => $this->appointment_type_id,
            'start_at' => $this->start_at->toIso8601String(),
            'end_at' => $this->end_at->toIso8601String(),
            'duration_minutes' => $this->duration_minutes,
            'status' => $this->status->value,
            'reason' => $this->reason,
            'notes' => $this->notes,
            'cancellation_reason' => $this->cancellation_reason,
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'cancelled_by' => $this->cancelled_by,
            'checked_in_at' => $this->checked_in_at?->toIso8601String(),
            'started_at' => $this->started_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'no_show_at' => $this->no_show_at?->toIso8601String(),
            'reschedule_count' => $this->reschedule_count,
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
            'patient' => $this->whenLoaded('patient', fn () => [
                'id' => $this->patient->id,
                'patient_code' => $this->patient->patient_code,
                'full_name' => $this->patient->full_name,
            ]),
            'dentist' => $this->whenLoaded('dentist', fn () => [
                'id' => $this->dentist->id,
                'name' => $this->dentist->name,
            ]),
            'appointment_type' => $this->whenLoaded('appointmentType', fn () => new AppointmentTypeResource($this->appointmentType)),
        ];
    }
}
