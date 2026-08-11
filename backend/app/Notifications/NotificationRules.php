<?php

namespace App\Notifications;

/**
 * Phase 5 (Notification System) design doc §3.2 / §5 — **the whitelist**.
 *
 * `PatientActivityOccurred` already fires for 24 distinct event types across 9 services. This map
 * is an explicit ALLOW-LIST, not a filter-out list: of those 24, exactly 8 produce a notification.
 * Silence is the default, and an event type that is not a key here is silently and intentionally
 * ignored.
 *
 * That is the primary answer to the design brief's performance requirement (design doc §9): the
 * cheapest notification is the one that is never created. It is also the answer to noise — a
 * notification centre that fills with routine lifecycle events gets ignored, which makes the
 * genuinely urgent ones (a patient has arrived, an accepted plan needs scheduling, a lab case is
 * back) worthless.
 *
 * Deliberately excluded, with reasoning recorded in the design doc's §5.1 exclusions table so the
 * inevitable "why isn't X here?" is answered once, in writing:
 *   - appointment.confirmed / .in_progress / .completed — routine lifecycle; the actor is present
 *   - invoice.issued, payment.recorded                  — routine front-desk volume; the *exceptions*
 *                                                         (void/refund) are what is worth surfacing
 *   - clinical_note.signed                              — the signer is the actor
 *   - document.uploaded, image.uploaded                 — routine; done by whoever wanted it
 *   - lab_case.sent, .quality_checked                   — `sent` is the actor's own action;
 *                                                         `quality_checked` deferred by Decision D4
 *   - treatment_plan.presented / .completed / .cancelled — actor-driven, low signal
 *   - medical_history.*                                 — deferred to Phase C by Decision D3: the
 *                                                         recipient query ("dentists with this
 *                                                         patient upcoming") deserves its own design
 *
 * Adding a type is a one-line change here plus one BaseNotification subclass — no new dispatch
 * call site anywhere, because the 9 services already fire the event.
 */
final class NotificationRules
{
    /**
     * Activity event type => the notification it produces.
     *
     * @var array<string, class-string<BaseNotification>>
     */
    public const RULES = [
        'appointment.checked_in' => AppointmentCheckedInNotification::class,
        'appointment.cancelled' => AppointmentCancelledNotification::class,
        'appointment.no_show' => AppointmentNoShowNotification::class,
        'treatment_plan.accepted' => TreatmentPlanAcceptedNotification::class,
        'treatment_plan.rejected' => TreatmentPlanRejectedNotification::class,
        'lab_case.received' => LabCaseReceivedNotification::class,
        'payment.refunded' => PaymentRefundedNotification::class,
        'invoice.voided' => InvoiceVoidedNotification::class,
    ];

    /**
     * @return class-string<BaseNotification>|null
     */
    public static function for(string $eventType): ?string
    {
        return self::RULES[$eventType] ?? null;
    }
}
