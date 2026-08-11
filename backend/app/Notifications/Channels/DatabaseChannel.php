<?php

namespace App\Notifications\Channels;

use App\Notifications\BaseNotification;
use Illuminate\Notifications\Channels\DatabaseChannel as BaseDatabaseChannel;
use Illuminate\Notifications\Notification;
use LogicException;

/**
 * Phase 5 (Notification System) design doc §3.1 / Decision D1.
 *
 * Laravel's stock DatabaseChannel writes only id/type/data/read_at. This subclass adds the four
 * additive columns the migration introduces, by asking the notification for them. It overrides one
 * documented extension point (`buildPayload`) and inherits everything else — it is not a fork.
 *
 * Bound over `Illuminate\Notifications\Channels\DatabaseChannel` in AppServiceProvider, so the
 * plain `'database'` channel name every Notification's `via()` returns resolves to this class.
 * That keeps the notifications themselves 100% conventional Laravel.
 */
class DatabaseChannel extends BaseDatabaseChannel
{
    /**
     * @param  mixed  $notifiable
     * @return array<string, mixed>
     */
    protected function buildPayload($notifiable, Notification $notification): array
    {
        if (! $notification instanceof BaseNotification) {
            // `category`/`subject_*` are NOT NULL by design (design doc §6.1), so a notification
            // that can't supply them cannot be stored. Failing here with the offending class name
            // is far more debuggable than the NOT NULL violation the insert would otherwise throw.
            throw new LogicException(sprintf(
                '%s must extend %s to use the database channel.',
                $notification::class,
                BaseNotification::class,
            ));
        }

        return array_merge(parent::buildPayload($notifiable, $notification), [
            'category' => $notification->category(),
            'subject_type' => $notification->subjectType(),
            'subject_id' => $notification->subjectId(),
            'patient_id' => $notification->patientId(),
        ]);
    }
}
