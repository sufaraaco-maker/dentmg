<?php

namespace App\Notifications;

use App\Models\Appointment;
use App\Services\RecipientResolver;
use Illuminate\Database\Eloquent\Collection;

/**
 * "A new appointment landed on your schedule." Recipient: the assigned dentist only — whoever
 * created the appointment (admin or receptionist) already knows they just did it, so they are
 * excluded either way, centrally by NotificationService, exactly like AppointmentCheckedInNotification.
 */
class AppointmentCreatedNotification extends BaseNotification
{
    public function type(): string
    {
        return 'appointment.created';
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
