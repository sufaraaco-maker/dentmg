<?php

namespace App\Enums;

enum InvoiceStatus: string
{
    case Draft = 'draft';
    case Issued = 'issued';
    case Void = 'void';

    /**
     * Allowed forward transitions from a given status (design doc §5/§8). `Void` is reachable only
     * from `Issued` — a `Draft` invoice was never assigned a real `sequence_number`/`invoice_number`,
     * so abandoning one is a delete, not a status transition.
     *
     * @return list<self>
     */
    public static function transitionsFrom(self $status): array
    {
        return match ($status) {
            self::Draft => [self::Issued],
            self::Issued => [self::Void],
            self::Void => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, self::transitionsFrom($this), true);
    }

    public function isTerminal(): bool
    {
        return $this === self::Void;
    }
}
