<?php

namespace App\Policies;

use App\Models\PatientAllergy;
use App\Models\PatientMedicalCondition;
use App\Models\PatientMedication;
use App\Models\User;

/**
 * One policy for all three Medical History entities (design doc §6.4) — registered against
 * `PatientAllergy`/`PatientMedicalCondition`/`PatientMedication` via `Gate::policy()` in
 * `AppServiceProvider` since Laravel's naming-convention auto-discovery only maps one policy per
 * model. Allergies/conditions/medications share one authorization shape (safety-relevant read
 * access for all staff, clinical-judgment write access for Admin/Dentist), so three near-identical
 * policy classes would just be duplication.
 */
class MedicalHistoryPolicy
{
    /**
     * Clinic-wide read visibility for all staff, same reasoning as `DentalChartEntryPolicy` —
     * allergies in particular are front-desk safety-relevant information.
     */
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission('medical_history.view');
    }

    public function view(User $actor, PatientAllergy|PatientMedicalCondition|PatientMedication $record): bool
    {
        return $actor->hasPermission('medical_history.view');
    }

    /**
     * Clinical judgment calls — Admin + Dentist only, matching `DentalChartEntryPolicy::create()`.
     */
    public function create(User $actor): bool
    {
        return $actor->hasPermission('medical_history.manage');
    }

    public function update(User $actor, PatientAllergy|PatientMedicalCondition|PatientMedication $record): bool
    {
        return $actor->hasPermission('medical_history.manage');
    }

    /**
     * Unlike `DentalChartEntryPolicy::delete()` (admin-only), delete stays Admin + Dentist here —
     * design doc §6.4's explicit default for this module.
     */
    public function delete(User $actor, PatientAllergy|PatientMedicalCondition|PatientMedication $record): bool
    {
        return $actor->hasPermission('medical_history.manage');
    }
}
