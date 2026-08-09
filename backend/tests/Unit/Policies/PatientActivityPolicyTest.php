<?php

namespace Tests\Unit\Policies;

use App\Models\User;
use App\Policies\PatientActivityPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 4 (Advanced Permissions & Audit) Step 2 — baseline regression coverage; zero dedicated
 * test coverage existed before this Policy was wired to `hasPermission()`. `allowedCategories()`'s
 * per-category composition of other Policies is exercised end-to-end by
 * tests/Feature/PatientActivityTest.php, not duplicated here.
 */
class PatientActivityPolicyTest extends TestCase
{
    use RefreshDatabase;

    private PatientActivityPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new PatientActivityPolicy;
    }

    public function test_any_role_can_view_any(): void
    {
        $this->assertTrue($this->policy->viewAny(User::factory()->admin()->create()));
        $this->assertTrue($this->policy->viewAny(User::factory()->dentist()->create()));
        $this->assertTrue($this->policy->viewAny(User::factory()->create(['role' => 'receptionist'])));
    }
}
