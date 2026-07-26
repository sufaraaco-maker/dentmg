<?php

namespace App\Policies;

use App\Models\SupplyCategory;
use App\Models\User;

/**
 * Design doc §10: Supply Category management is admin-only, mirroring SupplierPolicy exactly.
 */
class SupplyCategoryPolicy
{
    public function viewAny(User $actor): bool
    {
        return true;
    }

    public function view(User $actor, SupplyCategory $supplyCategory): bool
    {
        return true;
    }

    public function create(User $actor): bool
    {
        return $actor->isAdmin();
    }

    public function update(User $actor, SupplyCategory $supplyCategory): bool
    {
        return $actor->isAdmin();
    }

    public function delete(User $actor, SupplyCategory $supplyCategory): bool
    {
        return $actor->isAdmin();
    }
}
