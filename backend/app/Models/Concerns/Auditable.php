<?php

namespace App\Models\Concerns;

use App\Models\AuditLog;
use App\Observers\AuditObserver;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Opt-in trait: records a row in audit_logs on create/update/delete.
 * Add `use Auditable;` to any model that handles sensitive data.
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::observe(AuditObserver::class);
    }

    public function auditLogs(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'auditable')->latest('created_at');
    }
}
