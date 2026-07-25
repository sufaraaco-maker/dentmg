<?php

namespace Database\Seeders;

use App\Enums\DentalConditionCategory;
use App\Enums\UserRole;
use App\Models\DentalCondition;
use App\Models\Patient;
use App\Models\TreatmentPlan;
use App\Models\User;
use App\Services\TreatmentPlanService;
use Illuminate\Database\Seeder;

/**
 * Demo Treatment Plan data — local/dev only, called from DatabaseSeeder's environment-gated block
 * alongside the demo patients (never runs outside `local`, mirroring every other demo-data block in
 * that seeder).
 *
 * Deliberately drives every scenario through `TreatmentPlanService` (the same entry point the API
 * layer uses) rather than inserting rows directly — that's what keeps this "production-safe": every
 * seeded plan/item obeys the exact state machine, cost-freeze, and cascade rules a real user's
 * actions would, so demo data can never model a state the app itself considers invalid. Six patients
 * (out of DatabaseSeeder's 8) each get one plan illustrating a different point in the lifecycle
 * (design doc §5/§19); no patient/user counts change, so this doesn't affect any other module's
 * seeded expectations.
 */
class TreatmentPlanSeeder extends Seeder
{
    public function __construct(private TreatmentPlanService $treatmentPlanService) {}

    public function run(): void
    {
        $dentist = User::query()->where('role', UserRole::Dentist)->first();
        $admin = User::query()->where('role', UserRole::Admin)->first();

        if (! $dentist || ! $admin) {
            // Demo users weren't seeded (e.g. this seeder was called standalone outside the
            // local-gated block) — nothing sensible to attach plans to.
            return;
        }

        $patients = Patient::query()->inRandomOrder()->take(6)->get();

        if ($patients->count() < 6) {
            return;
        }

        [$draftPatient, $presentedPatient, $inProgressPatient, $completedPatient, $cancelledPatient, $revisionPatient] = $patients->all();

        $this->seedDraftPlan($draftPatient, $dentist, $admin);
        $this->seedPresentedPlan($presentedPatient, $dentist, $admin);
        $this->seedInProgressPlan($inProgressPatient, $dentist, $admin);
        $this->seedCompletedPlan($completedPatient, $dentist, $admin);
        $this->seedCancelledPlan($cancelledPatient, $dentist, $admin);
        $this->seedSupersededRevision($revisionPatient, $dentist, $admin);
    }

    /**
     * Still being authored — every field (including item cost/procedure) stays freely editable.
     */
    private function seedDraftPlan(Patient $patient, User $dentist, User $admin): void
    {
        $plan = $this->treatmentPlanService->createPlan([
            'patient_id' => $patient->id,
            'dentist_id' => $dentist->id,
            'title' => 'Restorative Plan — Draft',
            'notes' => 'Awaiting final tooth selection before presenting to patient.',
        ], $admin);

        $this->addItem($plan, 'Composite Filling', $admin, ['tooth_number' => '16']);
        $this->addItem($plan, 'Crown', $admin, ['tooth_number' => '26', 'phase' => 2]);
    }

    /**
     * Presented to the patient — item cost/procedure snapshot is now frozen (design doc §5/§15 Q2).
     */
    private function seedPresentedPlan(Patient $patient, User $dentist, User $admin): void
    {
        $plan = $this->treatmentPlanService->createPlan([
            'patient_id' => $patient->id,
            'dentist_id' => $dentist->id,
            'title' => 'Endodontic Plan — Option A',
            'notes' => 'Presented at checkout; patient taking a week to decide.',
        ], $admin);

        $this->addItem($plan, 'Root Canal Treatment', $admin, ['tooth_number' => '36']);
        $this->addItem($plan, 'Crown', $admin, ['tooth_number' => '36', 'phase' => 2, 'notes' => 'Post-endodontic crown, once root canal heals.']);

        $this->treatmentPlanService->present($plan, $admin);
    }

