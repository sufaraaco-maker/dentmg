<?php

namespace App\Policies;

use App\Models\DentalCondition;
use App\Models\User;

class DentalConditionPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission('dental_conditions.view');
    }

    public function view(User $actor, DentalCondition $dentalCondition): bool
    {
        return $actor->hasPermission('dental_conditions.view');
    }

    /**
     * The dental condition catalog is clinic configuration — admin only (plan §1.8), mirroring
     * `AppointmentTypePolicy` exactly.
     */
    public function create(User $actor): bool
    {
        return $actor->hasPermission('dental_conditions.manage');
    }

    public function update(User $actor, DentalCondition $dentalCondition): bool
    {
        return $actor->hasPermission('dental_conditions.manage');
    }

    public function delete(User $actor, DentalCondition $dentalCondition): bool
    {
        return $actor->hasPermission('dental_conditions.manage');
    }
}
