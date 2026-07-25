<?php

namespace Database\Factories;

use App\Models\BillingSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BillingSetting>
 */
class BillingSettingFactory extends Factory
{
    public function definition(): array
    {
        return [
            'currency_code' => 'USD',
            'tax_rate' => null,
            'invoice_number_prefix' => 'INV',
            'next_invoice_sequence' => 1,
        ];
    }
}
