<?php

namespace App\Policies;

use App\Models\LabCase;
use App\Models\User;

/**
 * Design doc §5 (Approval Log item 1): create/update while Draft is admin+dentist — choosing
 * lab/tooth/shade/material is a clinical prescription decision, mirroring ClinicalNotePolicy's
 * admin+dentist split. send/receive/qualityCheck (status transitions) are admin+receptionist —
 * once prescribed, packaging/shipping/receiving is front-desk logistics, mirroring
 * PurchaseOrderPolicy::place/receive exactly. cancel() stays with admin+dentist since reversing a
 * clinical decision is not a logistics action. Delete is gated tighter (admin-only), matching
 * every prior module's identical stricter-than-everything-else precedent.
 */
class LabCasePolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission('lab_cases.view');
    }

    public function view(User $actor, LabCase $labCase): bool
    {
        return $actor->hasPermission('lab_cases.view');
    }

    public function create(User $actor): bool
    {
        return $actor->hasPermission('lab_cases.prescribe');
    }

    public function update(User $actor, LabCase $labCase): bool
    {
        return $actor->hasPermission('lab_cases.prescribe');
    }

    public function send(User $actor, LabCase $labCase): bool
    {
        return $actor->hasPermission('lab_cases.process');
    }

    public function receive(User $actor, LabCase $labCase): bool
    {
        return $actor->hasPermission('lab_cases.process');
    }

    public function qualityCheck(User $actor, LabCase $labCase): bool
    {
        return $actor->hasPermission('lab_cases.process');
    }

    public function cancel(User $actor, LabCase $labCase): bool
    {
        return $actor->hasPermission('lab_cases.prescribe');
    }

    public function delete(User $actor, LabCase $labCase): bool
    {
        return $actor->hasPermission('lab_cases.delete');
    }
}
