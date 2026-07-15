<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Patient;
use App\Models\User;

class PatientPolicy
{
    /**
     * Any authenticated staff member can look up patients.
     */
    public function viewAny(User $actor): bool
    {
        return true;
    }

    public function view(User $actor, Patient $patient): bool
    {
        return true;
    }

    /**
     * Registering/editing patient records is front-desk work (admin, receptionist).
     * Dentists get read access here; clinical write access belongs to the future
     * Clinical Notes module rather than this one.
     */
    public function create(User $actor): bool
    {
        return in_array($actor->role, [UserRole::Admin, UserRole::Receptionist], true);
    }

    public function update(User $actor, Patient $patient): bool
    {
        return in_array($actor->role, [UserRole::Admin, UserRole::Receptionist], true);
    }

    public function delete(User $actor, Patient $patient): bool
    {
        return $actor->isAdmin();
    }

    public function viewAuditLogs(User $actor): bool
    {
        return $actor->isAdmin();
    }
}
