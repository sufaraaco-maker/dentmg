<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PermissionResource;
use App\Services\RolePermissionService;

/**
 * The fixed permission catalog (Phase 4 design doc §1.1) — read-only, admin-only. Powers the
 * Permissions matrix UI's rows; the matrix's columns/cells are RolePermissionController's concern.
 */
class PermissionController extends Controller
{
    public function __construct(private RolePermissionService $rolePermissionService) {}

    public function index()
    {
        $this->authorize('manage-permissions');

        return PermissionResource::collection($this->rolePermissionService->catalog());
    }
}
