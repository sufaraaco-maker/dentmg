<?php

namespace App\Policies;

use App\Models\Appointment;
use App\Models\Invoice;
use App\Models\LabCase;
use App\Models\Payment;
use App\Models\Supply;
use App\Models\TreatmentPlan;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

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
 *
 * Phase 5C design doc §8.1: `security` cannot join `CATEGORY_SUBJECT_MAP` — there is no Eloquent
 * model behind it, only the hardcoded admin-only `view-audit-logs` Gate Phase 4 §1.4 defined
 * (deliberately never routed through the permission matrix). `GATE_CATEGORIES` is the parallel map
 * for exactly this case; `inventory` needed no new mechanism since `SupplyPolicy::viewAny()` is a
 * real policy and fits `CATEGORY_SUBJECT_MAP` as-is.
 */
class NotificationPolicy
{
    /**
     * Categories are filtered in the query, never fetched and then hidden. One class-level `can()`
     * per category per request — a fixed, small number of checks, never one per row.
     *
     * @var array<string, class-string>
     */
    public const CATEGORY_SUBJECT_MAP = [
        'appointments' => Appointment::class,
        'treatment_plans' => TreatmentPlan::class,
        'laboratory' => LabCase::class,
        'billing' => Invoice::class,
        'payments' => Payment::class,
        'inventory' => Supply::class,
    ];

    /**
     * Categories authorized by a hardcoded Gate rather than a Policy's `viewAny()` — see the class
     * docblock. `security` covers both scheduled-notification types 12 and 13 (design doc §5.3);
     * both are gated identically, so one entry covers both.
     *
     * @var array<string, string>
     */
    public const GATE_CATEGORIES = [
        'security' => 'view-audit-logs',
    ];

    /**
     * Every valid category key, policy- and gate-backed alike — the single source of truth
     * `NotificationIndexRequest`'s `category` validation rule reads from, so a category can never be
     * accepted here without also being a real, checkable value there.
     *
     * @return list<string>
     */
    public static function allCategories(): array
    {
        return [...array_keys(self::CATEGORY_SUBJECT_MAP), ...array_keys(self::GATE_CATEGORIES)];
    }

    /**
     * @return list<string>
     */
    public static function allowedCategories(User $actor): array
    {
        $policyBacked = array_filter(
            array_keys(self::CATEGORY_SUBJECT_MAP),
            fn (string $category) => $actor->can('viewAny', self::CATEGORY_SUBJECT_MAP[$category]),
        );

        $gateBacked = array_filter(
            array_keys(self::GATE_CATEGORIES),
            fn (string $category) => Gate::forUser($actor)->allows(self::GATE_CATEGORIES[$category]),
        );

        // Array-unpacking into a literal already renumbers integer keys, so the result is a list on
        // its own — array_values() around it would be a no-op.
        return [...$policyBacked, ...$gateBacked];
    }
}
