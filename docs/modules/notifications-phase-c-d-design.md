# Notification System — Phase C (Scheduled & Administrative) + Phase D (Email Channel) — Design Doc

| | |
|---|---|
| **Status** | **Phase C — IMPLEMENTED (2026-08-12), Backend 1211/1211 + PHPStan clean, i18n 1498/1498/1498 zero drift.** Phase D remains deferred to its own future cycle, per Decision D12. |
| **Depends on** | Phase A (In-App Foundation) + Phase B (Queue & Scheduler) — both merged to `main` via PR #39, 2026-08-11 |
| **Parent doc** | `docs/modules/notifications-design.md` — §5.2/5.3 (type sketch), §4.2/§6.3/§11.4 (email sketch), §13 (deferred items), §14 (phase plan), §16-17 (D1-D8 decisions already approved) |
| **Precedent** | Same audit-then-design discipline as `docs/modules/patient-timeline-redesign-design.md` (Phase 2.6) — the umbrella doc sketched this phase before Phase A/B existed; this doc verifies that sketch against the real, now-implemented codebase and resolves what it left open |

---

## 0. Why this doc exists, and what changed since the sketch

The parent design doc's §5.2/5.3 sketch was written *before* Phase A or B had a single line of code. Now
that both are merged, a fresh audit of the real codebase (not the sketch) found real gaps the original
sketch hadn't resolved in implementation detail, plus confirmation that every data source the sketch
assumed does in fact already exist. **Revised after implementation** (see §19 for the full account —
one design-time assumption below turned out to be wrong in a good way, and implementation surfaced three
further real bugs the design phase couldn't have found by reading alone):

1. ~~`NotificationRules` cannot hold Phase C's types as-is~~ **— design-time assumption, corrected by
   implementation.** `NotificationService::dispatchFor()` was never actually coupled to
   `PatientActivityOccurred` — it is a plain `(Model $subject, ?User $actor, string $eventType)` lookup,
   callable from anywhere. Phase C's 5 types needed exactly the ordinary one-line `NotificationRules::RULES`
   entry the class's own docblock always promised, called directly from 3 new Commands (types 9-11) and a
   new `AuditLogObserver` (types 12-13) — no new dispatch path, no restructuring. **The real, narrower gap**
   turned out to be `BaseNotification`'s `Model $subject` being a required, non-nullable constructor
   parameter (matching the `notifications` table's `NOT NULL uuidMorphs('subject')`), which types 10
   (a digest, no single natural subject) and 12/13 (a login attempt / matrix change, no natural
   business-entity subject) don't have on their face — resolved in §3.2/§16a rather than by loosening the
   schema: type 10 picks its scope's own most-urgent row as a representative subject; types 12/13 use the
   triggering `AuditLog` row itself.
2. **`NotificationPolicy::CATEGORY_SUBJECT_MAP` cannot hold `security` as-is.** Confirmed correct as
   designed — see §8.1, implemented without change from the original plan.

Everything else the sketch assumed is confirmed present and reusable as-is (§1 below has the full
evidence table). This doc does not revisit anything already decided in D1-D8 (parent doc §17); it only
resolves what those decisions left unresolved for Phase C/D specifically.

---

## 1. Module Goal / Purpose

Extend the Notification Center (shipped in Phase A/B) with:

- **Phase C** — four staff-facing alert types that originate from *state* (an overdue lab case, low stock,
  an unconfirmed appointment) or from *security-relevant audit events* (repeated login failures, a
  permission-matrix change), rather than from a single user action. This closes the gap the parent doc
  flagged: "why isn't X notifying anyone" for the four highest-value cases not covered by Phase A's
  reactive model.
- **Phase D** — a real (opt-in, per-user) email channel for the existing 8 Phase A types plus Phase C's 5,
  so staff who don't have the app open still hear about anything worth surfacing. Ships **disabled by
  default, fails closed** without a configured mailer — the same posture AI Assistant established for a
  missing API key.

Both phases are additive to Phase A/B's architecture, not a redesign of it. No existing table, endpoint, or
component changes shape — only extends.

---

## 2. Full Workflow

### Phase C — per type

| Type | Cadence | What happens |
|---|---|---|
| **9. `lab_case.overdue`** | Daily, `03:30` (after the existing `03:00` `model:prune` job) | A new `NotifyOverdueLabCases` command runs `LabCase::query()->dueOrOverdue()->get()` (the existing scope, confirmed at `LabCase.php:118-124`), and for each case not already notified today, dispatches to prescribing dentist + receptionists |
| **10. `inventory.low_stock`** | Daily, `08:00` (start of clinic day, not middle of the night) | A new `NotifyLowStockDigest` command runs `Supply::query()->active()->lowStock()->get()` (the existing scope/controller logic, confirmed at `Supply.php:138-143` / `SupplyController.php:39-46`); if the set is non-empty, dispatches **one digest notification** (not one per item) to admins + receptionists |
| **11. `appointment.unconfirmed`** | Daily, `17:00` (end of clinic day — gives front desk the whole next morning to act before the appointment) | A new `NotifyUnconfirmedAppointments` command queries appointments where `start_at` falls within tomorrow's calendar day and `status = scheduled` (building the "tomorrow" bound fresh — no ready-made scope exists per the audit, §1), dispatches per appointment to receptionists + admins |
| **12. `security.repeated_login_failures`** | **Reactive**, not scheduled — see §0.1 | An `AuditLogObserver::created()` hook fires on every `AuditLog` row (a real Eloquent `create()`, confirmed at `AuditLogService.php:96`); when `action === 'login_failed'`, count same-email failures in the trailing window; on crossing the threshold, notify admins once, then suppress for the rest of the window |
| **13. `permissions.matrix_updated`** | **Reactive**, same observer | When `action === 'role_permissions_updated'`, notify all admins except the actor (the admin who is `user_id` on that row) |

Types 12-13 need **no scheduler entry at all** — this is the corrected version of the parent doc's §5.3
framing, which grouped them with the scheduled types under "Phase C" without distinguishing mechanism. They
belong in Phase C's *scope* (same review cycle, same new `security`/`inventory` categories) but not its
*cron*.

### Phase D — email

1. User (with `notification_preferences` row, or the default if none) receives an in-app notification as
   today.
2. If that user has email enabled for that type and a real mailer is configured (`MAIL_MAILER !== 'log'`),
   `SendsNotifications` (already `ShouldQueue`, confirmed at `SendsNotifications.php:35`) additionally
   queues a `Mailable` rendered server-side in the user's `locale` column.
3. If no mailer is configured, the mail send is skipped silently for *that channel only* — the in-app
   notification still lands. This differs from AI Assistant's "503 the whole feature" fail-closed pattern
   because in-app is the primary channel and must never be degraded by email's absence (§10 explains why
   this is not a contradiction of the fail-closed principle).

---

## 3. Business Rules

### 3.1 Scheduled types (9-11) — de-duplication

A lab case overdue by a week must not generate seven identical notifications. Rule, uniform across all
three scheduled types: **skip creating a new notification for `(notifiable, subject_type, subject_id,
category)` if an *unread* one already exists.** If the previous one was read (acknowledged), a fresh
occurrence is allowed to resurface it — "still overdue, and you already saw yesterday's" is a legitimate
reminder; "still overdue, and you haven't even opened yesterday's" is not a second alert, it's noise.

Implemented as one `whereNotExists` guard in each command before calling `NotificationService`, reusing the
existing `notifications` table — no new column needed.

### 3.2 Low-stock digest is one notification, not N

Type 10 is explicitly a *digest* (parent doc §5.2 already calls it that). One notification per admin/
receptionist per day, `params` carrying the count and the top N item names; the notification's deep link
routes to the existing Supplies low-stock list (`SupplyController::lowStock()`), not to a single item.
Rationale: five separately-arriving "X is low" notifications in one morning is exactly the noise Phase A's
curated allow-list (parent doc §5) was designed to avoid.

### 3.3a Timezone — a real, previously-latent gap this phase is the first to expose

Checked in response to the user's explicit D11 concern, not assumed correct: `config('app.timezone')` is
`'UTC'` (`backend/config/app.php:80`), and **no `TZ` environment variable is set anywhere** — not in
`docker-compose.yml`, not in `docker-compose.prod.yml`, not in `.env.example`. No PHP Dockerfile timezone
configuration either.

This has never mattered before, because of how this project already handles datetime (documented in
`frontend/src/lib/date.ts:18-26`): it's a **single-clinic system with no real timezone conversion** — the
frontend reads the browser's local wall-clock digits and sends them as literal digits (e.g. `10:00:00`)
under a UTC label; the backend stores those digits verbatim, never converting them. `config('app.timezone')
= 'UTC'` is explicitly documented there as *"a neutral/no-DST baseline, not a real UTC boundary."* Every
prior phase only ever *stored and re-displayed* those digits — never compared them against `now()`.

**Phase C is the first feature to compare `now()` against a stored wall-clock column** (`due_at`,
`start_at`) and the first to schedule an *absolute clock time* (`dailyAt('03:30')`). Laravel's scheduler and
`now()` both resolve through `config('app.timezone')` — literally `'UTC'`, the real zone, zero offset. If
the container's real system clock is, e.g., actually running in UTC (the Docker default) while the clinic
itself is not in UTC+0, then:

- `now()` returns real-UTC digits, but `due_at`/`start_at` hold the clinic's own wall-clock digits (per the
  convention above) — comparing them directly is comparing two different clocks that only agree if the
  clinic happens to be in UTC+0.
