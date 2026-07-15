<?php

namespace Database\Factories;

use App\Models\DentistTimeOff;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DentistTimeOff>
 */
class DentistTimeOffFactory extends Factory
{
    public function definition(): array
    {
        $startAt = fake()->dateTimeBetween('now', '+3 months');

        return [
            'user_id' => User::factory()->dentist(),
            'start_at' => $startAt,
            'end_at' => (clone $startAt)->modify('+1 day'),
            'reason' => fake()->optional()->sentence(),
        ];
    }
}
