<?php

namespace Tests\Unit\Services;

use App\Enums\TreatmentPlanItemStatus;
use App\Enums\TreatmentPlanStatus;
use App\Exceptions\TreatmentPlan\InvalidTreatmentPlanItemStatusTransitionException;
use App\Exceptions\TreatmentPlan\InvalidTreatmentPlanStatusTransitionException;
use App\Exceptions\TreatmentPlan\TreatmentPlanHasOpenItemsException;
use App\Exceptions\TreatmentPlan\TreatmentPlanItemLockedException;
use App\Models\Appointment;
use App\Models\DentalCondition;
use App\Models\Patient;
use App\Models\TreatmentPlan;
use App\Models\TreatmentPlanItem;
use App\Models\User;
use App\Services\TreatmentPlanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TreatmentPlanServiceTest extends TestCase
{
    use RefreshDatabase;

    private TreatmentPlanService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new TreatmentPlanService;
    }

    // --- createPlan --------------------------------------------------------------------------

    public function test_create_plan_always_starts_draft_and_sets_created_by_id(): void
    {
        $patient = Patient::factory()->create();
        $dentist = User::factory()->dentist()->create();
        $admin = User::factory()->admin()->create();

        $plan = $this->service->createPlan([
            'patient_id' => $patient->id,
            'dentist_id' => $dentist->id,
            'title' => 'Option A',
        ], $admin);

        $this->assertSame(TreatmentPlanStatus::Draft, $plan->status);
        $this->assertSame($admin->id, $plan->created_by_id);
        $this->assertSame($patient->id, $plan->patient_id);
        $this->assertNull($plan->presented_at);
    }

    // --- updatePlan ----------------------------------------------------------------------------

    public function test_update_plan_edits_administrative_fields(): void
    {
        $plan = TreatmentPlan::factory()->create(['title' => 'Old title']);
        $editor = User::factory()->dentist()->create();

        $updated = $this->service->updatePlan($plan, ['title' => 'New title'], $editor);

        $this->assertSame('New title', $updated->title);
    }

    public function test_update_plan_rejects_edits_to_a_terminal_plan(): void
    {
        $plan = TreatmentPlan::factory()->completed()->create();
        $editor = User::factory()->dentist()->create();

        $this->expectException(TreatmentPlanItemLockedException::class);

        $this->service->updatePlan($plan, ['title' => 'New title'], $editor);
    }

    // --- addItem -------------------------------------------------------------------------------

    public function test_add_item_snapshots_procedure_name_and_description_from_the_catalog(): void
    {
        $plan = TreatmentPlan::factory()->create();
        $condition = DentalCondition::factory()->procedure()->create([
            'name' => 'Composite Filling',
            'description' => 'A tooth-colored resin filling.',
            'default_cost' => 150,
            'applies_to_surface' => false,
        ]);
        $actor = User::factory()->dentist()->create();

        $item = $this->service->addItem($plan, [
            'dental_condition_id' => $condition->id,
            'tooth_number' => '16',
        ], $actor);

        $this->assertSame('Composite Filling', $item->procedure_name);
        $this->assertSame('A tooth-colored resin filling.', $item->procedure_description);
        $this->assertSame('150.00', $item->unit_cost);
        $this->assertSame(TreatmentPlanItemStatus::Planned, $item->status);
        $this->assertSame($actor->id, $item->created_by_id);
    }

    public function test_add_item_uses_an_explicit_unit_cost_override_instead_of_the_catalog_default(): void
    {
        $plan = TreatmentPlan::factory()->create();
        $condition = DentalCondition::factory()->procedure()->create(['default_cost' => 150]);
        $actor = User::factory()->dentist()->create();

        $item = $this->service->addItem($plan, [
            'dental_condition_id' => $condition->id,
            'unit_cost' => 300,
        ], $actor);

        $this->assertSame('300.00', $item->unit_cost);
    }

    public function test_add_item_rejects_when_the_plan_is_no_longer_draft(): void
    {
        $plan = TreatmentPlan::factory()->presented()->create();
        $condition = DentalCondition::factory()->procedure()->create();
        $actor = User::factory()->dentist()->create();

        $this->expectException(TreatmentPlanItemLockedException::class);

        $this->service->addItem($plan, ['dental_condition_id' => $condition->id], $actor);
    }

    // --- updateItem ------------------------------------------------------------------------------

    public function test_update_item_sets_updated_by_id(): void
    {
        $plan = TreatmentPlan::factory()->create();
        $item = TreatmentPlanItem::factory()->create(['treatment_plan_id' => $plan->id]);
        $editor = User::factory()->dentist()->create();

        $updated = $this->service->updateItem($item, ['notes' => 'Patient prefers morning slots'], $editor);

        $this->assertSame($editor->id, $updated->updated_by_id);
        $this->assertSame('Patient prefers morning slots', $updated->notes);
    }

    public function test_update_item_recomputes_the_snapshot_when_the_condition_changes_while_draft(): void
    {
        $plan = TreatmentPlan::factory()->create();
        $originalCondition = DentalCondition::factory()->procedure()->create(['applies_to_surface' => false]);
        $item = TreatmentPlanItem::factory()->create([
            'treatment_plan_id' => $plan->id,
            'dental_condition_id' => $originalCondition->id,
        ]);
        $newCondition = DentalCondition::factory()->procedure()->create([
            'name' => 'Crown',
            'description' => 'A full-coverage restoration.',
            'default_cost' => 800,
            'applies_to_surface' => false,
        ]);
        $editor = User::factory()->dentist()->create();

        $updated = $this->service->updateItem($item, ['dental_condition_id' => $newCondition->id], $editor);

        $this->assertSame('Crown', $updated->procedure_name);
        $this->assertSame('A full-coverage restoration.', $updated->procedure_description);
        $this->assertSame('800.00', $updated->unit_cost);
    }

    public function test_update_item_rejects_offer_field_edits_once_the_plan_is_no_longer_draft(): void
    {
        $plan = TreatmentPlan::factory()->presented()->create();
        $item = TreatmentPlanItem::factory()->create(['treatment_plan_id' => $plan->id, 'unit_cost' => 100]);
        $editor = User::factory()->dentist()->create();

        $this->expectException(TreatmentPlanItemLockedException::class);

        $this->service->updateItem($item, ['unit_cost' => 200], $editor);
    }

    public function test_update_item_still_allows_notes_and_appointment_id_once_the_plan_is_no_longer_draft(): void
    {
        $plan = TreatmentPlan::factory()->accepted()->create();
        $item = TreatmentPlanItem::factory()->create(['treatment_plan_id' => $plan->id]);
        $appointment = Appointment::factory()->create(['patient_id' => $plan->patient_id]);
        $editor = User::factory()->dentist()->create();

        $updated = $this->service->updateItem($item, [
            'notes' => 'Scheduled for next Tuesday',
            'appointment_id' => $appointment->id,
        ], $editor);

        $this->assertSame('Scheduled for next Tuesday', $updated->notes);
        $this->assertSame($appointment->id, $updated->appointment_id);
    }

    public function test_update_item_rejects_any_field_but_notes_on_a_terminal_item(): void
    {
        $item = TreatmentPlanItem::factory()->completed()->create();
        $editor = User::factory()->dentist()->create();

        $this->expectException(TreatmentPlanItemLockedException::class);

        $this->service->updateItem($item, ['quantity' => 2], $editor);
    }

    public function test_update_item_still_allows_notes_on_a_terminal_item(): void
    {
        $item = TreatmentPlanItem::factory()->cancelled()->create();
        $editor = User::factory()->dentist()->create();

        $updated = $this->service->updateItem($item, ['notes' => 'Patient declined'], $editor);

        $this->assertSame('Patient declined', $updated->notes);
    }

    // --- completeItem / cancelItem -----------------------------------------------------------

    public function test_complete_item_transitions_a_planned_item(): void
    {
        $item = TreatmentPlanItem::factory()->create(['status' => TreatmentPlanItemStatus::Planned]);
        $actor = User::factory()->dentist()->create();

        $completed = $this->service->completeItem($item, $actor);

        $this->assertSame(TreatmentPlanItemStatus::Completed, $completed->status);
        $this->assertNotNull($completed->completed_at);
        $this->assertSame($actor->id, $completed->updated_by_id);
    }

    public function test_complete_item_rejects_an_already_terminal_item(): void
    {
        $item = TreatmentPlanItem::factory()->cancelled()->create();
        $actor = User::factory()->dentist()->create();

        $this->expectException(InvalidTreatmentPlanItemStatusTransitionException::class);

        $this->service->completeItem($item, $actor);
    }

    public function test_cancel_item_transitions_a_planned_item(): void
    {
        $item = TreatmentPlanItem::factory()->create(['status' => TreatmentPlanItemStatus::Planned]);
        $actor = User::factory()->dentist()->create();

        $cancelled = $this->service->cancelItem($item, $actor);

        $this->assertSame(TreatmentPlanItemStatus::Cancelled, $cancelled->status);
        $this->assertNotNull($cancelled->cancelled_at);
    }

    public function test_delete_item_soft_deletes_the_item(): void
    {
        $item = TreatmentPlanItem::factory()->create();

        $this->service->deleteItem($item);

        $this->assertSoftDeleted('treatment_plan_items', ['id' => $item->id]);
    }

    // --- Plan-level transitions ----------------------------------------------------------------

    public function test_present_transitions_a_draft_plan(): void
    {
        $plan = TreatmentPlan::factory()->create();
        $actor = User::factory()->dentist()->create();

        $presented = $this->service->present($plan, $actor);

        $this->assertSame(TreatmentPlanStatus::Presented, $presented->status);
        $this->assertNotNull($presented->presented_at);
    }

    public function test_present_rejects_an_already_presented_plan(): void
    {
        $plan = TreatmentPlan::factory()->presented()->create();
        $actor = User::factory()->dentist()->create();

        $this->expectException(InvalidTreatmentPlanStatusTransitionException::class);

        $this->service->present($plan, $actor);
    }

    public function test_accept_transitions_a_presented_plan(): void
    {
        $plan = TreatmentPlan::factory()->presented()->create();
        $actor = User::factory()->dentist()->create();

        $accepted = $this->service->accept($plan, $actor);

        $this->assertSame(TreatmentPlanStatus::Accepted, $accepted->status);
        $this->assertNotNull($accepted->accepted_at);
    }

    public function test_accept_automatically_rejects_sibling_presented_plans_for_the_same_patient(): void
    {
        $patient = Patient::factory()->create();
        $optionA = TreatmentPlan::factory()->presented()->create(['patient_id' => $patient->id]);
        $optionB = TreatmentPlan::factory()->presented()->create(['patient_id' => $patient->id]);
        $draftPlan = TreatmentPlan::factory()->create(['patient_id' => $patient->id]);
        $actor = User::factory()->dentist()->create();

        $this->service->accept($optionA, $actor);

        $this->assertSame(TreatmentPlanStatus::Rejected, $optionB->fresh()->status);
        $this->assertNotNull($optionB->fresh()->rejected_at);
        // A draft plan was never a live alternative — left untouched (design doc §5/§15 Q3).
        $this->assertSame(TreatmentPlanStatus::Draft, $draftPlan->fresh()->status);
    }

    public function test_accept_does_not_reject_presented_plans_belonging_to_other_patients(): void
    {
        $plan = TreatmentPlan::factory()->presented()->create();
        $otherPatientPlan = TreatmentPlan::factory()->presented()->create();
        $actor = User::factory()->dentist()->create();

        $this->service->accept($plan, $actor);

        $this->assertSame(TreatmentPlanStatus::Presented, $otherPatientPlan->fresh()->status);
    }

    public function test_reject_transitions_a_presented_plan(): void
    {
        $plan = TreatmentPlan::factory()->presented()->create();
        $actor = User::factory()->dentist()->create();

        $rejected = $this->service->reject($plan, $actor);

        $this->assertSame(TreatmentPlanStatus::Rejected, $rejected->status);
        $this->assertNotNull($rejected->rejected_at);
    }

    public function test_start_transitions_an_accepted_plan(): void
    {
        $plan = TreatmentPlan::factory()->accepted()->create();
        $actor = User::factory()->dentist()->create();

        $started = $this->service->start($plan, $actor);

        $this->assertSame(TreatmentPlanStatus::InProgress, $started->status);
        $this->assertNotNull($started->started_at);
    }

    public function test_complete_transitions_an_in_progress_plan_when_no_items_are_still_planned(): void
    {
        $plan = TreatmentPlan::factory()->inProgress()->create();
        TreatmentPlanItem::factory()->completed()->create(['treatment_plan_id' => $plan->id]);
        TreatmentPlanItem::factory()->cancelled()->create(['treatment_plan_id' => $plan->id]);
        $actor = User::factory()->dentist()->create();

        $completed = $this->service->complete($plan, $actor);

        $this->assertSame(TreatmentPlanStatus::Completed, $completed->status);
        $this->assertNotNull($completed->completed_at);
    }

    public function test_complete_rejects_when_an_item_is_still_planned(): void
    {
        $plan = TreatmentPlan::factory()->inProgress()->create();
        TreatmentPlanItem::factory()->create(['treatment_plan_id' => $plan->id, 'status' => TreatmentPlanItemStatus::Planned]);
        $actor = User::factory()->dentist()->create();

        $this->expectException(TreatmentPlanHasOpenItemsException::class);

        $this->service->complete($plan, $actor);
    }

    public function test_cancel_transitions_a_draft_plan(): void
    {
        $plan = TreatmentPlan::factory()->create();
        $actor = User::factory()->dentist()->create();

        $cancelled = $this->service->cancel($plan, $actor);

        $this->assertSame(TreatmentPlanStatus::Cancelled, $cancelled->status);
        $this->assertNotNull($cancelled->cancelled_at);
    }

    public function test_cancel_cascades_to_cancel_every_still_planned_item(): void
    {
        $plan = TreatmentPlan::factory()->accepted()->create();
        $plannedItem = TreatmentPlanItem::factory()->create(['treatment_plan_id' => $plan->id, 'status' => TreatmentPlanItemStatus::Planned]);
        $completedItem = TreatmentPlanItem::factory()->completed()->create(['treatment_plan_id' => $plan->id]);
        $actor = User::factory()->dentist()->create();

        $this->service->cancel($plan, $actor);

        $this->assertSame(TreatmentPlanItemStatus::Cancelled, $plannedItem->fresh()->status);
        $this->assertNotNull($plannedItem->fresh()->cancelled_at);
        // Already-completed work is historical fact — untouched by the cascade.
        $this->assertSame(TreatmentPlanItemStatus::Completed, $completedItem->fresh()->status);
    }

    public function test_cancel_rejects_an_already_terminal_plan(): void
    {
        $plan = TreatmentPlan::factory()->completed()->create();
        $actor = User::factory()->dentist()->create();

        $this->expectException(InvalidTreatmentPlanStatusTransitionException::class);

        $this->service->cancel($plan, $actor);
    }

    public function test_delete_plan_soft_deletes_the_plan(): void
    {
        $plan = TreatmentPlan::factory()->create();

        $this->service->deletePlan($plan);

        $this->assertSoftDeleted('treatment_plans', ['id' => $plan->id]);
    }

    // --- Revision support ------------------------------------------------------------------------

    public function test_create_superseding_plan_clones_only_still_planned_items(): void
    {
        $original = TreatmentPlan::factory()->rejected()->create(['title' => 'Option A']);
        $plannedItem = TreatmentPlanItem::factory()->create([
            'treatment_plan_id' => $original->id,
            'status' => TreatmentPlanItemStatus::Planned,
            'procedure_name' => 'Crown',
            'unit_cost' => 800,
        ]);
        TreatmentPlanItem::factory()->completed()->create(['treatment_plan_id' => $original->id]);
        TreatmentPlanItem::factory()->cancelled()->create(['treatment_plan_id' => $original->id]);
        $actor = User::factory()->dentist()->create();

        $revision = $this->service->createSupersedingPlan($original, [], $actor);

        $this->assertSame(TreatmentPlanStatus::Draft, $revision->status);
        $this->assertSame($original->patient_id, $revision->patient_id);
        $this->assertCount(1, $revision->items);
        $this->assertSame('Crown', $revision->items->first()->procedure_name);
        $this->assertSame('800.00', $revision->items->first()->unit_cost);
        $this->assertNotSame($plannedItem->id, $revision->items->first()->id);
    }

    public function test_create_superseding_plan_links_the_original_plan_forward(): void
    {
        $original = TreatmentPlan::factory()->rejected()->create();
        $actor = User::factory()->dentist()->create();

        $revision = $this->service->createSupersedingPlan($original, [], $actor);

        $this->assertSame($revision->id, $original->fresh()->superseded_by_plan_id);
    }

    public function test_create_superseding_plan_preserves_the_original_plan_untouched_otherwise(): void
    {
        $original = TreatmentPlan::factory()->rejected()->create(['title' => 'Option A']);
        $actor = User::factory()->dentist()->create();

        $this->service->createSupersedingPlan($original, [], $actor);

        $fresh = $original->fresh();
        $this->assertSame(TreatmentPlanStatus::Rejected, $fresh->status);
        $this->assertSame('Option A', $fresh->title);
    }

    public function test_create_superseding_plan_applies_overrides(): void
    {
        $original = TreatmentPlan::factory()->rejected()->create(['title' => 'Option A']);
        $actor = User::factory()->dentist()->create();

        $revision = $this->service->createSupersedingPlan($original, ['title' => 'Option A — Revised'], $actor);

        $this->assertSame('Option A — Revised', $revision->title);
    }

    // --- Audit -----------------------------------------------------------------------------------

    public function test_every_plan_transition_records_an_audit_log_entry(): void
    {
        $actor = User::factory()->dentist()->create();
        $this->actingAs($actor);

        $plan = TreatmentPlan::factory()->create();
        $this->service->present($plan, $actor);

        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => TreatmentPlan::class,
            'auditable_id' => $plan->id,
            'action' => 'updated',
            'user_id' => $actor->id,
        ]);
    }

    public function test_accept_records_an_audit_log_entry_for_each_rejected_sibling(): void
    {
        $actor = User::factory()->dentist()->create();
        $this->actingAs($actor);

        $patient = Patient::factory()->create();
        $optionA = TreatmentPlan::factory()->presented()->create(['patient_id' => $patient->id]);
        $optionB = TreatmentPlan::factory()->presented()->create(['patient_id' => $patient->id]);

        $this->service->accept($optionA, $actor);

        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => TreatmentPlan::class,
            'auditable_id' => $optionB->id,
            'action' => 'updated',
        ]);
    }
}
