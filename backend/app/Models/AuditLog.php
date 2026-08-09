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