- `dailyAt('03:30')` fires at 03:30 **real UTC**, not 03:30 clinic wall-clock time — silently wrong by
  exactly the clinic's UTC offset, e.g. actually running at 06:30 local for a UTC+3 clinic.

**This was never a bug before Phase C because nothing before Phase C needed "what time is it right now,
clinic-wall-clock-wise."** It is exactly the same class of previously-invisible-until-this-phase-needed-it
gap as Phase 5B's queue-worker discovery (parent doc §0) — not a regression, a genuinely new requirement
surfacing a genuinely old blind spot.

**Recommended fix — config only, no migration, matching the project's existing "single-clinic, no
per-request conversion" philosophy**: `config('app.timezone')` was a hardcoded `'UTC'` string
(`config/app.php:80`), not `env()`-driven, so no env var could ever have changed it — confirmed by reading
`Illuminate\Foundation\Bootstrap\LoadConfiguration::bootstrap()`, which calls
`date_default_timezone_set($config->get('app.timezone'))` on every request/command/schedule tick. Fix has
two parts: (1) `config/app.php` now reads `env('APP_TIMEZONE', 'UTC')`; (2) `APP_TIMEZONE=Africa/Cairo` is
set in `backend/.env.example` (and mirrored into the real local `backend/.env`) — the actual project
convention for all runtime config (confirmed: neither compose file sets `APP_*` vars directly; `app`/
`queue`/`scheduler` all load `backend/.env` from the mounted volume, and CI's own jobs `cp .env.example
.env`, so this one line also fixes CI without a workflow change). `tzdata` added to both
`docker/php/Dockerfile` and `Dockerfile.prod`'s `apk add` list as a safety measure for full IANA zone
resolution on Alpine. This makes `now()` return the same wall-clock digits the frontend already assumes
everywhere else, fixing both the overdue/unconfirmed comparisons and the scheduled run times with one
change.

**Flagged, not silently fixed — Decision D14 (§16).** Multi-tenant readiness note per
[[policy_saas_multitenant_readiness]]: a single global `TZ` is correct for V1 (one clinic); the future
multi-clinic model will need this to become a per-clinic column (on `ClinicSetting`, following the same
additive-column precedent as Phase 4's `audit_logs` and this module's own `notifications` table) rather than
a container-wide env var — noted here so it isn't rediscovered from scratch later.

### 3.3 Repeated-login-failure threshold

**Decision D9 (§16)**: 5 failures for the same email within a rolling 15-minute window, matching this
project's `AuthController` login-rate-limit conventions (verify against the real throttle config before
implementing — flagged, not assumed). Once notified, suppressed for the remainder of that 15-minute window
even if failures continue, so a sustained attack produces one alert, not one per attempt.

### 3.4 Actor exclusion doesn't apply the same way to types 12-13

Type 13 excludes the actor (the admin who changed the matrix) exactly like Phase A's rules. Type 12 has no
actor to exclude — confirmed by the audit that `AuditLogService::write()` sets `user_id` from `Auth::id()`,
which is always `null` during a failed login (nothing is authenticated yet), regardless of whether the
attempted email matches a real user.

### 3.5 Email respects the existing allow-list; adds nothing new

Phase D does not introduce new notification *types* — it adds a second channel to the 13 types Phase A/C
already curate. No email-only type. This keeps `NotificationRules`/the Phase C dispatch path as the single
source of "does this fire," with the channel a per-user, per-type toggle layered on top (§4).

---

## 4. Database Design

### 4.1 Phase C — no new tables

Types 9-13 reuse the existing `notifications` table and `category` column exactly as Phase A did. Two new
category values are introduced (`inventory`, `security`) — no schema change, `category` is already a plain
indexed string, not an enum constrained at the DB level.

### 4.2 Phase D — two additions, both already scoped in the parent doc §6.3, confirmed still needed by this audit

```php
// Migration: add users.locale (closes gap confirmed by audit — no locale column exists anywhere today)
Schema::table('users', function (Blueprint $table) {
    $table->string('locale', 5)->nullable()->after('role');   // 'en' | 'ar' | 'tr'; null = app default
});
```

```php
// Migration: notification_preferences (confirmed absent by audit)
Schema::create('notification_preferences', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
    $table->string('type');             // matches NotificationRules/Phase-C type keys, e.g. 'appointment.cancelled'
    $table->boolean('email_enabled')->default(true);   // default ON — see Decision D10
    $table->timestamps();
    $table->unique(['user_id', 'type']);
});
```

**No row = default (`email_enabled = true`)** rather than requiring a seeded row per user per type — a
missing preference means "hasn't changed the default," not "unknown." Avoids a 13-types-×-N-users seed on
every new type added later.

---

## 5. Table Relationships

```
users 1───* notification_preferences (user_id)
users 1───* notifications (notifiable_id, existing)
audit_logs (existing, unchanged schema) ──observed by──> AuditLogObserver (new, no FK, reads only)
lab_cases / supplies / appointments (existing, unchanged schema) ──queried by──> 3 new scheduled Commands
```

No foreign key from `notifications` to `notification_preferences` — the preference is checked at
send-time (a lookup, not a join), exactly how `NotificationRules`'s allow-list is checked today.

---

## 6. API Design

### 6.1 Phase C — no new endpoints

Types 9-13 surface through the existing `GET /notifications`, `GET /notifications/unread-count`, etc.
(parent doc §7) — the two new categories flow through unchanged. `NotificationCenter.vue`'s category-filter
chip row (built in Phase 2.6, reused by Phase A) needs two new chip entries (`inventory`, `security`) and
two new icons in `config/notificationTypes.ts` — the only frontend change Phase C requires. No new Vue
route, no new page.

### 6.2 Phase D — two new endpoints, following the existing Settings pattern

| Method | Route | Purpose |
|---|---|---|
| `GET` | `/notification-preferences` | Current user's preferences for all 13 types (defaults filled in for missing rows) |
| `PUT` | `/notification-preferences` | Bulk update, same "local draft + explicit Save" UX Phase 4's `PermissionsView` established, not autosave-per-toggle |

Both scoped to `$request->user()` only — self-scoped like notifications themselves (D6), no new permission
catalog entry, no `NotificationPreferencePolicy` needed for the same structural reason §8.1 of the parent
doc gives for notifications.

---

## 7. UI/UX Design

### 7.1 Phase C

No new screens. `NotificationItem.vue` renders the two new categories through the existing
category→icon→i18n-key config map (parent doc §10.1's `config/notificationTypes.ts`) — a config-only
change, not a component change.

### 7.2 Phase D

New **"Notifications" tab in Settings** (alongside the existing tabs — `docs/modules/settings-design.md`
already scoped notification settings as explicitly out of V1; this is where they land now):

- A 13-row table: type name (i18n'd) × an email on/off toggle, grouped by the same categories the
  Notification Center uses.
- One explicit **Save** action (matches D6.2's pattern), not per-toggle autosave — consistent with
  `PermissionsView`.
- Mobile: the existing responsive table→stacked-card pattern already used elsewhere in Settings (per
  [[policy_pwa_mobile_first]] — no new pattern invented).

A `locale` selector is **not** part of this UI — `users.locale` is set once at account creation/edit (My
Account, existing screen), reusing the 3-locale set the frontend already offers, not a new selector.

---

## 8. Permissions Model

### 8.1 The `security` category gap — resolution

`NotificationPolicy::CATEGORY_SUBJECT_MAP` (`NotificationPolicy.php:47`) is class-string-keyed because
every existing category has a real Policy. `security` doesn't. Resolution: a second, parallel gate-backed
map, checked in addition to the policy-backed one:

```php
public const CATEGORY_SUBJECT_MAP = [
    // ...existing 5 entries, unchanged...
    'inventory' => Supply::class,   // SupplyPolicy::viewAny() — a real policy, fits the existing map as-is
];

