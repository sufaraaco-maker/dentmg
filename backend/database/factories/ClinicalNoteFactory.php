<?php

namespace Database\Factories;

use App\Enums\ClinicalNoteStatus;
use App\Enums\ClinicalNoteType;
use App\Models\ClinicalNote;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClinicalNote>
 */
class ClinicalNoteFactory extends Factory
{
    public function definition(): array
    {
        $dentist = User::factory()->dentist();

        return [
            'patient_id' => Patient::factory(),
            'appointment_id' => null,
            'dentist_id' => $dentist,
            'note_type' => ClinicalNoteType::Progress,
            'chief_complaint' => fake()->sentence(),
            'subjective' => fake()->paragraph(),
            'objective' => fake()->paragraph(),
            'assessment' => fake()->paragraph(),
            'plan' => fake()->paragraph(),
            'status' => ClinicalNoteStatus::Draft,
            'signed_at' => null,
            'signed_by_id' => null,
            'created_by_id' => $dentist,
            'updated_by_id' => null,
        ];
    }

    public function signed(): static
    {
        return $this->state(function (array $attributes) {
            $signer = $attributes['dentist_id'] ?? User::factory()->dentist();

            return [
                'status' => ClinicalNoteStatus::Signed,
                'signed_at' => fake()->dateTimeBetween('-1 month', 'now'),
                'signed_by_id' => $signer,
            ];
        });
    }
}
