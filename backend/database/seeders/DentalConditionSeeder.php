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
     *
     * `default_cost`/`description` (procedures only) were added by the Treatment Plans module's
     * migration (`docs/modules/treatment-plans-design.md` §6/§21 Step 4) — a procedure's
     * `default_cost` prefills a new `treatment_plan_items.unit_cost` (still nullable/editable per
     * item, §8's "no fee" precedent); findings are never priced, so they're left `null`.
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
            ['name' => 'Composite Filling', 'category' => 'procedure', 'applies_to_surface' => true, 'default_color' => '#2563EB', 'icon_key' => 'filling', 'default_cost' => 150.00, 'description' => 'A tooth-colored resin filling used to repair a cavity.'],
            ['name' => 'Amalgam Filling', 'category' => 'procedure', 'applies_to_surface' => true, 'default_color' => '#0891B2', 'icon_key' => 'filling', 'default_cost' => 120.00, 'description' => 'A durable silver-colored filling for back-tooth cavities.'],
            ['name' => 'Crown', 'category' => 'procedure', 'applies_to_surface' => false, 'default_color' => '#8B5CF6', 'icon_key' => 'crown', 'default_cost' => 800.00, 'description' => 'A full-coverage cap that restores a damaged or weakened tooth.'],
            ['name' => 'Root Canal Treatment', 'category' => 'procedure', 'applies_to_surface' => false, 'default_color' => '#EF4444', 'icon_key' => 'root_canal', 'default_cost' => 650.00, 'description' => 'Removes infected pulp to save a tooth from extraction.'],
            ['name' => 'Extraction', 'category' => 'procedure', 'applies_to_surface' => false, 'default_color' => '#78716C', 'icon_key' => 'extraction', 'default_cost' => 200.00, 'description' => 'Removal of a tooth that cannot be restored.'],
            ['name' => 'Implant', 'category' => 'procedure', 'applies_to_surface' => false, 'default_color' => '#0D9488', 'icon_key' => 'implant', 'default_cost' => 1800.00, 'description' => 'A titanium post surgically placed to replace a missing tooth root.'],
            ['name' => 'Sealant', 'category' => 'procedure', 'applies_to_surface' => true, 'default_color' => '#10B981', 'icon_key' => 'sealant', 'default_cost' => 60.00, 'description' => 'A protective coating applied to chewing surfaces to prevent decay.'],
            ['name' => 'Bridge', 'category' => 'procedure', 'applies_to_surface' => false, 'default_color' => '#F59E0B', 'icon_key' => 'bridge', 'default_cost' => 1200.00, 'description' => 'A fixed prosthetic that spans a gap left by one or more missing teeth.'],
            ['name' => 'Veneer', 'category' => 'procedure', 'applies_to_surface' => false, 'default_color' => '#EC4899', 'icon_key' => 'veneer', 'default_cost' => 950.00, 'description' => 'A thin custom shell bonded to the front of a tooth to improve appearance.'],
        ];

        foreach ($conditions as $sortOrder => $condition) {
            DentalCondition::query()->firstOrCreate(
                ['name' => $condition['name']],
                $condition + ['is_active' => true, 'sort_order' => $sortOrder + 1]
            );
        }
    }
}
