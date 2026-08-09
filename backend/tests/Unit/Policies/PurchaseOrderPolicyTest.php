<?php

namespace Tests\Unit\Policies;

use App\Models\PurchaseOrder;
use App\Models\User;
use App\Policies\PurchaseOrderPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 4 (Advanced Permissions & Audit) Step 2 — baseline regression coverage; zero dedicated
 * test coverage existed before this Policy was wired to `hasPermission()`.
 */
class PurchaseOrderPolicyTest extends TestCase
{
    use RefreshDatabase;

    private PurchaseOrderPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new PurchaseOrderPolicy;
    }

    public function test_any_role_can_view_purchase_orders(): void
    {
        $order = PurchaseOrder::factory()->create();

        $this->assertTrue($this->policy->viewAny(User::factory()->dentist()->create()));
        $this->assertTrue($this->policy->view(User::factory()->dentist()->create(), $order));
    }

    public function test_only_admin_and_receptionist_can_manage(): void
    {
        $order = PurchaseOrder::factory()->create();
        $admin = User::factory()->admin()->create();
        $receptionist = User::factory()->create(['role' => 'receptionist']);
        $dentist = User::factory()->dentist()->create();

        $this->assertTrue($this->policy->create($admin));
        $this->assertTrue($this->policy->update($receptionist, $order));
        $this->assertTrue($this->policy->place($admin, $order));
        $this->assertTrue($this->policy->receive($receptionist, $order));
        $this->assertTrue($this->policy->cancel($admin, $order));

        $this->assertFalse($this->policy->create($dentist));
        $this->assertFalse($this->policy->place($dentist, $order));
    }

    public function test_only_admin_can_delete(): void
    {
        $order = PurchaseOrder::factory()->create();

        $this->assertTrue($this->policy->delete(User::factory()->admin()->create(), $order));
        $this->assertFalse($this->policy->delete(User::factory()->create(['role' => 'receptionist']), $order));
    }
}
