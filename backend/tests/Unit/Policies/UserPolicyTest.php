<?php

namespace Tests\Unit\Policies;

use App\Models\User;
use App\Policies\UserPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 4 (Advanced Permissions & Audit) Step 2 — baseline regression coverage; zero dedicated
 * test coverage existed before this Policy was wired to `hasPermission()`.
 */
class UserPolicyTest extends TestCase
{
    use RefreshDatabase;

    private UserPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new UserPolicy;
    }

    public function test_any_role_can_view_users(): void
    {
        $target = User::factory()->dentist()->create();

        $this->assertTrue($this->policy->viewAny(User::factory()->dentist()->create()));
        $this->assertTrue($this->policy->view(User::factory()->create(['role' => 'receptionist']), $target));
    }

    public function test_only_admin_can_create_or_update_users(): void
    {
        $target = User::factory()->dentist()->create();
        $admin = User::factory()->admin()->create();
        $receptionist = User::factory()->create(['role' => 'receptionist']);

        $this->assertTrue($this->policy->create($admin));
        $this->assertTrue($this->policy->update($admin, $target));

        $this->assertFalse($this->policy->create($receptionist));
        $this->assertFalse($this->policy->update($receptionist, $target));
    }

    public function test_admin_can_delete_another_user_but_not_themselves(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->dentist()->create();

        $this->assertTrue($this->policy->delete($admin, $target));
        $this->assertFalse($this->policy->delete($admin, $admin));
    }

    public function test_non_admin_cannot_delete_any_user(): void
    {
        $receptionist = User::factory()->create(['role' => 'receptionist']);
        $target = User::factory()->dentist()->create();

        $this->assertFalse($this->policy->delete($receptionist, $target));
    }
}
