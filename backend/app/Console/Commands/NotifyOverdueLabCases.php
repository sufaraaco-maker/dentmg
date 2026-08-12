<?php

namespace App\Console\Commands;

use App\Models\LabCase;
use App\Services\NotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Phase 5C design doc §2/§14 — type 9. Scheduled daily (`routes/console.php`) at 03:30 clinic
 * wall-clock time (Decision D11/D14). Reuses `LabCase::dueOrOverdue()` — the same scope the
 * Dashboard widget already computes — rather than reimplementing the "overdue" definition here.
 */
class NotifyOverdueLabCases extends Command
{
    protected $signature = 'notifications:lab-cases-overdue';

    protected $description = 'Notify the prescribing dentist and receptionists of overdue lab cases (Phase 5C, type 9)';

    public function handle(NotificationService $notificationService): int
    {
        $overdueCases = LabCase::query()->dueOrOverdue()->get();

        foreach ($overdueCases as $labCase) {
            // Fail-open per lab case, matching SendsNotifications/AuditLogService: one bad row must
            // never stop the rest of the batch from being notified.
            try {
                $notificationService->dispatchFor($labCase, null, 'lab_case.overdue', deduplicateUnread: true);
            } catch (Throwable $exception) {
                Log::error('Scheduled notification dispatch failed', [
                    'command' => self::class,
                    'subject_type' => LabCase::class,
                    'subject_id' => $labCase->id,
                    'exception' => $exception->getMessage(),
                ]);
            }
        }

        $this->info("Checked {$overdueCases->count()} overdue lab case(s).");

        return self::SUCCESS;
    }
}
