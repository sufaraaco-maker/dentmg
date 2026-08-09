<?php

namespace Tests\Unit\Policies;

use App\Models\PatientDocument;
use App\Models\User;
use App\Policies\PatientDocumentPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 4 (Advanced Permissions & Audit) Step 2 — baseline regression coverage; zero dedicated
 * test coverage existed before this Policy was wired to `hasPermission()`.
 */
class PatientDocumentPolicyTest extends TestCase
{
    use RefreshDatabase;

    private PatientDocumentPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new PatientDocumentPolicy;
    }

    public function test_all_three_roles_can_view_and_manage_documents(): void
    {
        $document = PatientDocument::factory()->create();
        $admin = User::factory()->admin()->create();
        $dentist = User::factory()->dentist()->create();
        $receptionist = User::factory()->create(['role' => 'receptionist']);

        foreach ([$admin, $dentist, $receptionist] as $actor) {
            $this->assertTrue($this->policy->viewAny($actor));
            $this->assertTrue($this->policy->view($actor, $document));
            $this->assertTrue($this->policy->create($actor));
            $this->assertTrue($this->policy->update($actor, $document));
        }
    }

    public function test_only_admin_can_delete(): void
    {
        $document = PatientDocument::factory()->create();

        $this->assertTrue($this->policy->delete(User::factory()->admin()->create(), $document));
        $this->assertFalse($this->policy->delete(User::factory()->dentist()->create(), $document));
        $this->assertFalse($this->policy->delete(User::factory()->create(['role' => 'receptionist']), $document));
    }
}
