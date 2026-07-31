<?php

namespace Database\Factories;

use App\Enums\AiInteractionFeature;
use App\Models\AiInteractionLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiInteractionLog>
 */
class AiInteractionLogFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'feature' => AiInteractionFeature::DashboardInsight,
            'patient_id' => null,
            'prompt_summary' => fake()->sentence(),
            'response_summary' => fake()->paragraph(),
            'accepted' => null,
            'model' => 'claude-opus-5',
        ];
    }
}
