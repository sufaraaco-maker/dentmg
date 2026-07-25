<?php

namespace Tests\Unit\Models;

use App\Enums\TreatmentPlanStatus;
use App\Models\Patient;
use App\Models\TreatmentPlan;
use App\Models\TreatmentPlanItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class TreatmentPlanTest extends TestCase
{
    use RefreshDatabase;

    public function test_belongs_to_patient_dentist_and_created_by(): void
    {
        $patient = Patient::factory()->create();
        $dentist = User::factory()->dentist()->create();
        $admin = User::factory()->admin()->create();

        $plan = TreatmentPlan::factory()->create([
            'patient_id' => $patient->id,
            'dentist_id' => $dentist->id,
            'created_by_id' => $admin->id,
        ]);

        $this->assertTrue($plan->patient->is($patient));
        $this->assertTrue($plan->dentist->is($dentist));
        $this->assertTrue($plan->createdBy->is($admin));
    }

    public function test_patient_has_many_treatment_plans(): void
    {
        $patient = Patient::factory()->create();
        TreatmentPlan::factory()->count(3)->create(['patient_id' => $patient->id]);

        $this->assertCount(3, $patient->treatmentPlans);
    }

    public function test_has_many_items(): void
    {
        $plan = TreatmentPlan::factory()->create();
        TreatmentPlanItem::factory()->count(2)->create(['treatment_plan_id' => $plan->id]);

        $this->assertCount(2, $plan->items);
    }

    public function test_status_is_cast_to_the_backed_enum(): void
    {
        $plan = TreatmentPlan::factory()->presented()->create();

        $this->assertInstanceOf(TreatmentPlanStatus::class, $plan->status);
        $this->assertSame(TreatmentPlanStatus::Presented, $plan->fresh()->status);
    }

    public function test_lifecycle_timestamps_are_cast_to_datetime(): void
    {
        $plan = TreatmentPlan::factory()->completed()->create();

        $this->assertInstanceOf(Carbon::class, $plan->presented_at);
        $this->assertInstanceOf(Carbon::class, $plan->accepted_at);
        $this->assertInstanceOf(Carbon::class, $plan->started_at);
        $this->assertInstanceOf(Carbon::class, $plan->completed_at);
    }

    public function test_superseded_by_plan_is_a_self_referencing_belongs_to(): void
    {
        $replacement = TreatmentPlan::factory()->create();
        $original = TreatmentPlan::factory()->rejected()->create([
            'superseded_by_plan_id' => $replacement->id,
        ]);

        $this->assertTrue($original->supersededByPlan->is($replacement));
    }

    public function test_superseded_by_plan_id_is_nullable(): void
    {
        $plan = TreatmentPlan::factory()->create(['superseded_by_plan_id' => null]);

        $this->assertNull($plan->superseded_by_plan_id);
        $this->assertNull($plan->supersededByPlan);
    }

    public function test_scope_for_patient_and_with_status(): void
    {
        $patientA = Patient::factory()->create();
        $patientB = Patient::factory()->create();

        TreatmentPlan::factory()->create(['patient_id' => $patientA->id, 'status' => TreatmentPlanStatus::Draft]);
        TreatmentPlan::factory()->presented()->create(['patient_id' => $patientA->id]);
        TreatmentPlan::factory()->create(['patient_id' => $patientB->id, 'status' => TreatmentPlanStatus::Draft]);

        $this->assertCount(2, TreatmentPlan::forPatient($patientA->id)->get());
        $this->assertCount(2, TreatmentPlan::withStatus(TreatmentPlanStatus::Draft)->get());
        $this->assertCount(1, TreatmentPlan::forPatient($patientA->id)->withStatus(TreatmentPlanStatus::Draft)->get());
    }

    public function test_soft_delete_does_not_remove_the_row(): void
    {
        $plan = TreatmentPlan::factory()->create();

        $plan->delete();

        $this->assertSoftDeleted('treatment_plans', ['id' => $plan->id]);
    }

    public function test_uses_auditable_and_records_creation_in_audit_logs(): void
    {
        $actor = User::factory()->dentist()->create();
        $this->actingAs($actor);

        $plan = TreatmentPlan::factory()->create(['created_by_id' => $actor->id]);

        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => TreatmentPlan::class,
            'auditable_id' => $plan->id,
            'action' => 'created',
            'user_id' => $actor->id,
        ]);
    }
}
