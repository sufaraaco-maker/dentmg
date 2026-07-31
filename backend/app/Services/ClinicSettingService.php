<?php

namespace App\Services;

use App\Models\ClinicSetting;

/**
 * A singleton settings service (design doc §6): exactly one row ever exists. `current()`
 * self-heals a blank stub row on first access, mirroring `InvoiceService::reserveNextSequenceNumber()`'s
 * own self-heal precedent for `BillingSetting` — no seeder/migration-time row required.
 */
class ClinicSettingService
{
    public function current(): ClinicSetting
    {
        // The two AI Assistant flags are spelled out explicitly (not left to the DB column
        // default) so the in-memory model returned by a fresh create() reflects `false` right
        // away — Eloquent doesn't re-hydrate DB-computed defaults for attributes it didn't itself
        // set on insert, so relying on the migration's ->default(false) alone would leave these
        // `null` in PHP on the very request that self-heals the stub row.
        return ClinicSetting::query()->firstOrCreate([], [
            'name' => '',
            'ai_assistant_enabled' => false,
            'ai_assistant_phi_features_acknowledged' => false,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(array $data): ClinicSetting
    {
        $settings = $this->current();
        $settings->update($data);

        return $settings;
    }
}
