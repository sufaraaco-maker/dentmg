<?php

namespace Database\Factories;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supply;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseOrderItem>
 */
class PurchaseOrderItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'purchase_order_id' => PurchaseOrder::factory(),
            'supply_id' => Supply::factory(),
            'description' => fake()->words(3, true),
            'quantity_ordered' => fake()->numberBetween(5, 50),
            'quantity_received' => 0,
            'unit_cost' => fake()->randomFloat(2, 1, 200),
        ];
    }
}
