<?php

namespace App\Listeners;

use App\Events\PatientActivityOccurred;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
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
 * **Queued as of Phase 5B, and not before.** Phase 5A shipped this synchronously on purpose: no
 * queue worker process existed anywhere in the project (design doc §1.3/G1), so `ShouldQueue`
 * would have meant "enqueued and silently never delivered". Phase 5B added a real `queue`
 * container and verified it genuinely consumes jobs before this interface was added.
 * `RecordsPatientActivity` stays synchronous — the Timeline row is part of the record, whereas a
 * notification is a delivery side effect.
 *
 * `$afterCommit` is load-bearing, not decoration. Several of the dispatching services
 * (`InvoiceService::void()`, `PaymentService::refund()`, `LabCaseService::receive()`) fire this
 * event from *inside* a `DB::transaction()`. Without it, the worker can pick the job up before the
 * transaction commits and find no row — a real race that only appears under load, exactly the kind
 * that is miserable to diagnose in production.
 */
class SendsNotifications implements ShouldQueue
{
    use InteractsWithQueue;

    /** @see the class docblock — the dispatching services fire this inside DB transactions. */
    public bool $afterCommit = true;

    public function __construct(private NotificationService $notificationService) {}

    /**
     * Fail-open, exactly as Phase 4's AuditLogService does: a notification failure must never break
     * the business operation that triggered it. Cancelling an appointment has to succeed even if
     * notifying the dentist does not.
     *
     * Catching here rather than letting the job throw and retry is a deliberate trade: it gives up
     * automatic retries in exchange for that guarantee holding under *every* queue configuration —
     * including `QUEUE_CONNECTION=sync`, where an uncaught throw would propagate straight back into
     * the caller's request. For a clinical system, a lost notification is a far better failure than
     * a blocked cancellation.
     *
     * The error log deliberately carries the event type and subject identity only — never the
     * notification payload, which contains patient names.
     */
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
