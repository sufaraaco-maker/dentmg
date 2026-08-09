<?php

namespace App\Policies;

use App\Models\PatientImage;
use App\Models\User;

/**
 * Design doc §5 / Approval Log item 4: viewAny/view/create/update are admin+dentist+receptionist —
 * deliberately wider than Laboratory/Clinical Notes' clinical-only gating, since uploading/organizing
 * images is largely a front-desk logistics task in real clinics, not an exclusively clinical judgment
 * call. delete stays admin-only, matching every other policy in the codebase without exception. If
 * clinical annotation tools are added in V2+, they must be gated admin+dentist only (Approval Log item
 * 4) — not implemented here.
 */
class PatientImagePolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission('patient_images.view');
    }

    public function view(User $actor, PatientImage $patientImage): bool
    {
        return $actor->hasPermission('patient_images.view');
    }

    public function create(User $actor): bool
    {
        return $actor->hasPermission('patient_images.manage');
    }

    public function update(User $actor, PatientImage $patientImage): bool
    {
        return $actor->hasPermission('patient_images.manage');
    }

    public function delete(User $actor, PatientImage $patientImage): bool
    {
        return $actor->hasPermission('patient_images.delete');
    }
}
