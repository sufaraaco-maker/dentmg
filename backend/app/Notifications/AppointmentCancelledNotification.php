<?php

namespace App\Notifications;

use App\Enums\UserRole;
use App\Models\Appointment;
use App\Services\RecipientResolver;
use Illuminate\Database\Eloquent\Collection;

/**
 * Type 2 of the 8 whitelisted V1 types (design doc §5.1). The assigned dentist needs to know their
 * schedule changed; admins and receptionists need to know a slot has opened and can be refilled —
 * which is why this is the one appointment type that fans out beyond the dentist.
 */
class AppointmentCancelledNotification extends BaseNotification
{
    public function type(): string
    {
        return 'appointment.cancelled';
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
            'reason' => $appointment->cancellation_reason,
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
            $resolver->byRoles([UserRole::Admin, UserRole::Receptionist]),
        );
    }
}
