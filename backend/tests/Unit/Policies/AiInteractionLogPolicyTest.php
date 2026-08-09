<?php

namespace Tests\Unit\Policies;

use App\Models\AiInteractionLog;
use App\Models\User;
use App\Policies\AiInteractionLogPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 4 (Advanced Permissions & Audit) Step 2 — baseline regression coverage added because this
 * Policy is now wired to `hasPermission()` and had zero dedicated test coverage before.
 */
class AiInteractionLogPolicyTest extends TestCase
{
    use RefreshDatabase;

    private AiInteractionLogPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new AiInteractionLogPolicy;
    }

    public function test_any_role_can_view_any(): void
    {
        $this->assertTrue($this->policy->viewAny(User::factory()->admin()->create()));
        $this->assertTrue($this->policy->viewAny(User::factory()->dentist()->create()));
        $this->assertTrue($this->policy->viewAny(User::factory()->create(['role' => 'receptionist'])));
    }

    public function test_owner_can_view_their_own_log_entry(): void
    {
        $owner = User::factory()->dentist()->create();
        $log = AiInteractionLog::factory()->create(['user_id' => $owner->id]);

        $this->assertTrue($this->policy->view($owner, $log));
    }

    public function test_admin_can_view_another_users_log_entry(): void
    {
        $admin = User::factory()->admin()->create();
        $owner = User::factory()->dentist()->create();
        $log = AiInteractionLog::factory()->create(['user_id' => $owner->id]);

        $this->assertTrue($this->policy->view($admin, $log));
    }

    public function test_non_owner_non_admin_cannot_view_another_users_log_entry(): void
    {
        $viewer = User::factory()->create(['role' => 'receptionist']);
        $owner = User::factory()->dentist()->create();
        $log = AiInteractionLog::factory()->create(['user_id' => $owner->id]);

        $this->assertFalse($this->policy->view($viewer, $log));
    }
}
