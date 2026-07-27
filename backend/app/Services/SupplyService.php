<?php

namespace App\Services;

use App\Models\Supply;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class SupplyService
{
    /**
     * Paginated — unlike the small Supplier/Category lookups, a clinic's Supply catalog can
     * realistically grow into the hundreds (design doc §13), so this is a real paginated list, not
     * a dropdown-style full fetch.
     */
    public function paginate(?string $search = null, ?string $categoryId = null, bool $lowStockOnly = false, int $perPage = 20): LengthAwarePaginator
    {
        return Supply::query()
            ->with(['category', 'defaultSupplier'])
            ->when($lowStockOnly, fn ($query) => $query->lowStock(), fn ($query) => $query->withQuantityOnHand())
            ->when($search, fn ($query) => $query->where(fn ($q) => $q
                ->whereLike('supplies.name', "%{$search}%")
                ->orWhereLike('supplies.sku', "%{$search}%")
            ))
            ->when($categoryId, fn ($query) => $query->where('supplies.category_id', $categoryId))
            ->when(! $lowStockOnly, fn ($query) => $query->orderBy('supplies.name'))
            ->paginate($perPage);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Supply
    {
        $data['is_active'] ??= true;

        return Supply::create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Supply $supply, array $data): Supply
    {
        $supply->update($data);

        return $supply;
    }

    /**
     * Soft-disable, not a delete (design doc §5) — a Supply referenced by historical Purchase
     * Order items / Stock Movements keeps a valid FK.
     */
    public function deactivate(Supply $supply): void
    {
        $supply->update(['is_active' => false]);
    }
}
