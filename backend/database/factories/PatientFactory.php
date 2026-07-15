<?php

namespace Database\Factories;

use App\Enums\BloodType;
use App\Enums\PatientGender;
use App\Models\Patient;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Patient>
 */
class PatientFactory extends Factory
{
    protected static int $sequence = 0;

    public function definition(): array
    {
        return [
            'sequence_number' => ++static::$sequence,
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'date_of_birth' => fake()->dateTimeBetween('-90 years', '-1 year')->format('Y-m-d'),
            'gender' => fake()->randomElement(PatientGender::cases()),
            'phone' => fake()->unique()->numerify('05########'),
            'email' => fake()->optional()->safeEmail(),
            'address' => fake()->optional()->address(),
            'national_id' => fake()->optional()->numerify('###########'),
            'emergency_contact_name' => fake()->optional()->name(),
            'emergency_contact_phone' => fake()->optional()->numerify('05########'),
            'blood_type' => fake()->optional()->randomElement(BloodType::cases()),
            'allergies' => fake()->optional()->sentence(),
            'medical_history' => fake()->optional()->paragraph(),
            'insurance_provider' => fake()->optional()->company(),
            'insurance_number' => fake()->optional()->bothify('INS-#####'),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
