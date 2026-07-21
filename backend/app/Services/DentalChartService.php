<?php

namespace App\Services;

use App\Enums\DentalChartEntryStatus;
use App\Exceptions\DentalChart\EntryLockedException;
use App\Exceptions\DentalChart\InvalidStatusTransitionException;
use App\Models\DentalChartEntry;
use App\Models\User;
use Illuminate\Support\Collection;

class DentalChartService
{
    /**
     * Backs both the odontogram and the list view (plan §1.7/§1.9) with a single query, filtered
     * by `status`, `tooth_number`, and the referenced condition's `category`.
     *
     * @param  array<string, mixed>  $filters
     * @return Collection<int, DentalChartEntry>
     */
    public function listForPatient(string $patientId, array $filters): Collection
    {
        return DentalChartEntry::query()
            ->with(['dentalCondition', 'dentist', 'createdBy', 'updatedBy'])
            ->forPatient($patientId)
            ->when(
                $filters['tooth_number'] ?? null,
                fn ($query, $toothNumber) => $query->forTooth($toothNumber)
            )
            ->when(
                $filters['status'] ?? null,
                fn ($query, $status) => $query->withStatus(DentalChartEntryStatus::from($status))
            )
            ->when(
                $filters['category'] ?? null,
                fn ($query, $category) => $query->whereHas(
                    'dentalCondition',
                    fn ($conditionQuery) => $conditionQuery->where('category', $category)
                )
            )
            ->orderByDesc('recorded_at')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $data  Must include `patient_id` (set by the controller from
     *                                      the nested route, plan §1.9).
     */
    public function create(array $data, User $actor): DentalChartEntry
    {
        return DentalChartEntry::create([
            'patient_id' => $data['patient_id'],
            'dental_condition_id' => $data['dental_condition_id'],
            'dentist_id' => $data['dentist_id'],
            'created_by_id' => $actor->id,
            'updated_by_id' => null,
            'tooth_number' => $data['tooth_number'],
            'surfaces' => $data['surfaces'] ?? null,
            'status' => $data['status'],
            'notes' => $data['notes'] ?? null,
            'recorded_at' => $data['recorded_at'] ?? now(),
        ]);
    }

    /**
     * Design draft §4: `completed`/`cancelled` entries are historical — every field except
     * `notes` is locked. Non-terminal entries accept the full editable field set.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws EntryLockedException if the entry is terminal and `$data` touches a locked field.
     */
    public function update(DentalChartEntry $entry, array $data, User $actor): DentalChartEntry
    {
        $isTerminal = in_array($entry->status, [DentalChartEntryStatus::Completed, DentalChartEntryStatus::Cancelled], true);

        if ($isTerminal && array_diff_key($data, ['notes' => null]) !== []) {
            throw new EntryLockedException;
        }

        $data['updated_by_id'] = $actor->id;

        $entry->update($data);

        return $entry;
    }

    public function complete(DentalChartEntry $entry, User $actor): DentalChartEntry
    {
        $this->assertCanTransitionTo($entry, DentalChartEntryStatus::Completed);

        $entry->status = DentalChartEntryStatus::Completed;
        $entry->completed_at = now();
        $entry->updated_by_id = $actor->id;
        $entry->save();

        return $entry;
    }

    public function cancel(DentalChartEntry $entry, User $actor): DentalChartEntry
    {
        $this->assertCanTransitionTo($entry, DentalChartEntryStatus::Cancelled);

        $entry->status = DentalChartEntryStatus::Cancelled;
        $entry->cancelled_at = now();
        $entry->updated_by_id = $actor->id;
        $entry->save();

        return $entry;
    }

    /**
     * Soft delete only — a data-correction action, gated admin-only in the Policy, not here
     * (design draft §4/§19).
     */
    public function delete(DentalChartEntry $entry): void
    {
        $entry->delete();
    }

    private function assertCanTransitionTo(DentalChartEntry $entry, DentalChartEntryStatus $to): void
    {
        if (! $entry->status->canTransitionTo($to)) {
            throw new InvalidStatusTransitionException($entry->status, $to);
        }
    }
}
