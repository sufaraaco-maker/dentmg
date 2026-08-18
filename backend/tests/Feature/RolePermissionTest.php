<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\RolePermission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Phase 4 (Advanced Permissions & Audit) Step 1 — Permissions Foundation. These endpoints are
 * gated by the `manage-permissions` Gate (hardcoded `isAdmin()`, design doc §1.4), not by the
 * matrix they themselves manage. The permission catalog is seeded automatically for every
 * RefreshDatabase test class — see Tests\TestCase::setUp().
 */
class RolePermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_list_permissions(): void
    {
        $response = $this->getJson('/api/permissions');

        $response->assertUnauthorized();
    }

    public function test_non_admin_cannot_list_permissions(): void
    {
        $actor = User::factory()->dentist()->create();

        $response = $this->actingAs($actor)->getJson('/api/permissions');

        $response->assertForbidden();
    }

    public function test_admin_can_list_the_permission_catalog(): void
    {
        $actor = User::factory()->admin()->create();

        $response = $this->actingAs($actor)->getJson('/api/permissions');

        $response->assertOk();
        $this->assertCount(66, $response->json());
        $response->assertJsonFragment(['key' => 'users.manage', 'group' => 'users']);
    }

    public function test_non_admin_cannot_view_the_role_permission_matrix(): void
    {
        $actor = User::factory()->create(['role' => 'receptionist']);

        $response = $this->actingAs($actor)->getJson('/api/role-permissions');

        $response->assertForbidden();
    }

    public function test_admin_can_view_the_seeded_role_permission_matrix(): void
    {
        $actor = User::factory()->admin()->create();

        $response = $this->actingAs($actor)->getJson('/api/role-permissions');

        $response->assertOk();
        $matrix = $response->json();

        $this->assertCount(66, $matrix['admin']);
        $this->assertContains('users.manage', $matrix['admin']);
        $this->assertNotContains('users.manage', $matrix['dentist']);
        $this->assertNotContains('users.manage', $matrix['receptionist']);
        $this->assertContains('appointments.view', $matrix['dentist']);
        $this->assertNotContains('clinical_notes.view', $matrix['receptionist']);
    }

    public function test_non_admin_cannot_update_the_role_permission_matrix(): void
    {
        $actor = User::factory()->dentist()->create();

        $response = $this->actingAs($actor)->putJson('/api/role-permissions', [
            'assignments' => [
                'admin' => ['users.manage', 'patients.view'],
                'dentist' => ['patients.view'],
                'receptionist' => ['patients.view'],
            ],
        ]);

        $response->assertForbidden();
    }

    public function test_admin_can_update_the_role_permission_matrix(): void
    {
        $actor = User::factory()->admin()->create();

        $response = $this->actingAs($actor)->putJson('/api/role-permissions', [
            'assignments' => [
                'admin' => ['users.manage', 'patients.view', 'patients.manage', 'patients.delete'],
                'dentist' => ['patients.view'],
                // Grant receptionists something they don't have by default, to prove this is a
                // real write, not just echoing the seed back.
                'receptionist' => ['patients.view', 'clinical_notes.view'],
            ],
        ]);

        $response->assertOk();
        $matrix = $response->json();

        $this->assertSame(['patients.delete', 'patients.manage', 'patients.view', 'users.manage'], $matrix['admin']);
        $this->assertSame(['patients.view'], $matrix['dentist']);
        $this->assertSame(['clinical_notes.view', 'patients.view'], $matrix['receptionist']);

        $this->assertDatabaseCount('role_permissions', 7);
    }

    public function test_removing_users_manage_from_admin_is_rejected(): void
    {
        $actor = User::factory()->admin()->create();

        $response = $this->actingAs($actor)->putJson('/api/role-permissions', [
            'assignments' => [
                'admin' => ['patients.view'],
                'dentist' => ['patients.view'],
                'receptionist' => ['patients.view'],
            ],
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('assignments.admin');

        // The matrix must be unchanged — a rejected update must not partially apply.
        $this->assertDatabaseHas('role_permissions', ['role' => 'admin', 'permission_key' => 'users.manage']);
    }

    public function test_update_rejects_an_unknown_permission_key(): void
    {
        $actor = User::factory()->admin()->create();

        $response = $this->actingAs($actor)->putJson('/api/role-permissions', [
            'assignments' => [
                'admin' => ['users.manage', 'not.a.real.permission'],
                'dentist' => [],
                'receptionist' => [],
            ],
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('assignments.admin.1');
    }

    /**
     * Phase 4 Step 3 design doc §3 — a role_permissions change is itself audit-worthy.
     */
    public function test_updating_the_matrix_is_audited_with_before_and_after(): void
    {
        $actor = User::factory()->admin()->create();
        $dentistPermissionsBefore = RolePermission::where('role', 'dentist')->pluck('permission_key')->sort()->values()->all();

        $this->actingAs($actor)->putJson('/api/role-permissions', [
            'assignments' => [
                'admin' => ['users.manage', 'patients.view'],
                'dentist' => ['patients.view', 'clinical_notes.view'],
                'receptionist' => ['patients.view'],
            ],
        ])->assertOk();

        $log = AuditLog::query()->where('action', 'role_permissions_updated')->firstOrFail();

        $this->assertSame($actor->id, $log->user_id);
        $this->assertSame($dentistPermissionsBefore, $log->old_values['dentist']);
        $this->assertSame(['clinical_notes.view', 'patients.view'], $log->changes['dentist']);
    }

    public function test_rejected_matrix_updates_are_not_audited(): void
    {
        $actor = User::factory()->admin()->create();

        $this->actingAs($actor)->putJson('/api/role-permissions', [
            'assignments' => [
                'admin' => ['patients.view'],
                'dentist' => ['patients.view'],
                'receptionist' => ['patients.view'],
            ],
        ])->assertUnprocessable();

        $this->assertDatabaseMissing('audit_logs', ['action' => 'role_permissions_updated']);
    }

    /**
     * Regression coverage for the 2026_08_18 cleanup migration
     * (`purge_orphaned_ai_interaction_log_permissions`): any environment that ran the
     * pre-PR-#52 seeder still has `ai_interaction_log.*` rows live in `permissions`/
     * `role_permissions` (TECH_DEBT.md's "Orphaned ai_interaction_log..." entry) even though a
     * fresh seed never creates them (see the 66-count assertions above). Simulates that stale
     * state directly, then re-runs the migration's own `up()` to prove it purges both tables via
     * `role_permissions.permission_key`'s `cascadeOnDelete()` FK and clears the cached matrix.
     */
    public function test_stale_ai_interaction_log_permissions_are_purged_by_the_cleanup_migration(): void
    {
        DB::table('permissions')->insert([
            'id' => (string) Str::uuid(),
            'key' => 'ai_interaction_log.view',
            'group' => 'ai_interaction_log',
            'description' => 'View AI interaction log',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('role_permissions')->insert([
            'id' => (string) Str::uuid(),
            'role' => 'admin',
            'permission_key' => 'ai_interaction_log.view',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertDatabaseHas('permissions', ['group' => 'ai_interaction_log']);
        $this->assertDatabaseHas('role_permissions', ['permission_key' => 'ai_interaction_log.view']);

        (require database_path('migrations/2026_08_18_000001_purge_orphaned_ai_interaction_log_permissions.php'))->up();

        $this->assertDatabaseMissing('permissions', ['group' => 'ai_interaction_log']);
        $this->assertDatabaseMissing('role_permissions', ['permission_key' => 'ai_interaction_log.view']);
        $this->assertNotContains('ai_interaction_log.view', RolePermission::permissionKeysForRole(UserRole::Admin));
    }
}
