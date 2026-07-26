<?php

namespace Database\Factories;

use App\Models\Supplier;
use App\Models\Supply;
use App\Models\SupplyCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Supply>
 */
class SupplyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'category_id' => SupplyCategory::factory(),
            'default_supplier_id' => Supplier::factory(),
            'name' => fake()->randomElement([
                'Composite Resin A2', 'Nitrile Gloves M', 'Lidocaine 2%', 'Sterilization Pouches',
                'Cotton Rolls', 'Impression Material', 'Surgical Masks', 'Dental Floss',
            ]),
            'sku' => fake()->optional()->bothify('SKU-#####'),
            'unit_of_measure' => fake()->randomElement(['box', 'each', 'pack', 'bottle']),
            'unit_cost' => fake()->randomFloat(2, 1, 200),
            'reorder_level' => fake()->numberBetween(5, 50),
            'reorder_quantity' => fake()->numberBetween(10, 100),
            'is_active' => true,
        ];
    }
}
