<?php

namespace App\Policies;

use App\Models\AppointmentType;
use App\Models\User;

class AppointmentTypePolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission('appointment_types.view');
    }

    public function view(User $actor, AppointmentType $appointmentType): bool
    {
        return $actor->hasPermission('appointment_types.view');
    }

    /**
     * Appointment types are clinic configuration — admin only (design doc §14).
     */
    public function create(User $actor): bool
    {
        return $actor->hasPermission('appointment_types.manage');
    }

    public function update(User $actor, AppointmentType $appointmentType): bool
    {
        return $actor->hasPermission('appointment_types.manage');
    }

    public function delete(User $actor, AppointmentType $appointmentType): bool
    {
        return $actor->hasPermission('appointment_types.manage');
    }
}
