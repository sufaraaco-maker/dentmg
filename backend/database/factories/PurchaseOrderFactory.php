<?php

namespace Database\Factories;

use App\Enums\PurchaseOrderStatus;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseOrder>
 */
class PurchaseOrderFactory extends Factory
{
    public function definition(): array
    {
        static $sequence = 0;
        $sequence++;

        return [
            'supplier_id' => Supplier::factory(),
            'sequence_number' => $sequence,
            'order_number' => 'PO-'.str_pad((string) $sequence, 6, '0', STR_PAD_LEFT),
            'status' => PurchaseOrderStatus::Draft,
            'notes' => fake()->optional()->sentence(),
            'ordered_at' => null,
            'expected_at' => null,
            'created_by_id' => User::factory()->admin(),
        ];
    }

    public function placed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PurchaseOrderStatus::Placed,
            'ordered_at' => now()->toDateString(),
        ]);
    }

    public function received(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PurchaseOrderStatus::Received,
            'ordered_at' => now()->subDays(3)->toDateString(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PurchaseOrderStatus::Cancelled,
        ]);
    }
}
