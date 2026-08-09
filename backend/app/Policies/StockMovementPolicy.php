<?php

namespace App\Policies;

use App\Models\StockMovement;
use App\Models\User;

/**
 * Design doc §10/§15 Decision 1 (approved 2026-07-26): every authenticated role may view a
 * Supply's movement ledger, and every authenticated role may record a manual movement — dentists
 * are the ones actually consuming supplies chairside, so restricting this to admin+receptionist
 * (as Billing/Payments do) would not reflect reality. `received` movements are never created via
 * this path at all (StockMovementService::record() only handles manually-selectable reasons), so
 * no role check needs to special-case it here.
 */
class StockMovementPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission('stock_movements.view');
    }

    public function view(User $actor, StockMovement $stockMovement): bool
    {
        return $actor->hasPermission('stock_movements.view');
    }

    public function create(User $actor): bool
    {
        return $actor->hasPermission('stock_movements.create');
    }
}
