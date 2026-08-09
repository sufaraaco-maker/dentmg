<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\RolePermission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $this->assertCount(68, $response->json());
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

        $this->assertCount(68, $matrix['admin']);
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
}
