<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\LabCase;
use App\Models\User;

/**
 * Design doc §5 (Approval Log item 1): create/update while Draft is admin+dentist — choosing
 * lab/tooth/shade/material is a clinical prescription decision, mirroring ClinicalNotePolicy's
 * admin+dentist split. send/receive/qualityCheck (status transitions) are admin+receptionist —
 * once prescribed, packaging/shipping/receiving is front-desk logistics, mirroring
 * PurchaseOrderPolicy::place/receive exactly. cancel() stays with admin+dentist since reversing a
 * clinical decision is not a logistics action. Delete is gated tighter (admin-only), matching every
 * prior module's identical stricter-than-everything-else precedent.
 */
class LabCasePolicy
{
    public function viewAny(User $actor): bool
    {
        return true;
    }

    public function view(User $actor, LabCase $labCase): bool
    {
        return true;
    }

    public function create(User $actor): bool
    {
        return in_array($actor->role, [UserRole::Admin, UserRole::Dentist], true);
    }

    public function update(User $actor, LabCase $labCase): bool
    {
        return in_array($actor->role, [UserRole::Admin, UserRole::Dentist], true);
    }

    public function send(User $actor, LabCase $labCase): bool
    {
        return in_array($actor->role, [UserRole::Admin, UserRole::Receptionist], true);
    }

    public function receive(User $actor, LabCase $labCase): bool
    {
        return in_array($actor->role, [UserRole::Admin, UserRole::Receptionist], true);
    }

    public function qualityCheck(User $actor, LabCase $labCase): bool
    {
        return in_array($actor->role, [UserRole::Admin, UserRole::Receptionist], true);
    }

    public function cancel(User $actor, LabCase $labCase): bool
    {
        return in_array($actor->role, [UserRole::Admin, UserRole::Dentist], true);
    }

    public function delete(User $actor, LabCase $labCase): bool
    {
        return $actor->isAdmin();
    }
}
