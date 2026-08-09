<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * The fixed permission catalog (Phase 4 design doc §1.1) — backend-owned, not admin-editable.
 * Only App\Models\RolePermission (the role<->permission assignment) is admin-editable.
 */
class Permission extends Model
{
    use HasUuids;

    protected $fillable = [
        'key',
        'group',
        'description',
    ];
}
