<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\Permission;
use App\Models\RolePermission;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class RolePermissionService
{
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
     * @param  array<string, list<string>>  $assignments
     */
    public function updateMatrix(array $assignments): void
    {
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
    }
}
