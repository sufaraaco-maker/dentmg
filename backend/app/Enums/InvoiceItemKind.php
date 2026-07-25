<?php

namespace App\Enums;

/**
 * Determines an item's sign in the parent invoice's total formula (design doc §4/§6):
 * total = SUM(charge) + SUM(tax) - SUM(discount). `unit_amount` is always stored positive
 * regardless of kind — the sign comes from this enum, not the stored value.
 */
enum InvoiceItemKind: string
{
    case Charge = 'charge';
    case Discount = 'discount';
    case Tax = 'tax';
}
