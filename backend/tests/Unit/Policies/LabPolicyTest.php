<?php

namespace Tests\Unit\Policies;

use App\Models\Lab;
use App\Models\User;
use App\Policies\LabPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 4 (Advanced Permissions & Audit) Step 2 — baseline regression coverage; zero dedicated
 * test coverage existed before this Policy was wired to `hasPermission()`.
 */
class LabPolicyTest extends TestCase
{
    use RefreshDatabase;

    private LabPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new LabPolicy;
    }

    public function test_only_admin_can_create_update_or_delete_labs(): void
    {
        $lab = Lab::factory()->create();
        $admin = User::factory()->admin()->create();
        $dentist = User::factory()->dentist()->create();

        $this->assertTrue($this->policy->create($admin));
        $this->assertTrue($this->policy->update($admin, $lab));
        $this->assertTrue($this->policy->delete($admin, $lab));

        $this->assertFalse($this->policy->create($dentist));
        $this->assertFalse($this->policy->delete($dentist, $lab));
    }

    public function test_any_role_can_view_labs(): void
    {
        $lab = Lab::factory()->create();

        $this->assertTrue($this->policy->viewAny(User::factory()->dentist()->create()));
        $this->assertTrue($this->policy->view(User::factory()->create(['role' => 'receptionist']), $lab));
    }
}
