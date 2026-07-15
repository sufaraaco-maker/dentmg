<?php

namespace App\Services;

use App\Models\DentistWorkingHour;
use App\Models\User;
use Illuminate\Support\Collection;

class DentistWorkingHourService
{
    /**
     * @return Collection<int, DentistWorkingHour>
     */
    public function listForDentist(string $dentistId): Collection
    {
        return DentistWorkingHour::query()->forDentist($dentistId)->get();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $dentist, array $data): DentistWorkingHour
    {
        $data['user_id'] = $dentist->id;

        return DentistWorkingHour::create($data);
    }

    public function delete(DentistWorkingHour $workingHour): void
    {
        $workingHour->delete();
    }
}
