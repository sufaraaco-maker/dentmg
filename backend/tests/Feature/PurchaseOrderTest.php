<?php

namespace Tests\Feature;

use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\Supply;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseOrderTest extends TestCase
{
    use RefreshDatabase;

    // ---- index / store --------------------------------------------------------------------------

    public function test_guest_cannot_list_purchase_orders(): void
    {
        $response = $this->getJson('/api/purchase-orders');

        $response->assertUnauthorized();
    }

    public function test_admin_can_create_a_purchase_order(): void
    {
        $actor = User::factory()->admin()->create();
        $supplier = Supplier::factory()->create();

        $response = $this->actingAs($actor)->postJson('/api/purchase-orders', [
            'supplier_id' => $supplier->id,
        ]);

        $response->assertCreated();
        $this->assertSame('draft', $response->json('status'));
        $this->assertNotNull($response->json('order_number'));
    }

    public function test_dentist_cannot_create_a_purchase_order(): void
    {
        $actor = User::factory()->dentist()->create();
        $supplier = Supplier::factory()->create();

        $response = $this->actingAs($actor)->postJson('/api/purchase-orders', ['supplier_id' => $supplier->id]);

        $response->assertForbidden();
    }

    public function test_dentist_can_view_a_purchase_order(): void
    {
        $actor = User::factory()->dentist()->create();
        $order = PurchaseOrder::factory()->create();

        $response = $this->actingAs($actor)->getJson("/api/purchase-orders/{$order->id}");

        $response->assertOk();
    }

    // ---- items -----------------------------------------------------------------------------------

    public function test_receptionist_can_add_an_item_to_a_draft_order(): void
    {
        $actor = User::factory()->create(['role' => 'receptionist']);
        $order = PurchaseOrder::factory()->create();
        $supply = Supply::factory()->create(['unit_cost' => 9.99]);

        $response = $this->actingAs($actor)->postJson("/api/purchase-orders/{$order->id}/items", [
            'supply_id' => $supply->id,
            'quantity_ordered' => 15,
        ]);

        $response->assertCreated();
        $this->assertCount(1, $response->json('items'));
        $this->assertSame(15, $response->json('items.0.quantity_ordered'));
    }

    public function test_dentist_cannot_add_an_item(): void
    {
        $actor = User::factory()->dentist()->create();
        $order = PurchaseOrder::factory()->create();
        $supply = Supply::factory()->create();

        $response = $this->actingAs($actor)->postJson("/api/purchase-orders/{$order->id}/items", [
            'supply_id' => $supply->id,
            'quantity_ordered' => 5,
        ]);

        $response->assertForbidden();
    }

    public function test_adding_an_item_once_order_is_placed_is_rejected(): void
    {
        $actor = User::factory()->admin()->create();
        [$order] = $this->placedOrderWithItem($actor, quantityOrdered: 5);

        $response = $this->actingAs($actor)->postJson("/api/purchase-orders/{$order->id}/items", [
            'supply_id' => Supply::factory()->create()->id,
            'quantity_ordered' => 1,
        ]);

        $response->assertStatus(422)->assertJson(['code' => 'invalid_purchase_order_operation']);
    }

    // ---- place -----------------------------------------------------------------------------------

    public function test_admin_can_place_an_order_with_items(): void
    {
        $actor = User::factory()->admin()->create();
        $order = PurchaseOrder::factory()->create();
        $this->actingAs($actor)->postJson("/api/purchase-orders/{$order->id}/items", [
            'supply_id' => Supply::factory()->create()->id,
            'quantity_ordered' => 10,
        ]);

        $response = $this->actingAs($actor)->postJson("/api/purchase-orders/{$order->id}/place");

        $response->assertOk();
        $this->assertSame('placed', $response->json('status'));
    }

    public function test_placing_an_order_with_no_items_is_rejected(): void
    {
        $actor = User::factory()->admin()->create();
        $order = PurchaseOrder::factory()->create();

        $response = $this->actingAs($actor)->postJson("/api/purchase-orders/{$order->id}/place");

        $response->assertStatus(422)->assertJson(['code' => 'invalid_purchase_order_operation']);
    }

    public function test_dentist_cannot_place_an_order(): void
    {
        $actor = User::factory()->dentist()->create();
        $order = PurchaseOrder::factory()->create();

        $response = $this->actingAs($actor)->postJson("/api/purchase-orders/{$order->id}/place");

        $response->assertForbidden();
    }

    // ---- receive ---------------------------------------------------------------------------------

    public function test_admin_can_receive_an_item_fully(): void
    {
        $actor = User::factory()->admin()->create();
        $supply = Supply::factory()->create();
        [$order, $itemId] = $this->placedOrderWithItem($actor, $supply, 10);

        $response = $this->actingAs($actor)->postJson("/api/purchase-order-items/{$itemId}/receive", [
            'quantity' => 10,
        ]);

        $response->assertOk();
        $this->assertSame('received', $response->json('status'));
        $this->assertSame(10, $supply->fresh()->quantity_on_hand);
    }

    public function test_partial_receive_marks_order_partially_received(): void
    {
        $actor = User::factory()->admin()->create();
        [$order, $itemId] = $this->placedOrderWithItem($actor, quantityOrdered: 10);

        $response = $this->actingAs($actor)->postJson("/api/purchase-order-items/{$itemId}/receive", [
            'quantity' => 4,
        ]);

        $response->assertOk();
        $this->assertSame('partially_received', $response->json('status'));
    }

    public function test_receiving_more_than_ordered_is_rejected(): void
    {
        $actor = User::factory()->admin()->create();
        [$order, $itemId] = $this->placedOrderWithItem($actor, quantityOrdered: 5);

        $response = $this->actingAs($actor)->postJson("/api/purchase-order-items/{$itemId}/receive", [
            'quantity' => 6,
        ]);

        $response->assertStatus(422)->assertJson(['code' => 'invalid_purchase_order_operation']);
    }

    public function test_receptionist_can_receive_with_an_expiration_date(): void
    {
        $admin = User::factory()->admin()->create();
        $receptionist = User::factory()->create(['role' => 'receptionist']);
        [$order, $itemId] = $this->placedOrderWithItem($admin, quantityOrdered: 5);

        $response = $this->actingAs($receptionist)->postJson("/api/purchase-order-items/{$itemId}/receive", [
            'quantity' => 5,
            'expiration_date' => '2027-03-01',
        ]);

        $response->assertOk();
    }

    public function test_dentist_cannot_receive_an_item(): void
    {
        $admin = User::factory()->admin()->create();
        $dentist = User::factory()->dentist()->create();
        [$order, $itemId] = $this->placedOrderWithItem($admin, quantityOrdered: 5);

        $response = $this->actingAs($dentist)->postJson("/api/purchase-order-items/{$itemId}/receive", [
            'quantity' => 5,
        ]);

        $response->assertForbidden();
    }

    // ---- cancel ----------------------------------------------------------------------------------

    public function test_admin_can_cancel_a_draft_order(): void
    {
        $actor = User::factory()->admin()->create();
        $order = PurchaseOrder::factory()->create();

        $response = $this->actingAs($actor)->postJson("/api/purchase-orders/{$order->id}/cancel");

        $response->assertOk();
        $this->assertSame('cancelled', $response->json('status'));
    }

    public function test_cancel_rejected_once_an_item_has_been_received(): void
    {
        $actor = User::factory()->admin()->create();
        [$order, $itemId] = $this->placedOrderWithItem($actor, quantityOrdered: 5);
        $this->actingAs($actor)->postJson("/api/purchase-order-items/{$itemId}/receive", ['quantity' => 1]);

        $response = $this->actingAs($actor)->postJson("/api/purchase-orders/{$order->id}/cancel");

        $response->assertStatus(422)->assertJson(['code' => 'invalid_purchase_order_operation']);
    }

    // ---- destroy ---------------------------------------------------------------------------------

    public function test_admin_can_delete_a_draft_order(): void
    {
        $actor = User::factory()->admin()->create();
        $order = PurchaseOrder::factory()->create();

        $response = $this->actingAs($actor)->deleteJson("/api/purchase-orders/{$order->id}");

        $response->assertNoContent();
        $this->assertSoftDeleted('purchase_orders', ['id' => $order->id]);
    }

    public function test_receptionist_cannot_delete_a_purchase_order(): void
    {
        $actor = User::factory()->create(['role' => 'receptionist']);
        $order = PurchaseOrder::factory()->create();

        $response = $this->actingAs($actor)->deleteJson("/api/purchase-orders/{$order->id}");

        $response->assertForbidden();
    }

    /**
     * Builds a draft order, adds one item (while still draft — the only status that allows it),
     * then places it — returning [order, itemId] so the caller can go on to test receive/cancel
     * against a genuinely placed order.
     *
     * @return array{0: PurchaseOrder, 1: string}
     */
    private function placedOrderWithItem(User $actor, ?Supply $supply = null, int $quantityOrdered = 10): array
    {
        $order = PurchaseOrder::factory()->create();
        $supply ??= Supply::factory()->create();

        $itemResponse = $this->actingAs($actor)->postJson("/api/purchase-orders/{$order->id}/items", [
            'supply_id' => $supply->id,
            'quantity_ordered' => $quantityOrdered,
        ]);

        $this->actingAs($actor)->postJson("/api/purchase-orders/{$order->id}/place")->assertOk();

        return [$order->fresh(), $itemResponse->json('items.0.id')];
    }
}
