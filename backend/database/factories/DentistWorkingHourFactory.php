<?php

namespace Database\Factories;

use App\Models\DentistWorkingHour;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DentistWorkingHour>
 */
class DentistWorkingHourFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->dentist(),
            'day_of_week' => fake()->numberBetween(0, 6),
            'start_time' => '09:00',
            'end_time' => '17:00',
            'is_active' => true,
        ];
    }
}
