<?php

namespace App\Observers;

use App\Services\AuditLogService;
use Illuminate\Database\Eloquent\Model;

class AuditObserver
{
    public function __construct(private AuditLogService $auditLogService) {}

    public function created(Model $model): void
    {
        $this->auditLogService->record($model, 'created', $model->getAttributes());
    }

    public function updated(Model $model): void
    {
        $this->auditLogService->record($model, 'updated', $model->getChanges());
    }

    public function deleted(Model $model): void
    {
        $this->auditLogService->record($model, 'deleted', []);
    }
}
