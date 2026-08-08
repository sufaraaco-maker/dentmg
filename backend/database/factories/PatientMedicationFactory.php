<?php

namespace Database\Factories;

use App\Models\Patient;
use App\Models\PatientMedication;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PatientMedication>
 */
class PatientMedicationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'patient_id' => Patient::factory(),
            'medication_name' => fake()->randomElement(['Metformin', 'Lisinopril', 'Albuterol', 'Amoxicillin']),
            'dosage' => fake()->optional()->randomElement(['500mg', '10mg', '250mg']),
            'frequency' => fake()->optional()->randomElement(['Once daily', 'Twice daily', 'As needed']),
            'is_current' => true,
            'start_date' => fake()->optional()->dateTimeBetween('-2 years', 'now'),
            'end_date' => null,
            'notes' => fake()->optional()->sentence(),
            'created_by_id' => null,
            'updated_by_id' => null,
        ];
    }
}
