<?php

namespace Tests\Unit\Policies;

use App\Models\Patient;
use App\Models\User;
use App\Policies\PatientPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 4 (Advanced Permissions & Audit) Step 2 — baseline regression coverage; zero dedicated
 * test coverage existed before this Policy was wired to `hasPermission()`.
 */
class PatientPolicyTest extends TestCase
{
    use RefreshDatabase;

    private PatientPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new PatientPolicy;
    }

    public function test_any_role_can_view_patients(): void
    {
        $patient = Patient::factory()->create();

        $this->assertTrue($this->policy->viewAny(User::factory()->dentist()->create()));
        $this->assertTrue($this->policy->view(User::factory()->create(['role' => 'receptionist']), $patient));
    }

    public function test_only_admin_and_receptionist_can_create_or_update(): void
    {
        $patient = Patient::factory()->create();
        $admin = User::factory()->admin()->create();
        $receptionist = User::factory()->create(['role' => 'receptionist']);
        $dentist = User::factory()->dentist()->create();

        $this->assertTrue($this->policy->create($admin));
        $this->assertTrue($this->policy->update($receptionist, $patient));

        $this->assertFalse($this->policy->create($dentist));
        $this->assertFalse($this->policy->update($dentist, $patient));
    }

    public function test_only_admin_can_delete_or_view_audit_logs(): void
    {
        $patient = Patient::factory()->create();
        $admin = User::factory()->admin()->create();
        $receptionist = User::factory()->create(['role' => 'receptionist']);

        $this->assertTrue($this->policy->delete($admin, $patient));
        $this->assertTrue($this->policy->viewAuditLogs($admin));

        $this->assertFalse($this->policy->delete($receptionist, $patient));
        $this->assertFalse($this->policy->viewAuditLogs($receptionist));
    }
}
