<?php

namespace App\Enums;

/**
 * Design doc §4: `draft -> sent` and `sent -> received` and `received -> quality_checked` are the
 * only user-chosen transitions (send()/receive()/qualityCheck()). `cancelled` is reachable only
 * from `draft`/`sent`, and only while `received_at` is still null (design doc §4) — a case that's
 * already physically back from the lab can't be silently cancelled. Deliberately four checkpoints,
 * not more — per the design doc §0 Eaglesoft finding that more granular sub-statuses is the actual
 * documented failure mode (staff stop updating them).
 */
enum LabCaseStatus: string
{
    case Draft = 'draft';
    case Sent = 'sent';
    case Received = 'received';
    case QualityChecked = 'quality_checked';
    case Cancelled = 'cancelled';

    /**
     * @return list<self>
     */
    public static function transitionsFrom(self $status): array
    {
        return match ($status) {
            self::Draft => [self::Sent, self::Cancelled],
            self::Sent => [self::Received, self::Cancelled],
            self::Received => [self::QualityChecked],
            self::QualityChecked, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, self::transitionsFrom($this), true);
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::QualityChecked, self::Cancelled], true);
    }
}
