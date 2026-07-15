<?php

namespace Database\Seeders;

use App\Models\AppointmentType;
use Illuminate\Database\Seeder;

class AppointmentTypeSeeder extends Seeder
{
    /**
     * Seed the clinic's default appointment types.
     *
     * Matches the default set described in docs/modules/appointments-design-draft.md §4 —
     * a clinic cannot book an appointment without at least one active type.
     */
    public function run(): void
    {
        $types = [
            ['name' => 'Consultation', 'default_duration_minutes' => 30, 'color' => '#3B82F6'],
            ['name' => 'Cleaning', 'default_duration_minutes' => 45, 'color' => '#10B981'],
            ['name' => 'Filling', 'default_duration_minutes' => 45, 'color' => '#F59E0B'],
            ['name' => 'Root Canal', 'default_duration_minutes' => 90, 'color' => '#EF4444'],
            ['name' => 'Crown', 'default_duration_minutes' => 60, 'color' => '#8B5CF6'],
            ['name' => 'Extraction', 'default_duration_minutes' => 30, 'color' => '#F97316'],
        ];

        foreach ($types as $type) {
            AppointmentType::query()->firstOrCreate(
                ['name' => $type['name']],
                $type + ['is_active' => true]
            );
        }
    }
}
