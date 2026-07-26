<?php

namespace App\Services;

use App\Enums\StockMovementReason;
use App\Exceptions\Inventory\InsufficientStockException;
use App\Models\StockMovement;
use App\Models\Supply;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class StockMovementService
{
    public function paginate(Supply $supply, int $perPage = 20): LengthAwarePaginator
    {
        return $supply->stockMovements()
            ->with('performedBy')
            ->latest('occurred_at')
            ->paginate($perPage);
    }

    /**
     * Records a manual movement (design doc §2/§9) — `used`/`wasted`/`expired`/`correction`/
     * `initial_stock` only; `received` is exclusively produced by
     * PurchaseOrderService::receive() and rejected here at the Form Request layer
     * (StoreStockMovementRequest restricts `reason` to StockMovementReason::manuallySelectable()).
     *
     * Row-locks the Supply (design doc §8, mirrors PaymentService::refund()'s identical
     * locked-parent-row reasoning): without it, two concurrent decreasing movements against the
     * same Supply could each read the same not-yet-negative on-hand balance, both pass the
     * below-zero check individually, and together still drive the true balance negative.
     *
     * @param  array<string, mixed>  $data
     */
    public function record(Supply $supply, array $data, User $actor): StockMovement
    {
        return DB::transaction(function () use ($supply, $data, $actor) {
            $locked = Supply::query()->whereKey($supply->id)->lockForUpdate()->firstOrFail();

            $reason = $data['reason'] instanceof StockMovementReason
                ? $data['reason']
                : StockMovementReason::from($data['reason']);

            $delta = (int) $data['quantity_delta'];

            if ($delta < 0) {
                $onHand = (int) $locked->stockMovements()->sum('quantity_delta');

                if ($onHand + $delta < 0) {
                    throw new InsufficientStockException($onHand);
                }
            }

            return StockMovement::create([
                'supply_id' => $locked->id,
                'quantity_delta' => $delta,
                'reason' => $reason,
                'purchase_order_item_id' => null,
                'expiration_date' => $data['expiration_date'] ?? null,
                'notes' => $data['notes'] ?? null,
                'performed_by_id' => $actor->id,
                'occurred_at' => $data['occurred_at'] ?? now(),
            ]);
        });
    }
}
