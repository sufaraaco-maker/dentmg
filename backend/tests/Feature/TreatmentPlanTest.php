<?php

namespace Tests\Feature;

use App\Enums\TreatmentPlanItemStatus;
use App\Enums\TreatmentPlanStatus;
use App\Models\Patient;
use App\Models\TreatmentPlan;
use App\Models\TreatmentPlanItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TreatmentPlanTest extends TestCase
{
    use RefreshDatabase;

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'dentist_id' => User::factory()->dentist()->create()->id,
            'title' => 'Option A — Implant',
            'notes' => 'Patient prefers morning visits.',
        ], $overrides);
    }

    // ---- index ---------------------------------------------------------------------------

    public function test_guest_cannot_list_treatment_plans(): void
    {
        $patient = Patient::factory()->create();

        $response = $this->getJson("/api/patients/{$patient->id}/treatment-plans");

        $response->assertUnauthorized();
    }

    public function test_any_authenticated_role_can_list_a_patients_treatment_plans(): void
    {
        $actor = User::factory()->create(['role' => 'receptionist']);
        $patient = Patient::factory()->create();
        $other = Patient::factory()->create();

        TreatmentPlan::factory()->count(2)->create(['patient_id' => $patient->id]);
        TreatmentPlan::factory()->create(['patient_id' => $other->id]);

        $response = $this->actingAs($actor)->getJson("/api/patients/{$patient->id}/treatment-plans");

        $response->assertOk();
        $this->assertCount(2, $response->json('data'));
        $this->assertArrayHasKey('estimated_cost', $response->json('data.0'));
        $this->assertArrayHasKey('items', $response->json('data.0'));
        $this->assertArrayHasKey('dentist', $response->json('data.0'));
    }

    public function test_a_patients_treatment_plan_list_is_paginated(): void
    {
        $actor = User::factory()->admin()->create();
        $patient = Patient::factory()->create();
        TreatmentPlan::factory()->count(20)->create(['patient_id' => $patient->id]);

        $response = $this->actingAs($actor)->getJson("/api/patients/{$patient->id}/treatment-plans");

        $response->assertOk();
        $this->assertCount(15, $response->json('data'));
        $this->assertSame(20, $response->json('meta.total'));
    }

    public function test_list_includes_the_computed_estimated_cost_from_non_cancelled_items(): void
    {
        $actor = User::factory()->admin()->create();
        $patient = Patient::factory()->create();
        $plan = TreatmentPlan::factory()->create(['patient_id' => $patient->id]);
        TreatmentPlanItem::factory()->create(['treatment_plan_id' => $plan->id, 'unit_cost' => 100, 'quantity' => 2]);
        TreatmentPlanItem::factory()->cancelled()->create(['treatment_plan_id' => $plan->id, 'unit_cost' => 999, 'quantity' => 1]);

        $response = $this->actingAs($actor)->getJson("/api/patients/{$patient->id}/treatment-plans");

        $response->assertOk();
        $this->assertSame('200.00', $response->json('data.0.estimated_cost'));
    }

    // ---- store ----------------------------------------------------------------------------

    public function test_guest_cannot_create_a_treatment_plan(): void
    {
        $patient = Patient::factory()->create();

        $response = $this->postJson("/api/patients/{$patient->id}/treatment-plans", $this->validPayload());

        $response->assertUnauthorized();
    }

    public function test_admin_can_create_a_treatment_plan(): void
    {
        $actor = User::factory()->admin()->create();
        $patient = Patient::factory()->create();

        $response = $this->actingAs($actor)->postJson(
            "/api/patients/{$patient->id}/treatment-plans",
            $this->validPayload()
        );

        $response->assertCreated()->assertJson([
            'patient_id' => $patient->id,
            'title' => 'Option A — Implant',
            'status' => 'draft',
        ]);
        $this->assertDatabaseHas('treatment_plans', [
            'id' => $response->json('id'),
            'patient_id' => $patient->id,
            'created_by_id' => $actor->id,
        ]);
    }

    public function test_dentist_can_create_a_treatment_plan(): void
    {
        $actor = User::factory()->dentist()->create();
        $patient = Patient::factory()->create();

        $response = $this->actingAs($actor)->postJson(
            "/api/patients/{$patient->id}/treatment-plans",
            $this->validPayload()
        );

        $response->assertCreated();
    }

    public function test_receptionist_cannot_create_a_treatment_plan(): void
    {
        $actor = User::factory()->create(['role' => 'receptionist']);
        $patient = Patient::factory()->create();

        $response = $this->actingAs($actor)->postJson(
            "/api/patients/{$patient->id}/treatment-plans",
            $this->validPayload()
        );

        $response->assertForbidden();
    }

    public function test_create_treatment_plan_requires_valid_data(): void
    {
        $actor = User::factory()->admin()->create();
        $patient = Patient::factory()->create();

        $response = $this->actingAs($actor)->postJson("/api/patients/{$patient->id}/treatment-plans", []);

        $response->assertUnprocessable()->assertJsonValidationErrors(['dentist_id']);
    }

    public function test_create_rejects_a_dentist_id_that_is_not_a_dentist(): void
    {
        $actor = User::factory()->admin()->create();
        $patient = Patient::factory()->create();
        $notADentist = User::factory()->create(['role' => 'receptionist']);

        $response = $this->actingAs($actor)->postJson(
            "/api/patients/{$patient->id}/treatment-plans",
            $this->validPayload(['dentist_id' => $notADentist->id])
        );

        $response->assertUnprocessable()->assertJsonValidationErrors(['dentist_id']);
    }

    // ---- show -------------------------------------------------------------------------------

    public function test_show_returns_the_plan_with_eager_loaded_items_and_relations(): void
    {
        $actor = User::factory()->admin()->create();
        $plan = TreatmentPlan::factory()->create();
        $item = TreatmentPlanItem::factory()->create(['treatment_plan_id' => $plan->id]);

        $response = $this->actingAs($actor)->getJson("/api/treatment-plans/{$plan->id}");

        $response->assertOk();
        $this->assertSame($plan->id, $response->json('id'));
        $this->assertCount(1, $response->json('items'));
        $this->assertSame($item->id, $response->json('items.0.id'));
        $this->assertArrayHasKey('dental_condition', $response->json('items.0'));
    }

    // ---- update -----------------------------------------------------------------------------

    public function test_admin_can_update_a_treatment_plans_administrative_fields(): void
    {
        $actor = User::factory()->admin()->create();
        $plan = TreatmentPlan::factory()->create(['title' => 'Old title']);

        $response = $this->actingAs($actor)->putJson("/api/treatment-plans/{$plan->id}", [
            'title' => 'New title',
        ]);

        $response->assertOk()->assertJson(['title' => 'New title']);
    }

    public function test_receptionist_cannot_update_a_treatment_plan(): void
    {
        $actor = User::factory()->create(['role' => 'receptionist']);
        $plan = TreatmentPlan::factory()->create();

        $response = $this->actingAs($actor)->putJson("/api/treatment-plans/{$plan->id}", [
            'title' => 'New title',
        ]);

        $response->assertForbidden();
    }

    public function test_updating_a_terminal_plan_is_rejected(): void
    {
        $actor = User::factory()->admin()->create();
        $plan = TreatmentPlan::factory()->completed()->create();

        $response = $this->actingAs($actor)->putJson("/api/treatment-plans/{$plan->id}", [
            'title' => 'New title',
        ]);

        $response->assertStatus(422)->assertJson(['code' => 'treatment_plan_item_locked']);
    }

    // ---- lifecycle: present / accept / reject ------------------------------------------------

    public function test_admin_can_present_a_draft_plan(): void
    {
        $actor = User::factory()->admin()->create();
        $plan = TreatmentPlan::factory()->create();

        $response = $this->actingAs($actor)->postJson("/api/treatment-plans/{$plan->id}/present");

        $response->assertOk()->assertJson(['status' => 'presented']);
        $this->assertNotNull($response->json('presented_at'));
    }

    public function test_receptionist_cannot_present_a_plan(): void
    {
        $actor = User::factory()->create(['role' => 'receptionist']);
        $plan = TreatmentPlan::factory()->create();

        $response = $this->actingAs($actor)->postJson("/api/treatment-plans/{$plan->id}/present");

        $response->assertForbidden();
    }

    public function test_presenting_an_already_presented_plan_is_rejected(): void
    {
        $actor = User::factory()->admin()->create();
        $plan = TreatmentPlan::factory()->presented()->create();

        $response = $this->actingAs($actor)->postJson("/api/treatment-plans/{$plan->id}/present");

        $response->assertStatus(422)->assertJson(['code' => 'invalid_treatment_plan_status_transition']);
    }

    public function test_dentist_can_accept_a_presented_plan_and_it_auto_rejects_sibling_presented_plans(): void
    {
        $actor = User::factory()->dentist()->create();
        $patient = Patient::factory()->create();
        $optionA = TreatmentPlan::factory()->presented()->create(['patient_id' => $patient->id]);
        $optionB = TreatmentPlan::factory()->presented()->create(['patient_id' => $patient->id]);

        $response = $this->actingAs($actor)->postJson("/api/treatment-plans/{$optionA->id}/accept");

        $response->assertOk()->assertJson(['status' => 'accepted']);
        $this->assertSame(TreatmentPlanStatus::Rejected, $optionB->fresh()->status);
    }

    public function test_reject_transitions_a_presented_plan(): void
    {
        $actor = User::factory()->admin()->create();
        $plan = TreatmentPlan::factory()->presented()->create();

        $response = $this->actingAs($actor)->postJson("/api/treatment-plans/{$plan->id}/reject");

        $response->assertOk()->assertJson(['status' => 'rejected']);
        $this->assertNotNull($response->json('rejected_at'));
    }

    // ---- lifecycle: start / complete / cancel --------------------------------------------------

    public function test_start_transitions_an_accepted_plan_to_in_progress(): void
    {
        $actor = User::factory()->admin()->create();
        $plan = TreatmentPlan::factory()->accepted()->create();

        $response = $this->actingAs($actor)->postJson("/api/treatment-plans/{$plan->id}/start");

        $response->assertOk()->assertJson(['status' => 'in_progress']);
    }

    public function test_complete_is_rejected_when_an_item_is_still_planned(): void
    {
        $actor = User::factory()->admin()->create();
        $plan = TreatmentPlan::factory()->inProgress()->create();
        TreatmentPlanItem::factory()->create(['treatment_plan_id' => $plan->id, 'status' => TreatmentPlanItemStatus::Planned]);

        $response = $this->actingAs($actor)->postJson("/api/treatment-plans/{$plan->id}/complete");

        $response->assertStatus(422)->assertJson(['code' => 'treatment_plan_has_open_items']);
    }

    public function test_complete_transitions_an_in_progress_plan_once_no_items_remain_planned(): void
    {
        $actor = User::factory()->admin()->create();
        $plan = TreatmentPlan::factory()->inProgress()->create();
        TreatmentPlanItem::factory()->completed()->create(['treatment_plan_id' => $plan->id]);

        $response = $this->actingAs($actor)->postJson("/api/treatment-plans/{$plan->id}/complete");

        $response->assertOk()->assertJson(['status' => 'completed']);
    }

    public function test_cancel_cascades_to_cancel_every_still_planned_item(): void
    {
        $actor = User::factory()->admin()->create();
        $plan = TreatmentPlan::factory()->accepted()->create();
        $item = TreatmentPlanItem::factory()->create(['treatment_plan_id' => $plan->id, 'status' => TreatmentPlanItemStatus::Planned]);

        $response = $this->actingAs($actor)->postJson("/api/treatment-plans/{$plan->id}/cancel");

        $response->assertOk()->assertJson(['status' => 'cancelled']);
        $this->assertSame('cancelled', $response->json('items.0.status'));
        $this->assertSame(TreatmentPlanItemStatus::Cancelled, $item->fresh()->status);
    }

    public function test_cancelling_an_already_terminal_plan_is_rejected(): void
    {
        $actor = User::factory()->admin()->create();
        $plan = TreatmentPlan::factory()->completed()->create();

        $response = $this->actingAs($actor)->postJson("/api/treatment-plans/{$plan->id}/cancel");

        $response->assertStatus(422)->assertJson(['code' => 'invalid_treatment_plan_status_transition']);
    }

    // ---- revisions --------------------------------------------------------------------------

    public function test_admin_can_create_a_revision_of_a_rejected_plan(): void
    {
        $actor = User::factory()->admin()->create();
        $original = TreatmentPlan::factory()->rejected()->create(['title' => 'Option A']);
        TreatmentPlanItem::factory()->create([
            'treatment_plan_id' => $original->id,
            'status' => TreatmentPlanItemStatus::Planned,
            'procedure_name' => 'Crown',
        ]);

        $response = $this->actingAs($actor)->postJson("/api/treatment-plans/{$original->id}/revisions", [
            'title' => 'Option A — Revised',
        ]);

        $response->assertCreated()->assertJson([
            'status' => 'draft',
            'title' => 'Option A — Revised',
            'patient_id' => $original->patient_id,
        ]);
        $this->assertCount(1, $response->json('items'));
        $this->assertSame('Crown', $response->json('items.0.procedure_name'));
        $this->assertSame($response->json('id'), $original->fresh()->superseded_by_plan_id);
    }

    public function test_receptionist_cannot_create_a_revision(): void
    {
        $actor = User::factory()->create(['role' => 'receptionist']);
        $original = TreatmentPlan::factory()->rejected()->create();

        $response = $this->actingAs($actor)->postJson("/api/treatment-plans/{$original->id}/revisions");

        $response->assertForbidden();
    }

    // ---- destroy ------------------------------------------------------------------------------

    public function test_admin_can_delete_a_treatment_plan(): void
    {
        $actor = User::factory()->admin()->create();
        $plan = TreatmentPlan::factory()->create();

        $response = $this->actingAs($actor)->deleteJson("/api/treatment-plans/{$plan->id}");

        $response->assertNoContent();
        $this->assertSoftDeleted('treatment_plans', ['id' => $plan->id]);
    }

    public function test_dentist_cannot_delete_a_treatment_plan(): void
    {
        $actor = User::factory()->dentist()->create();
        $plan = TreatmentPlan::factory()->create();

        $response = $this->actingAs($actor)->deleteJson("/api/treatment-plans/{$plan->id}");

        $response->assertForbidden();
        $this->assertDatabaseHas('treatment_plans', ['id' => $plan->id, 'deleted_at' => null]);
    }
}
