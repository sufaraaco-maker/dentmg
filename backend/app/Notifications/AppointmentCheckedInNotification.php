<?php

namespace App\Notifications;

use App\Models\Appointment;
use App\Services\RecipientResolver;
use Illuminate\Database\Eloquent\Collection;

/**
 * Type 1 of the 8 whitelisted V1 types (design doc §5.1) — "your patient has arrived."
 * Recipient: the assigned dentist only. The front desk performed the check-in, so they are the
 * actor and are excluded centrally by NotificationService.
 */
class AppointmentCheckedInNotification extends BaseNotification
{
    public function type(): string
    {
        return 'appointment.checked_in';
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
        return $resolver->byId($this->subject->getAttribute('dentist_id'));
    }
}
