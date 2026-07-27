<?php

namespace Database\Factories;

use App\Enums\LabCaseStatus;
use App\Models\Lab;
use App\Models\LabCase;
use App\Models\Patient;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LabCase>
 */
class LabCaseFactory extends Factory
{
    public function definition(): array
    {
        static $sequence = 0;
        $sequence++;

        return [
            'sequence_number' => $sequence,
            'case_number' => 'LC-'.str_pad((string) $sequence, 6, '0', STR_PAD_LEFT),
            'patient_id' => Patient::factory(),
            'lab_id' => Lab::factory(),
            'dentist_id' => null,
            'treatment_plan_item_id' => null,
            'appointment_id' => null,
            'tooth_numbers' => ['16'],
            'case_type' => 'Crown',
            'shade' => fake()->randomElement(['A1', 'A2', 'A3', 'B1', 'C2']),
            'material' => fake()->randomElement(['Zirconia', 'PFM', 'E-max']),
            'instructions' => fake()->optional()->sentence(),
            'fee' => fake()->randomFloat(2, 50, 400),
            'tracking_number' => null,
            'status' => LabCaseStatus::Draft,
            'sent_at' => null,
            'due_at' => null,
            'received_at' => null,
            'quality_checked_at' => null,
            'cancelled_at' => null,
        ];
    }

    public function sent(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => LabCaseStatus::Sent,
            'sent_at' => now(),
            'due_at' => now()->addDays(7),
        ]);
    }

    public function received(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => LabCaseStatus::Received,
            'sent_at' => now()->subDays(5),
            'due_at' => now()->subDays(1),
            'received_at' => now(),
        ]);
    }

    public function qualityChecked(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => LabCaseStatus::QualityChecked,
            'sent_at' => now()->subDays(7),
            'due_at' => now()->subDays(2),
            'received_at' => now()->subDay(),
            'quality_checked_at' => now(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => LabCaseStatus::Cancelled,
            'cancelled_at' => now(),
        ]);
    }
}
