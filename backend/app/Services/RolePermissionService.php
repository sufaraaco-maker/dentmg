<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\Permission;
use App\Models\RolePermission;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class RolePermissionService
{
    public function __construct(private AuditLogService $auditLogService) {}

    /**
     * @return Collection<int, Permission>
     */
    public function catalog(): Collection
    {
        return Permission::query()->orderBy('group')->orderBy('key')->get();
    }

    /**
     * @return array<string, list<string>>
     */
    public function matrix(): array
    {
        $rows = RolePermission::all();

        return collect(UserRole::cases())
            ->mapWithKeys(fn (UserRole $role) => [
                $role->value => $rows->where('role', $role)->pluck('permission_key')->sort()->values()->all(),
            ])
            ->all();
    }

    /**
     * Full replace, per role (design doc §1.5 — the payload is the complete desired assignment,
     * not a diff).
     *
     * Phase 4 Step 3 design doc §3: a `role_permissions` change is itself audit-worthy — "who
     * changed what a role can do, and when." Not modeled as a per-row Auditable (rows are managed
     * as a set via PUT, not individually CRUD'd) — the full before/after matrix is recorded
     * directly via `AuditLogService::recordEvent()` instead, deliberately holding only the
     * permission-key arrays (no secrets, nothing beyond what's needed to review the change).
     *
     * @param  array<string, list<string>>  $assignments
     */
    public function updateMatrix(array $assignments): void
    {
        $before = $this->matrix();

        DB::transaction(function () use ($assignments) {
            RolePermission::query()->delete();

            foreach ($assignments as $role => $permissionKeys) {
                foreach (array_unique($permissionKeys) as $permissionKey) {
                    RolePermission::create([
                        'role' => $role,
                        'permission_key' => $permissionKey,
                    ]);
                }
            }
        });

        RolePermission::flushCache();

        $after = $this->matrix();

        $this->auditLogService->recordEvent(
            RolePermission::class,
            null,
            'role_permissions_updated',
            newValues: $after,
            oldValues: $before,
        );
    }
}
