<?php

namespace App\Enums;

enum AppointmentStatus: string
{
    case Scheduled = 'scheduled';
    case Confirmed = 'confirmed';
    case CheckedIn = 'checked_in';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case NoShow = 'no_show';

    /**
     * Statuses that occupy the dentist's time slot. Mirrors the status list hardcoded in the
     * appointments_no_overlapping_dentist_slots Postgres EXCLUDE constraint migration.
     *
     * @return list<self>
     */
    public static function occupyingStatuses(): array
    {
        return [self::Scheduled, self::Confirmed, self::CheckedIn, self::InProgress];
    }

    public function occupiesTime(): bool
    {
        return in_array($this, self::occupyingStatuses(), true);
    }
}
