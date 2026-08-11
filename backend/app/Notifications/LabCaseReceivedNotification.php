<?php

namespace App\Notifications;

use App\Models\LabCase;
use App\Services\RecipientResolver;
use Illuminate\Database\Eloquent\Collection;

/**
 * Type 6 of the 8 whitelisted V1 types (design doc §5.1) — "your lab case is back."
 * Prescribing dentist only; the receptionist who logged the delivery is the actor.
 *
 * `lab_case.quality_checked` was considered and left out of V1 by explicit decision (D4) — it is a
 * one-line addition to NotificationRules if the clinical workflow turns out to want it.
 */
class LabCaseReceivedNotification extends BaseNotification
{
    public function type(): string
    {
        return 'lab_case.received';
    }

    public function category(): string
    {
        return 'laboratory';
    }

    public function params(): array
    {
        /** @var LabCase $labCase */
        $labCase = $this->subject;

        return [
            'patientName' => $labCase->patient?->full_name,
            'caseNumber' => $labCase->case_number,
            'caseType' => $labCase->case_type,
        ];
    }

    public function route(): array
    {
        return ['name' => 'lab-case-detail', 'params' => ['id' => $this->subject->getKey()]];
    }

    public function recipients(RecipientResolver $resolver): Collection
    {
        return $resolver->byId($this->subject->getAttribute('dentist_id'));
    }
}
