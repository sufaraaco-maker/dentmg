# Notification System — Design Document (Phase 5)

> **Status: DESIGN — awaiting approval. No implementation code written.**
> Produced by auditing the actual codebase, not from assumption. Every "exists" / "does not exist"
> claim below was verified by reading the real file, migration, config, or `package.json` entry cited
> next to it. Where this document says something is absent, that absence was confirmed by search, not
> inferred.

| | |
|---|---|
| **Phase** | 5 of the post-roadmap 8-phase plan (Stabilization → Patient Profile → Dashboard 2.0 → Permissions/Audit → **Notifications** → SaaS Multi-Tenant → PWA/Mobile → AI Expansion → Launch) |
| **Baseline** | `main` at `75cf60b` (PR #38 merge — Phase 4 docs close-out); Phase 4 post-merge CI fully green (run `31333946387`) |
| **Predecessor** | Phase 4 (Advanced Permissions & Audit) — complete, PR #37 |
| **Scope** | **Staff-facing** In-App notifications. Email designed-for but shipped disabled. Web Push, patient-facing reminders, WhatsApp/SMS explicitly deferred — see §4.4 and §13. |

---

## 1. Current State Analysis

### 1.1 There is no notification system — and the absence is deliberate and documented

| Thing | State | Evidence |
|---|---|---|
| `notifications` table | **Does not exist** | No migration matches `*notif*` in `backend/database/migrations/` |
| `app/Notifications/` | **Does not exist** | Directory absent |
| `app/Mail/` | **Does not exist** | Directory absent |
| `app/Jobs/` | **Does not exist** | Directory absent |
| `config/broadcasting.php` | **Does not exist** | Not in `backend/config/` |
| Notification API endpoint | **None** | `backend/routes/api.php` has no notification route |
| Frontend notification store/service | **None** | No `stores/notifications.ts`, no `services/notifications/` |

`TECH_DEBT.md` already records this as a known, intentional gap:

> **Header notifications is inert UI (no notification backend)** — `AppHeader.vue`'s bell icon opens a
> popover that always reads "No notifications yet" … Scaffolded deliberately as inert UI … so the header
> only needs a data source filled in later, not a redesign.
> **Revisit**: when a real notification system exists on the backend.

This phase is that revisit.

### 1.2 What already exists and is directly reusable

This is the important half of the audit — the project is much closer to a notification system than the
table above suggests, because Phase 2.6 built the event backbone and Phase 4 built the permission backbone.

**(a) A proven event bus with 24 live dispatch points.**
[`PatientActivityOccurred`](../../backend/app/Events/PatientActivityOccurred.php) is one generic event
class carrying `(subject, actor, eventType, category, summary, metadata)`. It is dispatched from
**21 physical `event(new …)` call sites across 9 services**, covering **24 logical event types** (one site
in `AppointmentService::transitionTo()` interpolates `"appointment.{$to->value}"` and so covers 4 types):

| Service | Sites | Event types |
|---|---|---|
| `AppointmentService` | 3 | `appointment.cancelled`, `appointment.no_show`, + `confirmed`/`checked_in`/`in_progress`/`completed` via `transitionTo()` |
| `TreatmentPlanService` | 5 | `treatment_plan.presented` / `.accepted` / `.rejected` / `.completed` / `.cancelled` |
| `LabCaseService` | 3 | `lab_case.sent` / `.received` / `.quality_checked` |
| `MedicalHistoryService` | 3 | `medical_history.allergy_added` / `.condition_added` / `.medication_added` |
| `InvoiceService` | 2 | `invoice.issued` / `.voided` |
| `PaymentService` | 2 | `payment.recorded` / `.refunded` |
| `ClinicalNoteService` | 1 | `clinical_note.signed` |
| `PatientDocumentService` | 1 | `document.uploaded` |
| `PatientImageService` | 1 | `image.uploaded` |

Today exactly **one** listener subscribes:
[`RecordsPatientActivity`](../../backend/app/Listeners/RecordsPatientActivity.php), which writes one
`PatientActivity` row. It is **deliberately synchronous** (no `ShouldQueue`), with this comment:

> *"no queue worker actually runs in this project today (confirmed by audit), and a single indexed INSERT
> is cheap enough not to need one."*

**A second listener on the same event gives this module reactive coverage of 9 modules with zero new
dispatch call sites.** That is the single most consequential finding in this audit.

**(b) A permission catalog and a category-filtering security precedent.**
Phase 4 shipped `permissions`/`role_permissions`, `User::hasPermission()` with a per-role cache, and all
27 Policies converted to it. More directly relevant,
[`PatientActivityPolicy`](../../backend/app/Policies/PatientActivityPolicy.php) establishes the exact
pattern this module needs — a `CATEGORY_SUBJECT_MAP` plus `allowedCategories(User)`, which checks each
category against its *real owning policy's* `viewAny()` at the class level, then filters **in the query**:

> *"categories the actor can't read are excluded from the query itself, never fetched then hidden.
> Computed once per request — a fixed small number of `can()` checks, not one per row."*

**(c) `Notifiable` is already on the `User` model.**
[`User.php`](../../backend/app/Models/User.php) uses `Illuminate\Notifications\Notifiable` (Laravel
skeleton default). It is currently inert — there is no table behind it — but it means adopting Laravel's
native notification stack requires **zero change to the User model**.

**(d) A forward-compatible `appointment_reminders` table, built for exactly this module.**
Migration `2026_07_15_000006_create_appointment_reminders_table.php` and
[`AppointmentReminder`](../../backend/app/Models/AppointmentReminder.php) already exist, with a
`scopePending()` and a `[status, scheduled_at]` index. Both carry an explicit docblock:

> *"Forward-compatible schema only — not wired into any Service, Job, or API endpoint yet. Included now
> (per explicit approval of the Appointments design) so the future Notifications module has a table to
> query/write to without an Appointments-module migration later. `channel` is a plain string, not a backed
> enum: the Notifications module hasn't been designed yet, so the final channel set
> (email/SMS/WhatsApp/…) isn't decided here."*

**Important scope note:** this table is **patient-facing** (remind the *patient* about their appointment
via email/SMS/WhatsApp). That is a materially different feature from staff-facing in-app notifications —
it needs a real outbound transport, patient contact-preference capture, and consent handling, none of
which exist. See §4.4 / §13 for why V1 leaves it untouched rather than half-wiring it.

**(e) Established frontend conventions to follow, not reinvent.**
Pinia setup-stores with the store-owns-error-state pattern and an i18n key as the error value
(`stores/auditLogs.ts`); a raw-backend-value → i18n-label mapping layer applied *before* values reach the
UI (`config/auditableTypes.ts`); mandatory date formatting through `frontend/src/lib/date.ts`; 1453 i18n
keys at exact 3-locale parity (`en`/`ar`/`tr`, verified programmatically this session).

### 1.3 Infrastructure reality — the gap that shapes the whole phase plan

| Component | Configured? | Actually running? | Evidence |
|---|---|---|---|
| Redis | ✅ Yes | ✅ Yes | `redis` service in both `docker-compose.yml` and `docker-compose.prod.yml` |
| Queue connection | ✅ `QUEUE_CONNECTION=redis` (`.env.example`); `config/queue.php` default `database` | ❌ **No worker exists** | **No `queue:work`, no Horizon, no supervisor, no worker container** in either compose file |
| Scheduler | ❌ Nothing scheduled | ❌ **No `schedule:run`** | `routes/console.php` contains only the stock `inspire` command; no scheduler container in either compose file |
| Mail | ⚠️ `MAIL_MAILER=log` | ❌ Goes to a log file | `.env.example`; no `app/Mail/`, no Mailable, no mail template |
| Broadcasting | ⚠️ `BROADCAST_CONNECTION=log` | ❌ **No realtime layer** | No `config/broadcasting.php`; no Echo/Pusher/Reverb/socket dependency in `frontend/package.json` |
| PWA / service worker | ❌ **Absent** | ❌ | No `vite-plugin-pwa`/`workbox` in `frontend/package.json` or `vite.config.ts`; no manifest in `frontend/public/`. Already logged in `TECH_DEBT.md` as a whole-app gap |

**Consequences, stated plainly:**

1. **Anything `ShouldQueue` would silently never run today.** Jobs would pile up in Redis unconsumed.
   This is why `RecordsPatientActivity` is synchronous — that was the correct call at the time, and it
   constrains this design too.
2. **No scheduled notification is possible** (reminders, overdue lab cases, low-stock digests) until a
   scheduler exists. That is new infrastructure, not new application code.
3. **No server push.** With no broadcasting layer and no frontend socket client, the in-app Notification
   Center must **poll**. Designing for polling now and swapping in broadcast later is cheap; assuming a
   socket layer that does not exist is not.
4. **Web Push is not buildable in this phase.** It requires a service worker, which requires the PWA
   foundation, which is roadmap **Phase 6**. Building it here would mean building half of Phase 6 inside
   Phase 5.

### 1.4 Existing "alerting" surfaces that are *not* notifications

Two Dashboard widgets already surface time-sensitive state by polling on page load: the Inventory
**Low Stock** widget and the Laboratory **Lab Cases Due** widget. They are live queries, not notifications
— no read/unread state, no per-user addressing, no persistence. They are relevant here as **data sources**
for future scheduled notifications (§5, types 12–13), not as things to replace.

### 1.5 Architecture compatibility check

| Standing principle | Compatible? | How |
|---|---|---|
| Modular monolith, service layer, thin controllers | ✅ | `NotificationService` + `NotificationController`; recipient resolution in one service, never in controllers |
| API-first | ✅ | Every capability exposed as a REST endpoint before any UI consumes it |
| **SaaS multi-tenant readiness** (permanent directive) | ✅ | Recipients are resolved in **exactly one place** (`RecipientResolver`), so a future `clinic_id` scope lands in one query, not at 24 call sites. Table takes a nullable `clinic_id` additively later, same pattern `audit_logs` used in Phase 4 |
| **PWA & mobile-first** (permanent directive) | ✅ | Notification Center is a responsive panel: popover on `md:`+, full-screen sheet below. Touch targets ≥44px |
| Datetime policy (`lib/date.ts` mandatory) | ✅ | All timestamps rendered through it; no raw `Date` anywhere in this module |
| UUID PKs, soft deletes, audit logs | ⚠️ Partial by design | UUID PK ✅. **No soft deletes and not `Auditable`** — notifications are per-user delivery artifacts, not clinical/financial records; see §6.4 |
| Prefer Laravel native, no unnecessary abstraction | ✅ | Uses Laravel's own `notifications` table, `Notifiable`, `DatabaseNotification`, `Prunable` — see §3.1 |

---

## 2. Gaps

Ranked by how much they constrain the design.

| # | Gap | Severity | Resolution |
|---|---|---|---|
| **G1** | **No queue worker process exists anywhere** despite `QUEUE_CONNECTION=redis` | **High** — a latent trap: any future `ShouldQueue` silently no-ops | Phase B adds a `queue` worker container to dev + prod compose. Phase A deliberately stays synchronous so it does not depend on this |
| **G2** | **No scheduler** (`schedule:run`) | **High** — blocks every time-based notification | Phase B adds a `scheduler` container |
| **G3** | **No realtime/broadcast layer** | Medium | Poll `unread-count` on an interval; keep the API contract broadcast-swappable |
| **G4** | **No PWA/service worker** | Medium | Web Push deferred to roadmap Phase 6. `channel` abstraction leaves the slot open |
| **G5** | **`MAIL_MAILER=log`**, no Mailable, no template | Medium | Email designed in full, shipped **disabled-by-default**, fails closed without a configured mailer — the AI Assistant's established precedent |
| **G6** | **`users` has no `locale` column** (confirmed: `0001_01_01_000000_create_users_table.php` has none, and no later migration adds one) | Medium | Blocks *server-rendered* localized notifications (email). Not needed for in-app, which localizes client-side (§11). Column added in Phase D alongside email |
| **G7** | Backend has **no `lang/` files** for the 3 app locales | Medium | Same as G6 — only email needs them |
| **G8** | `appointment_reminders` is an unwired table with a `channel` column deliberately left undecided | Low | Stays untouched in V1; §13 explains why half-wiring it is worse than leaving it |
| **G9** | Header bell is inert placeholder UI; only 2 i18n keys exist (`common.notifications`, `common.noNotifications`) | Low | Replaced by the real Notification Center in Phase A |
| **G10** | No notification **preferences** concept anywhere; `Settings` design doc §2/§9 explicitly put notification/reminder settings out of its own scope | Low | V1 ships a deliberately small, curated, low-noise type set with **no preferences**. Preferences arrive with email (Phase D), when opting out first has real meaning |

---

## 3. Recommended Architecture

### 3.1 Use Laravel's native notification stack — with four additive columns

**Recommendation: adopt `Illuminate\Notifications` (the `notifications` table, `Notifiable`,
`DatabaseNotification`) rather than build a bespoke model.**

Laravel's `DatabaseNotification` gives, for free and already tested upstream, essentially the entire
Notification Center requirement list: `read_at`, `markAsRead()`, `markAllAsRead()`, `unreadNotifications`
/ `readNotifications` relations, and a `data` JSON payload. `User` already has `Notifiable`. Building a
parallel `Notification` model would be exactly the "unnecessary abstraction" the brief warns against.

**But the stock schema is not sufficient on its own.** It has no `category`, no `subject`, no `patient_id`
— which matters for two concrete reasons:

1. **Security (§8.2):** a notification must be re-checked against the recipient's *current* permissions at
   read time, not only at creation time. That requires a `WHERE category IN (…)` on an indexed column.
   Probing `data->>'category'` on every list request is measurably worse and cannot use the same index.
2. **Navigation (§7.4):** "open the notification → go to the related resource" needs a stable
   `subject_type`/`subject_id`, and deep links are patient-scoped throughout this app.

**Proposal:** extend the stock table with four real columns (`category`, `subject_type`, `subject_id`,
`patient_id`) and populate them via a ~20-line subclass of Laravel's own
`Illuminate\Notifications\Channels\DatabaseChannel` overriding `buildPayload()`, bound in
`AppServiceProvider`. This is a documented Laravel extension point, not a fork.

This is precisely the move **Phase 4 Step 3 already made on `audit_logs`** — additive columns on an
existing table plus a service that populates them — so it follows an in-repo precedent rather than
inventing one. **Flagged as Decision D1 (§16).**

### 3.2 Two entry points, one write path

```
┌─ REACTIVE (Phase A) ──────────────────────────────────────────┐
│  9 services  ──event(new PatientActivityOccurred)──┐          │
│  (24 existing types, ZERO new dispatch sites)      │          │
│                                                    ▼          │
│                             ┌──────────────────────────────┐  │
│                             │ RecordsPatientActivity       │  │ existing, untouched
│                             │   → patient_activities       │  │
│                             ├──────────────────────────────┤  │
│                             │ SendsNotifications   ← NEW   │  │
│                             │   consults NotificationRules │  │
│                             └──────────────┬───────────────┘  │
└────────────────────────────────────────────┼──────────────────┘
                                             │
┌─ SCHEDULED (Phase C, needs G1+G2) ─────────┤
│  scheduler → artisan commands ─────────────┤
│  (lab overdue, low stock, unconfirmed)     │
└────────────────────────────────────────────┤
                                             ▼
                        ┌────────────────────────────────────┐
                        │ NotificationService                │
                        │  1. NotificationRules: notify?     │  ← curated allow-list
                        │  2. RecipientResolver: whom?       │  ← the single tenant-scope seam
                        │  3. drop the actor from recipients │  ← universal anti-noise rule
                        │  4. Notification::send(...)        │  ← Laravel native
                        └────────────────┬───────────────────┘
                                         ▼
                     ┌───────────────────┴────────────────────┐
                     │ database (V1)  │ mail (Phase D, off)   │  push → Phase 6
                     └───────────────────┬────────────────────┘
                                         ▼
                        notifications table  ──GET──►  Notification Center
                                                       (polls unread-count)
```

**Key properties:**

- **No new dispatch call sites.** Phase A adds one listener to an event that already fires in 9 services.
  Contrast with Phase 2.6, which needed 24 hand-placed call sites.
- **`NotificationRules` is an explicit allow-list, not a filter-out.** Of the 24 existing activity types,
  V1 notifies on **8** (§5). Silence is the default. This is the primary answer to requirement 9
  (performance) — the cheapest notification is the one never created.
- **The actor is never notified of their own action.** Enforced once, in `NotificationService`, not
  per-rule.
- **`RecipientResolver` is the one multi-tenant seam.** Every "who receives this" query lives in one
  class. When multi-tenancy lands, one `where('clinic_id', …)` is added there.

### 3.3 Synchronous in Phase A, queued in Phase B

Phase A's listener is **synchronous**, matching `RecordsPatientActivity` and its documented reasoning —
because a worker does not exist (G1), and a `ShouldQueue` listener would silently never run.

Cost analysis: the heaviest V1 rule (`treatment_plan.accepted`) resolves recipients with one indexed
`users` query and inserts ≤ N rows in one batch. Well under the per-request budget already accepted for
`RecordsPatientActivity`'s synchronous INSERT.

Phase B adds the worker, then flips `SendsNotifications` to `ShouldQueue` — a **one-line change** with the
listener already written and tested. Email (Phase D) is queued from the start, since SMTP latency in a
request cycle is not acceptable.

### 3.4 Why not broadcasting / websockets in V1

No `config/broadcasting.php`, no Reverb/Pusher/Echo dependency, `BROADCAST_CONNECTION=log`. Introducing a
realtime layer means a new server process, a new frontend dependency, new auth for private channels, and
new E2E surface — for a clinic-scale app where a 60-second unread-count poll is indistinguishable in
practice. **Recommendation: poll in V1.** The endpoint contract (§7) is deliberately shaped so a broadcast
push can later update the same store without any UI change.

---

## 4. Channel Strategy

### 4.1 In-App (`database`) — V1, fully built
Laravel's `database` channel + the custom `DatabaseChannel` subclass (§3.1). This is the real deliverable:
persistent, per-user addressed, read/unread tracked, deep-linkable.

### 4.2 Email (`mail`) — designed now, shipped disabled (Phase D)
Blocked on G5 (no real mailer), G6 (`users.locale`), G7 (backend `lang/`). Ships **disabled by default and
fails closed** when no mailer is configured — the exact pattern AI Assistant established (no
`ANTHROPIC_API_KEY` ⇒ 503, never a silent no-op). Requires per-user preferences (§10) to exist first,
because email without an opt-out is a defect.

### 4.3 Web / PWA Push — deferred to roadmap Phase 6
Requires a service worker; none exists (G4, and `TECH_DEBT.md`'s standing whole-app PWA item). The
`channel` abstraction is Laravel's own, so adding a push channel later needs no redesign here.
**Recommendation: do not start it in this phase.**

### 4.4 Patient-facing reminders (SMS / WhatsApp / patient email) — out of scope
This is what `appointment_reminders` was built for. It needs an outbound transport, patient contact
preferences, consent/opt-out records, and a delivery-failure model — a module in its own right, with
regulatory weight. `PROJECT_STATUS.md` already flags WhatsApp as "a future channel, not built now."
V1 leaves the table untouched. **Half-wiring it would create a table that looks live but silently drops
every reminder** — worse than leaving it visibly unwired with its existing explanatory docblock.

---

## 5. Notification Types Matrix

Every "Trigger" below is an event that **already fires in the codebase today** (§1.2a) or a query over
data that **already exists**. Nothing here assumes a feature that is not built.

**Universal rules:** the actor never receives a notification for their own action; unresolvable recipients
(e.g. no assigned dentist) yield zero rows, never an error.

### 5.1 Phase A — reactive, from existing `PatientActivityOccurred` events

| # | Type | Trigger (existing event) | Recipients | Category | Deep link |
|---|---|---|---|---|---|
| 1 | `appointment.checked_in` | `appointment.checked_in` | Assigned **dentist** | `appointments` | Appointment |
| 2 | `appointment.cancelled` | `appointment.cancelled` | Assigned **dentist** + **admins & receptionists** (slot needs refilling) | `appointments` | Appointment |
| 3 | `appointment.no_show` | `appointment.no_show` | Assigned **dentist** + **admins** | `appointments` | Appointment |
| 4 | `treatment_plan.accepted` | `treatment_plan.accepted` | Presenting **dentist** + **admins & receptionists** (needs scheduling) | `treatment_plans` | Treatment plan |
| 5 | `treatment_plan.rejected` | `treatment_plan.rejected` | Presenting **dentist** | `treatment_plans` | Treatment plan |
| 6 | `lab_case.received` | `lab_case.received` | Prescribing **dentist** | `laboratory` | Lab case |
| 7 | `payment.refunded` | `payment.refunded` | **Admins** (exception event, financial control) | `payments` | Invoice / payment |
| 8 | `invoice.voided` | `invoice.voided` | **Admins** (exception event, financial control) | `billing` | Invoice |

**Deliberately NOT notified in V1** — and the reasoning, because "why is X missing" is the first review
question:

| Existing event | Why silent |
|---|---|
| `appointment.confirmed` / `.in_progress` / `.completed` | Routine lifecycle noise; the actor is present |
| `invoice.issued`, `payment.recorded` | High-frequency routine front-desk work. Refunds/voids are the *exceptions* worth surfacing |
| `clinical_note.signed` | The signer is the actor |
| `document.uploaded`, `image.uploaded` | Routine; almost always done by the person who wanted it |
| `lab_case.sent`, `.quality_checked` | `sent` is the actor's own action; `quality_checked` is arguably useful — **Decision D4 (§16)** |
| `treatment_plan.presented` / `.completed` / `.cancelled` | Actor-driven, low signal |
| `medical_history.*` | Clinically valuable but needs a non-trivial "which dentist has this patient upcoming" query — **Decision D3 (§16)** |

### 5.2 Phase C — scheduled (requires G1 + G2 resolved in Phase B)

| # | Type | Trigger | Recipients | Category |
|---|---|---|---|---|
| 9 | `lab_case.overdue` | Daily: `due_at` passed, status not `received`/`quality_checked`/`cancelled` (existing `due_at` column) | Prescribing **dentist** + **receptionists** | `laboratory` |
| 10 | `inventory.low_stock` | Daily digest, reusing the **existing** live low-stock computation behind the Dashboard widget | **Admins & receptionists** | `inventory` |
| 11 | `appointment.unconfirmed` | Daily: tomorrow's appointments still in `scheduled` (not `confirmed`) | **Receptionists & admins** | `appointments` |

### 5.3 Phase C — administrative / security (from existing Phase 4 audit data)

| # | Type | Trigger | Recipients | Category |
|---|---|---|---|---|
| 12 | `security.repeated_login_failures` | N+ `login_failed` audit rows for one email within a window (Phase 4 already logs these) | **Admins** | `security` |
| 13 | `permissions.matrix_updated` | Existing `role_permissions_updated` audit event | **Admins** (except the actor) | `security` |

**Categories introduced:** `appointments`, `treatment_plans`, `laboratory`, `payments`, `billing`
(all reuse `PatientActivityPolicy::CATEGORY_SUBJECT_MAP` keys verbatim) plus two new ones, `inventory` and
`security`, which need their own authorization mapping (§8.2).

---

## 6. Database / Data Model

### 6.1 `notifications` — Laravel's table, plus four additive columns

```php
// Laravel's stock create_notifications_table, then:
Schema::create('notifications', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('type');                              // stock: the Notification class FQCN
    $table->uuidMorphs('notifiable');                    // stock: always User in V1
    $table->text('data');                                // stock: JSON payload (i18n key + params)
    $table->timestamp('read_at')->nullable();            // stock
    $table->timestamps();                                // stock

    // --- Additive (this phase), rationale in §3.1 ---
    $table->string('category')->index();                 // authorization re-check at read time
    $table->uuidMorphs('subject');                       // deep-link target
    $table->foreignUuid('patient_id')->nullable()
          ->constrained()->nullOnDelete();               // patient-scoped deep links
});
```

**Indexes** (requirement 9 — performance is a design input, not an afterthought):

| Index | Serves |
|---|---|
| `(notifiable_type, notifiable_id, read_at)` | The unread `COUNT(*)` — the single hottest query, polled every 60s per session. A partial index `WHERE read_at IS NULL` is the Postgres-native option — **Decision D2 (§16)** |
| `(notifiable_type, notifiable_id, created_at DESC)` | The paginated list |
| `(category)` | The read-time authorization filter |
| `uuidMorphs('subject')` | Ships its own composite index (Laravel default) |

**Why `patient_id` is nullable:** types 10, 12 and 13 (low stock, security) have no patient. This is the
one place this module's schema genuinely diverges from `patient_activities`, which requires a patient.

### 6.2 `data` payload shape — translation keys, never translated text

```json
{
  "titleKey": "notifications.types.appointment_cancelled.title",
  "bodyKey":  "notifications.types.appointment_cancelled.body",
  "params":   { "patientName": "…", "dentistName": "…", "startAt": "2026-08-12T09:00:00Z" },
  "actorName": "…",
  "route":    { "name": "patient-detail", "params": { "id": "…" }, "query": { "tab": "appointments" } }
}
```

Storing keys + params rather than rendered strings is what makes §11 work. Justified in full there.

### 6.3 Phase D additions (email)

- `users.locale` (nullable, default from app config) — closes G6.
- `notification_preferences`: `(user_id, type, channel, enabled)` — introduced only when opting out has
  meaning, i.e. with email.

### 6.4 Deliberate deviations from the project's DB conventions

| Convention | This module | Why |
|---|---|---|
| Soft deletes | ❌ Not used | A dismissed notification is a delivery artifact, not a record with history. Pruned, not tombstoned |
| `Auditable` | ❌ Not used | Same reasoning `PatientActivity` documents: this is a derived feed, not an auditable business record. Auditing "user read a notification" would be pure noise |
| Immutable / append-only | ⚠️ Partly | Rows are append-only **except `read_at`**, which is the one mutable field by definition |
| Pruning | ✅ `Prunable` — read notifications older than 90 days | Needs the scheduler (G2), so it activates in Phase B |

---

## 7. Backend API Design

All routes sit inside the existing `auth:sanctum` group in `routes/api.php`.

| Method | Route | Purpose |
|---|---|---|
| `GET` | `/notifications` | Paginated list. Filters: `status=unread\|read\|all`, `category`. Default `per_page=15`, matching every other list endpoint |
| `GET` | `/notifications/unread-count` | `{ "count": 7 }` — one indexed `COUNT(*)`, the polled endpoint |
| `POST` | `/notifications/{notification}/read` | Mark one as read (idempotent) |
| `POST` | `/notifications/read-all` | Mark all of the caller's unread as read. Respects the caller's current category filter |
| `DELETE` | `/notifications/{notification}` | Dismiss one (optional — **Decision D5, §16**) |

**Structurally IDOR-proof by construction.** Every route resolves its target from `$request->user()`'s own
`notifications()` relation — never from a route-model-bound `{notification}` looked up globally. This is
the same structural guarantee `ProfileController` already relies on, and it is why no `NotificationPolicy`
is strictly required for ownership (§8.1).

**`unread-count` is deliberately separate from the list.** Polling must not deserialize 15 rows every
60 seconds just to render a badge.

**Broadcast-swappable contract:** the frontend store's only inputs are "here is a new count" and "here is a
page of notifications." A future broadcast event can feed the same store with no UI change (§3.4).

---

## 8. Permissions Model

Three independent layers. Requirement 10 is satisfied by layer 1 alone; layers 2 and 3 are defense in
depth against permission drift over time.

### 8.1 Layer 1 — Ownership (structural)
A notification is addressed to exactly one `notifiable` user. Every query is scoped through
`$request->user()->notifications()`. **A user cannot address another user's notifications, because no route
can express it.** No `where user_id = ?` to forget, no policy to misconfigure.

**Recommendation: no new permission catalog entry for reading one's own notifications** — reading your own
notifications is self-scoped, exactly like My Account, which correctly has no permission key either.
**Flagged as Decision D6 (§16)** since it deviates from "every endpoint has a catalog entry."

### 8.2 Layer 2 — Read-time category re-check (defense in depth)
Layer 1 is not sufficient on its own. Consider: a dentist is notified of a `lab_case.received` on Monday;
on Tuesday an admin revokes `laboratory.view` from the dentist role via the Phase 4 matrix. The
notification row still exists and still contains a clinical summary.

**Every list/count query therefore also filters `whereIn('category', $allowedCategories)`**, computed
exactly as `PatientActivityPolicy::allowedCategories()` does — one class-level `can()` per category per
request, never per row, filtering in the query rather than fetching then hiding.

`NotificationPolicy::CATEGORY_SUBJECT_MAP` extends the existing map with the two new categories:

| Category | Authorized by |
|---|---|
| `appointments`, `treatment_plans`, `laboratory`, `billing`, `payments` | The existing owning policy's `viewAny()` — reused verbatim |
| `inventory` | `SupplyPolicy::viewAny()` |
| `security` | The hardcoded admin-only `view-audit-logs` Gate (Phase 4 §1.4) — **never** routed through the matrix, matching that decision |

### 8.3 Layer 3 — Send-time authorization
`RecipientResolver` only ever resolves users who can actually see the underlying resource, checked with
the same `can()` calls. **A notification is never created for a user who could not open its target.**
Layer 2 then catches anyone whose permissions change afterwards.

### 8.4 Multi-tenant isolation
No cross-tenant leak is possible in V1 because `notifiable_id` is a single user. For the future multi-clinic
model, `RecipientResolver` is the single seam (§3.2); the table takes a nullable `clinic_id` additively,
exactly as `audit_logs` did in Phase 4.

---

## 9. Queue / Event Strategy

| Phase | Listener mode | Queue needed | Rationale |
|---|---|---|---|
| **A** | Synchronous | ❌ No | No worker exists (G1). One indexed recipient query + one batched insert — within the budget `RecordsPatientActivity` already set |
| **B** | `ShouldQueue` | ✅ Yes | A one-line change once a worker is running |
| **C** | Queued commands | ✅ Yes | Scheduled work is queue-native |
| **D** | Queued (mandatory) | ✅ Yes | SMTP latency must never sit in a request cycle |

**Phase B infrastructure additions** (the real work of that phase):

1. A `queue` service in `docker-compose.yml` and `docker-compose.prod.yml`, reusing the existing `app`
   image, running `php artisan queue:work redis --tries=3 --backoff=…`.
2. A `scheduler` service running `php artisan schedule:work`.
3. `routes/console.php` gains the schedule definitions (currently only the stock `inspire` command).
4. Failed-job handling: the stock `failed_jobs` table already exists via
   `0001_01_01_000002_create_jobs_table.php`.

**Failure isolation:** notification delivery must **never** break the business operation that triggered it.
`SendsNotifications` catches and logs its own exceptions — the same fail-open-for-the-operation posture
Phase 4's `AuditLogService` already established, with the same caveat that the error log must never carry
the notification payload.

**Caching:** the unread count starts as a plain indexed `COUNT(*)`, not a cached value. Redis caching is
the documented escalation path if measurement justifies it — a cache invalidated on every read, every send,
and every permission change (layer 2 makes the count permission-dependent) is materially harder to keep
correct than a well-indexed count, and this project's philosophy is "performance before complexity," not
"complexity in anticipation."

---

## 10. Frontend Notification Center Design

Replaces the inert `AppHeader.vue:126–136` bell + popover (G9).

### 10.1 Components

| Component | Role |
|---|---|
| `NotificationBell.vue` | The header trigger. Bell icon + unread badge (`9+` cap). Owns the poll lifecycle |
| `NotificationCenter.vue` | The panel: filter tabs (All / Unread), the list, "Mark all as read", pagination / infinite scroll |
| `NotificationItem.vue` | One row: icon by category, translated title/body, relative time via `lib/date.ts`, unread dot |
| `stores/notifications.ts` | Pinia setup store, store-owns-error-state, error as an i18n key — matching `auditLogs.ts` |
| `services/notifications/` | API layer, matching `services/auditLogs/` |
| `config/notificationTypes.ts` | Maps `category` → icon + accent, and type → i18n key. The same "never let a raw backend value reach the UI" layer as `config/auditableTypes.ts` |

### 10.2 Behavior against requirement 7

| Requirement | Implementation |
|---|---|
| See notifications | Paginated list in the panel; a full `/notifications` page for history and filtering |
| Read vs unread | Unread rows carry a dot + weighted background; All/Unread tabs |
| Open → go to the resource | Row click → `router.push(data.route)` → marks read → navigates |
| Mark as read | Per-row action, and implicitly on open |
| Mark all as read | Panel header button; optimistic update with rollback on failure |
| Unread count | Badge from `/notifications/unread-count`, polled every 60s **only while the tab is visible** (Page Visibility API) — a background tab must not poll |

### 10.3 Mobile-first & RTL (permanent directives)

- **≥ `md:`** — PrimeVue `Popover` anchored to the bell.
  **< `md:`** — full-screen `Drawer`, matching the Phase 4 `PermissionsView` precedent of swapping layout
  rather than shrinking it. Touch targets ≥44px.
- **RTL:** logical properties only (`ms-`/`me-`/`ps-`/`pe-`), which is already the codebase convention.
  Panel anchors to the inline-end edge in both directions.
- **Dates:** every timestamp through `frontend/src/lib/date.ts`. No raw `Date`, no manual timezone math —
  the standing Architecture Violation rule.
- **a11y:** `aria-live="polite"` on the badge, focus trap in the panel, `Esc` to close, full keyboard
  navigation of the list.

---

## 11. Localization Strategy

### 11.1 Store keys and params — never rendered text

A notification is written server-side at event time, but read by a user whose locale is their own and can
change at any moment. Storing `"تم إلغاء الموعد"` freezes the wrong answer permanently.

**Therefore `data` stores `titleKey` / `bodyKey` / `params` (§6.2), and vue-i18n renders at display time.**

Consequences, all of them good:
- Switching language re-renders existing notifications correctly — no backfill, no migration.
- Coverage is verifiable by the **same programmatic 3-locale parity check** already used this project
  (1453/1453/1453 today); new keys must land in `en`/`ar`/`tr` together or the check fails.
- The backend needs **no** `lang/` files (G7), which it currently does not have.
- It matches `config/auditableTypes.ts`, which already translates raw backend values before they reach the
  UI.

### 11.2 Interpolated values

`params` carries **raw** values (ISO-8601 timestamps, names, numbers, amounts). Formatting happens at
render: dates via `lib/date.ts`, currency via the existing `BillingSetting.currency_code` helper. Storing a
formatted `"12 Aug 2026, 9:00 AM"` would be a datetime-policy violation and would freeze a locale.

### 11.3 RTL
Covered in §10.3. Arabic remains the default locale and the module ships all 3 locales from first
implementation — never an English-first pass with translation deferred.

### 11.4 Email (Phase D) is the exception
Email is rendered **server-side**, so it genuinely needs backend `lang/{en,ar,tr}` files (G7) and
`users.locale` (G6). Both land with Phase D. This is a real, non-obvious asymmetry and the reason email is
not simply "one more channel toggle."

---

## 12. Testing Strategy

Matching this project's established bar: backend Feature tests + frontend Vitest + a **permanent Playwright
E2E spec**, with the security-critical case asserted three ways (rendered DOM, network request never made,
direct API access blocked) — the precedent `e2e/dashboard.spec.ts` set in Phase 3.

### 12.1 Backend
- **Rules:** each of the 8 V1 types fires on its event and produces the expected recipient set; each
  non-notifying event produces **zero** rows (the allow-list is as important as the list).
- **Actor exclusion:** the actor never receives their own action's notification.
- **Recipient resolution:** correct dentist/admin/receptionist sets; unresolvable recipients produce zero
  rows without throwing.
- **Authorization (layer 2):** a user whose permission is revoked **after** a notification exists no longer
  sees it in list or count — the drift case in §8.2, asserted directly.
- **IDOR (layer 1):** user A cannot read, mark, or delete user B's notification (404, not 403 — the
  relation-scoped lookup never finds it).
- **Fail-open:** a deliberately broken notification write does **not** break the appointment cancellation
  that triggered it.
- **Endpoint contracts:** filters, pagination, idempotent mark-as-read, `read-all` honoring the active
  filter.
- **Zero regressions** across the existing 1145 backend tests — the strongest available evidence a new
  listener on a shared event changed nothing else.

### 12.2 Frontend (Vitest)
Store (fetch/mark/mark-all/optimistic rollback/error-as-i18n-key), `NotificationBell` badge including the
`9+` cap and the visibility-gated poll, `NotificationItem` rendering per category, route construction from
`data.route`, and the i18n **3-locale parity check** as a test, not a manual step.

### 12.3 E2E (`frontend/e2e/notifications.spec.ts`)
- A receptionist cancels an appointment → **the assigned dentist's** session shows an unread badge, opens
  the panel, clicks through to the appointment, and the badge clears.
- The **actor** does not receive their own notification (asserted, not assumed).
- **Security-critical, asserted three ways** per the Phase 3 precedent: a role without `laboratory.view`
  never renders a `laboratory` notification, never receives one in the network response, and gets nothing
  leaked from direct API access.
- Mark-all-as-read persists across reload.
- Renders correctly in Arabic (RTL) and at a 390px viewport.

### 12.4 Manual verification
In a real browser against real seeded data, in all 3 locales and at a mobile viewport — this project's
standing practice, and the way several real bugs were caught in Phases 2–4.

---

## 13. Explicitly Out of Scope

Recorded so "why isn't this here" is answered once, in writing:

| Excluded | Why | Where it goes |
|---|---|---|
| **Web / PWA Push** | Needs a service worker; no PWA foundation exists (G4) | Roadmap **Phase 6** (PWA & Mobile) |
| **Patient-facing reminders** (SMS / WhatsApp / patient email) | Needs outbound transport, patient contact preferences, consent/opt-out, delivery-failure handling. Already flagged as future in `PROJECT_STATUS.md`. `appointment_reminders` stays untouched — a half-wired table that silently drops reminders is worse than a visibly unwired one (§4.4) | Its own future module |
| **Realtime websockets** | No broadcast layer, no socket client; polling is adequate at clinic scale (§3.4) | Revisit if polling measurably strains |
| **Per-user preferences** | Meaningless while in-app is the only channel and the type set is curated and small (G10) | **Phase D**, with email |
| **Notification templates / clinic-authored content** | Speculative; no requested need | Not planned |
| **Digest/batching** | The curated 8-type allow-list is the volume control. Revisit with real data | Revisit after Phase C |

---

## 14. Implementation Phases

Each phase is independently verifiable and leaves `main` releasable — the project's established
sub-phase discipline (Phase 2.1–2.6, Phase 4 Steps 1–5).

### Phase A — In-App Foundation *(the bulk of the value; no new infrastructure)*
1. **Backend:** migration (stock table + 4 additive columns + indexes); custom `DatabaseChannel`;
   `NotificationService`; `RecipientResolver`; `NotificationRules` (the 8-type allow-list);
   `SendsNotifications` listener (synchronous); 8 Notification classes; `NotificationController`;
   5 routes; `NotificationPolicy::CATEGORY_SUBJECT_MAP`; Feature tests.
2. **Frontend data layer:** `services/notifications/`, `stores/notifications.ts`,
   `config/notificationTypes.ts`, types; store tests.
3. **Frontend UI:** `NotificationBell` / `NotificationCenter` / `NotificationItem`; replace the inert
   header popover; full `/notifications` page; i18n across all 3 locales; component tests.
4. **E2E + docs:** `notifications.spec.ts`; update `PROJECT_STATUS.md`, `CHANGELOG.md`, `TECH_DEBT.md`
   (close the "Header notifications is inert UI" item), `docs/decisions.md`, `docs/roadmap.md`.

**Verified before Phase B:** Backend + Frontend + E2E green in CI; zero regressions; i18n parity.

### Phase B — Queue & Scheduler Infrastructure *(closes G1 + G2 — a latent trap, not just an enabler)*
`queue` + `scheduler` containers in dev and prod compose; `SendsNotifications` → `ShouldQueue`;
`Prunable` activated; `routes/console.php` schedule definitions; failed-job handling; deployment docs.
**Independently valuable** — it fixes a real repo-wide hazard where any future `ShouldQueue` would
silently never run.

### Phase C — Scheduled & Administrative Notifications *(types 9–13; depends on B)*
Lab overdue, low-stock digest, unconfirmed appointments, repeated-login-failure and permission-change
admin alerts. New `inventory` and `security` categories with their authorization mapping.

### Phase D — Email Channel *(depends on B; shipped disabled)*
`users.locale` (G6); backend `lang/{en,ar,tr}` (G7); `notification_preferences`; Mailables + templates;
real mailer config; fails closed without a configured mailer. Settings UI for preferences.

### Deferred beyond this phase
Web Push → roadmap Phase 6. Patient-facing reminders → their own module.

**Recommendation: approve and implement Phase A + Phase B in this phase.** A delivers the complete
Notification Center the brief asks for; B closes a real latent infrastructure hazard and unblocks C/D.
C and D are then separately scoped phases.

---

## 15. Requirements Traceability

| Brief requirement | Where addressed |
|---|---|
| 1. Audit backend/frontend/DB/routes/policies/queues for reusable structure | §1 (all of it) |
| 2. Check `PROJECT_CONTEXT.md` / `PROJECT_STATUS.md` / module docs for architecture conflict | §1.5 |
| 3. Notification types actually needed by this system | §5 — derived from 24 real, existing events |
| 4. Extensible; In-app / Email / Push separated | §3.2, §4 |
| 5. Multi-tenant ready; no cross-tenant leak | §3.2, §8.4 |
| 6. Arabic / English / Turkish + correct RTL | §10.3, §11 |
| 7. Real Notification Center (see / read-unread / open resource / mark read / mark all / count) | §10.2 |
| 8. Review Laravel Notifications + Queues/Caching before choosing; use Laravel conventions | §3.1, §3.3, §9 |
| 9. Performance — no expensive queries from notification volume | §6.1 indexes, §5 curated allow-list, §7 separate count endpoint, §9 caching posture |
| 10. Clear permissions — only what the user is entitled to see | §8 (three layers) |

---

## 16. Decisions Requiring Approval

Nothing below is assumed decided. Each carries a recommendation and its reasoning.

| # | Decision | Recommendation | Why |
|---|---|---|---|
| **D1** | Laravel's stock `notifications` table **+ 4 additive columns** and a `DatabaseChannel` subclass, vs. pure-stock (everything in `data` JSON) vs. a fully bespoke model | **Additive columns** | Keeps every native affordance (`markAsRead`, `unreadNotifications`, `Prunable`) while making the security filter (§8.2) a real indexed `WHERE`. Follows Phase 4's own additive-columns-on-`audit_logs` precedent. A bespoke model is the "unnecessary abstraction" the brief warns against |
| **D2** | Partial index `WHERE read_at IS NULL` for the unread count | **Yes** | Postgres-native; the count is the hottest query in the module. Small, reversible |
| **D3** | Notify on `medical_history.allergy_added`? | **Defer to Phase C** | Genuinely valuable clinically, but recipient resolution ("dentists with this patient upcoming") is a non-trivial query that deserves its own design, not a rushed V1 rule |
| **D4** | Notify on `lab_case.quality_checked` ("ready to fit")? | **Include in Phase A** if you want it — it is a one-line rule addition | Arguably as useful as `lab_case.received`. Your call on clinical workflow; I have left it out of the V1 eight to keep the set minimal |
| **D5** | Dismiss/delete a notification, or read-only + auto-prune? | **Read-only + prune** | Fewer states, fewer endpoints, less UI. Add dismiss later if it is actually missed |
| **D6** | A permission catalog entry for notifications, or self-scoped like My Account? | **Self-scoped, no catalog entry** | Reading your own notifications is structurally self-scoped (§8.1); My Account has no key either. Flagged because it deviates from "every endpoint has a catalog entry" |
| **D7** | Poll interval for the unread count | **60s, visibility-gated** | Invisible to users, negligible load. Trivially tunable |
| **D8** | Phase scope for this cycle | **A + B together** | A alone leaves G1/G2 open and C/D blocked; B is small and closes a real latent hazard |

---

## 17. Approval

**Awaiting user approval. No implementation code, commit, or PR before it is given.**

Once approved, this document gains an *Approval & Decision Log* section recording the resolution of D1–D8,
following the convention of `docs/modules/phase4-permissions-audit-design.md` and its predecessors.
