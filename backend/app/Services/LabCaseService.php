<?php

namespace App\Services;

use App\Enums\LabCaseStatus;
use App\Events\PatientActivityOccurred;
use App\Exceptions\Laboratory\InvalidLabCaseOperationException;
use App\Models\LabCase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class LabCaseService
{
    private const EDITABLE_FIELDS = [
        'lab_id', 'dentist_id', 'treatment_plan_item_id', 'appointment_id',
        'tooth_numbers', 'case_type', 'shade', 'material', 'instructions',
        'fee', 'tracking_number', 'due_at',
    ];

    /**
     * @param  array<string, mixed>  $data  Must include `patient_id`, `lab_id`.
     */
    public function create(array $data): LabCase
    {
        return DB::transaction(function () use ($data) {
            $sequence = $this->nextSequenceNumber();

            return LabCase::create([
                'sequence_number' => $sequence,
                'case_number' => 'LC-'.str_pad((string) $sequence, 6, '0', STR_PAD_LEFT),
                'patient_id' => $data['patient_id'],
                'lab_id' => $data['lab_id'],
                'dentist_id' => $data['dentist_id'] ?? null,
                'treatment_plan_item_id' => $data['treatment_plan_item_id'] ?? null,
                'appointment_id' => $data['appointment_id'] ?? null,
                'tooth_numbers' => $data['tooth_numbers'] ?? null,
                'case_type' => $data['case_type'] ?? null,
                'shade' => $data['shade'] ?? null,
                'material' => $data['material'] ?? null,
                'instructions' => $data['instructions'] ?? null,
                'fee' => $data['fee'] ?? null,
                'tracking_number' => $data['tracking_number'] ?? null,
                'status' => LabCaseStatus::Draft,
            ]);
        });
    }

    /**
     * Editable only while draft — same centralized-lock discipline as
     * PurchaseOrderService::update(). `patient_id`/`case_number`/`sequence_number` never change
     * after creation, matching every prior module's "the party of a record is immutable" rule.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(LabCase $labCase, array $data): LabCase
    {
        $this->assertDraft($labCase);

        $labCase->update(array_intersect_key($data, array_flip(self::EDITABLE_FIELDS)));

        return $labCase;
    }

    /**
     * Design doc §4: the only user-chosen draft -> sent transition. Auto-suggests `due_at` from the
     * lab's `default_turnaround_days` unless already manually set (Open Dental's auto-due-date
     * pattern) — never overwrites a value the user already entered.
     */
    public function send(LabCase $labCase): LabCase
    {
        return DB::transaction(function () use ($labCase) {
            $locked = LabCase::query()->whereKey($labCase->id)->lockForUpdate()->firstOrFail();

            $this->assertTransition($locked->status, LabCaseStatus::Sent);

            $locked->status = LabCaseStatus::Sent;
            $locked->sent_at = now();

            if ($locked->due_at === null) {
                $turnaroundDays = $locked->lab()->value('default_turnaround_days');

                if ($turnaroundDays !== null) {
                    $locked->due_at = now()->addDays($turnaroundDays);
                }
            }

            $locked->save();

            $fresh = $locked->fresh();

            event(new PatientActivityOccurred(
                $fresh, Auth::user(), 'lab_case.sent', 'laboratory', "Lab case {$fresh->case_number} sent",
            ));

            return $fresh;
        });
    }

    /**
     * Design doc §4: sent -> received. The case is physically back from the lab, closing off
     * cancel() from this point forward (mirrors PurchaseOrder's "blocked once any item has a real
     * receipt" rule).
     */
    public function receive(LabCase $labCase): LabCase
    {
        return DB::transaction(function () use ($labCase) {
            $locked = LabCase::query()->whereKey($labCase->id)->lockForUpdate()->firstOrFail();

            $this->assertTransition($locked->status, LabCaseStatus::Received);

            $locked->status = LabCaseStatus::Received;
            $locked->received_at = now();
            $locked->save();

            $fresh = $locked->fresh();

            event(new PatientActivityOccurred(
                $fresh, Auth::user(), 'lab_case.received', 'laboratory', "Lab case {$fresh->case_number} received",
            ));

            return $fresh;
        });
    }

    /**
     * Design doc §4: received -> quality_checked — terminal, "ready to deliver to the patient".
     */
    public function qualityCheck(LabCase $labCase): LabCase
    {
        return DB::transaction(function () use ($labCase) {
            $locked = LabCase::query()->whereKey($labCase->id)->lockForUpdate()->firstOrFail();

            $this->assertTransition($locked->status, LabCaseStatus::QualityChecked);

            $locked->status = LabCaseStatus::QualityChecked;
            $locked->quality_checked_at = now();
            $locked->save();

            $fresh = $locked->fresh();

            event(new PatientActivityOccurred(
                $fresh, Auth::user(), 'lab_case.quality_checked', 'laboratory',
                "Lab case {$fresh->case_number} passed quality check",
            ));

            return $fresh;
        });
    }

    /**
     * Design doc §4: reachable only from draft/sent, and only while `received_at` is still null —
     * a case already physically back from the lab can't be silently cancelled (a remake, if needed,
     * is a new case per the design doc's explicit V1 scope boundary).
     */
    public function cancel(LabCase $labCase): LabCase
    {
        return DB::transaction(function () use ($labCase) {
            $locked = LabCase::query()->whereKey($labCase->id)->lockForUpdate()->firstOrFail();

            $this->assertTransition($locked->status, LabCaseStatus::Cancelled);

            if ($locked->received_at !== null) {
                throw new InvalidLabCaseOperationException('A case already received from the lab cannot be cancelled.');
            }

            $locked->status = LabCaseStatus::Cancelled;
            $locked->cancelled_at = now();
            $locked->save();

            return $locked;
        });
    }

    /**
     * Soft delete — draft-only (design doc §5), mirrors PurchaseOrderService::delete()'s identical
     * draft-only guard.
     */
    public function delete(LabCase $labCase): void
    {
        $this->assertDraft($labCase);

        $labCase->delete();
    }

    private function assertDraft(LabCase $labCase): void
    {
        if ($labCase->status !== LabCaseStatus::Draft) {
            throw new InvalidLabCaseOperationException('This case can no longer be edited once sent.');
        }
    }

    private function assertTransition(LabCaseStatus $from, LabCaseStatus $to): void
    {
        if (! $from->canTransitionTo($to)) {
            throw new InvalidLabCaseOperationException("Cannot move a case from {$from->value} to {$to->value}.");
        }
    }

    /**
     * Locks the highest-numbered row to avoid two concurrent cases colliding on the same
     * case_number — mirrors PurchaseOrderService::nextSequenceNumber()'s identical pattern.
     * Postgres rejects `FOR UPDATE` combined with an aggregate (MAX), so this orders + limits
     * instead.
     */
    private function nextSequenceNumber(): int
    {
        $max = LabCase::withTrashed()
            ->orderByDesc('sequence_number')
            ->lockForUpdate()
            ->value('sequence_number');

        return ((int) $max) + 1;
    }
}
