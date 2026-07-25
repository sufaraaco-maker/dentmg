<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\TreatmentPlanItem;
use App\Models\User;

/**
 * No clinic-membership check exists anywhere below, for the same reason as `TreatmentPlanPolicy` —
 * see that class's docblock. No dentist-ownership/IDOR restriction either: any dentist may add/edit/
 * transition an item on any patient's plan, inherited directly from Dental Chart's identical,
 * already-approved precedent (no "assigned/primary dentist per patient" concept exists in the
 * system, design doc §10).
 */
class TreatmentPlanItemPolicy
{
    public function viewAny(User $actor): bool
    {
        return true;
    }

    public function view(User $actor, TreatmentPlanItem $treatmentPlanItem): bool
    {
        return true;
    }

    public function create(User $actor): bool
    {
        return in_array($actor->role, [UserRole::Admin, UserRole::Dentist], true);
    }

    public function update(User $actor, TreatmentPlanItem $treatmentPlanItem): bool
    {
        return in_array($actor->role, [UserRole::Admin, UserRole::Dentist], true);
    }

    public function complete(User $actor, TreatmentPlanItem $treatmentPlanItem): bool
    {
        return in_array($actor->role, [UserRole::Admin, UserRole::Dentist], true);
    }

    public function cancel(User $actor, TreatmentPlanItem $treatmentPlanItem): bool
    {
        return in_array($actor->role, [UserRole::Admin, UserRole::Dentist], true);
    }

    /**
     * Delete is gated tighter than the clinical transitions (admin-only) — a data-correction
     * action, not a clinical one, mirroring Dental Chart's identical delete-vs-cancel split.
     */
    public function delete(User $actor, TreatmentPlanItem $treatmentPlanItem): bool
    {
        return $actor->isAdmin();
    }
}
