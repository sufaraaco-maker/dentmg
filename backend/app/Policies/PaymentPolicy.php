<?php

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;

/**
 * Mirrors InvoicePolicy exactly (design doc §10, resolved at approval 2026-07-25) — payment
 * recording is front-desk/administrative work, not a clinical action: admin+receptionist write,
 * dentist read-only. Delete is gated tighter (admin-only), matching InvoicePolicy::delete()'s
 * identical stricter-than-everything-else precedent.
 */
class PaymentPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission('payments.view');
    }

    public function view(User $actor, Payment $payment): bool
    {
        return $actor->hasPermission('payments.view');
    }

    public function create(User $actor): bool
    {
        return $actor->hasPermission('payments.manage');
    }

    public function update(User $actor, Payment $payment): bool
    {
        return $actor->hasPermission('payments.manage');
    }

    public function apply(User $actor, Payment $payment): bool
    {
        return $actor->hasPermission('payments.manage');
    }

    public function refund(User $actor, Payment $payment): bool
    {
        return $actor->hasPermission('payments.manage');
    }

    public function delete(User $actor, Payment $payment): bool
    {
        return $actor->hasPermission('payments.delete');
    }
}
