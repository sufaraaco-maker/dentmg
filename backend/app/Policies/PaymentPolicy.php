<?php

namespace App\Policies;

use App\Enums\UserRole;
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
        return true;
    }

    public function view(User $actor, Payment $payment): bool
    {
        return true;
    }

    public function create(User $actor): bool
    {
        return in_array($actor->role, [UserRole::Admin, UserRole::Receptionist], true);
    }

    public function update(User $actor, Payment $payment): bool
    {
        return in_array($actor->role, [UserRole::Admin, UserRole::Receptionist], true);
    }

    public function apply(User $actor, Payment $payment): bool
    {
        return in_array($actor->role, [UserRole::Admin, UserRole::Receptionist], true);
    }

    public function refund(User $actor, Payment $payment): bool
    {
        return in_array($actor->role, [UserRole::Admin, UserRole::Receptionist], true);
    }

    public function delete(User $actor, Payment $payment): bool
    {
        return $actor->isAdmin();
    }
}
