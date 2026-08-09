<?php

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;

/**
 * Billing is front-desk/administrative work, not a clinical action — admin+receptionist write,
 * dentist read-only, mirroring Patients' precedent rather than Treatment Plans'/Dental Chart's
 * admin+dentist split (design doc §10, Decision Log item... confirmed at refinement review as
 * "approved as recommended, no changes requested").
 *
 * No clinic-membership check exists anywhere below, for the same reason as every other module's
 * policy — DentalSuite V1 has no tenant/clinic model yet (design doc §14).
 */
class InvoicePolicy
{
    /**
     * Read access for all staff, including dentists — useful context during a clinical
     * conversation ("has this been billed yet"), mirroring Treatment Plans' identical
     * clinic-wide-read reasoning even though write access differs.
     */
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission('invoices.view');
    }

    public function view(User $actor, Invoice $invoice): bool
    {
        return $actor->hasPermission('invoices.view');
    }

    public function create(User $actor): bool
    {
        return $actor->hasPermission('invoices.manage');
    }

    /**
     * Whether the invoice's current status still allows an edit is a Service-layer concern
     * (InvoiceService::assertEditable(), design doc §8, requirement 3) — this ability governs
     * *who* may attempt it, not *when*.
     */
    public function update(User $actor, Invoice $invoice): bool
    {
        return $actor->hasPermission('invoices.manage');
    }

    public function issue(User $actor, Invoice $invoice): bool
    {
        return $actor->hasPermission('invoices.manage');
    }

    public function void(User $actor, Invoice $invoice): bool
    {
        return $actor->hasPermission('invoices.manage');
    }

    /**
     * Delete is gated tighter than every other action (admin-only) — a data-correction action on
     * a still-draft invoice, mirroring Treatment Plans'/Dental Chart's identical delete-vs-cancel
     * (here, delete-vs-void) split. Only reachable on a draft anyway (InvoiceService), but the
     * Policy is deliberately stricter regardless of that Service-layer state check.
     */
    public function delete(User $actor, Invoice $invoice): bool
    {
        return $actor->hasPermission('invoices.delete');
    }
}
