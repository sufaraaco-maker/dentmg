<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AuditLog\AuditLogIndexRequest;
use App\Http\Resources\AuditLogResource;
use App\Services\AuditLogService;

/**
 * The general (non-patient-scoped) Audit Log viewer — Phase 4 Step 3 design doc §2.6. Admin-only
 * via `AuditLogIndexRequest::authorize()`. The existing per-patient viewer
 * (`PatientController::auditLogs()`) is unchanged and stays useful for in-context review.
 */
class AuditLogController extends Controller
{
    public function __construct(private AuditLogService $auditLogService) {}

    public function index(AuditLogIndexRequest $request)
    {
        return AuditLogResource::collection(
            $this->auditLogService->search($request->validated())
        );
    }
}
