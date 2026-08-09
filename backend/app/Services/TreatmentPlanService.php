<?php

namespace App\Services;

use App\Enums\TreatmentPlanItemStatus;
use App\Enums\TreatmentPlanStatus;
use App\Events\PatientActivityOccurred;
use App\Exceptions\TreatmentPlan\InvalidTreatmentPlanItemStatusTransitionException;
use App\Exceptions\TreatmentPlan\InvalidTreatmentPlanStatusTransitionException;
use App\Exceptions\TreatmentPlan\TreatmentPlanHasOpenItemsException;
use App\Exceptions\TreatmentPlan\TreatmentPlanItemLockedException;
use App\Models\DentalCondition;
use App\Models\TreatmentPlan;
use App\Models\TreatmentPlanItem;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * One service for both TreatmentPlan and TreatmentPlanItem operations (design doc §20) — a
 * deliberate consolidation, not an oversight: item mutations routinely need to check parent-plan
 * state (the cost-freeze rule, §5/§8), so splitting into two services would just move that
 * coupling to call sites instead of removing it.
 */
class TreatmentPlanService
{
    /**
     * "Offer" fields locked once the parent plan leaves `draft` (design doc §6/§8, Decision 2) —
     * everything the patient was actually shown/quoted. `notes`, `appointment_id`,
     * `diagnosis_entry_id`, `phase`, and `sequence` are deliberately NOT in this list: they're
     * administrative/scheduling metadata, not part of the commercial offer itself.
     */
    private const OFFER_FIELDS = ['dental_condition_id', 'tooth_number', 'surfaces', 'quantity', 'unit_cost'];

    /**
     * Clinic-wide treatment plan list (mirrors `InvoiceService::paginateAll()`'s exact shape and
     * reasoning) — every other TreatmentPlan endpoint is deliberately patient-scoped and
     * unpaginated (a single patient's lifetime plan count stays small, per
     * `TreatmentPlanController::index()`'s own doc comment), but the Sidebar's Treatment Plans
     * entry needs a real destination across the whole practice, which does need paging.
     */
    public function paginateAll(?string $search = null, ?TreatmentPlanStatus $status = null, int $perPage = 15): LengthAwarePaginator
    {
        return TreatmentPlan::query()
            ->when($status, fn ($query) => $query->withStatus($status))
            ->when($search, fn ($query) => $query->where(fn ($q) => $q
                ->whereLike('title', "%{$search}%")
                ->orWhereHas('patient', fn ($p) => $p
                    ->whereLike('first_name', "%{$search}%")
                    ->orWhereLike('last_name', "%{$search}%")
                )
            ))
            ->with(['items', 'dentist', 'createdBy', 'patient'])
            ->latest()
            ->paginate($perPage);
    }

    /**
     * @param  array<string, mixed>  $data  Must include `patient_id` (set by the controller from
     *                                      the nested route, design doc §9).
     */
    public function createPlan(array $data, User $actor): TreatmentPlan
    {
        return TreatmentPlan::create([
            'patient_id' => $data['patient_id'],
            'dentist_id' => $data['dentist_id'],
            'created_by_id' => $actor->id,
            'title' => $data['title'] ?? null,
            'status' => TreatmentPlanStatus::Draft,
            'notes' => $data['notes'] ?? null,
        ]);
    }

    /**
     * Design doc §8: a plan's administrative fields (title/notes/dentist) may be corrected at any
     * non-terminal status — only the *items'* commercial-offer fields freeze at `presented`
     * (§5/§8, Decision 2). A terminal plan (`completed`/`rejected`/`cancelled`) rejects every edit,
     * mirroring the "can't edit a completed record's core fields" precedent used throughout this
     * codebase.
     *
     * @param  array<string, mixed>  $data
     */
    public function updatePlan(TreatmentPlan $plan, array $data, User $actor): TreatmentPlan
    {
        if ($plan->status->isTerminal()) {
            throw new TreatmentPlanItemLockedException('This treatment plan is closed and can no longer be edited.');
        }

        $plan->update($data);

        return $plan;
    }

