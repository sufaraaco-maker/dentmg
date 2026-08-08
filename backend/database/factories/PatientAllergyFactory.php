<?php

namespace Database\Factories;

use App\Enums\AllergySeverity;
use App\Models\Patient;
use App\Models\PatientAllergy;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PatientAllergy>
 */
class PatientAllergyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'patient_id' => Patient::factory(),
            'allergen' => fake()->randomElement(['Penicillin', 'Latex', 'Ibuprofen', 'Sulfa drugs', 'Peanuts']),
            'severity' => fake()->randomElement(AllergySeverity::cases()),
            'reaction' => fake()->optional()->sentence(4),
            'notes' => fake()->optional()->sentence(),
            'created_by_id' => null,
            'updated_by_id' => null,
        ];
    }
}
