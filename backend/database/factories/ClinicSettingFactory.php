<?php

namespace Database\Factories;

use App\Models\ClinicSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClinicSetting>
 */
class ClinicSettingFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => $this->faker->company(),
            'phone' => $this->faker->phoneNumber(),
            'address' => $this->faker->address(),
            'email' => $this->faker->companyEmail(),
        ];
    }
}
