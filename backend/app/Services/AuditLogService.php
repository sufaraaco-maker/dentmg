<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class AuditLogService
{
    /**
     * Fields that should never be persisted into the audit trail, regardless of model.
     */
    private const EXCLUDED_KEYS = ['password', 'remember_token', 'created_at', 'updated_at', 'deleted_at'];

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
