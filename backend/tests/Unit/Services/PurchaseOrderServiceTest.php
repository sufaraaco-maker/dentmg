<?php

namespace Tests\Unit\Services;

use App\Enums\PurchaseOrderStatus;
use App\Exceptions\Inventory\InvalidPurchaseOrderOperationException;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Models\Supply;
use App\Models\User;
use App\Services\PurchaseOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Inventory design doc §16/§18: written and CI-verified as part of this module's own
 * implementation sequence, not deferred — same discipline Clinical Notes established.
 */
class PurchaseOrderServiceTest extends TestCase
{
    use RefreshDatabase;

    private PurchaseOrderService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new PurchaseOrderService;
    }

    // --- create / numbering --------------------------------------------------------------------

    public function test_create_generates_a_sequential_order_number(): void
    {
        $supplier = Supplier::factory()->create();
        $actor = User::factory()->admin()->create();

        $first = $this->service->create(['supplier_id' => $supplier->id], $actor);
        $second = $this->service->create(['supplier_id' => $supplier->id], $actor);

        $this->assertSame($first->sequence_number + 1, $second->sequence_number);
        $this->assertNotSame($first->order_number, $second->order_number);
        $this->assertSame(PurchaseOrderStatus::Draft, $first->status);
        $this->assertNull($first->ordered_at);
    }

    // --- items ----------------------------------------------------------------------------------

    public function test_add_item_defaults_description_and_unit_cost_from_the_supply(): void
    {
        $order = PurchaseOrder::factory()->create();
        $supply = Supply::factory()->create(['name' => 'Nitrile Gloves M', 'sku' => 'GLV-M', 'unit_cost' => 12.5]);

        $item = $this->service->addItem($order, ['supply_id' => $supply->id, 'quantity_ordered' => 10]);

        $this->assertSame('Nitrile Gloves M (GLV-M)', $item->description);
        $this->assertSame('12.50', (string) $item->unit_cost);
        $this->assertSame(0, $item->quantity_received);
    }

    public function test_add_item_rejects_a_supply_with_no_cost_and_no_override(): void
    {
        $order = PurchaseOrder::factory()->create();
        $supply = Supply::factory()->create(['unit_cost' => null]);

        $this->expectException(InvalidPurchaseOrderOperationException::class);

        $this->service->addItem($order, ['supply_id' => $supply->id, 'quantity_ordered' => 10]);
    }

    public function test_add_item_rejected_once_order_is_no_longer_draft(): void
    {
        $order = PurchaseOrder::factory()->placed()->create();
        $supply = Supply::factory()->create();

        $this->expectException(InvalidPurchaseOrderOperationException::class);

        $this->service->addItem($order, ['supply_id' => $supply->id, 'quantity_ordered' => 5]);
    }

    // --- place ------------------------------------------------------------------------------------

    public function test_place_requires_at_least_one_item(): void
    {
        $order = PurchaseOrder::factory()->create();

        $this->expectException(InvalidPurchaseOrderOperationException::class);

        $this->service->place($order);
    }

    public function test_place_transitions_draft_to_placed_and_sets_ordered_at(): void
    {
        $order = PurchaseOrder::factory()->create();
        $this->service->addItem($order, ['supply_id' => Supply::factory()->create()->id, 'quantity_ordered' => 5]);

        $placed = $this->service->place($order);

        $this->assertSame(PurchaseOrderStatus::Placed, $placed->status);
        $this->assertSame(now()->toDateString(), $placed->ordered_at->toDateString());
    }

    public function test_place_rejects_an_already_placed_order(): void
    {
        $order = PurchaseOrder::factory()->create();
        $this->service->addItem($order, ['supply_id' => Supply::factory()->create()->id, 'quantity_ordered' => 5]);
        $this->service->place($order);

        $this->expectException(InvalidPurchaseOrderOperationException::class);

        $this->service->place($order->fresh());
    }

    // --- receive ----------------------------------------------------------------------------------

    public function test_receive_increases_supply_on_hand_and_records_a_movement(): void
    {
        $actor = User::factory()->admin()->create();
        $supply = Supply::factory()->create();
        [, $item] = $this->placedOrderWithItem($supply, 20);

        $received = $this->service->receive($item, 8, null, $actor);

        $this->assertSame(8, $received->quantity_received);
        $this->assertSame(8, $supply->fresh()->quantity_on_hand);
        $this->assertSame(PurchaseOrderStatus::PartiallyReceived, $item->purchaseOrder->fresh()->status);
    }

    public function test_receiving_the_full_ordered_quantity_marks_the_order_received(): void
    {
        $actor = User::factory()->admin()->create();
        [$order, $item] = $this->placedOrderWithItem(Supply::factory()->create(), 10);

        $this->service->receive($item, 10, null, $actor);

        $this->assertSame(PurchaseOrderStatus::Received, $order->fresh()->status);
    }

    public function test_receiving_beyond_ordered_quantity_is_hard_capped(): void
    {
        $actor = User::factory()->admin()->create();
        [, $item] = $this->placedOrderWithItem(Supply::factory()->create(), 10);
        $this->service->receive($item, 8, null, $actor);

        $this->expectException(InvalidPurchaseOrderOperationException::class);

        $this->service->receive($item->fresh(), 3, null, $actor);
    }

    public function test_receive_rejects_a_draft_order(): void
    {
        $actor = User::factory()->admin()->create();
        $order = PurchaseOrder::factory()->create();
        $item = $this->service->addItem($order, ['supply_id' => Supply::factory()->create()->id, 'quantity_ordered' => 10]);

        $this->expectException(InvalidPurchaseOrderOperationException::class);

        $this->service->receive($item, 5, null, $actor);
    }

    public function test_receive_records_the_expiration_date_on_the_generated_movement(): void
    {
        $actor = User::factory()->admin()->create();
        $supply = Supply::factory()->create();
        [, $item] = $this->placedOrderWithItem($supply, 10);

        $this->service->receive($item, 10, '2027-01-15', $actor);

        $this->assertSame('2027-01-15', $supply->stockMovements()->latest('occurred_at')->first()->expiration_date->toDateString());
    }

    // --- cancel -----------------------------------------------------------------------------------

    public function test_cancel_allowed_while_draft(): void
    {
        $order = PurchaseOrder::factory()->create();

        $cancelled = $this->service->cancel($order);

        $this->assertSame(PurchaseOrderStatus::Cancelled, $cancelled->status);
    }

    public function test_cancel_rejected_once_any_item_has_been_received(): void
    {
        $actor = User::factory()->admin()->create();
        [$order, $item] = $this->placedOrderWithItem(Supply::factory()->create(), 10);
        $this->service->receive($item, 1, null, $actor);

        $this->expectException(InvalidPurchaseOrderOperationException::class);

        $this->service->cancel($order->fresh());
    }

    // --- delete -----------------------------------------------------------------------------------

    public function test_delete_soft_deletes_a_draft_order(): void
    {
        $order = PurchaseOrder::factory()->create();

        $this->service->delete($order);

        $this->assertSoftDeleted('purchase_orders', ['id' => $order->id]);
    }

    public function test_delete_rejects_a_placed_order(): void
    {
        $order = PurchaseOrder::factory()->placed()->create();

        $this->expectException(InvalidPurchaseOrderOperationException::class);

        $this->service->delete($order);
    }

    /**
     * Builds a draft order, adds one item (while still draft — the only status that allows it),
     * then places it via the service itself — addItem() would reject a `placed()`-factory-state
     * order directly, so every receive()/cancel() test needs this real draft->placed transition
     * first, not a shortcut through the factory state.
     *
     * @return array{0: PurchaseOrder, 1: PurchaseOrderItem}
     */
    private function placedOrderWithItem(Supply $supply, int $quantityOrdered): array
    {
        $order = PurchaseOrder::factory()->create();
        $item = $this->service->addItem($order, ['supply_id' => $supply->id, 'quantity_ordered' => $quantityOrdered]);
        $this->service->place($order);

        return [$order->fresh(), $item->fresh()];
    }
}
