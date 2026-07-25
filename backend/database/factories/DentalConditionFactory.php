<?php

namespace Database\Factories;

use App\Enums\DentalConditionCategory;
use App\Models\DentalCondition;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DentalCondition>
 */
class DentalConditionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(['Dental Caries', 'Composite Filling', 'Crown', 'Root Canal Treatment', 'Extraction']),
            'category' => fake()->randomElement(DentalConditionCategory::cases()),
            'applies_to_surface' => fake()->boolean(),
            'default_color' => fake()->hexColor(),
            'icon_key' => fake()->randomElement(['filling', 'crown', 'missing', 'root_canal', null]),
            'default_cost' => fake()->randomFloat(2, 50, 800),
            'description' => fake()->optional()->sentence(),
            'is_active' => true,
            'sort_order' => fake()->numberBetween(1, 20),
        ];
    }

    public function finding(): static
    {
        return $this->state(fn (array $attributes) => [
            'category' => DentalConditionCategory::Finding,
        ]);
    }

    public function procedure(): static
    {
        return $this->state(fn (array $attributes) => [
            'category' => DentalConditionCategory::Procedure,
        ]);
    }
}
