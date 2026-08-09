<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\RolePermission\UpdateRolePermissionsRequest;
use App\Services\RolePermissionService;
use Illuminate\Http\JsonResponse;

/**
 * The admin-configurable role<->permission matrix (Phase 4 design doc §1.2/§1.5). Both actions are
 * gated by the `manage-permissions` Gate — a hardcoded `isAdmin()` check, never routed through the
 * matrix this controller edits (design doc §1.4 — avoids a lockout chicken-and-egg).
 */
class RolePermissionController extends Controller
{
    public function __construct(private RolePermissionService $rolePermissionService) {}

    public function index(): JsonResponse
    {
        $this->authorize('manage-permissions');

        return response()->json($this->rolePermissionService->matrix());
    }

    public function update(UpdateRolePermissionsRequest $request): JsonResponse
    {
        $this->rolePermissionService->updateMatrix($request->validated('assignments'));

        return response()->json($this->rolePermissionService->matrix());
    }
}
