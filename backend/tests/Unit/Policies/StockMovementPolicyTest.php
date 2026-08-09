<?php

namespace Tests\Unit\Policies;

use App\Models\StockMovement;
use App\Models\User;
use App\Policies\StockMovementPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 4 (Advanced Permissions & Audit) Step 2 — baseline regression coverage; zero dedicated
 * test coverage existed before this Policy was wired to `hasPermission()`.
 */
class StockMovementPolicyTest extends TestCase
{
    use RefreshDatabase;

    private StockMovementPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new StockMovementPolicy;
    }

    public function test_all_three_roles_can_view_and_record_movements(): void
    {
        $movement = StockMovement::factory()->create();
        $admin = User::factory()->admin()->create();
        $dentist = User::factory()->dentist()->create();
        $receptionist = User::factory()->create(['role' => 'receptionist']);

        foreach ([$admin, $dentist, $receptionist] as $actor) {
            $this->assertTrue($this->policy->viewAny($actor));
            $this->assertTrue($this->policy->view($actor, $movement));
            $this->assertTrue($this->policy->create($actor));
        }
    }
}
