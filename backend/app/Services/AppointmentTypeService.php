<?php

namespace App\Services;

use App\Models\AppointmentType;
use Illuminate\Support\Collection;

class AppointmentTypeService
{
    /**
     * Returns every type (active or not), ordered by name — deliberately not paginated. This
     * is a small clinic-configured lookup table consumed as a dropdown in the booking UI, and
     * paginating it would break that UX (same category of deliberate exemption as the
     * date-range-bounded appointments list in `AppointmentService::search()`).
     *
     * @return Collection<int, AppointmentType>
     */
    public function list(): Collection
    {
        return AppointmentType::query()->orderBy('name')->get();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): AppointmentType
    {
        // Explicit default, not left to the DB column default (`is_active` boolean default
        // true): Eloquent doesn't refetch DB-applied defaults after an insert, so an omitted
        // `is_active` would otherwise leave the in-memory (and therefore API response) model
        // attribute null even though the row itself is true.
        $data['is_active'] ??= true;

        return AppointmentType::create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(AppointmentType $type, array $data): AppointmentType
    {
        $type->update($data);

        return $type;
    }

    public function delete(AppointmentType $type): void
    {
        $type->delete();
    }
}
