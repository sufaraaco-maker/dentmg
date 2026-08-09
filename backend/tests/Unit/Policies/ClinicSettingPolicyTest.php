<?php

namespace Tests\Unit\Policies;

use App\Models\User;
use App\Policies\ClinicSettingPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 4 (Advanced Permissions & Audit) Step 2 — baseline regression coverage; zero dedicated
 * test coverage existed before this Policy was wired to `hasPermission()`.
 */
class ClinicSettingPolicyTest extends TestCase
{
    use RefreshDatabase;

    private ClinicSettingPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new ClinicSettingPolicy;
    }

    public function test_any_role_can_view_clinic_settings(): void
    {
        $this->assertTrue($this->policy->view(User::factory()->dentist()->create()));
        $this->assertTrue($this->policy->view(User::factory()->create(['role' => 'receptionist'])));
    }

    public function test_only_admin_can_update_clinic_settings(): void
    {
        $this->assertTrue($this->policy->update(User::factory()->admin()->create()));
        $this->assertFalse($this->policy->update(User::factory()->dentist()->create()));
    }
}
