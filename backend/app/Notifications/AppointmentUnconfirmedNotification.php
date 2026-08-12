<?php

namespace App\Notifications;

use App\Enums\UserRole;
use App\Models\Appointment;
use App\Services\RecipientResolver;
use Illuminate\Database\Eloquent\Collection;

/**
 * Type 11 of Phase 5C's scheduled types (design doc §5.2). Dispatched daily by
 * `NotifyUnconfirmedAppointments` for tomorrow's appointments still in `AppointmentStatus::Scheduled`
 * — gives front desk the whole next morning to call and confirm before the appointment itself.
 */
class AppointmentUnconfirmedNotification extends BaseNotification
{
    public function type(): string
    {
        return 'appointment.unconfirmed';
    }

    public function category(): string
    {
        return 'appointments';
    }

    public function params(): array
    {
        /** @var Appointment $appointment */
        $appointment = $this->subject;

        return [
            'patientName' => $appointment->patient?->full_name,
            'startAt' => $appointment->start_at->toIso8601String(),
        ];
    }

    public function route(): array
    {
        return ['name' => 'appointment-detail', 'params' => ['id' => $this->subject->getKey()]];
    }

    public function recipients(RecipientResolver $resolver): Collection
    {
        return $resolver->byRoles([UserRole::Receptionist, UserRole::Admin]);
    }
}
