<?php

namespace Database\Factories;

use App\Models\Appointment;
use App\Models\Patient;
use App\Models\PatientActivity;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PatientActivity>
 */
class PatientActivityFactory extends Factory
{
    public function definition(): array
    {
        $subject = Appointment::factory()->create();

        return [
            'patient_id' => Patient::factory(),
            'event_type' => 'appointment.confirmed',
            'category' => 'appointments',
            'subject_type' => Appointment::class,
            'subject_id' => $subject->id,
            'actor_id' => User::factory(),
            'summary' => fake()->sentence(),
            'metadata' => null,
            'occurred_at' => fake()->dateTimeBetween('-30 days', 'now'),
        ];
    }
}
