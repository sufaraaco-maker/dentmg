<?php

namespace Database\Factories;

use App\Models\SupplyCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupplyCategory>
 */
class SupplyCategoryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->randomElement([
                'PPE', 'Restorative', 'Anesthetics', 'Sterilization', 'Lab Materials',
                'Office Supplies', 'Endodontics', 'Preventive',
            ]),
            'sort_order' => fake()->numberBetween(1, 20),
            'is_active' => true,
        ];
    }
}
