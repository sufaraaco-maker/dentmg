<?php

namespace App\Enums;

enum ClinicalNoteStatus: string
{
    case Draft = 'draft';
    case Signed = 'signed';

    /**
     * `signed` is terminal for V1 (design doc §5) — a note is never transitioned back to `draft`;
     * a post-sign correction is an Addendum, not a status change. Deliberately simpler than
     * DentalChartEntryStatus/TreatmentPlanStatus: a note's lifecycle genuinely has only one
     * meaningful transition.
     *
     * @return list<self>
     */
    public static function transitionsFrom(self $status): array
    {
        return match ($status) {
            self::Draft => [self::Signed],
            self::Signed => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, self::transitionsFrom($this), true);
    }
}
