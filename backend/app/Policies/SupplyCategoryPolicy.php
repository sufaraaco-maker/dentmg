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
        return $actor->hasPermission('supply_categories.view');
    }

    public function view(User $actor, SupplyCategory $supplyCategory): bool
    {
        return $actor->hasPermission('supply_categories.view');
    }

    public function create(User $actor): bool
    {
        return $actor->hasPermission('supply_categories.manage');
    }

    public function update(User $actor, SupplyCategory $supplyCategory): bool
    {
        return $actor->hasPermission('supply_categories.manage');
    }

    public function delete(User $actor, SupplyCategory $supplyCategory): bool
    {
        return $actor->hasPermission('supply_categories.manage');
    }
}
