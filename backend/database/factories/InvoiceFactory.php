<?php

namespace Database\Factories;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'patient_id' => Patient::factory(),
            'created_by_id' => User::factory()->admin(),
            'sequence_number' => null,
            'invoice_number' => null,
            'currency_code' => 'USD',
            'status' => InvoiceStatus::Draft,
            'notes' => fake()->optional()->sentence(),
            'issue_date' => null,
            'due_date' => null,
            'issued_at' => null,
            'voided_at' => null,
        ];
    }

    public function issued(): static
    {
        return $this->state(function (array $attributes) {
            $sequence = fake()->unique()->numberBetween(1, 100000);

            return [
                'status' => InvoiceStatus::Issued,
                'sequence_number' => $sequence,
                'invoice_number' => 'INV-'.str_pad((string) $sequence, 6, '0', STR_PAD_LEFT),
                'issue_date' => fake()->dateTimeBetween('-1 month', 'now'),
                'issued_at' => fake()->dateTimeBetween('-1 month', 'now'),
            ];
        });
    }

    public function void(): static
    {
        return $this->state(function (array $attributes) {
            $sequence = fake()->unique()->numberBetween(1, 100000);

            return [
                'status' => InvoiceStatus::Void,
                'sequence_number' => $sequence,
                'invoice_number' => 'INV-'.str_pad((string) $sequence, 6, '0', STR_PAD_LEFT),
                'issue_date' => fake()->dateTimeBetween('-2 months', '-1 month'),
                'issued_at' => fake()->dateTimeBetween('-2 months', '-1 month'),
                'voided_at' => fake()->dateTimeBetween('-1 month', 'now'),
            ];
        });
    }
}
