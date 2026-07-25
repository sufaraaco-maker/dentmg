<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'patient_id' => Patient::factory(),
            'invoice_id' => null,
            'refunded_payment_id' => null,
            'method' => PaymentMethod::Cash,
            'amount' => fake()->randomFloat(2, 20, 2000),
            'currency_code' => 'USD',
            'reference' => fake()->optional()->bothify('REF-####'),
            'notes' => fake()->optional()->sentence(),
            'received_at' => fake()->dateTimeBetween('-1 month', 'now')->format('Y-m-d'),
            'created_by_id' => User::factory()->admin(),
        ];
    }

    public function refundOf(Payment $payment, ?string $amount = null): static
    {
        return $this->state(fn () => [
            'patient_id' => $payment->patient_id,
            'invoice_id' => $payment->invoice_id,
            'refunded_payment_id' => $payment->id,
            'method' => $payment->method,
            'currency_code' => $payment->currency_code,
            'amount' => $amount !== null ? bcmul($amount, '-1', 2) : bcmul((string) $payment->amount, '-1', 2),
        ]);
    }
}
