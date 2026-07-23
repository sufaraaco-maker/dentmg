<?php

namespace Tests\Unit\Policies;

use App\Models\TreatmentPlanItem;
use App\Models\User;
use App\Policies\TreatmentPlanItemPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TreatmentPlanItemPolicyTest extends TestCase
{
    use RefreshDatabase;

    private TreatmentPlanItemPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new TreatmentPlanItemPolicy;
    }

    public function test_admin_and_dentist_can_create_update_complete_and_cancel_items(): void
    {
        $item = TreatmentPlanItem::factory()->create();
        $admin = User::factory()->admin()->create();
        $dentist = User::factory()->dentist()->create();
        $receptionist = User::factory()->create(['role' => 'receptionist']);

        $this->assertTrue($this->policy->create($admin));
        $this->assertTrue($this->policy->update($admin, $item));
        $this->assertTrue($this->policy->complete($admin, $item));
        $this->assertTrue($this->policy->cancel($admin, $item));

        $this->assertTrue($this->policy->create($dentist));
        $this->assertTrue($this->policy->update($dentist, $item));
        $this->assertTrue($this->policy->complete($dentist, $item));
        $this->assertTrue($this->policy->cancel($dentist, $item));

        $this->assertFalse($this->policy->create($receptionist));
        $this->assertFalse($this->policy->update($receptionist, $item));
        $this->assertFalse($this->policy->complete($receptionist, $item));
        $this->assertFalse($this->policy->cancel($receptionist, $item));
    }

    /**
     * No dentist-ownership/IDOR restriction — inherited directly from Dental Chart's identical,
     * already-approved precedent (design doc §10).
     */
    public function test_any_dentist_can_act_on_an_item_created_by_another_dentist(): void
    {
        $creatingDentist = User::factory()->dentist()->create();
        $otherDentist = User::factory()->dentist()->create();

        $item = TreatmentPlanItem::factory()->create(['created_by_id' => $creatingDentist->id]);

        $this->assertTrue($this->policy->update($otherDentist, $item));
        $this->assertTrue($this->policy->complete($otherDentist, $item));
        $this->assertTrue($this->policy->cancel($otherDentist, $item));
    }

    public function test_only_admin_can_delete_an_item(): void
    {
        $item = TreatmentPlanItem::factory()->create();

        $this->assertTrue($this->policy->delete(User::factory()->admin()->create(), $item));
        $this->assertFalse($this->policy->delete(User::factory()->dentist()->create(), $item));
        $this->assertFalse($this->policy->delete(User::factory()->create(['role' => 'receptionist']), $item));
    }

    public function test_any_role_can_view_items(): void
    {
        $item = TreatmentPlanItem::factory()->create();
        $receptionist = User::factory()->create(['role' => 'receptionist']);

        $this->assertTrue($this->policy->viewAny($receptionist));
        $this->assertTrue($this->policy->view($receptionist, $item));
    }
}
