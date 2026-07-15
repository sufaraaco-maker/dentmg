<?php

namespace Database\Factories;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\AppointmentType;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Appointment>
 */
class AppointmentFactory extends Factory
{
    public function definition(): array
    {
        $startAt = fake()->dateTimeBetween('now', '+1 month');
        $durationMinutes = fake()->randomElement([15, 30, 45, 60]);

        return [
            'patient_id' => Patient::factory(),
            'dentist_id' => User::factory()->dentist(),
            'appointment_type_id' => AppointmentType::factory(),
            'start_at' => $startAt,
            'end_at' => (clone $startAt)->modify("+{$durationMinutes} minutes"),
            'duration_minutes' => $durationMinutes,
            'status' => AppointmentStatus::Scheduled,
            'reason' => fake()->optional()->sentence(),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