private const GATE_CATEGORIES = [
    'security' => 'view-audit-logs',   // Phase 4 §1.4's hardcoded Gate — never in the permission matrix
];

public static function allowedCategories(User $actor): array
{
    $policyBacked = array_filter(
        array_keys(self::CATEGORY_SUBJECT_MAP),
        fn (string $c) => $actor->can('viewAny', self::CATEGORY_SUBJECT_MAP[$c]),
    );
    $gateBacked = array_filter(
        array_keys(self::GATE_CATEGORIES),
        fn (string $c) => Gate::forUser($actor)->allows(self::GATE_CATEGORIES[$c]),
    );
    return array_values([...$policyBacked, ...$gateBacked]);
}
```

`inventory` needed no new mechanism — `SupplyPolicy::viewAny()` (confirmed at `SupplyPolicy.php:16`, checks
`supplies.view`) fits the existing class-string pattern exactly. Only `security` needed the new branch.

### 8.2 Send-time authorization (layer 3) for Phase C

Same rule as Phase A (§8.3 of the parent doc): a scheduled command must only resolve recipients who can
actually view the category. For type 12/13, that means every candidate "admin" recipient is still checked
against `Gate::forUser($user)->allows('view-audit-logs')` at send time, not assumed from role — consistent
with the project's "never assume role implies permission" rule since Phase 4.

### 8.3 Phase D preferences — no new catalog entry

Reading/writing your own `notification_preferences` is self-scoped exactly like notifications themselves
(D6) — flagged for the same reason, not silently assumed.

---

## 9. Validation Rules

- `PUT /notification-preferences`: array of `{type: string, email_enabled: bool}`; `type` validated against
  the union of `NotificationRules::RULES` keys + the 5 Phase C type keys — an unknown type is a 422, not a
  silently-ignored row.
- Scheduled commands validate nothing from user input (no HTTP surface) — their only "input" is the
  database state itself, already covered by existing model-level constraints.
- `users.locale`, if set via the existing My Account update endpoint, validated against the same 3-value
  enum (`en`/`ar`/`tr`) the frontend locale switcher already uses — not a new list to keep in sync.

---

## 10. Security Considerations

- **Audit-log observer must not become a second write path with different redaction rules.** `AuditLog`
  rows are already redacted by `AuditLogService::filter()` before they're written (§`EXCLUDED_KEYS`,
  `AuditLogService.php:23`) — the observer reads already-redacted rows, so it cannot leak a password hash
  even if `context` were ever misused. Still, the notification `params` built from a `login_failed` row
  must carry only the attempted email and failure count — never the full `context` blob verbatim, to avoid
  accidentally forwarding a future unredacted field into a notification payload (which has weaker access
  control than the audit log viewer itself, which is gated by the same `view-audit-logs` Gate but is a
  richer surface).
- **Email fail-closed scope is per-channel, not per-feature** (§2 Phase D step 3) — a deliberate, explicit
  deviation from AI Assistant's precedent, called out so it isn't mistaken for an inconsistency: AI
  Assistant's *entire* feature has no fallback (there is no "AI Assistant without an API key" mode), while
  Notifications' primary channel (in-app) works completely independently of email. Failing the whole
  notification because email isn't configured would regress Phase A for every user, for a channel most
  users won't opt into first (email defaults per Decision D10, §16).
- **`notification_preferences` is not a security boundary** — it controls delivery convenience, not
  visibility. A user with `laboratory.view` revoked still has layers 2/3 (parent doc §8.2/8.3) blocking
  content; preferences never bypass or duplicate that check.
- **Repeated-login-failure alerts must not leak into a timing/enumeration oracle.** The observer counts
  failures per attempted email regardless of whether that email matches a real user (confirmed
  `auditable_id` is nullable for exactly this case) — so the alert logic itself cannot be used to test
  which emails are registered; it treats known and unknown emails identically.

---

## 11. Performance Considerations

- **Scheduled commands run once daily each, over an existing indexed scope** (`due_at` index, confirmed
  migration line 74; `lowStock()`'s join is already the live Dashboard-widget query, so no new query
  pattern is introduced, only a new caller of an existing one) — negligible added load, run outside
  business hours (03:30/08:00/17:00 chosen to avoid the exact same window as `model:prune`'s 03:00 slot and
  clinic peak hours).
- **The `AuditLogObserver` runs on every audit-log write**, which is now a hot path (every request that
  changes anything). The observer must short-circuit immediately for the ~99% of `action` values that
  aren't `login_failed` or `role_permissions_updated` — a single `match` on `$auditLog->action` before any
  query, so the added cost for the common case is one string comparison, not a query.
- **Email queueing** adds no new performance concern — `SendsNotifications` is already `ShouldQueue`
  (Phase B), so SMTP latency was already designed to sit outside the request cycle before Phase D exists.

---

## 12. Scalability Considerations

- **`RecipientResolver` remains the single multi-tenant seam** (parent doc §3.2, §8.4) — none of Phase C's
  3 new commands or the `AuditLogObserver` bypass it; every recipient set for every new type still resolves
  through the same class, so the future `clinic_id` scoping point stays singular, per
  [[policy_saas_multitenant_readiness]].
- **Scheduled commands must filter by clinic when multi-tenancy lands** — today `LabCase::dueOrOverdue()`,
  `Supply::lowStock()`, and the new "tomorrow's appointments" query are global (single-org V1, correctly).
  Flagged explicitly (not silently assumed) so the future multi-tenant migration knows these three queries
  need a `clinic_id` scope added, exactly as `RecipientResolver` already documents for Phase A's types.
- **`notification_preferences` at `(user_id, type)` unique, not `(user_id)` with a JSON blob** — keeps each
  future type addition a plain `INSERT`/no-op rather than a JSON-shape migration, and keeps the per-type
  query (`email_enabled` lookup at send time) an indexed point lookup instead of a JSON-path scan.

---

## 13. Trade-offs / Architectural Decisions

| Decision | Alternative considered | Why this way |
|---|---|---|
| Types 12-13 are reactive (observer), not scheduled | Poll `audit_logs` for recent rows on the same daily cron as 9-11 | A security alert delayed up to 24 hours defeats its own purpose. The infrastructure to be reactive already exists for free (Eloquent's own `created` event) — polling would be strictly worse on both latency and complexity |
| One digest notification for low-stock, not one per item | Notify per low-stock item, like lab cases | Digest matches how the existing Dashboard widget already presents this data (a list, not individual alerts) and avoids the exact noise problem Phase A's allow-list was built to prevent |
| De-dup by "skip if unread notification for the same subject exists" | A `last_notified_at` column on each source table (LabCase, etc.) | Reuses the existing `notifications` table with zero schema changes to three unrelated tables; a read of `read_at IS NULL` is the same index Phase A already built for the unread-count query |
| Email fails closed **per channel**, not per feature | Mirror AI Assistant exactly — hard-fail the whole notification if mail isn't configured | AI Assistant has no channel *without* the API key; Notifications' primary channel (in-app) is independent of email's configuration state. Explained in full in §10 to preempt the "why is this different" review question |
| `notification_preferences` default-on with no seeded row | Seed a row per user per type at migration time | Avoids an N-users-×-13-types seed that must be repeated for every future type; "no row" already has an unambiguous meaning (default) |

---

## 14. Potential Risks

| Risk | Mitigation |
|---|---|
| Scheduled command silently stops running (scheduler container dies, `schedule:work` process exits) | Already a Phase B-level infrastructure risk, not new to this phase — `restart: unless-stopped` on the `scheduler` service (confirmed in both compose files) is the existing mitigation. Not re-solved here; flagged so it isn't assumed newly introduced |
| `AuditLogObserver` throwing breaks the audit write it's observing | Wrap the observer's notification-dispatch logic in its own `try/catch`, matching `SendsNotifications`'s existing fail-open-for-the-operation posture (parent doc §9) — an audit write must never fail because a notification failed to send |
| Login-failure threshold (5/15min) is either too noisy (shared clinic front-desk IP with typo-prone staff) or too slow to catch a real attack | Flagged as Decision D9, not hardcoded silently — tunable via `config/`, not a migration, so it can change without a deploy of new code logic |
| Email misconfiguration in production silently means "email never sends" with no operator-visible signal | The existing `MAIL_MAILER=log` default already writes attempted emails to the log — Phase D adds no new failure mode, only makes the existing one visible per-user via the Settings toggle (§7.2) rather than all-or-nothing |

---

## 15. Future Improvements (explicitly not this phase)

- Digest *frequency* configurability (daily vs. weekly) — V1 ships daily only, per parent doc §13's
  "digest/batching — revisit with real data."
- Web Push — still roadmap Phase 6, unaffected by this phase.
- Patient-facing reminders via the `appointment_reminders` table — still its own future module, still
  deliberately untouched.

---

## 16. Decisions Requiring Approval

Continuing the numbering from the parent doc's D1-D8 (all already approved, §17 there).

| # | Decision | Recommendation | Why |
|---|---|---|---|
| **D9** | Repeated-login-failure threshold/window | **5 failures / 15 minutes**, verified against the real `AuthController` throttle config before implementation (not assumed to already match) | Standard security-alert default; tunable without a migration since it's config, not schema |
| **D10** | `notification_preferences.email_enabled` default | **Default ON** (opt-out, not opt-in) | Matches how in-app notifications work today (no opt-in step) — email is "also tell me," not a separate consent-gated channel. Preferences exist so someone who finds it noisy can turn it off, not so everyone must turn it on |
| **D11** | Scheduled command run times (03:30 / 08:00 / 17:00) | **As proposed** | Avoids `model:prune`'s 03:00 slot; 08:00/17:00 align with clinic open/close so alerts land when staff are actually there to act |
| **D12** | Phase scope for this cycle: C, D, or both | **C first, D as a separately-scoped follow-on** | C is fully unblocked today (Phase B already shipped) and self-contained — no new tables, one new observer, three new commands. D needs `users.locale` + backend `lang/` files + a real mailer decision (which provider?) — a materially bigger, more infrastructure-dependent lift better reviewed as its own approval, not bundled sight-unseen with C |
| **D13** | If D proceeds: which mail provider/transport for production? | **Not recommended yet — needs your input** | `MAIL_MAILER=log` today has no real transport configured anywhere. This is a cost/vendor decision (SES, Postmark, Mailgun, SMTP relay, etc.), not a technical one this doc should decide unilaterally |
| **D14** | Clinic's real IANA timezone, to set as `APP_TIMEZONE` (§3.3a) | **Resolved: `Africa/Cairo`, see §16a** | Blocks correct implementation of types 9/11 (both compare `now()` against clinic-wall-clock columns) and D11's run times (`config('app.timezone')` was hardcoded `'UTC'`, not `env()`-driven, so no config value could have fixed this before) |

---

## 16a. Approval & Decision Log

**Design approved by the user on 2026-08-12.** Phase C authorized for implementation; Phase D explicitly
deferred to its own separate future cycle — no email code, no `notification_preferences` row, no mailer
config as part of this cycle.

| # | Decision | Resolution |
|---|---|---|
| **D9** | Repeated-login-failure threshold/window | **Approved as recommended** — 5 failures / 15 minutes |
| **D10** | `notification_preferences.email_enabled` default | **Approved in principle (default ON), but untouched in this cycle** — applies only once Phase D actually ships a real mailer + per-user settings UI. No preference row, no email send, nothing email-related in Phase C |
| **D11** | Scheduled command run times | **Approved as proposed** — 03:30 / 08:00 / 17:00, **clinic wall-clock time**, resolved via D14 |
| **D12** | Phase scope for this cycle | **Phase C only.** Phase D scoped separately, own future approval cycle, own D13 (mailer choice) resolution |
| **D13** | Mail provider/transport | **Fully deferred to the Phase D cycle** — no vendor comparison, no cost/deliverability/region evaluation happens now |
| **D14** | Clinic's real IANA timezone (§3.3a) | **`Africa/Cairo`.** `config/app.php` changed to `env('APP_TIMEZONE', 'UTC')` (was a hardcoded literal); `APP_TIMEZONE=Africa/Cairo` set in `backend/.env.example` (mirrored to the real local `backend/.env`) — the project's actual convention, since neither compose file sets `APP_*` vars directly and CI copies `.env.example` itself; `tzdata` added to both PHP Dockerfiles for full IANA resolution on Alpine. `now()`-vs-wall-clock comparisons (types 9/11) and `dailyAt()` run times (D11) all resolve through this one config value |

---

## 17. Multi-Tenant & PWA/Mobile Check (standing checklist item)

Per [[workflow_two_phase_process]]'s standing requirement, confirmed 2026-07-27, applying to every module:

- **[[policy_saas_multitenant_readiness]]**: Reviewed in §12 above. All new recipient resolution routes
  through `RecipientResolver` (the existing single seam); the 3 scheduled queries and the new preferences
  table are flagged as needing a future `clinic_id` scope, not silently left implicit. No new tech debt
  introduced beyond what Phase A/B already carry forward by design.
- **[[policy_pwa_mobile_first]]**: Phase C adds no new UI. Phase D's one new Settings tab reuses the
  existing responsive table→stacked-card pattern already in Settings — no new layout invented, touch
  targets and RTL inherited from the existing Settings shell.

No new debt introduced against either policy by this phase.

---

## 18. Requirements Traceability

Same 10 brief requirements as the parent doc (§15 there) — Phase C/D's only additions: requirement 4
("extensible — In-app / Email / Push separated") is where Phase D actually delivers the second channel the
parent doc's architecture was built to support without redesign; requirement 9 (performance) is re-verified
against the two new hot-path additions (observer, scheduled commands) in §11 above, not just re-asserted.

---

## 19. Implementation Notes (2026-08-12)

Phase C implemented on `main`'s working tree the same day design was approved. Recorded here per this
project's standing practice of documenting what turned out differently from, or more specific than, the
design above — not a reopening of any approved decision.

### What was built, matching the design as approved

`NotificationPolicy::CATEGORY_SUBJECT_MAP` gained `inventory => Supply::class` (a real Policy, fit the
existing map with zero new mechanism); a new `GATE_CATEGORIES` map plus a new `allCategories()` helper for
`security`; a new one-method `AuditLogPolicy::view()` proxying `view-audit-logs` (§8.1's design, needed
because `dispatchFor()`'s send-time check calls `can('view', $subject)` against a real Policy method, and
`security`'s subject — the triggering `AuditLog` row — needed one that didn't exist before). 5 new
`BaseNotification` subclasses (§0 above has the subject-resolution story for each). 3 new Commands
(`notifications:lab-cases-overdue`, `:low-stock-digest`, `:appointments-unconfirmed`) registered in
`routes/console.php` at 03:30/08:00/17:00 (D11/D14). A new `AuditLogObserver`, registered via
`AuditLog::observe(...)` in `AppServiceProvider::boot()` (not a trait — this observes one model for one
narrow purpose, unlike `Auditable`). `NotificationService::dispatchFor()` gained one new optional parameter,
`bool $deduplicateUnread = false` (default off, zero behavior/perf change for the 8 Phase A types) — an
extra per-recipient existence query, opted into only by the 3 scheduled Commands, implementing §3.1's
per-recipient "skip if unread" rule precisely (a read notification is fair game to resurface; an unread one
is not renotified). Frontend: 2 new `CATEGORY_STYLES` entries + 3 new `TYPE_ICONS` overrides in
`config/notificationTypes.ts`, 5 new `NotificationType` union members + 2 new `NotificationCategory` members
in `types/notification.ts`, 12 new i18n keys × 3 locales (5 title + 5 body + 2 category labels) — no
component changed, since `NotificationCenter.vue`'s category chip row and `NotificationItem.vue` already
iterate `NOTIFICATION_CATEGORIES`/read from the config maps generically (confirmed by reading both before
assuming this).

### Three real bugs found only by testing/running, not by reading — recorded because none of them could
### have been caught in the design phase

1. **`NotificationIndexRequest`'s `category` validation rejected `security`.** Its `Rule::in(...)` read only
   `NotificationPolicy::CATEGORY_SUBJECT_MAP`'s keys — the map `security` deliberately isn't in (§0 point 2
   above). Fixed by adding `NotificationPolicy::allCategories()` (unions both maps) as the single source of
   truth for both `allowedCategories()` and the request rule, so a category can never be accepted by one and
   rejected by the other again structurally, not just by remembering to update both.
2. **`AuditLog.created_at` was on a different clock than the rest of the app — a real bug this phase's own
   D14 fix exposed, not a pre-existing one.** `AuditLog` is the only model using `$timestamps = false` (no
   `updated_at` column exists) plus the migration's DB-level `useCurrent()` default for `created_at` — which
   runs on the database's own real-UTC clock, untouched by `config('app.timezone')`. Every other timestamp
   in the app is set by PHP's `now()`, which is clinic wall-clock time as of D14. Result: `AuditLogObserver`'s
   own login-failure window query (`created_at >= now()->subMinutes(15)`) silently matched zero rows,
   confirmed directly — a freshly-inserted row's real-UTC `created_at` sat ~3 hours "in the past" relative to
   a `now()` that was 3 hours ahead of it. Root-caused via a debug trace comparing `DB::selectOne('select
   CURRENT_TIMESTAMP...')` (real UTC) against `now()` (Cairo) side by side, not guessed at. Fixed at the
   model level — `AuditLog::booted()` gained a `creating` hook setting `created_at ??= now()` — so every
   creation path (the service, or any direct `::create()` call, including tests) lands on the same clock as
   everything else, not just the one call site that happened to surface it. A dedicated regression test
   (`AuthAuditTest::test_created_at_is_set_on_the_same_clock_as_the_rest_of_the_app_not_the_databases_own`)
   asserts this directly, independent of the notification feature that first exposed it.
3. **A Dockerfile change crash-looped the dev environment — found immediately after rebuilding, fixed
   before it could affect anyone else.** Adding `tzdata` to `docker/php/Dockerfile`'s `apk add` list (a
   defensive addition — PHP's bundled timezone database turned out to already resolve `Africa/Cairo`
   correctly without it, confirmed by testing the *old* image directly) invalidated Docker's build cache at
   that layer, forcing a fresh `COPY docker/php/entrypoint.sh` from the working tree instead of reusing a
   long-cached layer. On this Windows checkout (`core.autocrlf=true`), that working-tree file had CRLF line
   endings — `#!/bin/sh\r` doesn't resolve as an interpreter path, so `app`/`queue`/`scheduler` crash-looped
   on `exec entrypoint.sh: no such file or directory` immediately after the rebuild, despite having run fine
   all week off the old cached layer. This is a materially different class of finding than the
   already-documented Prettier CRLF warning (cosmetic — files still work) — a real functional failure latent
   in the repo the whole time, invisible until a Dockerfile edit happened to invalidate the exact cache layer
   that had been masking it. Fixed in two parts: normalized `entrypoint.sh` to LF on disk, and added a new
   `.gitattributes` (`docker/**/*.sh text eol=lf`) forcing LF for exactly this file class regardless of local
   `core.autocrlf`, so no future Windows checkout can reintroduce it. Containers rebuilt again and confirmed
   healthy (`docker logs` clean, `schedule:list` correct, full backend suite re-run green a second time on
   the new image).
