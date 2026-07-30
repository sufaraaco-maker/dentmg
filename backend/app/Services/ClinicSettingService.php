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
        return ClinicSetting::query()->firstOrCreate([], ['name' => '']);
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
