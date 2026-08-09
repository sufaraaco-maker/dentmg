<?php

namespace App\Policies;

use App\Models\AiInteractionLog;
use App\Models\User;

/**
 * Named to match `AiInteractionLog` for Laravel's Policy auto-discovery convention (Model name →
 * `{Model}Policy`), even though the design doc refers to this conceptually as "AiAssistantPolicy"
 * (design doc §7) — it exists only to gate `AiInteractionLog` visibility. The toggle settings reuse
 * `ClinicSettingPolicy::update()` unchanged (§7's table), and every actual feature action re-checks
 * the existing Policy/Gate for the underlying resource (Reports/Patients/Appointments/
 * ClinicalNotes/TreatmentPlans) rather than duplicating that rule here.
 */
class AiInteractionLogPolicy
{
    /**
     * Every role may list interactions — scoped to their own by the controller unless they're an
     * admin listing everyone's (design doc §7).
     */
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission('ai_interaction_log.view');
    }

    public function view(User $actor, AiInteractionLog $log): bool
    {
        return $actor->id === $log->user_id || $actor->hasPermission('ai_interaction_log.view_others');
    }
}
