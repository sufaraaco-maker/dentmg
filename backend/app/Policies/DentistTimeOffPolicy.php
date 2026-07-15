<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\DentistTimeOff;
use App\Models\User;

class DentistTimeOffPolicy
{
    public function viewAny(User $actor): bool
    {
        return true;
    }

    public function view(User $actor, DentistTimeOff $timeOff): bool
    {
        return true;
    }

    /**
     * Time-off is self-service: a dentist can manage their own; admin can manage anyone's
     * (design doc §6) — routine requests shouldn't need an admin as a bottleneck.
     */
    public function create(User $actor, User $dentist): bool
    {
        if ($dentist->role !== UserRole::Dentist) {
            return false;
        }

        return $actor->isAdmin() || $actor->is($dentist);
    }

    public function delete(User $actor, DentistTimeOff $timeOff): bool
    {
        return $actor->isAdmin() || $actor->is($timeOff->dentist);
    }
}
