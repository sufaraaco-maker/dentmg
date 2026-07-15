<?php

namespace Database\Factories;

use App\Models\Appointment;
use App\Models\AppointmentReminder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AppointmentReminder>
 */
class AppointmentReminderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'appointment_id' => Appointment::factory(),
            'channel' => fake()->randomElement(['email', 'sms']),
            'scheduled_at' => fake()->dateTimeBetween('now', '+1 month'),
            'sent_at' => null,
            'status' => 'pending',
        ];
    }
}
