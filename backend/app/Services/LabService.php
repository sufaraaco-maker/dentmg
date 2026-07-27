<?php

namespace App\Services;

use App\Models\Lab;
use Illuminate\Support\Collection;

/**
 * Simple admin-managed catalog CRUD, mirrors SupplierService exactly (design doc §3/§6).
 */
class LabService
{
    /**
     * Not paginated — a small clinic-configured lookup table consumed as a dropdown (same
     * deliberate exemption as SupplierService::list()).
     *
     * @return Collection<int, Lab>
     */
    public function list(): Collection
    {
        return Lab::query()->orderBy('name')->get();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Lab
    {
        $data['is_active'] ??= true;

        return Lab::create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Lab $lab, array $data): Lab
    {
        $lab->update($data);

        return $lab;
    }

    /**
     * Soft-disable, not a delete (design doc §3a) — deactivating hides it from new-case pickers
     * without breaking historical FK references from Lab Cases.
     */
    public function deactivate(Lab $lab): void
    {
        $lab->update(['is_active' => false]);
    }
}
