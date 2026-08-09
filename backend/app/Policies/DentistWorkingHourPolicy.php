<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\DentistWorkingHour;
use App\Models\User;

class DentistWorkingHourPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission('dentist_working_hours.view');
    }

    public function view(User $actor, DentistWorkingHour $workingHour): bool
    {
        return $actor->hasPermission('dentist_working_hours.view');
    }

    /**
     * The recurring weekly template is admin-only, for any dentist (design doc §6) —
     * prevents a dentist from silently expanding their own booking window.
     */
    public function create(User $actor, User $dentist): bool
    {
        return $actor->hasPermission('dentist_working_hours.manage') && $dentist->role === UserRole::Dentist;
    }

    public function delete(User $actor, DentistWorkingHour $workingHour): bool
    {
        return $actor->hasPermission('dentist_working_hours.manage');
    }
}
