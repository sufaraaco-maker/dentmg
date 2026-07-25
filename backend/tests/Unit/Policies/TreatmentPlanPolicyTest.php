<?php

namespace Tests\Unit\Policies;

use App\Models\TreatmentPlan;
use App\Models\User;
use App\Policies\TreatmentPlanPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TreatmentPlanPolicyTest extends TestCase
{
    use RefreshDatabase;

    private TreatmentPlanPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new TreatmentPlanPolicy;
    }

    public function test_admin_and_dentist_can_create_and_edit_plans(): void
    {
        $plan = TreatmentPlan::factory()->create();
        $admin = User::factory()->admin()->create();
        $dentist = User::factory()->dentist()->create();
        $receptionist = User::factory()->create(['role' => 'receptionist']);

        $this->assertTrue($this->policy->create($admin));
        $this->assertTrue($this->policy->update($admin, $plan));
        $this->assertTrue($this->policy->create($dentist));
        $this->assertTrue($this->policy->update($dentist, $plan));

        $this->assertFalse($this->policy->create($receptionist));
        $this->assertFalse($this->policy->update($receptionist, $plan));
    }

    public function test_admin_and_dentist_can_perform_every_status_transition(): void
    {
        $plan = TreatmentPlan::factory()->create();
        $admin = User::factory()->admin()->create();
        $dentist = User::factory()->dentist()->create();

        foreach ([$admin, $dentist] as $actor) {
            $this->assertTrue($this->policy->present($actor, $plan));
            $this->assertTrue($this->policy->accept($actor, $plan));
            $this->assertTrue($this->policy->reject($actor, $plan));
            $this->assertTrue($this->policy->start($actor, $plan));
            $this->assertTrue($this->policy->complete($actor, $plan));
            $this->assertTrue($this->policy->cancel($actor, $plan));
        }
    }

    public function test_receptionist_cannot_perform_any_status_transition(): void
    {
        $plan = TreatmentPlan::factory()->create();
        $receptionist = User::factory()->create(['role' => 'receptionist']);

        $this->assertFalse($this->policy->present($receptionist, $plan));
        $this->assertFalse($this->policy->accept($receptionist, $plan));
        $this->assertFalse($this->policy->reject($receptionist, $plan));
        $this->assertFalse($this->policy->start($receptionist, $plan));
        $this->assertFalse($this->policy->complete($receptionist, $plan));
        $this->assertFalse($this->policy->cancel($receptionist, $plan));
    }

    public function test_admin_and_dentist_can_create_a_revision_receptionist_cannot(): void
    {
        $plan = TreatmentPlan::factory()->create();

        $this->assertTrue($this->policy->createRevision(User::factory()->admin()->create(), $plan));
        $this->assertTrue($this->policy->createRevision(User::factory()->dentist()->create(), $plan));
        $this->assertFalse($this->policy->createRevision(User::factory()->create(['role' => 'receptionist']), $plan));
    }

    /**
     * No dentist-ownership/IDOR restriction — inherited directly from Dental Chart's identical,
     * already-approved precedent (design doc §10). Any dentist may act on any patient's plan.
     */
    public function test_any_dentist_can_act_on_a_plan_authored_by_another_dentist(): void
    {
        $authoringDentist = User::factory()->dentist()->create();
        $otherDentist = User::factory()->dentist()->create();

        $plan = TreatmentPlan::factory()->create(['dentist_id' => $authoringDentist->id]);

        $this->assertTrue($this->policy->update($otherDentist, $plan));
        $this->assertTrue($this->policy->present($otherDentist, $plan));
        $this->assertTrue($this->policy->accept($otherDentist, $plan));
    }

    public function test_only_admin_can_delete_a_plan(): void
    {
        $plan = TreatmentPlan::factory()->create();

        $this->assertTrue($this->policy->delete(User::factory()->admin()->create(), $plan));
        $this->assertFalse($this->policy->delete(User::factory()->dentist()->create(), $plan));
        $this->assertFalse($this->policy->delete(User::factory()->create(['role' => 'receptionist']), $plan));
    }

    public function test_any_role_can_view_plans(): void
    {
        $plan = TreatmentPlan::factory()->create();
        $receptionist = User::factory()->create(['role' => 'receptionist']);

        $this->assertTrue($this->policy->viewAny($receptionist));
        $this->assertTrue($this->policy->view($receptionist, $plan));
    }
}
