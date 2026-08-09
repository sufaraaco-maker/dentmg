<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Throwable;

class AuditLogService
{
    /**
     * Fields that should never be persisted into the audit trail, regardless of model — applied
     * to `changes`, `old_values`, and `context` alike (Phase 4 Step 3 design doc §2.1/§5).
     *
     * `ai_assistant_api_key` (`ClinicSetting`, docs/modules/ai-assistant-settings-api-key-design.md
     * §4/§8): without this, the `encrypted` cast's decrypted value would be written straight into
     * `audit_logs.changes` on every save via `getChanges()`, defeating encryption-at-rest for
     * anyone with audit-log read access.
     */
    private const EXCLUDED_KEYS = [
        'password', 'remember_token', 'created_at', 'updated_at', 'deleted_at', 'ai_assistant_api_key',
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
        try {
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
