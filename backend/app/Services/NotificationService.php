<?php

namespace App\Services;

use App\Models\Notification as NotificationModel;
use App\Models\User;
use App\Notifications\BaseNotification;
use App\Notifications\NotificationRules;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Notification;

/**
 * Phase 5 (Notification System) design doc §3.2 — the single write path.
 *
 * Every notification in the system is created here, which is what lets the three universal rules
 * be enforced once rather than per notification type (where one of the eight would eventually
 * forget one):
 *
 *   1. The whitelist (NotificationRules) decides whether an event notifies at all.
 *   2. The actor never receives a notification for their own action.
 *   3. Send-time authorization: a notification is never created for a user who could not open its
 *      target anyway (design doc §8.3, authorization layer 3).
 *
 * Rule 3 is not the same guarantee as the read-time category re-check in NotificationController
 * (layer 2). This one prevents a notification from ever existing for the wrong person; that one
 * catches the case where permissions are revoked *after* it was created. Both are needed — neither
 * subsumes the other.
 */
class NotificationService
{
    public function __construct(private RecipientResolver $resolver) {}

    /**
     * @param  Model  $subject  the Appointment/TreatmentPlan/LabCase/Invoice/Payment/Supply/AuditLog
     *                          the event is about
     * @param  User|null  $actor  who performed the action, if anyone (null for system/scheduled events)
     * @param  string  $eventType  the PatientActivityOccurred event type (e.g. `appointment.cancelled`)
     *                             or a Phase 5C scheduled/reactive type (e.g. `lab_case.overdue`) —
     *                             both are just keys into NotificationRules, dispatchFor() doesn't
     *                             care which produced them
     * @param  bool  $deduplicateUnread  Phase 5C design doc §3.1: for the 3 scheduled types (a lab
     *                                   case can stay overdue for a week), skip a recipient who
     *                                   already has an unread notification for this exact
     *                                   (recipient, type, subject) — a read one is fair game to
     *                                   resurface. Default false and opt-in only: the 8 Phase A
     *                                   types are each one-time occurrences that never need this,
     *                                   and it costs one query per recipient, which the hot
     *                                   reactive path has no reason to pay
     */
    public function dispatchFor(Model $subject, ?User $actor, string $eventType, bool $deduplicateUnread = false): void
    {
        $notificationClass = NotificationRules::for($eventType);

        // Not whitelisted — by far the most common path (16 of the 24 live event types). Cheapest
        // possible: one array lookup, no query, no object built.
        if ($notificationClass === null) {
            return;
        }

        /** @var BaseNotification $notification */
        $notification = new $notificationClass($subject, $actor);

        $recipients = $notification->recipients($this->resolver)
            // Rule 2 — you already know what you just did.
            ->reject(fn (User $recipient) => $actor !== null && $recipient->is($actor))
            // Rule 3 — layer 3 authorization. Cheap despite looking per-user: hasPermission() is
            // backed by the per-role cache Phase 4 introduced, so this resolves in memory.
            ->filter(fn (User $recipient) => $recipient->can('view', $subject))
            ->when($deduplicateUnread, fn ($recipients) => $recipients->reject(
                fn (User $recipient) => NotificationModel::query()
                    ->where('notifiable_id', $recipient->id)
                    ->where('notifiable_type', $recipient->getMorphClass())
                    ->where('type', $notificationClass)
                    ->where('subject_type', $subject->getMorphClass())
                    ->where('subject_id', $subject->getKey())
                    ->whereNull('read_at')
                    ->exists(),
            ))
            ->values();

        if ($recipients->isEmpty()) {
            return;
        }

        Notification::send($recipients, $notification);
    }
}
