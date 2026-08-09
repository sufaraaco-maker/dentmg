<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\DentistTimeOff;
use App\Models\User;

class DentistTimeOffPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission('dentist_time_off.view');
    }

    public function view(User $actor, DentistTimeOff $timeOff): bool
    {
        return $actor->hasPermission('dentist_time_off.view');
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

        return $actor->hasPermission('dentist_time_off.manage_any') || $actor->is($dentist);
    }

    public function delete(User $actor, DentistTimeOff $timeOff): bool
    {
        return $actor->hasPermission('dentist_time_off.manage_any') || $actor->is($timeOff->dentist);
    }
}
