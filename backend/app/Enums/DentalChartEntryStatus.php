<?php

namespace App\Enums;

enum DentalChartEntryStatus: string
{
    case Existing = 'existing';
    case Active = 'active';
    case Planned = 'planned';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    /**
     * Allowed forward transitions from a given status. `existing`, `completed`, and `cancelled`
     * are terminal for V1 — no automatic supersession (see docs/modules/dental-chart-design-draft.md
     * resolved decision #7): an `active` finding and the `completed` procedure that later
     * addresses it both remain as independent, permanent records.
     *
     * @return list<self>
     */
    public static function transitionsFrom(self $status): array
    {
        return match ($status) {
            self::Active => [self::Planned],
            self::Planned => [self::Completed, self::Cancelled],
            self::Existing, self::Completed, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, self::transitionsFrom($this), true);
    }
}
