<?php

namespace App\Policies;

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
        return $actor->hasPermission('purchase_orders.view');
    }

    public function view(User $actor, PurchaseOrder $purchaseOrder): bool
    {
        return $actor->hasPermission('purchase_orders.view');
    }

    public function create(User $actor): bool
    {
        return $actor->hasPermission('purchase_orders.manage');
    }

    public function update(User $actor, PurchaseOrder $purchaseOrder): bool
    {
        return $actor->hasPermission('purchase_orders.manage');
    }

    public function place(User $actor, PurchaseOrder $purchaseOrder): bool
    {
        return $actor->hasPermission('purchase_orders.manage');
    }

    public function receive(User $actor, PurchaseOrder $purchaseOrder): bool
    {
        return $actor->hasPermission('purchase_orders.manage');
    }

    public function cancel(User $actor, PurchaseOrder $purchaseOrder): bool
    {
        return $actor->hasPermission('purchase_orders.manage');
    }

    public function delete(User $actor, PurchaseOrder $purchaseOrder): bool
    {
        return $actor->hasPermission('purchase_orders.delete');
    }
}
