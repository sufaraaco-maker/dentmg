<?php

namespace Tests\Unit\Policies;

use App\Models\SupplyCategory;
use App\Models\User;
use App\Policies\SupplyCategoryPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 4 (Advanced Permissions & Audit) Step 2 — baseline regression coverage; zero dedicated
 * test coverage existed before this Policy was wired to `hasPermission()`.
 */
class SupplyCategoryPolicyTest extends TestCase
{
    use RefreshDatabase;

    private SupplyCategoryPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new SupplyCategoryPolicy;
    }

    public function test_only_admin_can_create_update_or_delete_supply_categories(): void
    {
        $category = SupplyCategory::factory()->create();
        $admin = User::factory()->admin()->create();
        $dentist = User::factory()->dentist()->create();

        $this->assertTrue($this->policy->create($admin));
        $this->assertTrue($this->policy->update($admin, $category));
        $this->assertTrue($this->policy->delete($admin, $category));

        $this->assertFalse($this->policy->create($dentist));
        $this->assertFalse($this->policy->delete($dentist, $category));
    }

    public function test_any_role_can_view_supply_categories(): void
    {
        $category = SupplyCategory::factory()->create();

        $this->assertTrue($this->policy->viewAny(User::factory()->dentist()->create()));
        $this->assertTrue($this->policy->view(User::factory()->create(['role' => 'receptionist']), $category));
    }
}
