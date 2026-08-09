<?php

namespace Tests\Unit\Policies;

use App\Models\Supply;
use App\Models\User;
use App\Policies\SupplyPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 4 (Advanced Permissions & Audit) Step 2 — baseline regression coverage; zero dedicated
 * test coverage existed before this Policy was wired to `hasPermission()`.
 */
class SupplyPolicyTest extends TestCase
{
    use RefreshDatabase;

    private SupplyPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new SupplyPolicy;
    }

    public function test_any_role_can_view_supplies(): void
    {
        $supply = Supply::factory()->create();

        $this->assertTrue($this->policy->viewAny(User::factory()->dentist()->create()));
        $this->assertTrue($this->policy->view(User::factory()->create(['role' => 'receptionist']), $supply));
    }

    public function test_only_admin_and_receptionist_can_manage_supplies(): void
    {
        $supply = Supply::factory()->create();
        $admin = User::factory()->admin()->create();
        $receptionist = User::factory()->create(['role' => 'receptionist']);
        $dentist = User::factory()->dentist()->create();

        $this->assertTrue($this->policy->create($admin));
        $this->assertTrue($this->policy->update($receptionist, $supply));
        $this->assertTrue($this->policy->delete($admin, $supply));

        $this->assertFalse($this->policy->create($dentist));
        $this->assertFalse($this->policy->delete($dentist, $supply));
    }
}
