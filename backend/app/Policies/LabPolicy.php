<?php

namespace App\Policies;

use App\Models\Lab;
use App\Models\User;

/**
 * Design doc §5/§7 decision 8: Lab vendor management is admin-only, mirroring SupplierPolicy
 * exactly — clinic configuration, not day-to-day operational entry.
 */
class LabPolicy
{
    public function viewAny(User $actor): bool
    {
        return true;
    }

    public function view(User $actor, Lab $lab): bool
    {
        return true;
    }

    public function create(User $actor): bool
    {
        return $actor->isAdmin();
    }

    public function update(User $actor, Lab $lab): bool
    {
        return $actor->isAdmin();
    }

    public function delete(User $actor, Lab $lab): bool
    {
        return $actor->isAdmin();
    }
}
