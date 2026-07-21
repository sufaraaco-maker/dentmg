<?php

namespace Tests\Unit\Policies;

use App\Models\DentalCondition;
use App\Models\User;
use App\Policies\DentalConditionPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DentalConditionPolicyTest extends TestCase
{
    use RefreshDatabase;

    private DentalConditionPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new DentalConditionPolicy;
    }

    public function test_only_admin_can_create_update_or_delete_dental_conditions(): void
    {
        $condition = DentalCondition::factory()->create();
        $admin = User::factory()->admin()->create();
        $dentist = User::factory()->dentist()->create();
        $receptionist = User::factory()->create(['role' => 'receptionist']);

        $this->assertTrue($this->policy->create($admin));
        $this->assertTrue($this->policy->update($admin, $condition));
        $this->assertTrue($this->policy->delete($admin, $condition));

        $this->assertFalse($this->policy->create($dentist));
        $this->assertFalse($this->policy->update($receptionist, $condition));
        $this->assertFalse($this->policy->delete($dentist, $condition));
    }

    public function test_any_role_can_view_dental_conditions(): void
    {
        $condition = DentalCondition::factory()->create();

        $this->assertTrue($this->policy->viewAny(User::factory()->dentist()->create()));
        $this->assertTrue($this->policy->view(User::factory()->create(['role' => 'receptionist']), $condition));
    }
}
