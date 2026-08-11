<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Notifications\DatabaseNotification;

/**
 * Phase 5 (Notification System) design doc §3.1 / Decision D1 — a thin subclass of Laravel's own
 * `DatabaseNotification`, not a bespoke model. Everything the Notification Center needs
 * (`read_at`, `markAsRead()`, the `unreadNotifications` relation) is inherited, already tested
 * upstream; this class only adds what the four additive columns make possible.
 *
 * Deliberately NOT `Auditable` and NOT `SoftDeletes` (design doc §6.4): a notification is a
 * per-user delivery artifact, not a clinical or financial record with history. Auditing "user read
 * a notification" would be pure noise in the Phase 4 audit trail. Rows are append-only except
 * `read_at`, which is the one field that exists to be mutated, and are pruned rather than
 * tombstoned (pruning activates in Phase B, once a scheduler exists).
 *
 * `$guarded = []` is inherited from DatabaseNotification, so the additive columns are mass
 * assignable without restating a `$fillable` — the custom DatabaseChannel is the only writer.
 */
class Notification extends DatabaseNotification
{
    /**
     * The resource this notification points at — an Appointment, TreatmentPlan, LabCase, Invoice
     * or Payment. Read by the frontend for its deep link, never joined at list time (the payload
     * already carries everything needed to render a row).
     *
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<Patient, $this>
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /**
     * The read-time authorization filter (design doc §8.2). `read()`/`unread()` are NOT redefined
     * here — DatabaseNotification already ships both.
     *
     * @param  list<string>  $categories
     */
    public function scopeInCategories(Builder $query, array $categories): Builder
    {
        return $query->whereIn('category', $categories);
    }
}
