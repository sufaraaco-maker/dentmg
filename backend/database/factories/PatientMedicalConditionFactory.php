<?php

namespace Database\Factories;

use App\Enums\MedicalConditionStatus;
use App\Models\Patient;
use App\Models\PatientMedicalCondition;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PatientMedicalCondition>
 */
class PatientMedicalConditionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'patient_id' => Patient::factory(),
            'condition_name' => fake()->randomElement(['Diabetes Type 2', 'Hypertension', 'Asthma', 'Hepatitis B']),
            'status' => MedicalConditionStatus::Active,
            'diagnosed_date' => fake()->optional()->dateTimeBetween('-10 years', 'now'),
            'notes' => fake()->optional()->sentence(),
            'created_by_id' => null,
            'updated_by_id' => null,
        ];
    }
}
