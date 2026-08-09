<?php

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * The admin-configurable role<->permission matrix (Phase 4 design doc §1.2). Seeded to exactly
 * mirror the 27 Policy classes' pre-Phase-4 behavior — day 1, no effective permission changes.
 */
class RolePermission extends Model
{
    use HasUuids;

    protected $fillable = [
        'role',
        'permission_key',
    ];

    protected function casts(): array
    {
        return [
            'role' => UserRole::class,
        ];
    }

    public static function cacheKeyFor(UserRole $role): string
    {
        return "role_permissions:{$role->value}";
    }

    /**
     * @return list<string>
     */
    public static function permissionKeysForRole(UserRole $role): array
    {
        return Cache::remember(
            self::cacheKeyFor($role),
            now()->addHour(),
            fn () => self::query()->where('role', $role->value)->pluck('permission_key')->all(),
        );
    }

    /**
     * Called after any write to this table — the matrix changes rarely (an admin action), so
     * flushing all 3 roles' cache entries unconditionally is simpler and safer than tracking
     * exactly which roles a given update touched.
     */
    public static function flushCache(): void
    {
        foreach (UserRole::cases() as $role) {
            Cache::forget(self::cacheKeyFor($role));
        }
    }
}
