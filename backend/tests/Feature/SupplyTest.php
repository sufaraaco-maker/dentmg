<?php

namespace Tests\Feature;

use App\Models\Supply;
use App\Models\SupplyCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplyTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_list_supplies(): void
    {
        $response = $this->getJson('/api/supplies');

        $response->assertUnauthorized();
    }

    public function test_any_authenticated_role_can_list_supplies(): void
    {
        $actor = User::factory()->dentist()->create();
        Supply::factory()->count(3)->create();

        $response = $this->actingAs($actor)->getJson('/api/supplies');

        $response->assertOk();
        $this->assertCount(3, $response->json('data'));
    }

    public function test_list_includes_computed_quantity_on_hand_for_every_row(): void
    {
        $actor = User::factory()->admin()->create();
        $supply = Supply::factory()->create(['reorder_level' => 5]);
        $supply->stockMovements()->create([
            'quantity_delta' => 12,
            'reason' => 'initial_stock',
            'performed_by_id' => $actor->id,
            'occurred_at' => now(),
        ]);

        $response = $this->actingAs($actor)->getJson('/api/supplies');

        $response->assertOk();
        $this->assertSame(12, $response->json('data.0.quantity_on_hand'));
        $this->assertFalse($response->json('data.0.is_low_stock'));
    }

    public function test_low_stock_endpoint_returns_only_supplies_at_or_below_reorder_level(): void
    {
        $actor = User::factory()->admin()->create();

        $low = Supply::factory()->create(['reorder_level' => 10]);
        $low->stockMovements()->create([
            'quantity_delta' => 4, 'reason' => 'initial_stock', 'performed_by_id' => $actor->id, 'occurred_at' => now(),
        ]);

        $healthy = Supply::factory()->create(['reorder_level' => 10]);
        $healthy->stockMovements()->create([
            'quantity_delta' => 50, 'reason' => 'initial_stock', 'performed_by_id' => $actor->id, 'occurred_at' => now(),
        ]);

        $response = $this->actingAs($actor)->getJson('/api/supplies/low-stock');

        $response->assertOk();
        $ids = collect($response->json())->pluck('id');
        $this->assertTrue($ids->contains($low->id));
        $this->assertFalse($ids->contains($healthy->id));
    }

    public function test_admin_can_create_a_supply(): void
    {
        $actor = User::factory()->admin()->create();
        $category = SupplyCategory::factory()->create();

        $response = $this->actingAs($actor)->postJson('/api/supplies', [
            'category_id' => $category->id,
            'name' => 'Composite Resin A2',
            'unit_of_measure' => 'box',
            'reorder_level' => 5,
        ]);

        $response->assertCreated();
        $this->assertSame(0, $response->json('quantity_on_hand'));
    }

    public function test_receptionist_can_create_a_supply(): void
    {
        $actor = User::factory()->create(['role' => 'receptionist']);
        $category = SupplyCategory::factory()->create();

        $response = $this->actingAs($actor)->postJson('/api/supplies', [
            'category_id' => $category->id,
            'name' => 'Cotton Rolls',
            'unit_of_measure' => 'pack',
        ]);

        $response->assertCreated();
    }

    public function test_dentist_cannot_create_a_supply(): void
    {
        $actor = User::factory()->dentist()->create();
        $category = SupplyCategory::factory()->create();

        $response = $this->actingAs($actor)->postJson('/api/supplies', [
            'category_id' => $category->id,
            'name' => 'Cotton Rolls',
            'unit_of_measure' => 'pack',
        ]);

        $response->assertForbidden();
    }

    public function test_dentist_cannot_deactivate_a_supply(): void
    {
        $actor = User::factory()->dentist()->create();
        $supply = Supply::factory()->create();

        $response = $this->actingAs($actor)->deleteJson("/api/supplies/{$supply->id}");

        $response->assertForbidden();
    }

    public function test_admin_can_deactivate_a_supply(): void
    {
        $actor = User::factory()->admin()->create();
        $supply = Supply::factory()->create();

        $response = $this->actingAs($actor)->deleteJson("/api/supplies/{$supply->id}");

        $response->assertNoContent();
        $this->assertFalse($supply->fresh()->is_active);
    }
}
