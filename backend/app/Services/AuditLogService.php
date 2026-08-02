<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class AuditLogService
{
    /**
     * Fields that should never be persisted into the audit trail, regardless of model.
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
     * @param  array<string, mixed>  $changes
     */
    public function record(Model $model, string $action, array $changes): void
    {
        $changes = array_diff_key($changes, array_flip(self::EXCLUDED_KEYS));

        AuditLog::create([
            'user_id' => Auth::id(),
            'auditable_type' => $model::class,
            'auditable_id' => $model->getKey(),
            'action' => $action,
            'changes' => $changes ?: null,
        ]);
    }
}
