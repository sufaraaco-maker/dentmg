<?php

namespace Database\Factories;

use App\Models\AppointmentType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AppointmentType>
 */
class AppointmentTypeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(['Consultation', 'Cleaning', 'Filling', 'Extraction', 'Root Canal', 'Follow-up', 'Emergency']),
            'default_duration_minutes' => fake()->randomElement([15, 30, 45, 60]),
            'color' => fake()->hexColor(),
            'is_active' => true,
        ];
    }
}
