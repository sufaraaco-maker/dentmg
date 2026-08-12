<?php

namespace App\Notifications;

use App\Enums\UserRole;
use App\Models\LabCase;
use App\Services\RecipientResolver;
use Illuminate\Database\Eloquent\Collection;

/**
 * Type 9 of Phase 5C's scheduled types (design doc §5.2). Dispatched daily by
 * `NotifyOverdueLabCases` for every `LabCase::dueOrOverdue()` row — the same scope the Dashboard
 * widget already uses, reused rather than reimplemented.
 */
class LabCaseOverdueNotification extends BaseNotification
{
    public function type(): string
    {
        return 'lab_case.overdue';
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
            'dueAt' => $labCase->due_at?->toIso8601String(),
        ];
    }

    public function route(): array
    {
        return ['name' => 'lab-case-detail', 'params' => ['id' => $this->subject->getKey()]];
    }

    public function recipients(RecipientResolver $resolver): Collection
    {
        return $resolver->merge(
            $resolver->byId($this->subject->getAttribute('dentist_id')),
            $resolver->byRoles([UserRole::Receptionist]),
        );
    }
}
