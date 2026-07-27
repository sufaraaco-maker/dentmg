<?php

namespace Tests\Unit\Services;

use App\Enums\StockMovementReason;
use App\Exceptions\Inventory\InsufficientStockException;
use App\Models\Supply;
use App\Models\User;
use App\Services\StockMovementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockMovementServiceTest extends TestCase
{
    use RefreshDatabase;

    private StockMovementService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new StockMovementService;
    }

    public function test_record_creates_a_movement_and_increases_on_hand(): void
    {
        $supply = Supply::factory()->create();
        $actor = User::factory()->admin()->create();

        $movement = $this->service->record($supply, [
            'reason' => StockMovementReason::InitialStock,
            'quantity_delta' => 50,
        ], $actor);

        $this->assertSame(50, $movement->quantity_delta);
        $this->assertSame(50, $supply->fresh()->quantity_on_hand);
    }

    public function test_record_allows_a_decrease_that_keeps_on_hand_at_or_above_zero(): void
    {
        $supply = Supply::factory()->create();
        $actor = User::factory()->admin()->create();
        $this->service->record($supply, ['reason' => StockMovementReason::InitialStock, 'quantity_delta' => 10], $actor);

        $movement = $this->service->record($supply, ['reason' => StockMovementReason::Used, 'quantity_delta' => -10], $actor);

        $this->assertSame(-10, $movement->quantity_delta);
        $this->assertSame(0, $supply->fresh()->quantity_on_hand);
    }

    public function test_record_rejects_a_decrease_that_would_take_on_hand_below_zero(): void
    {
        $supply = Supply::factory()->create();
        $actor = User::factory()->admin()->create();
        $this->service->record($supply, ['reason' => StockMovementReason::InitialStock, 'quantity_delta' => 5], $actor);

        $this->expectException(InsufficientStockException::class);

        $this->service->record($supply, ['reason' => StockMovementReason::Used, 'quantity_delta' => -6], $actor);
    }

    public function test_record_allows_a_negative_correction_within_balance(): void
    {
        $supply = Supply::factory()->create();
        $actor = User::factory()->admin()->create();
        $this->service->record($supply, ['reason' => StockMovementReason::InitialStock, 'quantity_delta' => 20], $actor);

        $movement = $this->service->record($supply, ['reason' => StockMovementReason::Correction, 'quantity_delta' => -5], $actor);

        $this->assertSame(-5, $movement->quantity_delta);
        $this->assertSame(15, $supply->fresh()->quantity_on_hand);
    }

    public function test_record_stores_notes_and_expiration_date(): void
    {
        $supply = Supply::factory()->create();
        $actor = User::factory()->admin()->create();

        $movement = $this->service->record($supply, [
            'reason' => StockMovementReason::InitialStock,
            'quantity_delta' => 5,
            'notes' => 'Opening balance',
            'expiration_date' => '2027-06-01',
        ], $actor);

        $this->assertSame('Opening balance', $movement->notes);
        $this->assertSame('2027-06-01', $movement->expiration_date->toDateString());
        $this->assertSame($actor->id, $movement->performed_by_id);
    }
}
