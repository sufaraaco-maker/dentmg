<?php

namespace App\Services;

use App\Models\SupplyCategory;
use Illuminate\Support\Collection;

/**
 * Simple admin-managed catalog CRUD, mirrors AppointmentTypeService exactly (design doc §5/§11).
 */
class SupplyCategoryService
{
    /**
     * @return Collection<int, SupplyCategory>
     */
    public function list(): Collection
    {
        return SupplyCategory::query()->orderBy('sort_order')->orderBy('name')->get();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): SupplyCategory
    {
        $data['is_active'] ??= true;

        return SupplyCategory::create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(SupplyCategory $category, array $data): SupplyCategory
    {
        $category->update($data);

        return $category;
    }

    public function deactivate(SupplyCategory $category): void
    {
        $category->update(['is_active' => false]);
    }
}
