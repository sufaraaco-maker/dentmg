<?php

namespace App\Observers;

use App\Services\AuditLogService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

class AuditObserver
{
    public function __construct(private AuditLogService $auditLogService) {}

    public function created(Model $model): void
    {
        $this->auditLogService->record($model, 'created', $model->getAttributes());
    }

    /**
     * Phase 4 (Advanced Permissions & Audit) Step 3: captures `old_values` for the fields that
     * actually changed, alongside the existing `changes` (new values) — a real before/after diff,
     * not just the new state.
     */
    public function updated(Model $model): void
    {
        $changes = $model->getChanges();
        $original = Arr::only($model->getOriginal(), array_keys($changes));

        $this->auditLogService->record($model, 'updated', $changes, $original);
    }

    /**
     * Captures the record's full state immediately before deletion into `old_values` — previously
     * an empty array, meaning a deleted row's last-known state was unrecoverable from the audit
     * trail even though this app soft-deletes almost everything.
     */
    public function deleted(Model $model): void
    {
        $this->auditLogService->record($model, 'deleted', [], $model->getAttributes());
    }
}
