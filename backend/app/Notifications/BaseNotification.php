<?php

namespace App\Notifications;

use App\Models\User;
use App\Services\RecipientResolver;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notification;

/**
 * Phase 5 (Notification System) design doc §5 / §14 Phase A.
 *
 * Shared plumbing for the 8 whitelisted V1 notification types. Each concrete subclass declares
 * only what actually differs — its category, its translation keys, its params, its deep link, and
 * who receives it. Everything structural lives here once.
 *
 * NOT `ShouldQueue` in Phase A, and that is deliberate rather than an oversight: no queue worker
 * process exists in this project yet (design doc §1.3/G1 — confirmed by auditing both compose
 * files), so a queued notification would silently never be delivered. This mirrors the same
 * reasoning already documented on `RecordsPatientActivity`. Phase B adds a real worker, verifies
 * it actually consumes jobs, and only then introduces `ShouldQueue`.
 *
 * Localization (design doc §11): a notification stores translation KEYS and raw params, never
 * rendered text. The row is written once, server-side, but read by a user whose locale is their
 * own and can change at any moment — storing "تم إلغاء الموعد" would freeze the wrong answer
 * permanently. vue-i18n renders at display time instead.
 */
abstract class BaseNotification extends Notification
{
    public function __construct(
        public readonly Model $subject,
        public readonly ?User $actor = null,
    ) {}

    /**
     * The stable short type key (e.g. `appointment.cancelled`). Drives the frontend's i18n key
     * derivation and its per-category icon, and is what Phase D's per-type preferences will key
     * on alongside the `type` column's FQCN.
     */
    abstract public function type(): string;

    /**
     * The authorization category (design doc §8.2). Must be a key of
     * `NotificationPolicy::CATEGORY_SUBJECT_MAP`, so that every stored row can be re-checked
     * against the recipient's *current* permissions at read time.
     */
    abstract public function category(): string;

    /**
     * Interpolation values for the translation keys — always RAW (ISO-8601 timestamps, plain
     * names, unformatted amounts). Formatting happens at render time: dates through
     * `frontend/src/lib/date.ts` per the project-wide datetime policy, currency through the
     * existing BillingSetting helper. Storing a pre-formatted "12 Aug 2026, 9:00 AM" would both
     * violate that policy and freeze a single locale into the row.
     *
     * @return array<string, mixed>
     */
    abstract public function params(): array;

    /**
     * The vue-router target for "open the notification → go to the resource" (design doc §10.2).
     *
     * @return array{name: string, params?: array<string, mixed>, query?: array<string, mixed>}
     */
    abstract public function route(): array;

    /**
     * Who receives this. Every actual DB query lives in RecipientResolver, never here — that
     * single seam is what keeps this module multi-tenant ready (design doc §3.2/§8.4): when a
     * future clinic scope lands it is one `where('clinic_id', ...)` in the resolver, not an edit
     * at every rule.
     *
     * The actor's own exclusion and the send-time permission filter are applied centrally in
     * NotificationService, so no subclass can forget either.
     *
     * @return Collection<int, User>
     */
    abstract public function recipients(RecipientResolver $resolver): Collection;

    /**
     * @param  mixed  $notifiable
     * @return list<string>
     */
    public function via($notifiable): array
    {
        // Database only in V1. Mail is Phase D (shipped disabled, design doc §4.2); Web Push is
        // deferred to roadmap Phase 6, which owns the PWA/service-worker foundation it needs.
        return ['database'];
    }

    /**
     * @param  mixed  $notifiable
     * @return array<string, mixed>
     */
    public function toArray($notifiable): array
    {
        return [
            'type' => $this->type(),
            'titleKey' => "notifications.types.{$this->type()}.title",
            'bodyKey' => "notifications.types.{$this->type()}.body",
            'params' => $this->params(),
            'actorName' => $this->actor?->name,
            'route' => $this->route(),
        ];
    }

    public function subjectType(): string
    {
        return $this->subject->getMorphClass();
    }

    public function subjectId(): string
    {
        return (string) $this->subject->getKey();
    }

    /**
     * Nullable because Phase C's low-stock and security notifications have no patient — the one
     * deliberate divergence from `patient_activities`, which requires one (design doc §6.1).
     */
    public function patientId(): ?string
    {
        // getAttribute(), not the magic property: $subject is typed as the generic base Model, so
        // patient_id is not statically declared on it. Same reasoning as RecordsPatientActivity.
        $patientId = $this->subject->getAttribute('patient_id');

        return $patientId === null ? null : (string) $patientId;
    }
}
