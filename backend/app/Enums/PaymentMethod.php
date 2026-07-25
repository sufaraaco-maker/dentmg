<?php

namespace App\Enums;

/**
 * V1 set only (design doc §15 item 4, resolved 2026-07-25): no `check`/`insurance` — `other` plus
 * the free-text `reference` field covers those until a real need names them explicitly.
 */
enum PaymentMethod: string
{
    case Cash = 'cash';
    case Card = 'card';
    case BankTransfer = 'bank_transfer';
    case Other = 'other';
}
