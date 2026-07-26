<?php

namespace App\Services;

use App\Models\Supplier;
use Illuminate\Support\Collection;

/**
 * Simple admin-managed catalog CRUD, mirrors AppointmentTypeService exactly (design doc §5/§11).
 */
class SupplierService
{
    /**
     * Not paginated — a small clinic-configured lookup table consumed as a dropdown (same
     * deliberate exemption as AppointmentTypeService::list()).
     *
     * @return Collection<int, Supplier>
     */
    public function list(): Collection
    {
        return Supplier::query()->orderBy('name')->get();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Supplier
    {
        $data['is_active'] ??= true;

        return Supplier::create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Supplier $supplier, array $data): Supplier
    {
        $supplier->update($data);

        return $supplier;
    }

    /**
     * Soft-disable, not a delete (design doc §5) — deactivating hides it from new-order pickers
     * without breaking historical FK references from Supplies/Purchase Orders.
     */
    public function deactivate(Supplier $supplier): void
    {
        $supplier->update(['is_active' => false]);
    }
}
