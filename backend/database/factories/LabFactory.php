<?php

namespace Database\Factories;

use App\Models\Lab;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lab>
 */
class LabFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->company().' Dental Lab',
            'contact_name' => fake()->name(),
            'phone' => fake()->phoneNumber(),
            'email' => fake()->companyEmail(),
            'address' => fake()->address(),
            'default_turnaround_days' => fake()->numberBetween(3, 14),
            'notes' => fake()->optional()->sentence(),
            'is_active' => true,
        ];
    }
}
