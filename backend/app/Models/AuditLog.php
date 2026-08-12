<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;

class AuditLog extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'auditable_type',
        'auditable_id',
        'action',
        'changes',
        'old_values',
        'ip_address',
        'user_agent',
        'context',
    ];

    protected function casts(): array
    {
        return [
            'changes' => 'array',
            'old_values' => 'array',
            'context' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * Phase 4 (Advanced Permissions & Audit) Step 3 design doc §2.5 — write-once by construction
     * (no update/destroy route or service method exists anywhere). This is the belt-and-suspenders
     * layer: even a future accidental `$log->update(...)`/`$log->delete()` in code is blocked
     * loudly, not silently allowed. Deliberately NOT caught/swallowed anywhere else — a genuine
     * bug-catcher, unlike a failed *write* (AuditLogService::write() fails open by design).
     */
    protected static function booted(): void
    {
        // Phase 5C (Notification System — scheduled/administrative types) design doc §3.3a /
        // Decision D14 — a real bug this phase's own timezone fix exposed, not a pre-existing one:
        // this table is the only one in the schema whose `created_at` was left to the migration's
        // `useCurrent()` DB-level default (necessary because `$timestamps = false` above, itself
        // required since there is no `updated_at` column). A DB-level default runs on the
        // database's own real-UTC clock, never touched by `config('app.timezone')` — every other
        // timestamp in this project is set by PHP's `now()`, which is clinic wall-clock time as of
        // D14. Left alone, `AuditLog::created()`'s own `created_at` would sit ~`APP_TIMEZONE`'s
        // offset away from every `now()` comparison run against it (confirmed: `AuditLogObserver`'s
        // login-failure window query silently matched zero rows before this fix). Setting it here,
        // on the same clock as everything else, if the caller hasn't already, closes that gap for
        // every creation path — the service and any direct `::create()` call alike — not just the
        // one that happened to surface it.
        static::creating(function (self $log): void {
            $log->created_at ??= now();
        });

        static::updating(function (): void {
            throw new LogicException('Audit log entries are immutable — updates are not permitted.');
        });

        static::deleting(function (): void {
            throw new LogicException('Audit log entries are immutable — deletes are not permitted.');
        });
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }
}
