<?php

namespace Tests\Unit\Policies;

use App\Models\LabCase;
use App\Models\User;
use App\Policies\LabCasePolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 4 (Advanced Permissions & Audit) Step 2 — baseline regression coverage; zero dedicated
 * test coverage existed before this Policy was wired to `hasPermission()`.
 */
class LabCasePolicyTest extends TestCase
{
    use RefreshDatabase;

    private LabCasePolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new LabCasePolicy;
    }

    public function test_any_role_can_view_lab_cases(): void
    {
        $labCase = LabCase::factory()->create();

        $this->assertTrue($this->policy->viewAny(User::factory()->create(['role' => 'receptionist'])));
        $this->assertTrue($this->policy->view(User::factory()->dentist()->create(), $labCase));
    }

    public function test_only_admin_and_dentist_can_prescribe_or_cancel(): void
    {
        $labCase = LabCase::factory()->create();
        $admin = User::factory()->admin()->create();
        $dentist = User::factory()->dentist()->create();
        $receptionist = User::factory()->create(['role' => 'receptionist']);

        $this->assertTrue($this->policy->create($admin));
        $this->assertTrue($this->policy->update($dentist, $labCase));
        $this->assertTrue($this->policy->cancel($admin, $labCase));

        $this->assertFalse($this->policy->create($receptionist));
        $this->assertFalse($this->policy->cancel($receptionist, $labCase));
    }

    public function test_only_admin_and_receptionist_can_process_logistics(): void
    {
        $labCase = LabCase::factory()->create();
        $admin = User::factory()->admin()->create();
        $dentist = User::factory()->dentist()->create();
        $receptionist = User::factory()->create(['role' => 'receptionist']);

        $this->assertTrue($this->policy->send($admin, $labCase));
        $this->assertTrue($this->policy->receive($receptionist, $labCase));
        $this->assertTrue($this->policy->qualityCheck($admin, $labCase));

        $this->assertFalse($this->policy->send($dentist, $labCase));
        $this->assertFalse($this->policy->receive($dentist, $labCase));
    }

    public function test_only_admin_can_delete(): void
    {
        $labCase = LabCase::factory()->create();

        $this->assertTrue($this->policy->delete(User::factory()->admin()->create(), $labCase));
        $this->assertFalse($this->policy->delete(User::factory()->dentist()->create(), $labCase));
    }
}
