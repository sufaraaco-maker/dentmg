<?php

namespace App\Services;

use App\Models\DentalCondition;
use Illuminate\Support\Collection;

class DentalConditionService
{
    /**
     * Returns every condition (active or not), ordered by `sort_order` then `name` — the
     * catalog dropdown needs a stable, curator-controlled order, not alphabetical-only, mirroring
     * `AppointmentTypeService::list()`'s deliberately-unpaginated small-lookup-table exemption.
     *
     * @return Collection<int, DentalCondition>
     */
    public function list(): Collection
    {
        return DentalCondition::query()->orderBy('sort_order')->orderBy('name')->get();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): DentalCondition
    {
        // Explicit default, not left to the DB column default, for the same reason as
        // `AppointmentTypeService::create()`: Eloquent doesn't refetch DB-applied defaults after
        // an insert.
        $data['is_active'] ??= true;

        return DentalCondition::create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(DentalCondition $condition, array $data): DentalCondition
    {
        $condition->update($data);

        return $condition;
    }

    /**
     * Hard delete, mirroring `AppointmentTypeService::delete()`'s actual (not documented)
     * behavior exactly (plan §1.9: "verify at implementation time and mirror it precisely").
     * `dental_chart_entries.dental_condition_id` is `restrictOnDelete`, so deleting a
     * still-referenced condition surfaces as an uncaught QueryException — same accepted gap as
     * the AppointmentType precedent.
     */
    public function delete(DentalCondition $condition): void
    {
        $condition->delete();
    }
}
