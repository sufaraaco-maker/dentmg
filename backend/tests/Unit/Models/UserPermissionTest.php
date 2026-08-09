<?php

namespace Tests\Unit\Models;

use App\Enums\UserRole;
use App\Models\Permission;
use App\Models\RolePermission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 4 (Advanced Permissions & Audit) design doc §1.3 — `User::hasPermission()` is the
 * replacement for raw `$this->role === UserRole::X` checks, now wired into all 27 Policies
 * (Step 2). The permission catalog is seeded automatically for every RefreshDatabase test class —
 * see Tests\TestCase::setUp().
 */
class UserPermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_has_a_permission_seeded_admin_only(): void
    {
        $admin = User::factory()->admin()->create();

        $this->assertTrue($admin->hasPermission('users.manage'));
    }

    public function test_dentist_lacks_a_permission_seeded_admin_only(): void
    {
        $dentist = User::factory()->dentist()->create();

        $this->assertFalse($dentist->hasPermission('users.manage'));
    }

    public function test_receptionist_lacks_clinical_notes_view_by_default(): void
    {
        $receptionist = User::factory()->create(['role' => 'receptionist']);

        $this->assertFalse($receptionist->hasPermission('clinical_notes.view'));
    }

    public function test_all_three_roles_have_a_permission_seeded_open_to_all(): void
    {
        $admin = User::factory()->admin()->create();
        $dentist = User::factory()->dentist()->create();
        $receptionist = User::factory()->create(['role' => 'receptionist']);

        $this->assertTrue($admin->hasPermission('patients.view'));
        $this->assertTrue($dentist->hasPermission('patients.view'));
        $this->assertTrue($receptionist->hasPermission('patients.view'));
    }

    public function test_unknown_permission_key_is_false_for_every_role(): void
    {
        $admin = User::factory()->admin()->create();

        $this->assertFalse($admin->hasPermission('not.a.real.permission'));
    }

    public function test_permission_lookup_is_cached_per_role(): void
    {
        $dentist = User::factory()->dentist()->create();

        $this->assertFalse($dentist->hasPermission('users.manage'));

        // Mutate the underlying table directly (bypassing the service/flushCache()) — the cached
        // lookup must still reflect the stale seeded state, proving the cache is real and not a
        // no-op.
        RolePermission::create([
            'role' => UserRole::Dentist->value,
            'permission_key' => 'users.manage',
        ]);

        $this->assertFalse($dentist->hasPermission('users.manage'));
    }

    public function test_flushing_the_cache_picks_up_a_direct_table_change(): void
    {
        $dentist = User::factory()->dentist()->create();

        $this->assertFalse($dentist->hasPermission('users.manage'));

        RolePermission::create([
            'role' => UserRole::Dentist->value,
            'permission_key' => 'users.manage',
        ]);
        RolePermission::flushCache();

        $this->assertTrue($dentist->hasPermission('users.manage'));
    }

    public function test_permission_catalog_matches_the_27_policy_derived_count(): void
    {
        $this->assertSame(68, Permission::count());
    }
}
