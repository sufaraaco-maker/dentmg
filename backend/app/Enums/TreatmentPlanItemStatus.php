<?php

namespace App\Enums;

enum TreatmentPlanItemStatus: string
{
    case Planned = 'planned';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    /**
     * Deliberately just three states — no stored "scheduled" state. Whether an item is scheduled
     * is fully derived from appointment_id being set to a non-cancelled Appointment, not stored
     * separately (design doc §5, same "don't store derivable state" principle as
     * DentalChartEntry::dentition_type).
     *
     * @return list<self>
     */
    public static function transitionsFrom(self $status): array
    {
        return match ($status) {
            self::Planned => [self::Completed, self::Cancelled],
            self::Completed, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, self::transitionsFrom($this), true);
    }

    public function isTerminal(): bool
    {
        return $this !== self::Planned;
    }
}
