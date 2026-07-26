<?php

namespace App\Policies;

use App\Models\Supplier;
use App\Models\User;

/**
 * Design doc §10: Supplier management is admin-only, mirroring DentalConditionPolicy/
 * AppointmentTypePolicy exactly — clinic configuration, not day-to-day operational entry.
 */
class SupplierPolicy
{
    public function viewAny(User $actor): bool
    {
        return true;
    }

    public function view(User $actor, Supplier $supplier): bool
    {
        return true;
    }

    public function create(User $actor): bool
    {
        return $actor->isAdmin();
    }

    public function update(User $actor, Supplier $supplier): bool
    {
        return $actor->isAdmin();
    }

    public function delete(User $actor, Supplier $supplier): bool
    {
        return $actor->isAdmin();
    }
}
