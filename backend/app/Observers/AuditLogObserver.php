<?php

namespace App\Observers;

use App\Models\AuditLog;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Phase 5C (Notification System — scheduled/administrative types) design doc §0/§2/§3.4.
 *
 * `AuditLogService::write()` always goes through `AuditLog::create()` — a real Eloquent `create()`
 * (confirmed by reading it), so its own `created` event already fires for free. Types 12-13 need no
 * scheduler entry at all because of this: a security event is caught the moment it happens, not up
 * to 24 hours late on the next scheduled tick — a strictly better outcome than the daily-cron
 * mechanism types 9-11 need, made possible only because the underlying data source is itself an
 * Eloquent write, unlike "a lab case's due date has passed," which no write ever announces.
 *
 * Registered directly in `AppServiceProvider::boot()` (`AuditLog::observe(...)`), not via a trait on
 * the model — this observes one model for one narrow purpose, unlike `Auditable`, which many models
 * mix in for a much broader concern.
 */
class AuditLogObserver
{
    /** Decision D9 (design doc §16a): approved as recommended. */
    private const LOGIN_FAILURE_THRESHOLD = 5;

    private const LOGIN_FAILURE_WINDOW_MINUTES = 15;

    public function __construct(private NotificationService $notificationService) {}

    /**
     * Fail-open, exactly as `SendsNotifications` and `AuditLogService` already are: a notification
     * failure must never break the audit write that triggered it, nor the request/command that
     * triggered *that*.
     */
    public function created(AuditLog $auditLog): void
    {
        try {
            match ($auditLog->action) {
                'login_failed' => $this->handleLoginFailed($auditLog),
                'role_permissions_updated' => $this->handlePermissionsMatrixUpdated($auditLog),
                default => null,
            };
        } catch (Throwable $exception) {
            Log::error('Audit-log-triggered notification dispatch failed', [
                'action' => $auditLog->action,
                'audit_log_id' => $auditLog->id,
                'exception' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * Notify exactly once per window, with no extra dedup query: `$count` only ever increases by
     * one per invocation (one new row triggered this call), so checking for an *exact* match against
     * the threshold — not `>=` — fires on the attempt that first crosses it and silently does
     * nothing on every attempt after, for the rest of the window (Decision D9). A fresh burst after
     * the window has fully elapsed legitimately earns a fresh alert.
     */
    private function handleLoginFailed(AuditLog $auditLog): void
    {
        $email = $auditLog->context['email'] ?? null;

        if ($email === null) {
            return;
        }

        $recentFailureCount = AuditLog::query()
            ->where('action', 'login_failed')
            ->where('context->email', $email)
            ->where('created_at', '>=', now()->subMinutes(self::LOGIN_FAILURE_WINDOW_MINUTES))
            ->count();

        if ($recentFailureCount !== self::LOGIN_FAILURE_THRESHOLD) {
            return;
        }

        $this->notificationService->dispatchFor($auditLog, null, 'security.repeated_login_failures');
    }

    private function handlePermissionsMatrixUpdated(AuditLog $auditLog): void
    {
        $this->notificationService->dispatchFor($auditLog, $auditLog->user, 'permissions.matrix_updated');
    }
}
