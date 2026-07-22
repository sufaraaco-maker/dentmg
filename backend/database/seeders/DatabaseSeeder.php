<?php

namespace Database\Seeders;

use App\Models\Patient;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     *
     * AppointmentTypeSeeder and DentalConditionSeeder both seed real reference data the app needs
     * to function (a clinic can't book an appointment without types, or chart without conditions)
     * and always run. The demo users/patients below use a known password ("password", Laravel's
     * factory default — see docs/demo-guide.md) and must never exist outside local/dev
     * environments — see docs/deployment.md "First Admin User" for the production equivalent
     * (`php artisan app:create-admin`).
     */
    public function run(): void
    {
        $this->call(AppointmentTypeSeeder::class);
        $this->call(DentalConditionSeeder::class);

        if (! app()->environment('local')) {
            return;
        }

        User::factory()->admin()->create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
        ]);

        User::factory()->dentist()->create([
            'name' => 'Dr. Sara Al-Amin',
            'email' => 'dentist@example.com',
        ]);

        User::factory()->create([
            'name' => 'Layla Front-Desk',
            'email' => 'receptionist@example.com',
        ]);

        Patient::factory()->count(8)->create();
    }
}
