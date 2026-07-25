<?php

namespace Database\Factories;

use App\Enums\TreatmentPlanStatus;
use App\Models\Patient;
use App\Models\TreatmentPlan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TreatmentPlan>
 */
class TreatmentPlanFactory extends Factory
{
    public function definition(): array
    {
        $dentist = User::factory()->dentist();

        return [
            'patient_id' => Patient::factory(),
            'dentist_id' => $dentist,
            'created_by_id' => $dentist,
            'title' => fake()->optional()->sentence(3),
            'status' => TreatmentPlanStatus::Draft,
            'notes' => fake()->optional()->sentence(),
            'presented_at' => null,
            'accepted_at' => null,
            'rejected_at' => null,
            'started_at' => null,
            'completed_at' => null,
            'cancelled_at' => null,
            'superseded_by_plan_id' => null,
        ];
    }

    public function presented(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TreatmentPlanStatus::Presented,
            'presented_at' => fake()->dateTimeBetween('-1 month', 'now'),
        ]);
    }

    public function accepted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TreatmentPlanStatus::Accepted,
            'presented_at' => fake()->dateTimeBetween('-1 month', '-1 week'),
            'accepted_at' => fake()->dateTimeBetween('-1 week', 'now'),
        ]);
    }

    public function inProgress(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TreatmentPlanStatus::InProgress,
            'presented_at' => fake()->dateTimeBetween('-2 months', '-1 month'),
            'accepted_at' => fake()->dateTimeBetween('-1 month', '-1 week'),
            'started_at' => fake()->dateTimeBetween('-1 week', 'now'),
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TreatmentPlanStatus::Completed,
            'presented_at' => fake()->dateTimeBetween('-3 months', '-2 months'),
            'accepted_at' => fake()->dateTimeBetween('-2 months', '-1 month'),
            'started_at' => fake()->dateTimeBetween('-1 month', '-1 week'),
            'completed_at' => fake()->dateTimeBetween('-1 week', 'now'),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TreatmentPlanStatus::Rejected,
            'presented_at' => fake()->dateTimeBetween('-1 month', '-1 week'),
            'rejected_at' => fake()->dateTimeBetween('-1 week', 'now'),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TreatmentPlanStatus::Cancelled,
            'cancelled_at' => fake()->dateTimeBetween('-1 month', 'now'),
        ]);
    }
}
