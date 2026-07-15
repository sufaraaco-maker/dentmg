<?php

namespace App\Services;

use App\Models\Patient;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class PatientService
{
    public function paginate(?string $search = null, int $perPage = 15): LengthAwarePaginator
    {
        return Patient::query()
            ->when($search, fn ($query) => $query->where(fn ($q) => $q
                ->whereLike('first_name', "%{$search}%")
                ->orWhereLike('last_name', "%{$search}%")
                ->orWhereLike('phone', "%{$search}%")
                ->orWhereLike('national_id', "%{$search}%")
                ->orWhereLike('email', "%{$search}%")
            ))
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->paginate($perPage);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Patient
    {
        return DB::transaction(function () use ($data) {
            $data['sequence_number'] = $this->nextSequenceNumber();

            return Patient::create($data);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Patient $patient, array $data): Patient
    {
        $patient->update($data);

        return $patient;
    }

    public function delete(Patient $patient): void
    {
        $patient->delete();
    }

    /**
     * Locks the highest-numbered row to avoid two concurrent registrations colliding on the
     * same patient_code. Postgres rejects `FOR UPDATE` combined with an aggregate (MAX), so this
     * orders + limits instead of aggregating. Portable across SQLite (tests) and PostgreSQL (dev/prod).
     */
    private function nextSequenceNumber(): int
    {
        $max = Patient::withTrashed()
            ->orderByDesc('sequence_number')
            ->lockForUpdate()
            ->value('sequence_number');

        return ((int) $max) + 1;
    }
}
