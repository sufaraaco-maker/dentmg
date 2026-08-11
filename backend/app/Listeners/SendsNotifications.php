<?php

namespace App\Listeners;

use App\Events\PatientActivityOccurred;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Phase 5 (Notification System) design doc §3.2 — the second listener on an event that already
 * fires in 9 services.
 *
 * This is the whole reason the module needed no new dispatch call sites: `PatientActivityOccurred`
 * was built in Phase 2.6 as one generic event with 21 call sites covering 24 event types, and
 * `RecordsPatientActivity` (untouched by this phase) is the first subscriber. Subscribing a second
 * listener gives reactive notification coverage across Appointments, Treatment Plans, Laboratory,
 * Billing and Payments without editing a single existing service method.
 *
 * NOT `ShouldQueue` — deliberate, not an oversight. No queue worker process exists in this project
 * yet (design doc §1.3/G1, confirmed by auditing both compose files), so a queued listener would
 * silently never run. Phase B adds a real worker, proves it consumes jobs, and only then flips
 * this to `ShouldQueue`. Same reasoning already documented on RecordsPatientActivity.
 *
 * Fail-open, exactly as Phase 4's AuditLogService does: a notification failure must never break
 * the business operation that triggered it. Cancelling an appointment must succeed even if
 * notifying the dentist does not. The error log deliberately carries the event type and subject
 * identity only — never the notification payload, which contains patient names.
 */
class SendsNotifications
{
    public function __construct(private NotificationService $notificationService) {}

    public function handle(PatientActivityOccurred $event): void
    {
        try {
            $this->notificationService->dispatchFor($event->subject, $event->actor, $event->eventType);
        } catch (Throwable $exception) {
            Log::error('Notification dispatch failed', [
                'event_type' => $event->eventType,
                'subject_type' => $event->subject->getMorphClass(),
                'subject_id' => $event->subject->getKey(),
                'exception' => $exception->getMessage(),
            ]);
        }
    }
}
