<?php

namespace App\Enums;

/**
 * Design doc §5: `draft -> placed` is the only user-chosen transition (an explicit "Place Order"
 * action). `-> partially_received`/`-> received` are recomputed by PurchaseOrderService after every
 * receive action, never user-chosen directly. `cancelled` is reachable only from `draft`/`placed`,
 * and only while every item's quantity_received is still 0 (design doc §8).
 */
enum PurchaseOrderStatus: string
{
    case Draft = 'draft';
    case Placed = 'placed';
    case PartiallyReceived = 'partially_received';
    case Received = 'received';
    case Cancelled = 'cancelled';

    /**
     * @return list<self>
     */
    public static function transitionsFrom(self $status): array
    {
        return match ($status) {
            self::Draft => [self::Placed, self::Cancelled],
            self::Placed => [self::PartiallyReceived, self::Received, self::Cancelled],
            self::PartiallyReceived => [self::Received],
            self::Received, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, self::transitionsFrom($this), true);
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Received, self::Cancelled], true);
    }
}
