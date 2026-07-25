<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\InvoiceItem;
use App\Models\User;

/**
 * Same admin+receptionist-write/dentist-read-only split as InvoicePolicy (design doc §10). Item
 * removal is NOT gated admin-only the way Invoice-level delete is: an InvoiceItem has no
 * cancel/void action of its own (design doc §5 — "no separate status enum for InvoiceItem"), so
 * removing a still-draft line item is normal invoice-building work, not a data-correction action
 * needing extra restriction — the real safeguard is InvoiceService::assertEditable() making
 * removal impossible at all once the parent invoice leaves draft, regardless of role.
 */
class InvoiceItemPolicy
{
    public function viewAny(User $actor): bool
    {
        return true;
    }

    public function view(User $actor, InvoiceItem $invoiceItem): bool
    {
        return true;
    }

    public function create(User $actor): bool
    {
        return in_array($actor->role, [UserRole::Admin, UserRole::Receptionist], true);
    }

    public function update(User $actor, InvoiceItem $invoiceItem): bool
    {
        return in_array($actor->role, [UserRole::Admin, UserRole::Receptionist], true);
    }

    public function delete(User $actor, InvoiceItem $invoiceItem): bool
    {
        return in_array($actor->role, [UserRole::Admin, UserRole::Receptionist], true);
    }
}