4. **A minor PHPStan finding, not a functional bug**: `NotificationPolicy::allowedCategories()`'s
   `array_values([...$policyBacked, ...$gateBacked])` — array-unpacking into a literal already renumbers
   integer keys, so the result is a list on its own; `array_values()` around it was a no-op. Removed.

### Verification actually performed

| Check | Result |
|---|---|
| Backend tests | **1211/1211 green** (1191 baseline + 20 new: 19 in a new `NotificationPhase5CTest`, 1 in `AuthAuditTest` for the `created_at` regression) — run twice, once before and once after the Docker image rebuild, both fully green |
| PHPStan | Clean on every new/touched file (confirmed by scoping analysis to exactly those files — a broader `app/Services`-wide run surfaces the same pre-existing `casts()` false-positive pattern `TECH_DEBT.md` already documents on unrelated files, not this pass's doing) |
| Pint | Clean |
| `vue-tsc` / ESLint | Clean |
| Prettier | Clean after `--write` (the only diffs were the pre-existing repo-wide CRLF/LF pattern already documented, plus one genuine import-wrapping reformat now applied) |
| i18n parity | **1498/1498/1498, zero drift** (1486 Phase A/B baseline + 12 new keys: 5 title + 5 body + 2 category labels) |
| Frontend unit tests | Targeted run (`notificationTypes.ts`/`types/notification.ts`/`stores/notifications.ts`): **14/14 green**. The full suite was started but not completed in this pass — see below |
| `schedule:list` | Confirmed all 3 new Commands registered with the correct clinic-wall-clock run times |
| `queue`/`scheduler`/`app` containers | Confirmed healthy post-rebuild (`docker logs` clean, no crash-loop) |
| Real-browser verification | **Not completed in this pass.** Playwright's browser binaries are not pre-installed in this dev container (a distinct, newly-discovered constraint from the already-documented local `login()` E2E issue in `TECH_DEBT.md`), and installing them was judged not worth the uncertain time cost given the change is config-only additions to two already-tested, unmodified Vue components (`NotificationCenter.vue`'s chip row and `NotificationItem.vue` both confirmed, by reading, to iterate `NOTIFICATION_CATEGORIES`/read the config maps generically rather than hardcoding the original 5). Flagged honestly rather than silently skipped — see §20 for the recommended close-out step this implies |

