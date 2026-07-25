<?php

namespace Database\Factories;

use App\Enums\TreatmentPlanItemStatus;
use App\Models\DentalCondition;
use App\Models\TreatmentPlan;
use App\Models\TreatmentPlanItem;
use App\Models\User;
use App\Support\ToothChart;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TreatmentPlanItem>
 */
class TreatmentPlanItemFactory extends Factory
{
    public function definition(): array
    {
        $condition = DentalCondition::factory()->procedure();
        $dentist = User::factory()->dentist();

        return [
            'treatment_plan_id' => TreatmentPlan::factory(),
            'dental_condition_id' => $condition,
            'procedure_name' => fake()->randomElement(['Composite Filling', 'Crown', 'Root Canal Treatment', 'Extraction', 'Implant']),
            'procedure_description' => fake()->optional()->sentence(),
            'diagnosis_entry_id' => null,
            'tooth_number' => fake()->randomElement(ToothChart::allCodes()),
            'surfaces' => null,
            'quantity' => 1,
            'unit_cost' => fake()->randomFloat(2, 50, 2000),
            'phase' => 1,
            'sequence' => null,
            'status' => TreatmentPlanItemStatus::Planned,
            'appointment_id' => null,
            'notes' => fake()->optional()->sentence(),
            'completed_at' => null,
            'cancelled_at' => null,
            'created_by_id' => $dentist,
            'updated_by_id' => null,
        ];
    }

    public function withSurfaces(array $surfaces = ['M', 'O']): static
    {
        return $this->state(fn (array $attributes) => [
            'surfaces' => $surfaces,
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TreatmentPlanItemStatus::Completed,
            'completed_at' => fake()->dateTimeBetween('-1 month', 'now'),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TreatmentPlanItemStatus::Cancelled,
            'cancelled_at' => fake()->dateTimeBetween('-1 month', 'now'),
        ]);
    }
}
