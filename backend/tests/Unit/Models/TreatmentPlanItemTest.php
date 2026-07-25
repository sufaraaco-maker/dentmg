<?php

namespace Tests\Unit\Models;

use App\Enums\TreatmentPlanItemStatus;
use App\Models\Appointment;
use App\Models\DentalChartEntry;
use App\Models\DentalCondition;
use App\Models\TreatmentPlan;
use App\Models\TreatmentPlanItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class TreatmentPlanItemTest extends TestCase
{
    use RefreshDatabase;

    public function test_belongs_to_treatment_plan_dental_condition_created_by_and_updated_by(): void
    {
        $plan = TreatmentPlan::factory()->create();
        $condition = DentalCondition::factory()->procedure()->create();
        $dentist = User::factory()->dentist()->create();
        $admin = User::factory()->admin()->create();

        $item = TreatmentPlanItem::factory()->create([
            'treatment_plan_id' => $plan->id,
            'dental_condition_id' => $condition->id,
            'created_by_id' => $dentist->id,
            'updated_by_id' => $admin->id,
        ]);

        $this->assertTrue($item->treatmentPlan->is($plan));
        $this->assertTrue($item->dentalCondition->is($condition));
        $this->assertTrue($item->createdBy->is($dentist));
        $this->assertTrue($item->updatedBy->is($admin));
    }

    public function test_updated_by_is_nullable(): void
    {
        $item = TreatmentPlanItem::factory()->create(['updated_by_id' => null]);

        $this->assertNull($item->updated_by_id);
        $this->assertNull($item->updatedBy);
    }

    public function test_treatment_plan_has_many_items(): void
    {
        $plan = TreatmentPlan::factory()->create();
        TreatmentPlanItem::factory()->count(3)->create(['treatment_plan_id' => $plan->id]);

        $this->assertCount(3, $plan->items);
    }

    public function test_diagnosis_entry_is_a_nullable_read_only_reference(): void
    {
        $entry = DentalChartEntry::factory()->create();
        $item = TreatmentPlanItem::factory()->create(['diagnosis_entry_id' => $entry->id]);

        $this->assertTrue($item->diagnosisEntry->is($entry));

        $withoutDiagnosis = TreatmentPlanItem::factory()->create(['diagnosis_entry_id' => null]);
        $this->assertNull($withoutDiagnosis->diagnosisEntry);
    }

    public function test_appointment_is_a_nullable_reference(): void
    {
        $appointment = Appointment::factory()->create();
        $item = TreatmentPlanItem::factory()->create(['appointment_id' => $appointment->id]);

        $this->assertTrue($item->appointment->is($appointment));

        $unscheduled = TreatmentPlanItem::factory()->create(['appointment_id' => null]);
        $this->assertNull($unscheduled->appointment);
    }

    public function test_surfaces_is_cast_to_array(): void
    {
        $item = TreatmentPlanItem::factory()->withSurfaces(['M', 'O'])->create();

        $this->assertIsArray($item->surfaces);
        $this->assertSame(['M', 'O'], $item->fresh()->surfaces);
    }

    public function test_status_is_cast_to_the_backed_enum(): void
    {
        $item = TreatmentPlanItem::factory()->completed()->create();

        $this->assertInstanceOf(TreatmentPlanItemStatus::class, $item->status);
        $this->assertSame(TreatmentPlanItemStatus::Completed, $item->fresh()->status);
    }

    public function test_completed_and_cancelled_at_are_cast_to_datetime(): void
    {
        $item = TreatmentPlanItem::factory()->completed()->create();

        $this->assertInstanceOf(Carbon::class, $item->completed_at);
    }

    public function test_estimated_cost_is_unit_cost_times_quantity_and_never_stored(): void
    {
        $item = TreatmentPlanItem::factory()->create(['unit_cost' => 150.00, 'quantity' => 2]);

        $this->assertSame('300.00', $item->estimated_cost);

        // Not a real column — confirmed absent from the raw database attributes.
        $this->assertArrayNotHasKey('estimated_cost', $item->getAttributes());
    }

    public function test_procedure_name_and_description_are_snapshotted_not_live_joined(): void
    {
        $condition = DentalCondition::factory()->procedure()->create([
            'name' => 'Composite Filling',
            'description' => 'A tooth-colored resin filling used to repair a cavity.',
        ]);

        $item = TreatmentPlanItem::factory()->create([
            'dental_condition_id' => $condition->id,
            'procedure_name' => $condition->name,
            'procedure_description' => $condition->description,
        ]);

        // The catalog entry is renamed afterwards (e.g. by an admin) — the already-created item's
        // own snapshot columns must not change, since display code reads these, never a live join
        // through dental_condition_id (design doc §6/§8, Decision 2).
        $condition->update(['name' => 'Tooth-Colored Filling', 'description' => 'Renamed.']);

        $this->assertSame('Composite Filling', $item->fresh()->procedure_name);
        $this->assertSame('A tooth-colored resin filling used to repair a cavity.', $item->fresh()->procedure_description);
    }

    public function test_scope_for_plan_and_with_status(): void
    {
        $planA = TreatmentPlan::factory()->create();
        $planB = TreatmentPlan::factory()->create();

        TreatmentPlanItem::factory()->create(['treatment_plan_id' => $planA->id, 'status' => TreatmentPlanItemStatus::Planned]);
        TreatmentPlanItem::factory()->completed()->create(['treatment_plan_id' => $planA->id]);
        TreatmentPlanItem::factory()->create(['treatment_plan_id' => $planB->id, 'status' => TreatmentPlanItemStatus::Planned]);

        $this->assertCount(2, TreatmentPlanItem::forPlan($planA->id)->get());
        $this->assertCount(2, TreatmentPlanItem::withStatus(TreatmentPlanItemStatus::Planned)->get());
        $this->assertCount(1, TreatmentPlanItem::forPlan($planA->id)->withStatus(TreatmentPlanItemStatus::Planned)->get());
    }

    public function test_soft_delete_does_not_remove_the_row(): void
    {
        $item = TreatmentPlanItem::factory()->create();

        $item->delete();

        $this->assertSoftDeleted('treatment_plan_items', ['id' => $item->id]);
    }

    public function test_uses_auditable_and_records_creation_in_audit_logs(): void
    {
        $actor = User::factory()->dentist()->create();
        $this->actingAs($actor);

        $item = TreatmentPlanItem::factory()->create(['created_by_id' => $actor->id]);

        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => TreatmentPlanItem::class,
            'auditable_id' => $item->id,
            'action' => 'created',
            'user_id' => $actor->id,
        ]);
    }
}
