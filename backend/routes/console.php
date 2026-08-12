<?php

use App\Models\Notification;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduled Tasks
|--------------------------------------------------------------------------
|
| Phase 5B (Notification System) — this file previously contained nothing but the stock `inspire`
| command, because no scheduler process existed anywhere in the project (design doc §1.3/G2). The
| `scheduler` container added in that phase runs `php artisan schedule:work`, so entries below now
| genuinely execute.
|
| All `dailyAt()` times below are clinic wall-clock time (Decision D14, config/app.php's
| `APP_TIMEZONE`), not UTC — see that decision's own note for why this only became a real question
| once a schedule needed to mean a specific hour rather than just "once a day".
|
*/

// Deletes read notifications older than Notification::RETENTION_DAYS. Unread rows are never
// pruned, however old — see Notification::prunable(). Runs off-peak; `onOneServer` is a no-op
// today (single scheduler container) but makes the intent explicit and is correct in advance of
// any future horizontal scaling, since a double-run would just delete the same rows twice.
Schedule::command('model:prune', ['--model' => [Notification::class]])
    ->dailyAt('03:00')
    ->onOneServer()
    ->withoutOverlapping();

// Phase 5C (design doc §2/§14, Decision D11) — types 9-11. 03:30 avoids the prune job's 03:00 slot;
// 08:00/17:00 align with clinic open/close so alerts land when staff are actually there to act.
Schedule::command('notifications:lab-cases-overdue')
    ->dailyAt('03:30')
    ->onOneServer()
    ->withoutOverlapping();

Schedule::command('notifications:low-stock-digest')
    ->dailyAt('08:00')
    ->onOneServer()
    ->withoutOverlapping();

Schedule::command('notifications:appointments-unconfirmed')
    ->dailyAt('17:00')
    ->onOneServer()
    ->withoutOverlapping();

// Types 12-13 (repeated login failures, permission-matrix updates) need no entry here at all —
// they're reactive off AuditLog::created via AuditLogObserver (registered in AppServiceProvider),
// not scheduled (design doc §0/§3.4).
