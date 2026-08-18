<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class AuditLogService
{
    /**
     * Fields that should never be persisted into the audit trail, regardless of model — applied
     * to `changes`, `old_values`, and `context` alike (Phase 4 Step 3 design doc §2.1/§5).
     */
    private const EXCLUDED_KEYS = [
        'password', 'remember_token', 'created_at', 'updated_at', 'deleted_at',
    ];

    /**
     * Model-observer path (`AuditObserver`) — a live model instance always exists.
     *
     * @param  array<string, mixed>  $newValues
     * @param  array<string, mixed>  $oldValues
     */
    public function record(Model $model, string $action, array $newValues, array $oldValues = []): void
    {
        $this->write($model::class, (string) $model->getKey(), $action, $newValues, $oldValues);
    }

    /**
     * Non-model-observer path — authentication events (no model changed) and cross-cutting events
     * like a role_permissions matrix update (not a single Eloquent model's create/update/delete).
     * `$auditableId` is nullable for events with no resolvable target (e.g. a failed login against
     * an email that matches no user).
     *
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $newValues
     * @param  array<string, mixed>  $oldValues
     */
    public function recordEvent(
        string $auditableType,
        ?string $auditableId,
        string $action,
        array $context = [],
        array $newValues = [],
        array $oldValues = [],
    ): void {
        $this->write($auditableType, $auditableId, $action, $newValues, $oldValues, $context);
    }

    /**
     * The general (non-patient-scoped) Audit Log viewer — Phase 4 Step 3 design doc §2.6.
     *
     * @param  array{user_id?: string, auditable_type?: string, action?: string, date_from?: string, date_to?: string}  $filters
     */
    public function search(array $filters): LengthAwarePaginator
    {
        return AuditLog::query()
            ->with('user')
            ->when($filters['user_id'] ?? null, fn ($query, $userId) => $query->where('user_id', $userId))
            ->when($filters['auditable_type'] ?? null, fn ($query, $type) => $query->where('auditable_type', $type))
            ->when($filters['action'] ?? null, fn ($query, $action) => $query->where('action', $action))
            ->when($filters['date_from'] ?? null, fn ($query, $date) => $query->whereDate('created_at', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($query, $date) => $query->whereDate('created_at', '<=', $date))
            ->latest('created_at')
            ->paginate(15);
    }

    /**
     * @param  array<string, mixed>  $newValues
     * @param  array<string, mixed>  $oldValues
     * @param  array<string, mixed>  $context
     */
    private function write(
        string $auditableType,
        ?string $auditableId,
        string $action,
        array $newValues,
        array $oldValues,
        array $context = [],
    ): void {
        // Fail-open for the business operation, fail-closed for sensitive data (confirmed with
        // the user during Step 3's design): a broken audit write must never take down a login, a
        // permission-matrix update, or any model save — but the failure itself is logged without
        // the actual (possibly still-unredacted-at-this-point) payload, so a redaction bug can
        // never leak sensitive values into the general application log as a side effect.
        //
        // The insert is wrapped in its own DB::transaction() so this actually holds on real
        // PostgreSQL, not just SQLite: a caller like RolePermissionService::updateMatrix() or
        // TreatmentPlanService::accept()/cancel() runs this write *inside* its own already-open
        // DB::transaction(). Catching the Throwable stops it from propagating, but on Postgres a
        // failed statement still leaves the enclosing transaction itself aborted ("current
        // transaction is aborted, commands ignored until end of transaction block") — the very
        // next query in that same transaction fails too, silently breaking the documented
        // fail-open guarantee for exactly the callers that need it most. Laravel's
        // DB::transaction() automatically issues a SAVEPOINT when already inside a transaction, so
        // a failure here only rolls back to that savepoint and the outer transaction stays usable.
        // Found 2026-08-18 by AuditLogTest's own regression test, which SQLite's laxer transaction
        // handling had let pass for every prior run (TECH_DEBT.md: the backend suite never used to
        // run against real Postgres).
        try {
            DB::transaction(function () use ($auditableType, $auditableId, $action, $newValues, $oldValues, $context) {
                AuditLog::create([
                    'user_id' => Auth::id(),
                    'auditable_type' => $auditableType,
                    'auditable_id' => $auditableId,
                    'action' => $action,
                    'changes' => $this->filter($newValues) ?: null,
                    'old_values' => $this->filter($oldValues) ?: null,
                    'context' => $this->filter($context) ?: null,
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                ]);
            });
        } catch (Throwable $e) {
            Log::error('Audit log write failed', [
                'action' => $action,
                'auditable_type' => $auditableType,
                'exception' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function filter(array $values): array
    {
        return array_diff_key($values, array_flip(self::EXCLUDED_KEYS));
    }
}
