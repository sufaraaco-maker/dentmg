<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Supply;
use App\Models\User;

/**
 * Design doc §10/§15 Decision 1 (approved 2026-07-26): managing the Supply catalog itself
 * (reorder settings, cost, default supplier) is admin+receptionist — a front-desk/administrative
 * function, same split as Payments/Billing. Dentists get read-only access here; their write
 * access is scoped to logging usage only (StockMovementPolicy::create()), not the catalog.
 */
class SupplyPolicy
{
    public function viewAny(User $actor): bool
    {
        return true;
    }

    public function view(User $actor, Supply $supply): bool
    {
        return true;
    }

    public function create(User $actor): bool
    {
        return in_array($actor->role, [UserRole::Admin, UserRole::Receptionist], true);
    }

    public function update(User $actor, Supply $supply): bool
    {
        return in_array($actor->role, [UserRole::Admin, UserRole::Receptionist], true);
    }

    public function delete(User $actor, Supply $supply): bool
    {
        return in_array($actor->role, [UserRole::Admin, UserRole::Receptionist], true);
    }
}
