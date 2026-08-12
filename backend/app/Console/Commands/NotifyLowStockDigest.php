<?php

namespace App\Console\Commands;

use App\Models\Supply;
use App\Services\NotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Phase 5C design doc §2/§3.2/§14 — type 10. Scheduled daily (`routes/console.php`) at 08:00 clinic
 * wall-clock time (Decision D11/D14), start of the clinic day rather than the middle of the night.
 * One digest notification, not one per item — reuses `Supply::lowStock()` (the same query already
 * behind `SupplyController::lowStock()`/the Dashboard widget) and, if non-empty, dispatches once
 * using its most-urgent row as the notification's representative `$subject` (design doc §3.2,
 * `LowStockDigestNotification`'s own docblock).
 */
class NotifyLowStockDigest extends Command
{
    protected $signature = 'notifications:low-stock-digest';

    protected $description = 'Notify admins and receptionists of low-stock supplies as one daily digest (Phase 5C, type 10)';

    public function handle(NotificationService $notificationService): int
    {
        $mostUrgent = Supply::query()->active()->lowStock()->first();

        if ($mostUrgent === null) {
            $this->info('No low-stock supplies today.');

            return self::SUCCESS;
        }

        try {
            $notificationService->dispatchFor($mostUrgent, null, 'inventory.low_stock', deduplicateUnread: true);
        } catch (Throwable $exception) {
            Log::error('Scheduled notification dispatch failed', [
                'command' => self::class,
                'subject_type' => Supply::class,
                'subject_id' => $mostUrgent->id,
                'exception' => $exception->getMessage(),
            ]);
        }

        $this->info('Low-stock digest dispatched.');

        return self::SUCCESS;
    }
}
