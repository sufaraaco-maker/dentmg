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
        return $actor->hasPermission('suppliers.view');
    }

    public function view(User $actor, Supplier $supplier): bool
    {
        return $actor->hasPermission('suppliers.view');
    }

    public function create(User $actor): bool
    {
        return $actor->hasPermission('suppliers.manage');
    }

    public function update(User $actor, Supplier $supplier): bool
    {
        return $actor->hasPermission('suppliers.manage');
    }

    public function delete(User $actor, Supplier $supplier): bool
    {
        return $actor->hasPermission('suppliers.manage');
    }
}
