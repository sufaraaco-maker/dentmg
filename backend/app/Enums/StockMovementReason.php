<?php

namespace App\Enums;

/**
 * Design doc §4/§6: the only vocabulary for why a stock_movements row's quantity_delta exists.
 * `received` is reserved for movements generated automatically by PurchaseOrderService::receive()
 * (design doc §8) — never chosen directly by a "record a manual movement" request (see
 * StoreStockMovementRequest).
 */
enum StockMovementReason: string
{
    case InitialStock = 'initial_stock';
    case Received = 'received';
    case Used = 'used';
    case Wasted = 'wasted';
    case Expired = 'expired';
    case Correction = 'correction';

    /**
     * Reasons a staff member may pick when recording a manual movement via
     * `POST /api/supplies/{supply}/stock-movements` (design doc §9) — excludes `received`, which is
     * only ever produced by receiving a Purchase Order item.
     *
     * @return list<self>
     */
    public static function manuallySelectable(): array
    {
        return [self::InitialStock, self::Used, self::Wasted, self::Expired, self::Correction];
    }
}
