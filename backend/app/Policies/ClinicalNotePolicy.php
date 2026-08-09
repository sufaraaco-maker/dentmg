<?php

namespace App\Policies;

use App\Models\ClinicalNote;
use App\Models\User;

/**
 * Design doc §10/§15 Decision D (approved 2026-07-25): receptionists have NO access to Clinical
 * Notes at all — a deliberate divergence from Dental Chart/Treatment Plans, which both grant
 * receptionist read access for scheduling/billing context. Clinical narrative content
 * (informed-consent language, behavioral observations, exam detail) is more sensitive than a
 * structured finding or a cost estimate, and front desk has no clinical need to read it.
 *
 * No clinic-membership check exists anywhere below — same already-reviewed, system-wide V1 posture
 * as every other module (no tenant/clinic concept exists yet, see dental-chart.md's SaaS Readiness
 * section for the future migration path).
 */
class ClinicalNotePolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission('clinical_notes.view');
    }

    public function view(User $actor, ClinicalNote $clinicalNote): bool
    {
        return $actor->hasPermission('clinical_notes.view');
    }

    /**
     * No per-patient-assigned-dentist / ownership check (design doc §8 rule 5) — any dentist may
     * author, sign, or addend any patient's note, inheriting Dental Chart's/Treatment Plans'
     * already-approved precedent.
     */
    public function create(User $actor): bool
    {
        return $actor->hasPermission('clinical_notes.manage');
    }

    /**
     * Whether the note is actually still editable in its current status (draft-only) is a
     * `ClinicalNoteService` concern (design doc §8 rule 3), not this ability's.
     */
    public function update(User $actor, ClinicalNote $clinicalNote): bool
    {
        return $actor->hasPermission('clinical_notes.manage');
    }

    public function sign(User $actor, ClinicalNote $clinicalNote): bool
    {
        return $actor->hasPermission('clinical_notes.manage');
    }

    public function addAddendum(User $actor, ClinicalNote $clinicalNote): bool
    {
        return $actor->hasPermission('clinical_notes.manage');
    }

    /**
     * Delete is gated tighter than every clinical action above (admin-only) — a data-correction
     * action, not a clinical one, allowed regardless of status (design doc §8 rule 6, §15 Decision
     * C) — always soft delete, never hard, mirroring every other module's identical delete-vs-
     * clinical-action split.
     */
    public function delete(User $actor, ClinicalNote $clinicalNote): bool
    {
        return $actor->hasPermission('clinical_notes.delete');
    }
}
