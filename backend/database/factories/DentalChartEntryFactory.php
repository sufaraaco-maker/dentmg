<?php

namespace Database\Factories;

use App\Enums\DentalChartEntryStatus;
use App\Models\DentalChartEntry;
use App\Models\DentalCondition;
use App\Models\Patient;
use App\Models\User;
use App\Support\ToothChart;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DentalChartEntry>
 */
class DentalChartEntryFactory extends Factory
{
    public function definition(): array
    {
        $dentist = User::factory()->dentist();

        return [
            'patient_id' => Patient::factory(),
            'dental_condition_id' => DentalCondition::factory(),
            'dentist_id' => $dentist,
            'created_by_id' => $dentist,
            'updated_by_id' => null,
            'tooth_number' => fake()->randomElement(ToothChart::allCodes()),
            'surfaces' => null,
            'status' => DentalChartEntryStatus::Active,
            'notes' => fake()->optional()->sentence(),
            'recorded_at' => fake()->dateTimeBetween('-6 months', 'now'),
            'completed_at' => null,
            'cancelled_at' => null,
        ];
    }

    public function withSurfaces(array $surfaces = ['M', 'O']): static
    {
        return $this->state(fn (array $attributes) => [
            'surfaces' => $surfaces,
        ]);
    }

    public function planned(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DentalChartEntryStatus::Planned,
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DentalChartEntryStatus::Completed,
            'completed_at' => fake()->dateTimeBetween('-1 month', 'now'),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DentalChartEntryStatus::Cancelled,
            'cancelled_at' => fake()->dateTimeBetween('-1 month', 'now'),
        ]);
    }
}
