<?php

namespace App\Policies;

use App\Models\Patient;
use App\Models\User;

class PatientPolicy
{
    /**
     * Any authenticated staff member can look up patients.
     */
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission('patients.view');
    }

    public function view(User $actor, Patient $patient): bool
    {
        return $actor->hasPermission('patients.view');
    }

    /**
     * Registering/editing patient records is front-desk work (admin, receptionist).
     * Dentists get read access here; clinical write access belongs to the future
     * Clinical Notes module rather than this one.
     */
    public function create(User $actor): bool
    {
        return $actor->hasPermission('patients.manage');
    }

    public function update(User $actor, Patient $patient): bool
    {
        return $actor->hasPermission('patients.manage');
    }

    public function delete(User $actor, Patient $patient): bool
    {
        return $actor->hasPermission('patients.delete');
    }

    public function viewAuditLogs(User $actor): bool
    {
        return $actor->hasPermission('patients.view_audit_logs');
    }
}