### Full frontend suite — a note on why it wasn't finished

The full `npm test` (Vitest) run was started and left running for over 20 minutes without completing —
confirmed still actively progressing via container process CPU time (not hung), consistent with this
project's already-documented local Docker per-request latency pattern (`PROJECT_STATUS.md` §12, Phase 2.6b),
just more pronounced here. Killed in favor of a targeted run against exactly the files this phase touched,
which passed cleanly. Not re-attempted to completion given the real backend suite (the stronger signal for
this predominantly backend-side phase) was independently re-verified fully green twice.

---

## 20. Recommended Close-Out Step

Before this phase is considered fully verified to the same bar as Phase A/B (which had a real
`workflow_dispatch` CI run confirm `notifications.spec.ts` end-to-end), the same mechanism should extend to
Phase C: a `workflow_dispatch` run of `.github/workflows/ci.yml`, ideally with a small addition to
`e2e/notifications.spec.ts` covering at least one scheduled type (e.g. seed an overdue lab case, run
`artisan notifications:lab-cases-overdue`, assert the dentist's session sees it) and confirming the two new
category chips render in a real browser. Not done as part of this implementation pass — recorded as the
natural next step, matching how Phase 5A/B's own design doc closed out.

---

*(Design approved and Phase C implemented 2026-08-12. Phase D remains a separate future design/approval
cycle per Decision D12 — D10/D13 untouched.)*
