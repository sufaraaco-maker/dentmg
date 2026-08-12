<?php

namespace App\Console\Commands;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Services\NotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Phase 5C design doc §2/§14 — type 11. Scheduled daily (`routes/console.php`) at 17:00 clinic
 * wall-clock time (Decision D11/D14), end of the clinic day — gives front desk the whole next
 * morning to call and confirm before the appointment itself. No ready-made "tomorrow" scope existed
 * anywhere in the codebase (confirmed by audit before writing this), so the boundary is built here,
 * against `now()`, which only means "clinic wall-clock time" because of the Decision D14 fix.
 */
class NotifyUnconfirmedAppointments extends Command
{
    protected $signature = 'notifications:appointments-unconfirmed';

    protected $description = "Notify receptionists and admins of tomorrow's still-unconfirmed appointments (Phase 5C, type 11)";

    public function handle(NotificationService $notificationService): int
    {
        $tomorrow = now()->addDay();

        $unconfirmed = Appointment::query()
            ->where('status', AppointmentStatus::Scheduled->value)
            ->whereBetween('start_at', [$tomorrow->copy()->startOfDay(), $tomorrow->copy()->endOfDay()])
            ->get();

        foreach ($unconfirmed as $appointment) {
            try {
                $notificationService->dispatchFor(
                    $appointment,
                    null,
                    'appointment.unconfirmed',
                    deduplicateUnread: true,
                );
            } catch (Throwable $exception) {
                Log::error('Scheduled notification dispatch failed', [
                    'command' => self::class,
                    'subject_type' => Appointment::class,
                    'subject_id' => $appointment->id,
                    'exception' => $exception->getMessage(),
                ]);
            }
        }

        $this->info("Checked {$unconfirmed->count()} unconfirmed appointment(s) for tomorrow.");

        return self::SUCCESS;
    }
}
