<?php

namespace App\Enums;

enum TreatmentPlanStatus: string
{
    case Draft = 'draft';
    case Presented = 'presented';
    case Accepted = 'accepted';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    /**
     * Allowed forward transitions from a given status (design doc §5). `cancel` is available from
     * every non-terminal status — reflected here by every non-terminal case including Cancelled in
     * its own transition list.
     *
     * @return list<self>
     */
    public static function transitionsFrom(self $status): array
    {
        return match ($status) {
            self::Draft => [self::Presented, self::Cancelled],
            self::Presented => [self::Accepted, self::Rejected, self::Cancelled],
            self::Accepted => [self::InProgress, self::Cancelled],
            self::InProgress => [self::Completed, self::Cancelled],
            self::Completed, self::Rejected, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, self::transitionsFrom($this), true);
    }

    /**
     * Non-terminal statuses — a plan in one of these can still be cancelled, and its items are
     * still actionable. Used by the cancel-cascade rule (design doc §5/§8).
     *
     * @return list<self>
     */
    public static function nonTerminalStatuses(): array
    {
        return [self::Draft, self::Presented, self::Accepted, self::InProgress];
    }

    public function isTerminal(): bool
    {
        return ! in_array($this, self::nonTerminalStatuses(), true);
    }
}
