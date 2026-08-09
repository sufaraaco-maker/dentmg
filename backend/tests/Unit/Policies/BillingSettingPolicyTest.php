<?php

namespace Tests\Unit\Policies;

use App\Models\User;
use App\Policies\BillingSettingPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 4 (Advanced Permissions & Audit) Step 2 — baseline regression coverage; zero dedicated
 * test coverage existed before this Policy was wired to `hasPermission()`.
 */
class BillingSettingPolicyTest extends TestCase
{
    use RefreshDatabase;

    private BillingSettingPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new BillingSettingPolicy;
    }

    public function test_only_admin_can_view_or_update_billing_settings(): void
    {
        $admin = User::factory()->admin()->create();
        $dentist = User::factory()->dentist()->create();
        $receptionist = User::factory()->create(['role' => 'receptionist']);

        $this->assertTrue($this->policy->view($admin));
        $this->assertTrue($this->policy->update($admin));

        $this->assertFalse($this->policy->view($dentist));
        $this->assertFalse($this->policy->update($receptionist));
    }
}
