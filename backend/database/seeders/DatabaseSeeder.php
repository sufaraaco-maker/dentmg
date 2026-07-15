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
     * Demo accounts below all share the password "password" (Laravel's default factory
     * password) — documented for reviewers in docs/demo-guide.md, not a production credential.
     */
    public function run(): void
    {
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

        $this->call(AppointmentTypeSeeder::class);
    }
}
