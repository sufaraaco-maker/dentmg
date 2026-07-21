<?php

namespace Database\Seeders;

use App\Models\DentalCondition;
use Illuminate\Database\Seeder;

class DentalConditionSeeder extends Seeder
{
    /**
     * Seed the clinic's default dental condition catalog.
     *
     * Matches the default set described in docs/modules/dental-chart-design-draft.md §24 — a
     * clinic cannot chart anything without at least one condition. `applies_to_surface` is true
     * only for the restorative/localized items (Caries, Composite Filling, Amalgam Filling,
     * Sealant); everything else is charted at the whole-tooth level.
     */
    public function run(): void
    {
        $conditions = [
            // Findings
            ['name' => 'Caries', 'category' => 'finding', 'applies_to_surface' => true, 'default_color' => '#DC2626', 'icon_key' => 'caries'],
            ['name' => 'Fracture', 'category' => 'finding', 'applies_to_surface' => false, 'default_color' => '#F97316', 'icon_key' => 'fracture'],
            ['name' => 'Missing Tooth', 'category' => 'finding', 'applies_to_surface' => false, 'default_color' => '#6B7280', 'icon_key' => 'missing'],
            ['name' => 'Impacted Tooth', 'category' => 'finding', 'applies_to_surface' => false, 'default_color' => '#A855F7', 'icon_key' => 'impacted'],

            // Procedures
            ['name' => 'Composite Filling', 'category' => 'procedure', 'applies_to_surface' => true, 'default_color' => '#2563EB', 'icon_key' => 'filling'],
            ['name' => 'Amalgam Filling', 'category' => 'procedure', 'applies_to_surface' => true, 'default_color' => '#0891B2', 'icon_key' => 'filling'],
            ['name' => 'Crown', 'category' => 'procedure', 'applies_to_surface' => false, 'default_color' => '#8B5CF6', 'icon_key' => 'crown'],
            ['name' => 'Root Canal Treatment', 'category' => 'procedure', 'applies_to_surface' => false, 'default_color' => '#EF4444', 'icon_key' => 'root_canal'],
            ['name' => 'Extraction', 'category' => 'procedure', 'applies_to_surface' => false, 'default_color' => '#78716C', 'icon_key' => 'extraction'],
            ['name' => 'Implant', 'category' => 'procedure', 'applies_to_surface' => false, 'default_color' => '#0D9488', 'icon_key' => 'implant'],
            ['name' => 'Sealant', 'category' => 'procedure', 'applies_to_surface' => true, 'default_color' => '#10B981', 'icon_key' => 'sealant'],
            ['name' => 'Bridge', 'category' => 'procedure', 'applies_to_surface' => false, 'default_color' => '#F59E0B', 'icon_key' => 'bridge'],
            ['name' => 'Veneer', 'category' => 'procedure', 'applies_to_surface' => false, 'default_color' => '#EC4899', 'icon_key' => 'veneer'],
        ];

        foreach ($conditions as $sortOrder => $condition) {
            DentalCondition::query()->firstOrCreate(
                ['name' => $condition['name']],
                $condition + ['is_active' => true, 'sort_order' => $sortOrder + 1]
            );
        }
    }
}