    /**
     * @param  array<string, mixed>  $data  Must not include `procedure_name`/`procedure_description`
     *                                      (never client-writable — computed here from the
     *                                      resolved DentalCondition, design doc §6/§8, Decision 2).
     */
    public function addItem(TreatmentPlan $plan, array $data, User $actor): TreatmentPlanItem
    {
        // Items may only be added while the plan is still being drafted — once presented, the
        // item set itself is part of the commercial offer (Decision 2's spirit extended
        // consistently, not just its literal cost-field wording). A genuinely new procedure
        // discovered after presenting belongs on a superseding plan (createSupersedingPlan()
        // below), which already exists for exactly this kind of revision.
        if ($plan->status !== TreatmentPlanStatus::Draft) {
            throw new TreatmentPlanItemLockedException('Items can only be added while the treatment plan is still a draft.');
        }

        $condition = DentalCondition::findOrFail($data['dental_condition_id']);

        return TreatmentPlanItem::create([
            'treatment_plan_id' => $plan->id,
            'dental_condition_id' => $condition->id,
            'procedure_name' => $condition->name,
            'procedure_description' => $condition->description,
            'diagnosis_entry_id' => $data['diagnosis_entry_id'] ?? null,
            'tooth_number' => $data['tooth_number'] ?? null,
            'surfaces' => $data['surfaces'] ?? null,
            'quantity' => $data['quantity'] ?? 1,
            'unit_cost' => $data['unit_cost'] ?? $condition->default_cost,
            'phase' => $data['phase'] ?? 1,
            'sequence' => $data['sequence'] ?? null,
            'status' => TreatmentPlanItemStatus::Planned,
            'appointment_id' => $data['appointment_id'] ?? null,
            'notes' => $data['notes'] ?? null,
            'created_by_id' => $actor->id,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateItem(TreatmentPlanItem $item, array $data, User $actor): TreatmentPlanItem
    {
        if ($item->status->isTerminal()) {
            if (array_diff_key($data, ['notes' => null]) !== []) {
                throw new TreatmentPlanItemLockedException;
            }
        } elseif ($item->treatmentPlan->status !== TreatmentPlanStatus::Draft) {
            if (array_intersect_key($data, array_flip(self::OFFER_FIELDS)) !== []) {
                throw new TreatmentPlanItemLockedException;
            }
        }

        if (array_key_exists('dental_condition_id', $data)) {
            $condition = DentalCondition::findOrFail($data['dental_condition_id']);
            $data['procedure_name'] = $condition->name;
            $data['procedure_description'] = $condition->description;
            $data['unit_cost'] = $data['unit_cost'] ?? $condition->default_cost;
        }

        $data['updated_by_id'] = $actor->id;
        $item->update($data);

        return $item;
    }

    public function completeItem(TreatmentPlanItem $item, User $actor): TreatmentPlanItem
    {
        $this->assertItemCanTransitionTo($item, TreatmentPlanItemStatus::Completed);

        $item->status = TreatmentPlanItemStatus::Completed;
        $item->completed_at = now();
        $item->updated_by_id = $actor->id;
        $item->save();

        return $item;
    }

    public function cancelItem(TreatmentPlanItem $item, User $actor): TreatmentPlanItem
    {
        $this->assertItemCanTransitionTo($item, TreatmentPlanItemStatus::Cancelled);

        $item->status = TreatmentPlanItemStatus::Cancelled;
        $item->cancelled_at = now();
        $item->updated_by_id = $actor->id;
        $item->save();

        return $item;
    }

    /**
     * Soft delete only — a data-correction action, gated admin-only in the Policy, not here
     * (design doc §8, mirrors Dental Chart's identical delete-vs-cancel split).
     */
    public function deleteItem(TreatmentPlanItem $item): void
    {
        $item->delete();
    }

    public function present(TreatmentPlan $plan, User $actor): TreatmentPlan
    {
        $this->assertPlanCanTransitionTo($plan, TreatmentPlanStatus::Presented);

        $plan->status = TreatmentPlanStatus::Presented;
        $plan->presented_at = now();
        $plan->save();

        event(new PatientActivityOccurred(
            $plan, $actor, 'treatment_plan.presented', 'treatment_plans', "Treatment plan \"{$plan->title}\" presented",
        ));

        return $plan;
    }

    /**
     * Design doc §5/§8/§15 Q3 (Decision 3): accepting a plan automatically rejects every other
     * `presented` plan for the same patient, in the same transaction — the mechanism that
     * collapses "several presented alternatives" down to "one accepted plan."
     */
    public function accept(TreatmentPlan $plan, User $actor): TreatmentPlan
    {
        $this->assertPlanCanTransitionTo($plan, TreatmentPlanStatus::Accepted);

        DB::transaction(function () use ($plan) {
            $plan->status = TreatmentPlanStatus::Accepted;
            $plan->accepted_at = now();
            $plan->save();

            TreatmentPlan::query()
                ->where('patient_id', $plan->patient_id)
                ->where('id', '!=', $plan->id)
                ->where('status', TreatmentPlanStatus::Presented)
                ->get()
                ->each(function (TreatmentPlan $sibling) {
                    $sibling->status = TreatmentPlanStatus::Rejected;
                    $sibling->rejected_at = now();
                    $sibling->save();
                });
        });

        event(new PatientActivityOccurred(
            $plan, $actor, 'treatment_plan.accepted', 'treatment_plans', "Treatment plan \"{$plan->title}\" accepted",
        ));

        return $plan->fresh();
    }

    public function reject(TreatmentPlan $plan, User $actor): TreatmentPlan
    {
        $this->assertPlanCanTransitionTo($plan, TreatmentPlanStatus::Rejected);

        $plan->status = TreatmentPlanStatus::Rejected;
        $plan->rejected_at = now();
        $plan->save();

        event(new PatientActivityOccurred(
            $plan, $actor, 'treatment_plan.rejected', 'treatment_plans', "Treatment plan \"{$plan->title}\" rejected",
        ));

        return $plan;
    }

    public function start(TreatmentPlan $plan, User $actor): TreatmentPlan
    {
        $this->assertPlanCanTransitionTo($plan, TreatmentPlanStatus::InProgress);

        $plan->status = TreatmentPlanStatus::InProgress;
        $plan->started_at = now();
        $plan->save();

        return $plan;
    }

    /**
     * Design doc §5/§8: rejected (`422`) if any item is still `planned` — staff must complete or
     * cancel every item first. No "force complete" override for V1.
     */
    public function complete(TreatmentPlan $plan, User $actor): TreatmentPlan
    {
        $this->assertPlanCanTransitionTo($plan, TreatmentPlanStatus::Completed);

        if ($plan->items()->where('status', TreatmentPlanItemStatus::Planned)->exists()) {
            throw new TreatmentPlanHasOpenItemsException;
        }

        $plan->status = TreatmentPlanStatus::Completed;
        $plan->completed_at = now();
        $plan->save();

        event(new PatientActivityOccurred(
            $plan, $actor, 'treatment_plan.completed', 'treatment_plans', "Treatment plan \"{$plan->title}\" completed",
        ));

        return $plan;
    }

    /**
     * Design doc §5/§8: available from every non-terminal status, and cascades to cancel every
     * still-`planned` item in the same transaction — the only cross-entity cascade this module
     * performs (§7 explains why this one is safe while completion-sync between a
     * TreatmentPlanItem and its linked DentalChartEntry is deliberately not built).
     */
    public function cancel(TreatmentPlan $plan, User $actor): TreatmentPlan
    {
        $this->assertPlanCanTransitionTo($plan, TreatmentPlanStatus::Cancelled);

        DB::transaction(function () use ($plan, $actor) {
            $plan->status = TreatmentPlanStatus::Cancelled;
            $plan->cancelled_at = now();
            $plan->save();

            $plan->items()
                ->where('status', TreatmentPlanItemStatus::Planned)
                ->get()
                ->each(function (TreatmentPlanItem $item) use ($actor) {
                    $item->status = TreatmentPlanItemStatus::Cancelled;
                    $item->cancelled_at = now();
                    $item->updated_by_id = $actor->id;
                    $item->save();
                });
        });

        event(new PatientActivityOccurred(
            $plan, $actor, 'treatment_plan.cancelled', 'treatment_plans', "Treatment plan \"{$plan->title}\" cancelled",
        ));

        return $plan->fresh();
    }

    /**
     * Soft delete only — a data-correction action, gated admin-only in the Policy, not here.
     */
    public function deletePlan(TreatmentPlan $plan): void
    {
        $plan->delete();
    }

    /**
     * Revision support (design doc §15 Q4, Decision 4): creates a new `draft` plan cloning the
     * original's still-`planned` items (not `completed`/`cancelled` ones — already-done work is
     * historical fact on the original plan, not something a revision needs to re-quote), then
     * links the original plan forward via `superseded_by_plan_id`. The original plan and its items
     * are never modified or removed beyond that one link — "preserve historical records" means the
     * old plan stays exactly as it was, readable in full, not archived or rewritten.
     *
     * @param  array<string, mixed>  $overrides  Optional new `title`/`notes`/`dentist_id` for the
     *                                           revision; falls back to the original plan's own
     *                                           values for anything omitted.
     */
    public function createSupersedingPlan(TreatmentPlan $originalPlan, array $overrides, User $actor): TreatmentPlan
    {
        return DB::transaction(function () use ($originalPlan, $overrides, $actor) {
            $newPlan = TreatmentPlan::create([
                'patient_id' => $originalPlan->patient_id,
                'dentist_id' => $overrides['dentist_id'] ?? $originalPlan->dentist_id,
                'created_by_id' => $actor->id,
                'title' => $overrides['title'] ?? $originalPlan->title,
                'status' => TreatmentPlanStatus::Draft,
                'notes' => $overrides['notes'] ?? $originalPlan->notes,
            ]);

            $originalPlan->items()
                ->where('status', TreatmentPlanItemStatus::Planned)
                ->get()
                ->each(function (TreatmentPlanItem $oldItem) use ($newPlan, $actor) {
                    TreatmentPlanItem::create([
                        'treatment_plan_id' => $newPlan->id,
                        'dental_condition_id' => $oldItem->dental_condition_id,
                        'procedure_name' => $oldItem->procedure_name,
                        'procedure_description' => $oldItem->procedure_description,
                        'diagnosis_entry_id' => $oldItem->diagnosis_entry_id,
                        'tooth_number' => $oldItem->tooth_number,
                        'surfaces' => $oldItem->surfaces,
                        'quantity' => $oldItem->quantity,
                        'unit_cost' => $oldItem->unit_cost,
                        'phase' => $oldItem->phase,
                        'sequence' => $oldItem->sequence,
                        'status' => TreatmentPlanItemStatus::Planned,
                        // appointment_id deliberately not carried over — scheduling is redone
                        // against the new plan, not inherited from the superseded one.
                        'notes' => $oldItem->notes,
                        'created_by_id' => $actor->id,
                    ]);
                });

            $originalPlan->update(['superseded_by_plan_id' => $newPlan->id]);

            return $newPlan;
        });
    }

    /**
     * Plan-level cost roll-up (design doc §6/§13): `SUM(unit_cost * quantity)` over the plan's
     * non-cancelled items, computed as a single query — never stored/cached (§6's "don't store
     * derivable state" principle, extended from the item-level `estimated_cost` accessor to the
     * plan-level total).
     */
    public function estimatedCost(TreatmentPlan $plan): string
    {
        $total = $plan->items()
            ->where('status', '!=', TreatmentPlanItemStatus::Cancelled)
            ->selectRaw('COALESCE(SUM(unit_cost * quantity), 0) as total')
            ->value('total');

        return number_format((float) $total, 2, '.', '');
    }

    private function assertPlanCanTransitionTo(TreatmentPlan $plan, TreatmentPlanStatus $to): void
    {
        if (! $plan->status->canTransitionTo($to)) {
            throw new InvalidTreatmentPlanStatusTransitionException($plan->status, $to);
        }
    }

    private function assertItemCanTransitionTo(TreatmentPlanItem $item, TreatmentPlanItemStatus $to): void
    {
        if (! $item->status->canTransitionTo($to)) {
            throw new InvalidTreatmentPlanItemStatusTransitionException($item->status, $to);
        }
    }
}
