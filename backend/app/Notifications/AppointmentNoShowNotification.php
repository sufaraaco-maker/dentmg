<?php

namespace App\Notifications;

use App\Enums\UserRole;
use App\Models\Appointment;
use App\Services\RecipientResolver;
use Illuminate\Database\Eloquent\Collection;

/**
 * Type 3 of the 8 whitelisted V1 types (design doc §5.1). Dentist + admins — a no-show is a
 * schedule fact for the dentist and a follow-up/policy matter for management. Receptionists are
 * deliberately not included: they are almost always the actor here.
 */
class AppointmentNoShowNotification extends BaseNotification
{
    public function type(): string
    {
        return 'appointment.no_show';
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
        return $resolver->merge(
            $resolver->byId($this->subject->getAttribute('dentist_id')),
            $resolver->byRoles([UserRole::Admin]),
        );
    }
}
