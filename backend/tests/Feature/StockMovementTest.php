<?php

namespace Tests\Feature;

use App\Models\Supply;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockMovementTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_record_a_movement(): void
    {
        $supply = Supply::factory()->create();

        $response = $this->postJson("/api/supplies/{$supply->id}/stock-movements", [
            'reason' => 'initial_stock',
            'quantity_delta' => 10,
        ]);

        $response->assertUnauthorized();
    }

    public function test_admin_can_record_initial_stock(): void
    {
        $actor = User::factory()->admin()->create();
        $supply = Supply::factory()->create();

        $response = $this->actingAs($actor)->postJson("/api/supplies/{$supply->id}/stock-movements", [
            'reason' => 'initial_stock',
            'quantity_delta' => 25,
        ]);

        $response->assertCreated();
        $this->assertSame(25, $supply->fresh()->quantity_on_hand);
    }

    /**
     * Design doc §15 Decision 1: dentists may log usage — a deliberate divergence from the
     * admin+receptionist-only write split Billing/Payments use, since dentists are the ones
     * actually consuming supplies chairside.
     */
    public function test_dentist_can_record_usage(): void
    {
        $actor = User::factory()->admin()->create();
        $dentist = User::factory()->dentist()->create();
        $supply = Supply::factory()->create();
        $this->actingAs($actor)->postJson("/api/supplies/{$supply->id}/stock-movements", [
            'reason' => 'initial_stock',
            'quantity_delta' => 10,
        ]);

        $response = $this->actingAs($dentist)->postJson("/api/supplies/{$supply->id}/stock-movements", [
            'reason' => 'used',
            'quantity_delta' => -3,
        ]);

        $response->assertCreated();
        $this->assertSame(7, $supply->fresh()->quantity_on_hand);
    }

    public function test_receptionist_can_record_usage(): void
    {
        $actor = User::factory()->create(['role' => 'receptionist']);
        $supply = Supply::factory()->create();
        $this->actingAs($actor)->postJson("/api/supplies/{$supply->id}/stock-movements", [
            'reason' => 'initial_stock', 'quantity_delta' => 10,
        ]);

        $response = $this->actingAs($actor)->postJson("/api/supplies/{$supply->id}/stock-movements", [
            'reason' => 'wasted',
            'quantity_delta' => -2,
        ]);

        $response->assertCreated();
    }

    public function test_reason_received_is_rejected_as_a_manual_entry(): void
    {
        $actor = User::factory()->admin()->create();
        $supply = Supply::factory()->create();

        $response = $this->actingAs($actor)->postJson("/api/supplies/{$supply->id}/stock-movements", [
            'reason' => 'received',
            'quantity_delta' => 10,
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['reason']);
    }

    public function test_a_decrease_exceeding_on_hand_is_rejected(): void
    {
        $actor = User::factory()->admin()->create();
        $supply = Supply::factory()->create();
        $this->actingAs($actor)->postJson("/api/supplies/{$supply->id}/stock-movements", [
            'reason' => 'initial_stock', 'quantity_delta' => 5,
        ]);

        $response = $this->actingAs($actor)->postJson("/api/supplies/{$supply->id}/stock-movements", [
            'reason' => 'used',
            'quantity_delta' => -6,
        ]);

        $response->assertStatus(422)->assertJson(['code' => 'inventory_insufficient_stock']);
    }

    public function test_movement_ledger_can_be_listed_for_a_supply(): void
    {
        $actor = User::factory()->admin()->create();
        $supply = Supply::factory()->create();
        $this->actingAs($actor)->postJson("/api/supplies/{$supply->id}/stock-movements", [
            'reason' => 'initial_stock', 'quantity_delta' => 10,
        ]);

        $response = $this->actingAs($actor)->getJson("/api/supplies/{$supply->id}/stock-movements");

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }
}
