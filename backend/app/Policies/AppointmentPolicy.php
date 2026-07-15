<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\User;

class AppointmentPolicy
{
    /**
     * Clinic-wide read visibility for all staff, including dentists (design doc §14).
     */
    public function viewAny(User $actor): bool
    {
        return true;
    }

    public function view(User $actor, Appointment $appointment): bool
    {
        return true;
    }

    /**
     * Booking, editing, and rescheduling is front-desk work (admin, receptionist).
     */
    public function create(User $actor): bool
    {
        return in_array($actor->role, [UserRole::Admin, UserRole::Receptionist], true);
    }

    public function update(User $actor, Appointment $appointment): bool
    {
        return in_array($actor->role, [UserRole::Admin, UserRole::Receptionist], true);
    }

    public function cancel(User $actor, Appointment $appointment): bool
    {
        return in_array($actor->role, [UserRole::Admin, UserRole::Receptionist], true);
    }

    public function markNoShow(User $actor, Appointment $appointment): bool
    {
        return in_array($actor->role, [UserRole::Admin, UserRole::Receptionist], true);
    }

    /**
     * Optional confirmation-call step is front-desk work (design doc §14).
     */
    public function confirm(User $actor, Appointment $appointment): bool
    {
        return in_array($actor->role, [UserRole::Admin, UserRole::Receptionist], true);
    }

    /**
     * Check-in is front-desk work (design doc §14).
     */
    public function checkIn(User $actor, Appointment $appointment): bool
    {
        return in_array($actor->role, [UserRole::Admin, UserRole::Receptionist], true);
    }

    /**
     * Progressing an appointment into the chair is open to front desk or the treating dentist
     * for their own appointment — IDOR check via `$actor->is($appointment->dentist)` prevents
     * one dentist from marking another dentist's patient as seen (design doc §14/§18).
     */
    public function start(User $actor, Appointment $appointment): bool
    {
        return in_array($actor->role, [UserRole::Admin, UserRole::Receptionist], true)
            || $actor->is($appointment->dentist);
    }

    /**
     * Same rule as `start` (design doc §14/§18).
     */
    public function complete(User $actor, Appointment $appointment): bool
    {
        return in_array($actor->role, [UserRole::Admin, UserRole::Receptionist], true)
            || $actor->is($appointment->dentist);
    }

    public function delete(User $actor, Appointment $appointment): bool
    {
        return $actor->isAdmin();
    }
}
