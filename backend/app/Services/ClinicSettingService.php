<?php

namespace App\Services;

use App\Models\ClinicSetting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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

    /**
     * Always the `public` disk (never `config('filesystems.default')`, unlike PatientImageService)
     * — a clinic logo must render as a plain <img> with no authenticated round trip, so it can
     * never live on the auth-gated `local` disk patient images/documents use.
     */
    public function updateLogo(UploadedFile $file): ClinicSetting
    {
        $settings = $this->current();

        $this->deleteLogoFile($settings);

        $extension = strtolower($file->extension() ?: $file->guessExtension() ?: 'jpg');
        $path = 'clinic/logo-'.Str::uuid().'.'.$extension;

        Storage::disk('public')->put($path, file_get_contents($file->getRealPath()));

        $settings->update(['logo_disk' => 'public', 'logo_path' => $path]);

        return $settings;
    }

    public function removeLogo(): ClinicSetting
    {
        $settings = $this->current();

        $this->deleteLogoFile($settings);
        $settings->update(['logo_disk' => null, 'logo_path' => null]);

        return $settings;
    }

    private function deleteLogoFile(ClinicSetting $settings): void
    {
        if ($settings->logo_disk && $settings->logo_path) {
            Storage::disk($settings->logo_disk)->delete($settings->logo_path);
        }
    }
}
