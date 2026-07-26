<?php

namespace Database\Factories;

use App\Enums\StockMovementReason;
use App\Models\StockMovement;
use App\Models\Supply;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockMovement>
 */
class StockMovementFactory extends Factory
{
    public function definition(): array
    {
        return [
            'supply_id' => Supply::factory(),
            'quantity_delta' => fake()->numberBetween(10, 100),
            'reason' => StockMovementReason::InitialStock,
            'purchase_order_item_id' => null,
            'expiration_date' => null,
            'notes' => fake()->optional()->sentence(),
            'performed_by_id' => User::factory()->admin(),
            'occurred_at' => now(),
        ];
    }

    public function used(int $quantity = 1): static
    {
        return $this->state(fn (array $attributes) => [
            'quantity_delta' => -$quantity,
            'reason' => StockMovementReason::Used,
        ]);
    }
}
