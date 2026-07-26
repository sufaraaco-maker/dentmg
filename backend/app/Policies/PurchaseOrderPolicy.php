<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\PurchaseOrder;
use App\Models\User;

/**
 * Design doc §10: procurement (creating/placing/receiving/cancelling Purchase Orders) is
 * admin+receptionist, mirroring Billing/Payments' identical front-desk/administrative split.
 * Delete is gated tighter (admin-only, draft-only correction), matching
 * InvoicePolicy::delete()/PaymentPolicy::delete()'s identical stricter-than-everything-else
 * precedent.
 */
class PurchaseOrderPolicy
{
    public function viewAny(User $actor): bool
    {
        return true;
    }

    public function view(User $actor, PurchaseOrder $purchaseOrder): bool
    {
        return true;
    }

    public function create(User $actor): bool
    {
        return in_array($actor->role, [UserRole::Admin, UserRole::Receptionist], true);
    }

    public function update(User $actor, PurchaseOrder $purchaseOrder): bool
    {
        return in_array($actor->role, [UserRole::Admin, UserRole::Receptionist], true);
    }

    public function place(User $actor, PurchaseOrder $purchaseOrder): bool
    {
        return in_array($actor->role, [UserRole::Admin, UserRole::Receptionist], true);
    }

    public function receive(User $actor, PurchaseOrder $purchaseOrder): bool
    {
        return in_array($actor->role, [UserRole::Admin, UserRole::Receptionist], true);
    }

    public function cancel(User $actor, PurchaseOrder $purchaseOrder): bool
    {
        return in_array($actor->role, [UserRole::Admin, UserRole::Receptionist], true);
    }

    public function delete(User $actor, PurchaseOrder $purchaseOrder): bool
    {
        return $actor->isAdmin();
    }
}
