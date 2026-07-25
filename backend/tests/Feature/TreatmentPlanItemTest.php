<?php

namespace Tests\Feature;

use App\Enums\TreatmentPlanItemStatus;
use App\Models\DentalCondition;
use App\Models\TreatmentPlan;
use App\Models\TreatmentPlanItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TreatmentPlanItemTest extends TestCase
{
    use RefreshDatabase;

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'dental_condition_id' => DentalCondition::factory()->procedure()->create([
                'applies_to_surface' => false,
                'default_cost' => 150,
            ])->id,
            'tooth_number' => '16',
            'quantity' => 1,
        ], $overrides);
    }

    // ---- store (add item) ---------------------------------------------------------------------

    public function test_guest_cannot_add_an_item(): void
    {
        $plan = TreatmentPlan::factory()->create();

        $response = $this->postJson("/api/treatment-plans/{$plan->id}/items", $this->validPayload());

        $response->assertUnauthorized();
    }

    public function test_admin_can_add_an_item_to_a_draft_plan(): void
    {
        $actor = User::factory()->admin()->create();
        $plan = TreatmentPlan::factory()->create();

        $response = $this->actingAs($actor)->postJson(
            "/api/treatment-plans/{$plan->id}/items",
            $this->validPayload()
        );

        // Item mutation endpoints return the full parent plan (design doc §9).
        $response->assertCreated();
        $this->assertSame($plan->id, $response->json('id'));
        $this->assertCount(1, $response->json('items'));
        $this->assertSame('16', $response->json('items.0.tooth_number'));
        $this->assertSame('150.00', $response->json('items.0.unit_cost'));
        $this->assertSame('planned', $response->json('items.0.status'));
    }

    public function test_dentist_can_add_an_item(): void
    {
        $actor = User::factory()->dentist()->create();
        $plan = TreatmentPlan::factory()->create();

        $response = $this->actingAs($actor)->postJson(
            "/api/treatment-plans/{$plan->id}/items",
            $this->validPayload()
        );

        $response->assertCreated();
    }

    public function test_receptionist_cannot_add_an_item(): void
    {
        $actor = User::factory()->create(['role' => 'receptionist']);
        $plan = TreatmentPlan::factory()->create();

        $response = $this->actingAs($actor)->postJson(
            "/api/treatment-plans/{$plan->id}/items",
            $this->validPayload()
        );

        $response->assertForbidden();
    }

    public function test_add_item_requires_valid_data(): void
    {
        $actor = User::factory()->admin()->create();
        $plan = TreatmentPlan::factory()->create();

        $response = $this->actingAs($actor)->postJson("/api/treatment-plans/{$plan->id}/items", []);

        $response->assertUnprocessable()->assertJsonValidationErrors(['dental_condition_id']);
    }

    public function test_add_item_rejects_a_non_procedure_dental_condition(): void
    {
        $actor = User::factory()->admin()->create();
        $plan = TreatmentPlan::factory()->create();
        $finding = DentalCondition::factory()->finding()->create();

        $response = $this->actingAs($actor)->postJson(
            "/api/treatment-plans/{$plan->id}/items",
            $this->validPayload(['dental_condition_id' => $finding->id])
        );

        $response->assertUnprocessable()->assertJsonValidationErrors(['dental_condition_id']);
    }

    public function test_add_item_is_rejected_once_the_plan_is_no_longer_draft(): void
    {
        $actor = User::factory()->admin()->create();
        $plan = TreatmentPlan::factory()->presented()->create();

        $response = $this->actingAs($actor)->postJson(
            "/api/treatment-plans/{$plan->id}/items",
            $this->validPayload()
        );

        $response->assertStatus(422)->assertJson(['code' => 'treatment_plan_item_locked']);
    }

    // ---- update ---------------------------------------------------------------------------------

    public function test_admin_can_update_an_items_notes_while_the_plan_is_no_longer_draft(): void
    {
        $actor = User::factory()->admin()->create();
        $plan = TreatmentPlan::factory()->accepted()->create();
        $item = TreatmentPlanItem::factory()->create(['treatment_plan_id' => $plan->id]);

        $response = $this->actingAs($actor)->putJson("/api/treatment-plan-items/{$item->id}", [
            'notes' => 'Scheduled for next Tuesday',
        ]);

        $response->assertOk();
        $this->assertSame('Scheduled for next Tuesday', $response->json('items.0.notes'));
    }

    public function test_receptionist_cannot_update_an_item(): void
    {
        $actor = User::factory()->create(['role' => 'receptionist']);
        $item = TreatmentPlanItem::factory()->create();

        $response = $this->actingAs($actor)->putJson("/api/treatment-plan-items/{$item->id}", [
            'notes' => 'New notes',
        ]);

        $response->assertForbidden();
    }

    public function test_updating_an_offer_field_is_rejected_once_the_parent_plan_is_no_longer_draft(): void
    {
        $actor = User::factory()->admin()->create();
        $plan = TreatmentPlan::factory()->presented()->create();
        $item = TreatmentPlanItem::factory()->create(['treatment_plan_id' => $plan->id, 'unit_cost' => 100]);

        $response = $this->actingAs($actor)->putJson("/api/treatment-plan-items/{$item->id}", [
            'unit_cost' => 500,
        ]);

        $response->assertStatus(422)->assertJson(['code' => 'treatment_plan_item_locked']);
    }

    public function test_updating_a_terminal_items_locked_fields_is_rejected(): void
    {
        $actor = User::factory()->admin()->create();
        $item = TreatmentPlanItem::factory()->completed()->create();

        $response = $this->actingAs($actor)->putJson("/api/treatment-plan-items/{$item->id}", [
            'quantity' => 3,
        ]);

        $response->assertStatus(422)->assertJson(['code' => 'treatment_plan_item_locked']);
    }

    // ---- complete / cancel ------------------------------------------------------------------------

    public function test_admin_can_complete_a_planned_item(): void
    {
        $actor = User::factory()->admin()->create();
        $item = TreatmentPlanItem::factory()->create(['status' => TreatmentPlanItemStatus::Planned]);

        $response = $this->actingAs($actor)->postJson("/api/treatment-plan-items/{$item->id}/complete");

        $response->assertOk();
        $this->assertSame('completed', $response->json('items.0.status'));
        $this->assertNotNull($response->json('items.0.completed_at'));
    }

    public function test_completing_an_already_terminal_item_is_rejected(): void
    {
        $actor = User::factory()->admin()->create();
        $item = TreatmentPlanItem::factory()->cancelled()->create();

        $response = $this->actingAs($actor)->postJson("/api/treatment-plan-items/{$item->id}/complete");

        $response->assertStatus(422)->assertJson(['code' => 'invalid_treatment_plan_item_status_transition']);
    }

    public function test_receptionist_cannot_complete_an_item(): void
    {
        $actor = User::factory()->create(['role' => 'receptionist']);
        $item = TreatmentPlanItem::factory()->create(['status' => TreatmentPlanItemStatus::Planned]);

        $response = $this->actingAs($actor)->postJson("/api/treatment-plan-items/{$item->id}/complete");

        $response->assertForbidden();
    }

    public function test_dentist_can_cancel_a_planned_item(): void
    {
        $actor = User::factory()->dentist()->create();
        $item = TreatmentPlanItem::factory()->create(['status' => TreatmentPlanItemStatus::Planned]);

        $response = $this->actingAs($actor)->postJson("/api/treatment-plan-items/{$item->id}/cancel");

        $response->assertOk();
        $this->assertSame('cancelled', $response->json('items.0.status'));
    }

    // ---- destroy -------------------------------------------------------------------------------

    public function test_admin_can_delete_an_item(): void
    {
        $actor = User::factory()->admin()->create();
        $item = TreatmentPlanItem::factory()->create();

        $response = $this->actingAs($actor)->deleteJson("/api/treatment-plan-items/{$item->id}");

        $response->assertNoContent();
        $this->assertSoftDeleted('treatment_plan_items', ['id' => $item->id]);
    }

    public function test_dentist_cannot_delete_an_item(): void
    {
        $actor = User::factory()->dentist()->create();
        $item = TreatmentPlanItem::factory()->create();

        $response = $this->actingAs($actor)->deleteJson("/api/treatment-plan-items/{$item->id}");

        $response->assertForbidden();
        $this->assertDatabaseHas('treatment_plan_items', ['id' => $item->id, 'deleted_at' => null]);
    }
}
