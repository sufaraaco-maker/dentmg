<?php

namespace App\Notifications;

use App\Models\TreatmentPlan;
use App\Services\RecipientResolver;
use Illuminate\Database\Eloquent\Collection;

/**
 * Type 5 of the 8 whitelisted V1 types (design doc §5.1). Presenting dentist only — a rejection is
 * clinical feedback on their own proposal, not front-desk work like an acceptance is.
 */
class TreatmentPlanRejectedNotification extends BaseNotification
{
    public function type(): string
    {
        return 'treatment_plan.rejected';
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
        return $resolver->byId($this->subject->getAttribute('dentist_id'));
    }
}
