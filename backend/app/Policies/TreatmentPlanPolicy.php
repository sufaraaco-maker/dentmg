<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\TreatmentPlan;
use App\Models\User;

/**
 * No clinic-membership check exists anywhere below — a deliberate, already-reviewed choice, not an
 * oversight: DentalSuite V1 has no tenant/clinic model at all (confirmed system-wide during Dental
 * Chart's SaaS Readiness checkpoint, docs/modules/dental-chart.md), so there is no "clinic" concept
 * to scope against yet. When multi-tenancy is eventually built, this policy gains a clinic-match
 * check alongside a global tenant-scoping trait on the model — the same migration path already
 * documented for every other module (docs/modules/treatment-plans-design.md §14).
 */
class TreatmentPlanPolicy
{
    /**
     * Clinic-wide read visibility for all staff, including receptionists — front desk needs
     * treatment-plan context for scheduling and billing conversations (design doc §10, mirrors
     * Dental Chart's identical read-access reasoning).
     */
    public function viewAny(User $actor): bool
    {
        return true;
    }

    public function view(User $actor, TreatmentPlan $treatmentPlan): bool
    {
        return true;
    }

    public function create(User $actor): bool
    {
        return in_array($actor->role, [UserRole::Admin, UserRole::Dentist], true);
    }

    /**
     * Administrative edits (title/notes/dentist) — whether the plan's current status still allows
     * it is a `TreatmentPlanService` concern (design doc §8), not this ability's.
     */
    public function update(User $actor, TreatmentPlan $treatmentPlan): bool
    {
        return in_array($actor->role, [UserRole::Admin, UserRole::Dentist], true);
    }

    public function present(User $actor, TreatmentPlan $treatmentPlan): bool
    {
        return in_array($actor->role, [UserRole::Admin, UserRole::Dentist], true);
    }

    public function accept(User $actor, TreatmentPlan $treatmentPlan): bool
    {
        return in_array($actor->role, [UserRole::Admin, UserRole::Dentist], true);
    }

    public function reject(User $actor, TreatmentPlan $treatmentPlan): bool
    {
        return in_array($actor->role, [UserRole::Admin, UserRole::Dentist], true);
    }

    public function start(User $actor, TreatmentPlan $treatmentPlan): bool
    {
        return in_array($actor->role, [UserRole::Admin, UserRole::Dentist], true);
    }

    public function complete(User $actor, TreatmentPlan $treatmentPlan): bool
    {
        return in_array($actor->role, [UserRole::Admin, UserRole::Dentist], true);
    }

    public function cancel(User $actor, TreatmentPlan $treatmentPlan): bool
    {
        return in_array($actor->role, [UserRole::Admin, UserRole::Dentist], true);
    }

    /**
     * Revision support (design doc §15 Q4) — creating a superseding plan is authored clinical/
     * business work, same bar as `create`, not a data-correction action.
     */
    public function createRevision(User $actor, TreatmentPlan $treatmentPlan): bool
    {
        return in_array($actor->role, [UserRole::Admin, UserRole::Dentist], true);
    }

    /**
     * Delete is gated tighter than every clinical/business transition above (admin-only) — a
     * data-correction action, not a clinical one, mirroring Dental Chart's identical delete-vs-
     * cancel split (design doc §8).
     */
    public function delete(User $actor, TreatmentPlan $treatmentPlan): bool
    {
        return $actor->isAdmin();
    }
}
