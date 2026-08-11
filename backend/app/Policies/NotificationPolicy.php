<?php

namespace App\Policies;

use App\Models\Appointment;
use App\Models\Invoice;
use App\Models\LabCase;
use App\Models\Payment;
use App\Models\TreatmentPlan;
use App\Models\User;

/**
 * Phase 5 (Notification System) design doc §8.2 — authorization layer 2, the read-time category
 * re-check.
 *
 * This is NOT an ownership policy, and that is the point. Ownership (layer 1) is structural: every
 * route in NotificationController resolves its target from `$request->user()->notifications()`, so
 * a user cannot address another user's notifications because no route can express it — there is no
 * `where user_id = ?` to forget. That is why Decision D6 adds no permission catalog entry for
 * reading your own notifications, matching My Account's identically self-scoped precedent.
 *
 * What layer 1 does NOT cover is permission drift over time. A dentist is notified of a
 * `lab_case.received` on Monday; on Tuesday an admin revokes `lab_cases.view` from the dentist role
 * via the Phase 4 matrix. The row still exists and still carries a clinical summary. Every list and
 * count query therefore also filters `whereIn('category', allowedCategories($user))`, re-derived
 * from each category's *real owning policy* on every request — so notification visibility can never
 * drift out of sync with the module it describes.
 *
 * Structure and reasoning deliberately mirror PatientActivityPolicy (Phase 2.6), including its hard
 * rule: one category per real policy, never a category spanning two policies with different rules.
 * `payments` maps to Payment rather than being folded into `billing`/Invoice specifically to honour
 * that — PaymentPolicy and InvoicePolicy are separate policies with separately-grantable
 * permissions (`payments.view` vs `invoices.view`).
 */
class NotificationPolicy
{
    /**
     * Categories are filtered in the query, never fetched and then hidden. One class-level `can()`
     * per category per request — a fixed, small number of checks, never one per row.
     *
     * Phase C adds `inventory` (SupplyPolicy) and `security` (the hardcoded admin-only
     * `view-audit-logs` Gate, which by Phase 4 §1.4's decision is never routed through the
     * permission matrix).
     *
     * @var array<string, class-string>
     */
    public const CATEGORY_SUBJECT_MAP = [
        'appointments' => Appointment::class,
        'treatment_plans' => TreatmentPlan::class,
        'laboratory' => LabCase::class,
        'billing' => Invoice::class,
        'payments' => Payment::class,
    ];

    /**
     * @return list<string>
     */
    public static function allowedCategories(User $actor): array
    {
        return array_values(array_filter(
            array_keys(self::CATEGORY_SUBJECT_MAP),
            fn (string $category) => $actor->can('viewAny', self::CATEGORY_SUBJECT_MAP[$category]),
        ));
    }
}
