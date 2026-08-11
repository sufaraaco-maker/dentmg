<?php

namespace App\Notifications;

use App\Enums\UserRole;
use App\Models\TreatmentPlan;
use App\Services\RecipientResolver;
use Illuminate\Database\Eloquent\Collection;

/**
 * Type 4 of the 8 whitelisted V1 types (design doc §5.1). The presenting dentist learns their plan
 * was accepted; admins and receptionists learn there is now work to schedule — an accepted plan
 * that nobody books is the single most expensive thing to miss in a dental practice, which is why
 * this fans out to the front desk rather than staying with the dentist.
 */
class TreatmentPlanAcceptedNotification extends BaseNotification
{
    public function type(): string
    {
        return 'treatment_plan.accepted';
    }

    public function category(): string
    {
        return 'treatment_plans';
    }

    public function params(): array
    {
        /** @var TreatmentPlan $plan */
        $plan = $this->subject;

        return [
            'patientName' => $plan->patient?->full_name,
            'planTitle' => $plan->title,
        ];
    }

    public function route(): array
    {
        return [
            'name' => 'treatment-plan-detail',
            'params' => [
                'id' => $this->subject->getAttribute('patient_id'),
                'planId' => $this->subject->getKey(),
            ],
        ];
    }

    public function recipients(RecipientResolver $resolver): Collection
    {
        return $resolver->merge(
            $resolver->byId($this->subject->getAttribute('dentist_id')),
            $resolver->byRoles([UserRole::Admin, UserRole::Receptionist]),
        );
    }
}
