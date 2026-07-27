<?php

namespace App\Services;

use App\Enums\PurchaseOrderStatus;
use App\Enums\StockMovementReason;
use App\Exceptions\Inventory\InvalidPurchaseOrderOperationException;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\StockMovement;
use App\Models\Supply;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class PurchaseOrderService
{
    /**
     * @param  array<string, mixed>  $data  Must include `supplier_id`.
     */
    public function create(array $data, User $actor): PurchaseOrder
    {
        return DB::transaction(function () use ($data, $actor) {
            $sequence = $this->nextSequenceNumber();

            return PurchaseOrder::create([
                'supplier_id' => $data['supplier_id'],
                'sequence_number' => $sequence,
                'order_number' => 'PO-'.str_pad((string) $sequence, 6, '0', STR_PAD_LEFT),
                'status' => PurchaseOrderStatus::Draft,
                'notes' => $data['notes'] ?? null,
                'ordered_at' => null,
                'expected_at' => $data['expected_at'] ?? null,
                'created_by_id' => $actor->id,
            ]);
        });
    }

    /**
     * Design doc §8: notes/expected_at are editable only while draft — same centralized-lock
     * discipline as InvoiceService::updateDraft().
     *
     * @param  array<string, mixed>  $data
     */
    public function update(PurchaseOrder $order, array $data): PurchaseOrder
    {
        $this->assertDraft($order);

        $order->update(array_intersect_key($data, array_flip(['notes', 'expected_at'])));

        return $order;
    }

    /**
     * @param  array<string, mixed>  $data  Must include `supply_id`, `quantity_ordered`.
     *                                      `description`/`unit_cost` default from the Supply
     *                                      (design doc §6 snapshot convention) when omitted.
     */
    public function addItem(PurchaseOrder $order, array $data): PurchaseOrderItem
    {
        $this->assertDraft($order);

        $supply = Supply::query()->findOrFail($data['supply_id']);

        $description = $data['description'] ?? ($supply->sku !== null ? "{$supply->name} ({$supply->sku})" : $supply->name);
        $unitCost = $data['unit_cost'] ?? $supply->unit_cost;

        if ($unitCost === null) {
            throw new InvalidPurchaseOrderOperationException('A unit cost is required — this supply has no default cost on file.');
        }

        return PurchaseOrderItem::create([
            'purchase_order_id' => $order->id,
            'supply_id' => $supply->id,
            'description' => $description,
            'quantity_ordered' => $data['quantity_ordered'],
            'quantity_received' => 0,
            'unit_cost' => $unitCost,
        ]);
    }

    /**
     * Draft-only (design doc §8) — quantity_received is never touched here, only via receive().
     *
     * @param  array<string, mixed>  $data
     */
    public function updateItem(PurchaseOrderItem $item, array $data): PurchaseOrderItem
    {
        $this->assertDraft($item->purchaseOrder);

        $item->update(array_intersect_key($data, array_flip(['description', 'quantity_ordered', 'unit_cost'])));

        return $item;
    }

    public function deleteItem(PurchaseOrderItem $item): void
    {
        $this->assertDraft($item->purchaseOrder);

        $item->delete();
    }

    /**
     * The only user-chosen status transition (design doc §5) — draft -> placed, sets ordered_at.
     * Requires at least one item: an order with nothing on it has nothing to ever receive.
     */
    public function place(PurchaseOrder $order): PurchaseOrder
    {
        return DB::transaction(function () use ($order) {
            $locked = PurchaseOrder::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            $this->assertTransition($locked->status, PurchaseOrderStatus::Placed);

            if (! $locked->items()->exists()) {
                throw new InvalidPurchaseOrderOperationException('Add at least one item before placing this order.');
            }

            $locked->status = PurchaseOrderStatus::Placed;
            // A Carbon instance, not ->toDateString(): `ordered_at` is cast to 'date' (Carbon|null),
            // and PHPStan/Larastan's cast-aware property typing (phpstan.neon's parseModelCastsMethod)
            // rejects a plain string assignment even though Eloquent would normalize it identically at
            // runtime — assigning the already-correct type avoids the mismatch outright.
            $locked->ordered_at = now();
            $locked->save();

            return $locked->fresh('items');
        });
    }

    /**
     * Design doc §8: hard-capped so quantity_received can never exceed quantity_ordered (§15
     * Decision 5) — genuine over-shipment becomes a separate `correction` movement instead. Creates
     * exactly one new `received` StockMovement per call, and recomputes the parent order's status
     * (never user-chosen directly, design doc §5).
     *
     * Locks the parent PurchaseOrder row for the whole operation (mirrors
     * InvoiceService::issue()'s identical reasoning): this serializes every concurrent receive
     * against any item of this same order, so the order-status recompute below always sees a
     * consistent, fully up-to-date picture of every item's quantity_received.
     */
    public function receive(PurchaseOrderItem $item, int $quantity, ?string $expirationDate, User $actor): PurchaseOrderItem
    {
        if ($quantity <= 0) {
            throw new InvalidPurchaseOrderOperationException('The received quantity must be greater than zero.');
        }

        return DB::transaction(function () use ($item, $quantity, $expirationDate, $actor) {
            $lockedOrder = PurchaseOrder::query()->whereKey($item->purchase_order_id)->lockForUpdate()->firstOrFail();

            if (! in_array($lockedOrder->status, [PurchaseOrderStatus::Placed, PurchaseOrderStatus::PartiallyReceived], true)) {
                throw new InvalidPurchaseOrderOperationException('Only a placed order can receive items.');
            }

            $freshItem = PurchaseOrderItem::query()->whereKey($item->id)->firstOrFail();

            if ($freshItem->quantity_received + $quantity > $freshItem->quantity_ordered) {
                throw new InvalidPurchaseOrderOperationException(
                    'This would exceed the ordered quantity — record a correction instead if genuinely more arrived.'
                );
            }

            $freshItem->quantity_received += $quantity;
            $freshItem->save();

            StockMovement::create([
                'supply_id' => $freshItem->supply_id,
                'quantity_delta' => $quantity,
                'reason' => StockMovementReason::Received,
                'purchase_order_item_id' => $freshItem->id,
                'expiration_date' => $expirationDate,
                'notes' => null,
                'performed_by_id' => $actor->id,
                'occurred_at' => now(),
            ]);

            $allReceived = $lockedOrder->items()->whereColumn('quantity_received', '<', 'quantity_ordered')->doesntExist();

            $lockedOrder->status = $allReceived ? PurchaseOrderStatus::Received : PurchaseOrderStatus::PartiallyReceived;
            $lockedOrder->save();

            return $freshItem->fresh();
        });
    }

    /**
     * Design doc §8: only reachable while every item's quantity_received is still 0 — once any
     * real stock has moved against it, it is real history, not erasable (mirrors
     * PaymentService::delete()'s identical "can't undo once real" guard).
     */
    public function cancel(PurchaseOrder $order): PurchaseOrder
    {
        return DB::transaction(function () use ($order) {
            $locked = PurchaseOrder::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            $this->assertTransition($locked->status, PurchaseOrderStatus::Cancelled);

            if ($locked->items()->where('quantity_received', '>', 0)->exists()) {
                throw new InvalidPurchaseOrderOperationException('An order with any received items cannot be cancelled.');
            }

            $locked->status = PurchaseOrderStatus::Cancelled;
            $locked->save();

            return $locked;
        });
    }

    /**
     * Soft delete — draft-only (design doc §6/§8), mirrors InvoiceService::deleteInvoice()'s
     * identical draft-only guard.
     */
    public function delete(PurchaseOrder $order): void
    {
        $this->assertDraft($order);

        $order->delete();
    }

    private function assertDraft(PurchaseOrder $order): void
    {
        if ($order->status !== PurchaseOrderStatus::Draft) {
            throw new InvalidPurchaseOrderOperationException('This order can no longer be edited once placed.');
        }
    }

    private function assertTransition(PurchaseOrderStatus $from, PurchaseOrderStatus $to): void
    {
        if (! $from->canTransitionTo($to)) {
            throw new InvalidPurchaseOrderOperationException("Cannot move an order from {$from->value} to {$to->value}.");
        }
    }

    /**
     * Locks the highest-numbered row to avoid two concurrent orders colliding on the same
     * order_number — mirrors PatientService::nextSequenceNumber()'s identical pattern (an
     * operational sequence, not a legal/financial one, so the heavier dedicated-settings-row
     * approach InvoiceService uses for invoice numbers isn't warranted here). Postgres rejects
     * `FOR UPDATE` combined with an aggregate (MAX), so this orders + limits instead.
     */
    private function nextSequenceNumber(): int
    {
        $max = PurchaseOrder::withTrashed()
            ->orderByDesc('sequence_number')
            ->lockForUpdate()
            ->value('sequence_number');

        return ((int) $max) + 1;
    }
}
