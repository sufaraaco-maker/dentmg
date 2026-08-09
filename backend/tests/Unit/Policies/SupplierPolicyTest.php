<?php

namespace Tests\Unit\Policies;

use App\Models\Supplier;
use App\Models\User;
use App\Policies\SupplierPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 4 (Advanced Permissions & Audit) Step 2 — baseline regression coverage; zero dedicated
 * test coverage existed before this Policy was wired to `hasPermission()`.
 */
class SupplierPolicyTest extends TestCase
{
    use RefreshDatabase;

    private SupplierPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new SupplierPolicy;
    }

    public function test_only_admin_can_create_update_or_delete_suppliers(): void
    {
        $supplier = Supplier::factory()->create();
        $admin = User::factory()->admin()->create();
        $receptionist = User::factory()->create(['role' => 'receptionist']);

        $this->assertTrue($this->policy->create($admin));
        $this->assertTrue($this->policy->update($admin, $supplier));
        $this->assertTrue($this->policy->delete($admin, $supplier));

        $this->assertFalse($this->policy->create($receptionist));
        $this->assertFalse($this->policy->delete($receptionist, $supplier));
    }

    public function test_any_role_can_view_suppliers(): void
    {
        $supplier = Supplier::factory()->create();

        $this->assertTrue($this->policy->viewAny(User::factory()->dentist()->create()));
        $this->assertTrue($this->policy->view(User::factory()->create(['role' => 'receptionist']), $supplier));
    }
}