    /**
     * Accepted and underway — one item already completed, the other still planned (illustrates the
     * plan-level vs. item-level lifecycle split, design doc §4/§5).
     */
    private function seedInProgressPlan(Patient $patient, User $dentist, User $admin): void
    {
        $plan = $this->treatmentPlanService->createPlan([
            'patient_id' => $patient->id,
            'dentist_id' => $dentist->id,
            'title' => 'Extraction + Implant Plan',
        ], $admin);

        $extraction = $this->addItem($plan, 'Extraction', $admin, ['tooth_number' => '48']);
        $this->addItem($plan, 'Implant', $admin, ['tooth_number' => '48', 'phase' => 2, 'notes' => 'Scheduled once extraction site has healed.']);

        $this->treatmentPlanService->present($plan, $admin);
        $this->treatmentPlanService->accept($plan, $admin);
        $this->treatmentPlanService->start($plan, $admin);
        $this->treatmentPlanService->completeItem($extraction, $admin);
    }

    /**
     * Fully delivered — every item completed, plan itself marked complete (only legal once no item
     * is still `planned`, design doc §8).
     */
    private function seedCompletedPlan(Patient $patient, User $dentist, User $admin): void
    {
        $plan = $this->treatmentPlanService->createPlan([
            'patient_id' => $patient->id,
            'dentist_id' => $dentist->id,
            'title' => 'Anterior Fillings',
        ], $admin);

        $left = $this->addItem($plan, 'Composite Filling', $admin, ['tooth_number' => '11']);
        $right = $this->addItem($plan, 'Composite Filling', $admin, ['tooth_number' => '21']);

        $this->treatmentPlanService->present($plan, $admin);
        $this->treatmentPlanService->accept($plan, $admin);
        $this->treatmentPlanService->start($plan, $admin);
        $this->treatmentPlanService->completeItem($left, $admin);
        $this->treatmentPlanService->completeItem($right, $admin);
        $this->treatmentPlanService->complete($plan, $admin);
    }

    /**
     * Abandoned after acceptance — `cancel()` cascades to cancel the still-planned item in the same
     * transaction (design doc §5/§8), so only the plan-level action is needed here.
     */
    private function seedCancelledPlan(Patient $patient, User $dentist, User $admin): void
    {
        $plan = $this->treatmentPlanService->createPlan([
            'patient_id' => $patient->id,
            'dentist_id' => $dentist->id,
            'title' => 'Bridge Plan',
            'notes' => 'Patient moved out of the area before treatment began.',
        ], $admin);

        $this->addItem($plan, 'Bridge', $admin, ['tooth_number' => '15']);

        $this->treatmentPlanService->present($plan, $admin);
        $this->treatmentPlanService->accept($plan, $admin);
        $this->treatmentPlanService->cancel($plan, $admin);
    }

    /**
     * Declined, then revised — `createSupersedingPlan()` clones the original's still-`planned`
     * items into a new `draft` plan and links the original forward via `superseded_by_plan_id`
     * (design doc §15 Q4).
     */
    private function seedSupersededRevision(Patient $patient, User $dentist, User $admin): void
    {
        $original = $this->treatmentPlanService->createPlan([
            'patient_id' => $patient->id,
            'dentist_id' => $dentist->id,
            'title' => 'Cosmetic Veneers — Option A',
        ], $admin);

        $this->addItem($original, 'Veneer', $admin, ['tooth_number' => '11']);
        $this->addItem($original, 'Veneer', $admin, ['tooth_number' => '21']);

        $this->treatmentPlanService->present($original, $admin);
        $this->treatmentPlanService->reject($original, $admin);

        $this->treatmentPlanService->createSupersedingPlan($original, [
            'title' => 'Cosmetic Veneers — Option B (Revised Estimate)',
            'notes' => 'Patient asked for a lower-cost alternative material.',
        ], $admin);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function addItem(TreatmentPlan $plan, string $procedureName, User $actor, array $overrides = [])
    {
        $condition = DentalCondition::query()
            ->where('name', $procedureName)
            ->where('category', DentalConditionCategory::Procedure)
            ->firstOrFail();

        return $this->treatmentPlanService->addItem($plan, [
            'dental_condition_id' => $condition->id,
            'quantity' => 1,
            'phase' => 1,
        ] + $overrides, $actor);
    }
}
