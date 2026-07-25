<?php

namespace Database\Factories;

use App\Enums\InvoiceItemKind;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\TreatmentPlanItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InvoiceItem>
 */
class InvoiceItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'invoice_id' => Invoice::factory(),
            'treatment_plan_item_id' => null,
            'kind' => InvoiceItemKind::Charge,
            'description' => fake()->randomElement(['Composite Filling', 'Crown', 'Root Canal Treatment', 'Extraction', 'Consultation']),
            'quantity' => 1,
            'unit_amount' => fake()->randomFloat(2, 50, 2000),
            'sequence' => null,
            'notes' => fake()->optional()->sentence(),
            'created_by_id' => User::factory()->admin(),
        ];
    }

    public function discount(): static
    {
        return $this->state(fn (array $attributes) => [
            'kind' => InvoiceItemKind::Discount,
            'description' => 'Discount',
            'unit_amount' => fake()->randomFloat(2, 5, 100),
        ]);
    }

    public function tax(): static
    {
        return $this->state(fn (array $attributes) => [
            'kind' => InvoiceItemKind::Tax,
            'description' => 'Tax',
            'unit_amount' => fake()->randomFloat(2, 1, 50),
        ]);
    }

    public function fromTreatmentPlanItem(): static
    {
        return $this->state(fn (array $attributes) => [
            'treatment_plan_item_id' => TreatmentPlanItem::factory(),
        ]);
    }
}
