<?php

namespace App\Services;

use App\Models\DentistTimeOff;
use App\Models\User;
use Illuminate\Support\Collection;

class DentistTimeOffService
{
    /**
     * @return Collection<int, DentistTimeOff>
     */
    public function listForDentist(string $dentistId): Collection
    {
        return DentistTimeOff::query()->forDentist($dentistId)->get();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $dentist, array $data): DentistTimeOff
    {
        $data['user_id'] = $dentist->id;

        return DentistTimeOff::create($data);
    }

    public function delete(DentistTimeOff $timeOff): void
    {
        $timeOff->delete();
    }
}
