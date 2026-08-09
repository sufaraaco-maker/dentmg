<?php

namespace App\Policies;

use App\Models\PatientDocument;
use App\Models\User;

/**
 * Design doc §9/§16 decision 2: clones PatientImagePolicy exactly — filing/finding paperwork is a
 * front-desk task, not clinical-only. delete stays admin-only, matching every other policy in the
 * codebase without exception. Auto-discovered via Laravel's `PatientDocument` -> `PatientDocumentPolicy`
 * naming convention — no `Gate::policy()` registration needed (unlike `MedicalHistoryPolicy`, which
 * covers 3 models).
 */
class PatientDocumentPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission('patient_documents.view');
    }

    public function view(User $actor, PatientDocument $patientDocument): bool
    {
        return $actor->hasPermission('patient_documents.view');
    }

    public function create(User $actor): bool
    {
        return $actor->hasPermission('patient_documents.manage');
    }

    public function update(User $actor, PatientDocument $patientDocument): bool
    {
        return $actor->hasPermission('patient_documents.manage');
    }

    public function delete(User $actor, PatientDocument $patientDocument): bool
    {
        return $actor->hasPermission('patient_documents.delete');
    }
}
