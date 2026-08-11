<?php

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
| Phase C's time-based notifications (overdue lab cases, the low-stock digest, tomorrow's
| unconfirmed appointments) register here too.
|
*/

// Deletes read notifications older than Notification::RETENTION_DAYS. Unread rows are never
// pruned, however old — see Notification::prunable(). Runs off-peak; `onOneServer` is a no-op
// today (single scheduler container) but makes the intent explicit and is correct in advance of
// any future horizontal scaling, since a double-run would just delete the same rows twice.
Schedule::command('model:prune', ['--model' => [\App\Models\Notification::class]])
    ->dailyAt('03:00')
    ->onOneServer()
    ->withoutOverlapping();
