# Changelog

All notable changes to DentalSuite are documented here. Format is chronological, grouped by module.

## Unreleased

_`main` is at `v1.0.0-appointments` (release tag not yet bumped). Every module on the original 17-module
roadmap is merged to `main` (Dental Chart 2026-07-22; Treatment Plans/Billing/Payments 2026-07-25 via PR #1,
plus a same-day concurrency fix via PR #2; Clinical Notes 2026-07-26 via PR #3; Inventory 2026-07-27 via
PR #4; Laboratory 2026-07-27 via PR #5; Imaging 2026-07-28 via PR #6; **Reports 2026-07-28 via PR #7**;
**Settings 2026-07-30 via PR #8**; AI Assistant 2026-07-31 via PR #9) but `main` is not yet re-tagged.
Clinical Notes, Inventory, Laboratory, Imaging, Reports, Settings, and AI Assistant each shipped their own
permanent E2E suite and are confirmed Production Ready.
Billing and Payments still lack a permanent E2E suite (Billing also lacks a backend
Feature-test suite and its final `modules/billing.md` doc) before either meets the same "Production Ready"
bar as the modules above; Treatment Plans gained a clinic-wide list (PR #12) but is in the same position —
see `docs/roadmap.md` and `TECH_DEBT.md` for current per-module status.

Post-roadmap: Frontend UX & Navigation Redesign Phase 1 (PR #10) and Premium Visual Redesign Steps 1-3
(PR #13) are merged; **Phase 1: Stabilization** of the follow-on 8-phase roadmap closed 2026-08-07 via
PR #15 (+ PR #14), then formally verified and closed out via PR #16 — see that entry below. **Milestone:
"Phase 1 — Foundation Complete" (2026-08-07)**, the baseline for all future development. **Phase 2: Patient
Profile Redesign** design approved 2026-08-07; **2.1 (Foundation)** merged via **PR #18**; **2.2 (Billing)**
merged via **PR #20**; **2.3 (Medical History)** merged via **PR #22** (2026-08-08); **2.4 (Laboratory)**
merged via **PR #24** (2026-08-08, `9de50fb`); **2.5 (Documents)** merged via **PR #27**
(2026-08-08, `98ae299`); **2.6a (Timeline foundation)** merged via **PR #31** (`cb7b52d`,
2026-08-09); **2.6b (Timeline UI)** merged via **PR #32** (`70b6ba6`), an E2E test fix that
post-merge CI's real E2E run caught merged via **PR #33** (`83c584b`) — this file's newest entry
below. **Phase 2 (Patient Profile Redesign) closed out via PR #34.** **Phase 3 (Dashboard 2.0)** — the
next phase in the 8-phase roadmap, absorbing the Premium Visual Redesign's own Dashboard-restyle
plan — merged via **PR #35** (`0f04b35`, 2026-08-09); post-merge CI on `main` (Backend/Frontend/E2E,
including the real `e2e/dashboard.spec.ts` run) fully green. **Milestone: "Phase 3 — Dashboard 2.0
Complete."** **Phase 4: Advanced Permissions & Audit** — design approved 2026-08-09 (fine-grained
permissions over the current 3 roles, a full audit-trail overhaul, simple immutability now/retention
deferred); **Steps 1-2 (Permissions Foundation + Policy refactor)**, **Step 3 (Audit Overhaul)**,
**Step 4 (Frontend)**, and **Step 5 (E2E + final docs)** all implemented 2026-08-09 on
`feature/phase4-permissions-foundation` — this file's newest entry below — pushed, **CI fully green
(Backend/Frontend/E2E, run `31328615397`)**, then a full final diff review against `main` (103 files, 14
points checked) found zero blockers; **merged via PR #37** (`0bdf3d8`, 2026-08-09); post-merge CI on `main`
(Backend/Frontend/E2E, including the real E2E run) fully green. **Milestone: "Phase 4 — Advanced
Permissions & Audit Complete."** See `docs/PROJECT_STATUS.md` for the living, continuously-updated status
book this file's own per-PR history now feeds into._

### Added — Phase 5: Notification System, Phases A + B (`feature/phase5-notifications`, 2026-08-11)

**Phase A — In-App Notification Center (backend + frontend)**

- New `notifications` table: Laravel's own stock schema (so `Notifiable`, `DatabaseNotification`,
  `markAsRead()`, `unreadNotifications` and `Prunable` all work natively) plus four additive columns —
  `category`, `subject_type`/`subject_id`, and a nullable `patient_id` — populated by a ~20-line subclass
  of Laravel's `DatabaseChannel` bound in `AppServiceProvider`. The same additive-columns-on-a-framework-
  table approach Phase 4 Step 3 used on `audit_logs`; not a bespoke model, and not a fork.
- **Zero new event dispatch call sites.** `SendsNotifications` is a *second* listener on the existing
  `PatientActivityOccurred` event, which Phase 2.6 already fires from 21 call sites across 9 services —
  so Appointments, Treatment Plans, Laboratory, Billing and Payments all gained notification coverage
  without a single existing service method being edited.
- `NotificationRules` is an explicit **allow-list**: 8 of the 24 live event types notify, the other 16 stay
  silent by design (`appointment.checked_in`/`.cancelled`/`.no_show`, `treatment_plan.accepted`/`.rejected`,
  `lab_case.received`, `payment.refunded`, `invoice.voided`). Per-type exclusion reasoning is recorded in
  `docs/modules/notifications-design.md` §5.1.
- Two universal rules enforced centrally in `NotificationService`, so no rule can forget either: the actor
  never receives a notification for their own action, and a notification is never created for a user who
  could not open its target.
- `RecipientResolver` is the **single multi-tenant seam** — every "who receives this" query lives in one
  class, so a future clinic scope is one `where()` there rather than an audit of every rule.
- Three authorization layers: structural ownership (every route resolves from
  `$request->user()->notifications()`, so another user's row 404s because it is never in scope — no
  permission catalog entry needed, matching My Account's precedent), a read-time category re-check on both
  the list *and* the count (so a notification stops being visible if its category permission is revoked
  after it was created), and the send-time check above.
- 5 new endpoints: `GET /notifications`, `GET /notifications/unread-count` (deliberately separate — it is
  polled, and must not deserialize a page of rows to render a badge), `POST /notifications/{id}/read`,
  `POST /notifications/read-all` (honours the active category filter). No delete endpoint by design.
- Frontend: `NotificationBell` (unread badge capped at `9+`, 60s poll gated on `document.visibilityState`,
  `Popover` on desktop / full-height `Drawer` under `md:`), one embeddable `NotificationCenter` shared by
  the popover, the drawer, and the new `/notifications` page, `NotificationItem`, a `notifications` Pinia
  store (optimistic mark-read with rollback, error-as-i18n-key), and `config/notificationTypes.ts` so no raw
  backend value reaches the UI. Replaces the inert bell + "No notifications yet" popover that
  `TECH_DEBT.md` had tracked since the layout work — no header redesign was needed, exactly as predicted.
- **Localization stores translation keys + raw params, never rendered text**, so switching language
  re-renders existing notifications correctly with no backfill. 32 new keys × 3 locales; parity re-verified
  programmatically at **1486/1486/1486**, zero drift.

**Phase B — Queue & Scheduler infrastructure**

- Added `queue` and `scheduler` containers to **both** `docker-compose.yml` and `docker-compose.prod.yml`.
  This closes a real, previously-untracked latent hazard: `QUEUE_CONNECTION=redis` had been configured since
  the project's first `.env`, but **no worker process existed anywhere**, so any `ShouldQueue` job would have
  been enqueued and silently never run. Verified by observing a real job go `RUNNING` → `DONE` in
  `dentalsuite_queue`, not merely by the container starting.
- `docker/php/entrypoint.sh` gained a `RUN_MIGRATIONS` guard so only the `app` container migrates, rather
  than three containers racing `migrate --force` at boot.
- `SendsNotifications` became `ShouldQueue` **only after** the worker was proven to consume jobs, with
  `$afterCommit = true` — load-bearing, because `InvoiceService::void()`, `PaymentService::refund()` and
  `LabCaseService::receive()` all fire the event from inside a `DB::transaction()`. It keeps its
  `try/catch`, trading automatic retries for a fail-open guarantee that holds under every queue driver
  including `sync`. `RecordsPatientActivity` stays synchronous.
- `Notification` became `MassPrunable` (read notifications older than 90 days; unread rows are never pruned
  however old), and `routes/console.php` gained its first-ever scheduled task.

**Verification**: Backend 1186/1186 tests green (41 new: 18 dispatch, 16 endpoint, 7 queue/scheduler), zero
regressions. Frontend 1003/1003 green (34 new). Pint clean, `vue-tsc`/ESLint/Prettier clean, E2E types clean.
Migration verified against real Postgres including the partial unread index. Full design, decision log, and
deferred scope: `docs/modules/notifications-design.md`.

**Deferred by explicit decision** (see that doc's §13 and `TECH_DEBT.md`): Email (Phase D), Web/PWA Push
(roadmap Phase 6), patient-facing SMS/WhatsApp reminders (their own future module), per-user preferences,
and the scheduled/administrative notification types (Phase C).

### Fixed — Phase 5 pre-PR review findings (`feature/phase5-notifications`, 2026-08-11)

Found in a pre-PR review of Phase A/B before either was ever pushed or opened as a PR — the two marked
SECURITY were treated as blockers on the PR itself, not deferred.

- **SECURITY — a signed-out session's notifications survived in memory for the next user on the same
  device.** `notifications.ts`'s `reset()` existed and was unit-tested in isolation, but nothing ever
  called it — `NotificationBell` lives in the always-mounted `DefaultLayout`, so its Pinia store outlived
  any one session, and a same-tab login as a different user rendered the previous user's notification
  rows until the next poll/fetch happened to overwrite them. `stores/auth.ts`'s `logout()` now calls
  `useNotificationsStore().reset()` directly. New regression test in `auth.test.ts`.
- **SECURITY — a dentist's password hash and a patient's PHI could sit in the Redis queue payload.**
  `SendsNotifications` is `ShouldQueue`, so every dispatch of `PatientActivityOccurred` serializes into a
  real `CallQueuedListener` job. The event's `subject`/`actor` were plain `readonly` `Model` properties
  with no serialization contract, so PHP's default object serialization walked their full `$attributes`
  — bcrypt hash, `remember_token`, and (whenever a relation happened to be preloaded) patient PHI included.
  Fixed with `Illuminate\Queue\SerializesModels` on the event class. `readonly` promoted properties are
  exactly why this needed verifying, not assuming: the trait's `__serialize()` only *reads* the live
  properties, and `__unserialize()` initializes them for the first time on a freshly-allocated,
  not-yet-constructed object — the one case PHP's readonly rules permit. Proven both ways: a new
  `NotificationEventSerializationTest` (serialize → assert no hash/PHI substring → unserialize → confirm
  the listener still resolves and notifies correctly) and a live run against the real
  `dentalsuite_queue`/Redis — payload inspected directly via `redis-cli` while the worker was paused,
  confirmed clean, then the worker resumed and observed carrying the job `RUNNING` → `DONE` with a real
  `notifications` row written.
- **`POST /notifications/{id}/read` 500'd on a malformed id instead of 404ing.** `markAsRead()`'s
  `findOrFail()` runs against the `notifications.id` `uuid` column with no format check of its own — on
  Postgres, a non-UUID string makes the driver throw `22P02: invalid input syntax for type uuid`, an
  uncaught `QueryException` that becomes a 500. SQLite (this suite's own test connection) stores the
  column as untyped text and never throws, which is exactly why the existing suite never caught it.
  Fixed with `Route::whereUuid('notification')` — Laravel's own route-constraint helper, not custom
  parsing — so a malformed id never reaches the controller; confirmed directly against real Postgres
  (`Router::getRoutes()->match()`: `NotFoundHttpException` before hitting any query). New
  `NotificationEndpointTest` cases for a malformed id and a missing id segment.
- **Notification Center never refreshed after the first time it was opened.** `Popover`/`Drawer` unmount
  and remount their content on every open/close (confirmed from PrimeVue's own render output), but the
  Pinia store's `items` survive across that remount — so `NotificationCenter`'s `onMounted` guard,
  `if (store.items.length === 0)`, skipped every fetch after the very first open, forever. Removed the
  guard; the panel now fetches fresh on every open. New `NotificationCenter.test.ts` case: mount → close
  (unmount) → a new notification arrives → reopen (remount) → it's there.
- **`docs/PROJECT_STATUS.md`/this file both said i18n parity was `1485/1485/1485`; the real, re-verified
  count is `1486/1486/1486`** (exact key-set parity, zero drift either direction — not just a count match).
  Corrected in both files rather than hand-edited to a new number without re-running the check.

Backend **1191/1191** green (5 new: 3 serialization, 2 malformed-id), zero regressions. Frontend
**1005/1005** green (2 new: logout-reset, reopen-refetch), zero regressions. Pint clean, PHPStan clean on
every touched/new file (only the pre-existing local-only `casts()` false-positive pattern
`TECH_DEBT.md`/`docs/PROJECT_STATUS.md` already document elsewhere), `vue-tsc`/ESLint clean. Not opened as
a PR until this pass; still not merged.

### Added — Phase 4 Step 5: E2E + final docs closure (`feature/phase4-permissions-foundation`, merged 2026-08-09 via PR #37)
- New `role-permissions.spec.ts`: the Admin/`users.manage` self-lockout cell stays disabled+checked
  in a real browser; an admin toggling a permission off persists across reload, takes effect for a
  live receptionist session (the New Patient button disappears), and is audited as
  `role_permissions_updated` with a real before/after diff (verified by filtering the Audit Log and
  expanding the row) — restores the original global matrix via a direct API call afterward, since
  `role_permissions` is shared by every other E2E spec; a non-admin gets 403 on `GET /permissions`,
  `GET`/`PUT /role-permissions`.
- New `audit-log.spec.ts`: filtering by action narrows results and a row expands to show it has no
  field diff; a failed login (made from a genuinely unauthenticated session — an earlier draft of
  this test fired it from an already-logged-in admin session and wrongly recorded the admin as the
  actor instead of `null`, since `Auth::id()` reflects the current session, not what a login attempt
  claims) is audited with the attempted email as both its Actor and in its Context; a non-admin gets
  403 on `GET /audit-logs`.
- `permissions.spec.ts`: added admin-can-reach-both-screens and
  non-admin-has-no-sidebar-entry-and-is-blocked-on-direct-URL cases, alongside the existing
  pre-Phase-4 permission-boundary tests (unmodified and still passing — the design doc's own
  required "receptionist retains today's effective permissions after the Policy refactor" proof).
- Iterating locally against the dev stack surfaced and fixed 4 real bugs in the tests themselves (a
  PUT missing its XSRF header returning 419 instead of 403; a CSS-transition click flaking under
  this environment's per-request latency; a locale-forcing gap showing Arabic text to an
  English-text assertion; a wrong expected error string) plus a real fetch-ordering race between a
  page's initial unfiltered load and an immediately-applied filter. Every test passed cleanly at
  least once locally; remaining local flakiness matches this environment's already-documented
  per-request latency (`docs/PROJECT_STATUS.md`), not application behavior.
- Final 3-locale i18n parity re-verified after Step 5: 1453/1453/1453 keys, zero drift either way.
- `docs/decisions.md`'s Phase 4 entry and `TECH_DEBT.md`'s Appointment-audit-log item both updated;
  `docs/modules/roles-permissions.md` formally marked superseded by the Phase 4 design doc.
- Pushed to `origin/feature/phase4-permissions-foundation`; first CI run (`31328029404`) caught one
  real ordering bug local runs never surfaced — a `page.waitForResponse()` registered *after* the
  `page.goto()` that triggers the response, a race a fast environment (CI) loses and this local
  Docker setup's own latency happens to mask — fixed and **re-run (`31328615397`) fully green:
  Backend, Frontend, and E2E (53/53) all `success`.**

Design doc: `docs/modules/phase4-permissions-audit-design.md` §9/§14. No backend behavior changed.

### Added — Phase 4 Step 4: Frontend (`feature/phase4-permissions-foundation`, merged 2026-08-09 via PR #37)
- New admin-only **Permissions** screen (`PermissionsView.vue`): a role×permission matrix (68
  catalog entries grouped by module, one toggle switch per role) editable as a single draft with
  explicit Save/Discard — no per-toggle autosave. The `users.manage` cell on the Admin role renders
  disabled/checked, matching `UpdateRolePermissionsRequest`'s server-side self-lockout rule
  (§1.4) exactly, so the UI explains the constraint instead of letting an admin uncheck it and
  learn only from a rejected save. A 422 from the save endpoint is shown as that specific
  self-lockout message, not a generic error. Responsive per the design doc's §10: the matrix table
  (desktop) is replaced by a stacked Accordion — one panel per role — on narrow viewports, since a
  grid of toggles doesn't fit a phone width.
- New admin-only **Audit Log** screen (`AuditLogsView.vue`): the general (non-patient-scoped)
  viewer over `GET /audit-logs`, with User/Resource type/Action/date-range filters, server-side
  pagination, and a per-row expansion showing the old/new field diff (or the login/matrix-update
  `context`, e.g. the attempted email on a `login_failed` row — shown as that row's "Actor" too,
  since there's no resolved `user`). Raw values (PHP FQCNs, permission-key arrays) are never shown
  to the user — a `config/auditableTypes.ts` map and i18n action/resource-type labels translate
  every raw backend value first.
- New `permissions.ts`/`auditLogs.ts` Pinia stores and `services/permissions`,
  `services/auditLogs` API layers, following this app's established store-owns-error-state pattern.
- New Sidebar entries (admin-only, `ADMINISTRATION` section) and routes (`/permissions`,
  `/audit-logs`), gated the same way `Users`/`Settings` already are.
- i18n: ~300 new keys across all 3 locales (`permissions.*` — 28 group labels + 68 catalog labels,
  `auditLog.*` — 7 action labels + 22 resource-type labels + filter/table copy) — 3-locale key
  parity verified programmatically (1453 keys in each of `en`/`ar`/`tr`, zero missing either way).
- Frontend: 20 new tests (2 new stores, `PermissionsView`/`AuditLogsView` component tests covering
  matrix toggle/locked-cell rendering/save, filter controls, row expansion, and the
  no-resolved-user actor case) — **969/969 frontend tests green**, `vue-tsc`/ESLint/Prettier clean.
  Manually verified in a real browser against real seeded audit-trail data: the full matrix and a
  live Audit Log both render correctly in English, Arabic (RTL), and at a mobile viewport; a
  non-admin (dentist) session has no `Permissions`/`Audit Log` sidebar entry and gets the app's
  standard 403 page on direct navigation to either route.

Design doc: `docs/modules/phase4-permissions-audit-design.md` §1.6/§2.6/§8/§10. No backend behavior
changed — Step 4 is frontend-only, consuming the Step 1-3 API surface as-is.

### Added — Phase 4 Step 3: Audit Overhaul (`feature/phase4-permissions-foundation`, merged 2026-08-09 via PR #37)
- Closes the two critical gaps the design-phase audit found: `User` was not audited, and no
  authentication event was logged anywhere.
- Additive `audit_logs` migration: `old_values`, `ip_address`, `user_agent`, `context` columns;
  `auditable_id` relaxed to nullable for targetless events (e.g. a failed login against an
  unknown email).
- `AuditLogService` rewritten: `record()` (model-observer path) and `recordEvent()` (auth events,
  role_permissions matrix changes) both capture real before/after diffs and IP/User-Agent; a new
  `search()` backs the general viewer. Writes fail open (a broken audit write never breaks the
  underlying login/save/permission-update) but fail closed on sensitive data (the failure log
  itself never carries the payload).
- `AuditObserver::updated()` now captures `old_values` for changed fields only (previously new
  values only); `deleted()` captures the full pre-deletion state (previously an empty array).
- `User` model is now `Auditable`.
- `AuthService` logs `login_succeeded`/`login_failed`/`logged_out` — a failed login records the
  attempted email in `context`, never the password; rate-limited attempts aren't logged as a
  distinct action (the `login_failed` events that caused the throttle were already each logged);
  logout is audited *before* the guard actually logs out, so the actor field isn't lost.
- `RolePermissionService::updateMatrix()` now audits its own before/after matrix as
  `role_permissions_updated` — closes a gap Step 1 itself had left open.
- `AuditLog` model: immutability guard (throws on any update/delete attempt).
- New `GET /audit-logs` — the first general (non-patient-scoped) Audit Log viewer, with
  user_id/auditable_type/action/date-range filters and pagination, gated by a hardcoded
  `view-audit-logs` Gate (`isAdmin()`, same self-lockout-proof pattern as `manage-permissions`).
- `bootstrap/app.php` gained `trustProxies(at: '*')` — a real gap closed, not just documented:
  `docs/deployment.md`'s host-level reverse proxy already sets `X-Forwarded-For` correctly, but
  Laravel had zero trusted-proxy config, so captured IPs would have silently been the proxy's own
  loopback address in production.
- 24 new tests (old/new diffs, delete snapshots, redaction across `changes`/`old_values`/`context`,
  IP/UA capture, fail-open write behavior, login success/failure/logout including the
  unknown-email case, matrix-update auditing, immutability, endpoint authorization/filters/
  pagination). Backend: **1145/1145 tests green** (1121 + 24 new), zero regressions.

Design doc: `docs/modules/phase4-permissions-audit-design.md` §2. See `docs/decisions.md`'s
2026-08-09 entries for the audit-write fail-open/fail-closed policy and the trusted-proxy fix.

### Added — Phase 4 Steps 1-2: Permissions Foundation + Policy refactor (`feature/phase4-permissions-foundation`, merged 2026-08-09 via PR #37)
- New `permissions` catalog (68 entries) and `role_permissions` matrix, derived 1:1 from a full
  line-by-line read of all 27 Policy classes' actual pre-Phase-4 behavior — day 1, zero effective
  permission change; verified independently (seeded per-role grant counts — admin=68, dentist=36,
  receptionist=37 — matched a manual derivation exactly).
- `User::hasPermission()` with a per-role cache (`RolePermission::permissionKeysForRole()`,
  invalidated via `flushCache()`).
- New admin-only endpoints: `GET /permissions`, `GET /role-permissions`, `PUT /role-permissions` —
  gated by a hardcoded `manage-permissions` Gate (`isAdmin()`), never routed through the matrix
  itself, so self-lockout is structurally impossible, not just validated-against. `users.manage` is
  additionally protected server-side from ever being revoked from Admin.
- All 27 Policy classes converted from raw `UserRole` comparisons to `hasPermission()` calls; every
  identity/ownership/target-role check (e.g. `Appointment::start()`'s
  `$actor->is($appointment->dentist)`, `DentistTimeOff`'s target-role validation, `User::delete()`'s
  self-delete block) preserved verbatim, unchanged.
- `Tests\TestCase` now seeds the permission catalog automatically for every `RefreshDatabase` test
  class; 15 new baseline Policy tests added for the previously-untested policies (35 new tests).
- Backend: **1121/1121 tests green** (1086 + 35 new), zero regressions across every pre-existing
  Feature/Policy test. Pint clean; targeted PHPStan clean on all 27 Policies + 15 new test files.

Design doc: `docs/modules/phase4-permissions-audit-design.md`. See `docs/decisions.md`'s 2026-08-09
entry for the fine-grained-permissions-over-hierarchy decision.

### Added — Dashboard 2.0 (`feature/dashboard-2-0`, merged 2026-08-09 via PR #35)
- New admin-only `GET /dashboard/financial-summary` endpoint: production/collections
  period-over-period trend (this month vs. last), and an A/R aging snapshot — each a thin wrapper
  around an existing `ReportService` method, no new report logic. Gated by the same
  `view-financial-reports` Gate `reports/production`/`reports/collections`/`reports/ar-aging`
  already use.
- New `unscheduled_accepted_treatment` field on `GET /dashboard/summary` (open to every role):
  accepted treatment-plan items that are neither scheduled nor completed, capped at 5 for a widget,
  not a full report.
- Frontend: `FinancialSnapshotWidget.vue` (admin-only) and `UnscheduledTreatmentWidget.vue` (all
  roles) on the Dashboard; restyled stat cards (bigger icon badges, distinct tint per card, trend
  badge), a gradient-styled `AiQuestionBox.vue`, and `gap-6` grid spacing throughout — absorbs
  `docs/modules/frontend-visual-redesign-design.md` §6 in full, which is now superseded by
  `docs/modules/dashboard-2.0-design.md`.
- `PatientDetailView.vue` now accepts an optional `?tab=` query param on first load (validated
  against the same tab-key list every other tab-visibility check uses) so the new Unscheduled
  Treatment widget can deep-link a row straight to that patient's Treatment Plans tab.

### Fixed — a real, pre-existing security gap in `/dashboard/summary`
- `monthly_revenue` was returned to **every authenticated role** with zero authorization — the
  identical figure `reports/collections` already restricts to admins. Found during Dashboard 2.0's
  design-phase audit, not introduced by it. Fixed by removing `monthly_revenue` from the open
  endpoint entirely and moving it to the new admin-gated `/dashboard/financial-summary` — see
  `docs/decisions.md`'s 2026-08-09 entry for the full write-up.

### Fixed — `e2e/timeline.spec.ts` (`fix/timeline-e2e-multi-instance-scoping`, merged 2026-08-09 via PR #33)
- Post-merge CI on `main` runs the E2E job for real (PR-triggered runs skip it by design), and
  caught 2 real bugs the PR #32 merge introduced, both in the test spec itself, not application
  code: a lab case's `case_number` was read from `<h1>` before its async fetch replaced a fallback
  string ("Lab Cases"), and an unscoped `getByText('Clinical note signed')` hit a strict-mode
  violation because the Overview preview's and the Timeline tab's `ActivityTimeline` instances are
  both mounted simultaneously (the exact scenario `patientActivities.ts`'s composite cache key
  exists to handle). Fixed by waiting for the lab case's status tag before reading `case_number`,
  and scoping every assertion to its own `tabpanel` via `getByRole('tabpanel', { name: ... })`.

### Added — Phase 2.6b: Timeline UI (`feature/patient-profile-phase2-6b-timeline-ui`, merged 2026-08-09 via PR #32)
- **Context**: second and final PR of Phase 2.6 (Timeline), closing out Phase 2 (Patient Profile
  Redesign) in full — see `docs/modules/patient-timeline-redesign-design.md` §13/§16 decision 7.
- **`ActivityTimeline.vue`**: one embeddable component (`patientId`/`category`/`pageSize`/`compact`
  props) backing all three call sites the design doc requires as a single component — the new
  `timeline` tab (unfiltered, category chips), Billing's Payment History sub-section
  (`category="billing"`, replacing its `FutureFeaturePlaceholder`), and an Overview recent-activity
  preview card (`compact`, with a "View Timeline" button that switches the active tab).
- **`patientActivities` Pinia store**, keyed by `patientId::category::perPage` rather than patient
  id alone (every sibling store's convention) — necessary because `PatientDetailView.vue`'s `Tabs`
  isn't `lazy`, so up to three `ActivityTimeline` instances are mounted simultaneously on one
  patient page, each wanting a different slice; a patient-id-only cache would let one instance's
  fetch overwrite another's. "Load more" pagination (appends pages, never replaces).
- **Category-filter chip row**, built from scratch: the design doc cited an existing mobile
  chip-row pattern (Laboratory's status filter) to match, which turned out to be a plain dropdown
  with no chip-row component anywhere in the codebase — flagged and confirmed with the user before
  implementation, who chose to build the chip row as originally specified.
- **i18n** added for all 3 locales (`patients.tabs.timeline`, `patients.timelinePanel.*`); the
  now-unused `patients.billingPanel.paymentHistoryTitle` key was removed.
- **Tests**: 27 new (`patientActivities.test.ts`'s query-isolation logic, `ActivityTimeline.test.ts`'s
  filter/pagination/compact-mode behavior) plus updated assertions in
  `PatientBillingPanel.test.ts`/`PatientDetailView.test.ts` for the real wiring. Frontend
  933/933 green, `vue-tsc`/ESLint/Prettier clean. Playwright E2E written
  (`e2e/timeline.spec.ts`) covering cross-module aggregation, category filtering, and the
  security-critical case design doc §18 mandates — a receptionist session never sees
  `clinical_notes`-category rows even when explicitly filtering for them.

### Added — Phase 2.6a: Timeline foundation (`feature/patient-profile-phase2-6a-timeline-foundation`, merged 2026-08-09 via PR #31)
- **Context**: first of two PRs for the sixth and final implementation sub-phase of Phase 2
  (Patient Profile Redesign) — see `docs/modules/patient-timeline-redesign-design.md`. Unlike
  every prior sub-phase, this one is split into 2.6a (this PR — events, listener, security,
  no UI) and 2.6b (Timeline UI, not yet started), per the design doc's own §16 decision 7: the
  umbrella doc's only phase rated "Highest risk," since it touches every module's service.
- **`PatientActivity`** (new model: UUID PK, append-only — no `updated_at`, no soft deletes) +
  **`Patient::activities()`** + `patient_activities` migration (`event_type`, `category`,
  polymorphic `subject`, `actor_id`, precomputed `summary`, `metadata`, `occurred_at`).
- **One generic `PatientActivityOccurred` event** + **one synchronous `RecordsPatientActivity`
  listener**, relying on Laravel's default auto-discovery (introducing this codebase's event
  system for the first time — confirmed zero `app/Events`/`app/Listeners` existed before this
  PR). No queue — confirmed no queue worker actually runs in this project despite
  `QUEUE_CONNECTION=redis` being configured. Verified genuinely wired end-to-end (not faked) by a
  dedicated integration test.
- **24 event dispatch call sites** across all 9 candidate services (`AppointmentService`,
  `TreatmentPlanService`, `ClinicalNoteService`, `InvoiceService`, `PaymentService`,
  `MedicalHistoryService`, `LabCaseService`, `PatientImageService`, `PatientDocumentService`).
- **`PatientActivityPolicy`**: a static `CATEGORY_SUBJECT_MAP` (one category per real owning
  policy class — `appointments`, `treatment_plans`, `clinical_notes`, `billing`,
  `medical_history`, `laboratory`, `imaging`, `documents`), checked once per category per
  request via `$actor->can('viewAny', ...)`, never per row. A real category-taxonomy conflict
  (originally one `clinical` category spanning `TreatmentPlan`/`ClinicalNote`/Medical History
  despite their different `viewAny()` rules) was found and fixed before implementation started —
  see the design doc's §5 correction note.
- **`GET /patients/{patient}/activities`** — patient-scoped only, paginated 15/page,
  `?category=`/`?from=`/`?to=` filters, most-recent-first.
- **Tests**: 25 dispatch tests (one per event, plus one confirming `ClinicalNoteService::sign()`'s
  idempotent already-signed path never double-fires) + 9 controller/pagination/filter tests,
  including **the security-critical test §9A itself mandates** — a receptionist's request to
  `/activities` never returns `clinical_notes`-category rows, asserted directly against the
  response body. Full suite 1054/1054 green (1020 + 34 new), Pint clean, PHPStan clean on every
  new/touched file (one real finding fixed: a generic-`Model`-typed magic-property access,
  switched to `getAttribute()`).

### Added — Phase 2.5: Documents (`feature/patient-profile-phase2-5-documents`, merged 2026-08-08 via PR #27)
- **Context**: fifth implementation sub-phase of Phase 2 (Patient Profile Redesign) — see
  `docs/modules/patient-documents-redesign-design.md` (this sub-phase's own drill-down design doc,
  produced by auditing the actual current codebase rather than assuming the umbrella doc's §5.2/§6/§7/
  §8/§10/§12/§17 outline needed no verification). Closes the gap the umbrella doc named: a generic
  patient-documents foundation (model, upload/download, metadata, categories) — versioning, sharing,
  advanced per-document permissions, and OCR remain explicitly out of scope.
- **`PatientDocument`** (new model: UUID PK, `SoftDeletes`, `Auditable`, `belongsTo(Patient)`,
  `belongsTo(User, 'uploaded_by')`) + **`Patient::documents()`** (new `hasMany` relation) + a new
  `patient_documents` migration (`category`, `title`, `original_filename`, `disk`/`path`/`mime_type`/
  `file_size`, `notes` — no `thumbnail_path`/`width`/`height`, those are Imaging-specific).
- **`DocumentCategory`** enum: `consent_form | insurance | referral | clinical_summary |
  correspondence | other` — drops the originally-proposed `lab_report` in favor of `clinical_summary`
  (design doc §7/§16 decision 1) to avoid reading as "where lab results go" now that Laboratory
  (Phase 2.4) already owns that workflow via `LabCase`.
- **`GET/POST patients/{patient}/documents`, `GET documents/{id}/file` (named
  `patient-documents.file`), `PUT/DELETE documents/{id}`** — patient-scoped only (design doc §16
  decision 4): unlike Laboratory, there was no pre-existing flat `GET /documents` endpoint to
  reconcile with, so this never adds one.
- **`PatientDocumentService`** clones `PatientImageService`'s Storage-disk convention exactly
  (resolve `config('filesystems.default')` once per call, store it explicitly per row, soft-delete
  only — the underlying file is never removed from storage on delete) minus thumbnail generation
  (generic documents don't need it). **One file per upload, not a batch** — a small, deliberate
  deviation from `PatientImageService`'s shared-metadata batch convention: a document's title is
  naturally per-file, so batching would force an awkward shared title across unrelated files.
- **`PatientDocumentPolicy`** clones `PatientImagePolicy` exactly (design doc §9/§16 decision 2):
  `viewAny`/`view` all staff, `create`/`update` Admin+Dentist+Receptionist, `delete` Admin-only.
  Auto-discovered via Laravel's naming convention — no `Gate::policy()` registration needed.
- **`patientDocuments.ts`** (Pinia store) — same id-keyed cache + per-patient page-tracking shape as
  `patientLabCases.ts`, the standard convention every patient-scoped tab uses.
- **`PatientDocumentsPanel.vue`** (new Documents tab, appended last after `billing` — design doc §8.1/
  §16 decision 3, matching the umbrella doc's administrative-tabs IA grouping) — read/write: upload
  via `AttachmentUpload.vue` (generalized from `UploadImagesDialog.vue`'s dropzone, single-file),
  edit metadata via `EditDocumentDialog.vue`, list via `AttachmentList.vue` — a row list (mirroring
  `PatientLabCasesPanel.vue`'s card-row convention) rather than Imaging's photo grid, since a
  document's title/category/filename metadata is the primary identifying information.
- **Permissions**: no new roles — `PatientDocumentPolicy` is the only new authorization surface,
  fully specced in the design doc's §9 (approved as recommended, no deviations).
- **i18n**: `patients.tabs.documents`, `patients.documentsPanel.*`, and a top-level `documents.*`
  namespace added in `ar`/`en`/`tr`; 31/31 keys verified programmatically across all three locales.
- **Tests**: backend +13 (upload/permissions/index-filter/update/delete/streaming) — full suite
  1020/1020 green, Pint clean. Frontend +21 new tests (10 `patientDocuments.ts` store tests, 10
  `PatientDocumentsPanel.vue` tests, +1 `PatientDetailView.test.ts` tab-rendering assertion) — full
  suite 915/915 green, type-check/ESLint/Prettier clean.

### Added — Phase 2.4: Laboratory (`feature/patient-profile-phase2-4-laboratory`, merged 2026-08-08 via PR #24)
- **Context**: fourth implementation sub-phase of Phase 2 (Patient Profile Redesign) — see
  `docs/modules/patient-laboratory-redesign-design.md` (this sub-phase's own drill-down design doc,
  produced by auditing the actual current Laboratory code on `main` rather than assuming the umbrella
  doc's earlier §17 outline still matched it). Closes the gap the umbrella doc's own §2 named: "Laboratory
  — Not visible from a patient's record at all." The existing standalone Laboratory module (`Lab`/`LabCase`,
  PR #5, 2026-07-27) is unchanged and untouched — this phase is a patient-scoped integration layered on
  top of it, not a rebuild.
- **`Patient::labCases()`** (new `hasMany` relation) and **`LabCase::scopeForPatient()`** (mirrors
  `TreatmentPlan::scopeForPatient()` exactly) — the two missing pieces the umbrella doc's §6.2/§6.3
  identified.
- **`GET /patients/{patient}/lab-cases`** (new, paginated at 15/page via `LabCaseController::forPatient()`)
  — the Route→Scope→Store→Panel pattern applied identically to how Treatment Plans/Clinical Notes/Imaging
  already work. The flat `GET /lab-cases` endpoint's `?patient_id=` query filter is removed — confirmed
  unused by any frontend caller and superseded by this real patient-scoped route (design doc §4.1).
- **Required fix, not deferred**: `App\Rules\BelongsToPatient`'s real SQL error against `TreatmentPlanItem`
  (open since PR #6/2026-07-28, see `TECH_DEBT.md`'s now-resolved entry) is fixed in
  `StoreLabCaseRequest`/`UpdateLabCaseRequest`, using the same `whereHas('treatmentPlan', ...)` pattern
  already proven correct in Imaging's own Form Requests. 4 new regression tests (`LabCaseTest.php`) cover
  same-patient acceptance and cross-patient rejection on both create and update.
- **`patientLabCases.ts`** (Pinia store) — same id-keyed cache + per-patient page-tracking shape as
  `stores/treatmentPlans.ts`; `create`/`send`/`receive`/`qualityCheck`/`cancel` wrap the existing,
  unchanged Laboratory endpoints and upsert their responses into the patient-scoped cache.
- **`PatientLabCasesPanel.vue`** (new Laboratory tab, inserted immediately after Imaging in
  `PatientDetailView.vue`'s tab order — design doc §8.1) — read/write, per the approved design decision:
  create a case (pre-filled patient, no search step) and take status-transition actions inline, reusing
  `LabCaseStatusChip.vue`/`LabCaseActionsBar.vue` as-is. Structured as a card-row list rather than a
  `DataTable`, deliberately avoiding the row-click/inline-button event-bubbling conflict a `DataTable`
  would introduce (design doc §4.5).
- **`CreateLabCaseDialog.vue`** gains an optional `patientId` prop — when set (the new Patient Profile
  tab), `PatientSearchSelect` is skipped and the create routes through `patientLabCases.ts`'s `create()`
  instead of the raw endpoint (same precedent `CreateTreatmentPlanDialog.vue` already set); when unset
  (the standalone Lab Cases page, unchanged), behavior is identical to before.
- **Permissions**: no new policy, no new roles — `LabCasePolicy`/`LabPolicy` reused entirely unchanged
  (design doc §9); the new tab is visible to all staff (matches `LabCasePolicy::viewAny`), not gated like
  Clinical Notes.
- **i18n**: `patients.tabs.laboratory`, `patients.laboratoryPanel.*`, and `laboratory.labCases.loadError`
  added in `ar`/`en`/`tr`; all other panel strings reuse the existing `laboratory.labCases.*` namespace.
- **Tests**: backend +13 (9 patient-scoped-index/pagination/status-filter/response-shape tests, 4
  `treatment_plan_item_id` regression tests) — full suite 1007/1007 green, Pint clean. Frontend +27 new
  tests (`patientLabCases.ts` store, `PatientLabCasesPanel.vue`, `CreateLabCaseDialog.vue`'s patientId
  behavior, +1 `PatientDetailView.test.ts` tab-rendering assertion) — full suite 894/894 green,
  type-check/ESLint/Prettier clean.

### Added — Phase 2.3: Medical History (`feature/patient-profile-phase2-3-medical-history`, merged via PR #22, 2026-08-08)
- **Context**: third implementation sub-phase of Phase 2 (Patient Profile Redesign) — see
  `docs/modules/patient-profile-redesign-design.md` §6/§7/§10. Replaces the free-text `patients.allergies`/
  `patients.medical_history` fields with a structured Medical History tab (Allergies / Medical Conditions /
  Current Medications).
- **New tables**: `patient_allergies` (allergen, severity, reaction, notes), `patient_medical_conditions`
  (condition_name, status, diagnosed_date, notes), `patient_medications` (medication_name, dosage, frequency,
  is_current, start/end date, notes) — UUID PK, `SoftDeletes`, `Auditable`, `created_by_id`/`updated_by_id`,
  matching every other clinical-adjacent table's shape.
- **`MedicalHistoryService`**: one service for all three entities (design doc §6.3 — avoids three
  near-identical services for what is one logical feature).
- **`MedicalHistoryPolicy`**: one policy for all three entities — `view`/`viewAny` open to all staff
  (allergies are front-desk safety-relevant, same reasoning as `DentalChartEntryPolicy`), `create`/`update`/
  `delete` restricted to Admin + Dentist. Registered against all three models via `Gate::policy()` in
  `AppServiceProvider`, since Laravel's naming-convention auto-discovery only maps one policy per model —
  the closest fit to the design doc's explicit "one policy" requirement without inventing a new permission
  abstraction.
- **`MedicalHistoryController`**: one controller, 12 endpoints (`GET`/`POST` per section under
  `patients/{patient}/...`, `PUT`/`DELETE` per record at the top level), paginated at 15/page matching every
  other new list endpoint this phase (design doc §11.2).
- **Backfill**: `2026_08_08_000004_backfill_patient_allergies_from_legacy_column` migrates any non-empty
  legacy `patients.allergies` text into one best-effort `patient_allergies` row per patient. `patients.allergies`
  itself is kept, deprecated, not dropped this phase (design doc §7); `patients.medical_history` is
  unaffected — repurposed as the Notes field, no migration needed.
- **`medicalHistory.ts` Pinia store + `MedicalHistoryPanel.vue`**: one store for all three sections (mirrors
  the backend's shape), `AllergyList`/`MedicalConditionList`/`MedicationList.vue` presentational list
  components (`InvoiceListTable.vue` convention) plus one create/edit dialog per entity. New `medicalHistory`
  tab added to `PatientDetailView.vue` right after Overview, per the design doc §4 tab order — all staff can
  read, Admin/Dentist can write.
- **i18n**: full `ar`/`en`/`tr` parity for the new `medicalHistory` namespace (no automated check exists in
  this repo — verified manually per `docs/PROJECT_STATUS.md` §12/§5).
- **Tests**: backend +46 Feature/Unit tests (25 CRUD/permission Feature tests, 9 Service unit tests, 9
  Policy unit tests — including explicit per-model `Gate::policy()` registration checks, since this is the
  first policy in the codebase registered against more than one model — 3 backfill migration `up()`/`down()`
  tests) — full suite 998/998 green, Pint clean. Frontend +35 new tests (store, API layer, panel, one added
  to `PatientDetailView.test.ts`) — full suite 867/867 green, type-check and lint clean.

### Added — Phase 2.2: Billing (`feature/patient-profile-phase2-2-billing`, merged via PR #20, 2026-08-08)
- **Context**: second implementation sub-phase of Phase 2 (Patient Profile Redesign) — see
  `docs/modules/patient-profile-redesign-design.md` §4/§5.2/§17. Merges the separate Invoices and
  Payments patient-detail tabs into one **Billing** tab; resolves the Invoices/Payments pagination debt
  Phase 2.1 deliberately deferred (`TECH_DEBT.md`).
- **`PatientBillingPanel.vue`**: new Billing tab shell — hosts `BillingSummaryCard.vue` (Outstanding
  Balance hero + Total Invoiced/Total Paid/Invoice Count/Last Payment Date summary row) and a
  `SelectButton` switching between Invoices, Payments (both reused as-is from Phase 2.1/earlier — zero
  edits), and a Payment History placeholder (`FutureFeaturePlaceholder.vue` — the real
  `ActivityTimeline.vue`-backed feature lands in Phase 2.6).
- **`GET /patients/{patient}/billing-summary`**: new aggregate endpoint (`BillingSummaryService`),
  computed entirely via SQL `SUM`/`COUNT`/`MAX`/`EXISTS` (design doc §11.4 — no Invoice/Payment rows
  hydrated). A standalone service, not added to `InvoiceService`/`PaymentService`, since the aggregate
  spans both domains equally.
- **Invoices/Payments pagination resolved**: `InvoiceController::index()`/`PaymentController::index()`
  now `->paginate(15)`, mirroring Treatment Plans/Clinical Notes. New `GET /invoices/{invoice}/payments`
  endpoint (`PaymentController::forInvoice()`, via the existing `Invoice::payments()` relation) replaces
  `InvoicePaymentsPanel.vue`'s former client-side filter of the full patient payment list.
  `ApplyPaymentDialog.vue`'s invoice picker now calls a new `invoicesApi.listIssued()` (`?status=issued`
  filter on the same endpoint) instead of the now-paginated store getter, so it still sees every issued
  invoice regardless of page. `invoices.ts`/`payments.ts` stores reworked to the same
  `patientPageIds`/`patientPageMeta`/`loadedPatientPage` pagination shape `treatmentPlans.ts` already
  established in Phase 2.1; `payments.ts` gained a second, independent page-tracking slice for
  invoice-scoped payments (a patient's payments and one invoice's payments are different pagination
  axes).
- **Mobile dropdown tab switcher**: `PatientDetailView.vue` previously had no `<768px` fallback for its
  `TabList` — added now since Billing's tab-count change (two tabs collapsing into one) was the trigger
  named in the design doc's §17 2.2 scope.
- **One new deliberate trade-off, logged in `TECH_DEBT.md`**: `PatientInvoicesPanel.vue`/
  `PatientPaymentsPanel.vue` are reused unchanged inside the Billing tab, so they show only page 1 (no
  `Paginator`) there — a patient with more than 15 invoices/payments can't see older ones from the
  Billing tab in this sub-phase.
- **Tests**: backend 952 (Pint clean); frontend 832, type-check/lint clean. i18n: `ar`/`en`/`tr` key
  parity confirmed (no automated check exists in this repo — verified manually per `docs/PROJECT_STATUS.md`
  §12/§5).

### Added — Phase 2.1: Patient Profile Foundation (`feature/patient-profile-phase2-1-foundation`, merged via PR #18, 2026-08-07)
- **Context**: first implementation sub-phase of Phase 2 (Patient Profile Redesign) — see
  `docs/modules/patient-profile-redesign-design.md` for the full design, approved 2026-08-07. Explicitly
  scoped to architecture/consistency groundwork only: no Billing, Medical History, Laboratory, Documents, or
  Timeline in this PR — those land in Phase 2.2 onward.
- **`patients.ts` Pinia store**: closes the one architectural gap Patients had versus every sibling module —
  `PatientsView.vue`/`PatientDetailView.vue`/`PatientFormDialog.vue` called `@/lib/api` inline before this;
  now route through a store (list/fetchOne/create/update/remove/audit-logs), patient-level state only, not a
  replacement for the domain stores (`treatmentPlans.ts`, `invoices.ts`, etc.).
- **`patientImages.ts` Pinia store**: wraps the existing `services/imaging` functions, giving Imaging the
  same store-backed reactivity as every other tab. Backend untouched — pure frontend refactor.
- **Pagination**: `GET /patients/{patient}/treatment-plans` and `.../clinical-notes` are now paginated
  (15/page) — both were previously unbounded `->get()` calls, a real if previously-undocumented scalability
  risk for a long-tenured patient. `PatientTreatmentPlansPanel.vue`/`PatientClinicalNotesPanel.vue` gained a
  real `Paginator`. Invoices/Payments' identical endpoints are deliberately **not** paginated in this PR —
  `ApplyPaymentDialog.vue`'s invoice picker and `InvoicePaymentsPanel.vue` both assume the full unpaginated
  set today; Phase 2.2 (Billing) adds the missing invoice-scoped payments endpoint and fixes both together
  rather than paginating first and breaking them in between. New `TECH_DEBT.md` entry logs the Invoices/
  Payments half of this as open.
- **`EmptyState.vue`**: first component in a new `components/common/` folder — no such shared/generic
  component location existed before (Step 1 analysis confirmed every prior "reusable" pattern in this app
  was copy-the-convention, not import-a-base-component). Referenced as already built in
  `frontend-visual-redesign-design.md` §6 but never actually created; retrofit into Imaging/Treatment
  Plans/Clinical Notes/Patients-list empty states as those files were touched this phase.
- **Lucide icon migration for the Patients module**: `PatientDetailView.vue`, `PatientsView.vue`, and the
  Imaging components (`PatientImagingPanel.vue`, `UploadImagesDialog.vue`, `ImageThumbnail.vue`,
  `ImageLightbox.vue`, `EditImageDialog.vue`) — Patients was next in the migration's own stated rollout
  order. The shared `InputIcon` search-box glyph and `ConfirmDialog`'s `pi-exclamation-triangle` are
  deliberately left as-is: both are identical, unmigrated patterns used across a dozen+ other views app-wide,
  not specific to Patients — changing only here would create a visible inconsistency, not fix one.
- **`PatientDetailView.vue` tab-list structure cleanup**: extracted a config-driven `tabDefinitions` array
  (key/label/visibility) so each future tab this redesign adds (Medical History, Laboratory, Billing,
  Documents, Timeline) extends one array instead of duplicating a `v-if` across both `TabList` and
  `TabPanels` separately.
- **Security Architecture Decision** (binding on Phase 2.6 and beyond): Patient Timeline will be built on a
  dedicated `PatientActivity` event model, not the `Auditable` trail, with permissions enforced server-side
  per category — see `docs/decisions.md`'s 2026-08-07 entry and the design doc's §9A.

### Docs — Phase 1 release verification + close-out (`docs/phase-1-closeout`, PR #16, 2026-08-07)
- Independently re-verified Phase 1 before merging rather than trusting a pre-filled checklist: re-ran and
  confirmed Backend + Frontend CI green firsthand, confirmed zero merge conflicts with `main`
  (`git merge-tree`), confirmed no TODO/FIXME introduced by the diff.
- Reconciled the staleness `docs/PROJECT_STATUS.md`'s own §0 had flagged: this file's "Unreleased" section
  (missing PR #11-13), `docs/roadmap.md`/`PROJECT_CONTEXT.md`'s stale "Reports/Settings not yet merged"
  claims, and a new `docs/decisions.md` entry flagging the role-hierarchy question for Phase 4.
- Tagged the milestone: **Phase 1 — Foundation Complete.**

### Fixed — Phase 1: Stabilization (`fix/stabilization-phase-1`, PR #15, 2026-08-07; + PR #14, docs-only)
- **Context**: first phase of a new 8-phase roadmap (Stabilization → Patient Profile redesign → Dashboard
  2.0 → Permissions/Audit → SaaS Multi-Tenant → PWA/Mobile → AI Expansion → Launch Prep) — see
  `docs/PROJECT_STATUS.md` for the full mapping of what already exists vs. what each later phase needs.
- **Restored a fully green `main`**: CI's E2E job had been red since 2026-08-01 (`DemoDataSeeder`'s ~110
  seeded patients broke two tests' "first row"/"page 1" assumptions, per the diagnosis already logged in
  `TECH_DEBT.md`). `appointments.spec.ts` now navigates to the appointment it just created by the real id
  from the `POST` response, not the List view's first row. `reports.spec.ts` needed two separate backend
  fixes, found one at a time as each unmasked the next: `ReportService::collections()` had no `ORDER BY` at
  all (added most-recent-first, then a `created_at` tie-break once same-day `received_at` ties — that
  column is deliberately date-only — still landed the test's own fresh payment off page 1); and
  `newPatients()` did have an `ORDER BY`, but ascending, so a newly-registered patient sorted to the
  report's *last* page. Both are genuine correctness fixes for the reports themselves, not test-only
  workarounds — confirmed via `workflow_dispatch` across 3 runs, final run fully green (Backend 932/932,
  Frontend 759/759, E2E 39/39).
- **Fixed `DashboardService.today_appointments`**: was an unscoped `COUNT(*)` over the whole `appointments`
  table, not date-filtered — logged in PR #14, fixed here with the same full-day-bounds convention
  `ReportService` already uses.
- **Frontend consistency pass**: a missing loading indicator (`DentistScheduleView.vue`), two missing
  `#empty` DataTable slots (`PatientsView.vue`/`UsersView.vue`, previously falling back to PrimeVue's
  untranslated default text), one previously-silent fetch failure (`InvoicesView.vue`'s bare
  `try`/`finally` with no `catch`), and four stores (`appointmentTypes`/`dentalConditions`/`suppliers`/
  `labs`) whose `fetchAll()` had no error handling at all — standardized onto the same store-owns-error-
  state pattern `appointments.ts`/`invoices.ts`/`treatmentPlans.ts`/`payments.ts` already used, each with a
  new regression test.
- **Docs**: adds `docs/PROJECT_STATUS.md` (living status book, single source of truth for project status
  going forward) and `CLAUDE.md` (auto-loaded operational playbook enforcing it every session).

### Added — AI Assistant Settings-managed Anthropic API key (`feature/ai-assistant-api-key`, PR #11, 2026-08-02)
- Admins can set/replace/remove the Anthropic API key from **Settings → AI Assistant** instead of only via
  `backend/.env` (which remains a working fallback). Key is `encrypted` at rest on `ClinicSetting`; the API
  only ever returns a `configured` boolean + last-4 characters, never the key itself; explicitly excluded
  from the audit trail (`AuditLogService::EXCLUDED_KEYS`). `AiAssistantService::client()` prefers the
  Settings-stored key when present. 926/926 backend + 749/749 frontend tests green at merge; see
  `docs/modules/ai-assistant-settings-api-key-design.md`.

### Added — Treatment Plans clinic-wide list (`feature/treatment-plans-clinic-index`, PR #12, 2026-08-02)
- Fixes a real gap: the Treatment Plans sidebar entry had been stuck on a stale `comingSoon` flag despite
  the module being fully production-ready since PR #1 — there was no clinic-wide list route to point it at,
  only per-patient tabs. New `GET /treatment-plans` (`TreatmentPlanController::indexAll`, paginated,
  searchable by plan title or patient name, filterable by status) + `TreatmentPlansView.vue`, mirroring
  `InvoicesView.vue`'s own server-paginated pattern from the identical prior fix to Billing's nav. 928/928
  backend + 763/763 frontend tests green at merge.

### Added — Premium Visual Redesign, Steps 1-3 (`feature/premium-visual-redesign`, PR #13, 2026-08-02)
- Amends the original Frontend UX & Navigation Redesign plan: removes "Recent Items" entirely (decided with
  user), replaces the old Phase 2 (Dashboard) scope with a fully-specified premium redesign (not yet
  built), and starts an app-wide **PrimeIcons → Lucide** icon migration (68 distinct icon classes across
  ~66 files), done module-by-module to contain risk rather than in one pass. Design thesis: "premium" comes
  from restraint + consistent spacing/motion + one accent treatment, not decoration — stays within the
  existing "100% PrimeVue semantic tokens, no hardcoded hex" rule. Steps 1-3 cover foundation design
  tokens, Sidebar section-grouping + partial icon migration, and Header/Command Palette polish. 928/928
  backend + 755/755 frontend tests green at merge; see `docs/modules/frontend-visual-redesign-design.md`.

### Added — Frontend UX & Navigation Redesign, Phase 1: Navigation Shell (`feature/frontend-nav-shell`, 2026-07-31)
- **Context**: with every module on the original roadmap Production Ready ✅ (including Settings and AI
  Assistant, both since merged to `main`), this is the first phase of a cross-cutting, frontend-only
  quality initiative benchmarked against Linear/Notion/Stripe/Vercel — see
  `docs/modules/frontend-ux-redesign.md` for the full design doc and phase breakdown.
- **Sidebar**: collapsible section grouping (Clinical/Operations/Insights/Admin), Favorites (star
  toggle, `localStorage`-only), Recent Items (last 5 visited record pages, upgraded from a generic
  fallback label to the real name once a detail view loads it, no extra fetch), and true recursive
  nesting (`AppSidebarItem.vue` referencing itself, resolving a previously-tracked one-level limit).
- **Header**: a breadcrumb trail derived from the existing `config/navigation.ts` (no per-route
  duplication) plus a global search/Command Palette entry button.
- **Command Palette** (`Ctrl+K`/`Cmd+K`): role-filtered "Go to X" for every reachable route, a "New
  Patient" quick action, arrow-key navigation, fuzzy text filter.
- **`useAppShortcuts`**: app-wide `Ctrl+K`, `?` (shortcuts help overlay), and Linear-style `g`-then-X
  go-to chords — generalizes the guard pattern already proven in the Appointments board's own
  `useCalendarKeyboardShortcuts.ts` (untouched, still calendar-scoped) rather than duplicating it.
- **Billing navigation fix**: the Sidebar's Billing entry had been stuck on a stale `comingSoon` flag
  despite the underlying Invoice CRUD working end to end — the real gap was a missing clinic-wide list.
  Added `GET /invoices` (paginated, searchable by invoice number/patient name, status filter) — one
  small, explicitly user-approved backend exception to this phase's otherwise frontend-only scope —
  plus `InvoicesView.vue`, the Sidebar's new real destination.
- **Tests**: 913/913 backend tests (8 new: `InvoiceControllerTest`) + 751/751 frontend Vitest tests (30
  new), `vue-tsc -b`/ESLint/Prettier clean, a new permanent E2E suite
  (`frontend/e2e/frontend-nav-shell.spec.ts`) confirmed 39/39 green via the GitHub Actions API across
  three `workflow_dispatch` runs (final run `30629204011`) — see `TECH_DEBT.md` for the real findings
  along the way (a `vue-tsc -b` build-only type error, three E2E-spec-only bugs, one genuine
  Command-Palette UX bug its own test suite caught before release).

### Added — Reports (design approved and implemented same-day, 2026-07-28)
- **`ReportService`**: six live-query reports over existing data, no new tables — Production (billed
  charges by dentist), Collections (payments received, by method), A/R Aging (outstanding invoice
  balances bucketed by days overdue — a point-in-time snapshot, not date-ranged), Appointment Analytics
  (completed/no-show/cancellation rates), Treatment Plan Acceptance (presented vs. accepted/rejected,
  by `accepted_at`/`rejected_at` rather than current status), and New Patients (registrations by range).
  Every method returns a plain `['summary', 'rows']` array with no HTTP/CSV/view knowledge, so
  `ReportController` and `DashboardService` both reuse it without duplicating any aggregation logic.
- **Permissions**: two plain Gate abilities (`view-financial-reports`/`view-operational-reports`,
  `AppServiceProvider::boot()`) since Reports has no natural Eloquent model for a Policy. Financial
  reports (Production/Collections/A-R Aging) are `admin`-only — a stricter tier than this codebase's
  usual "everyone can view" convention, since these expose practice-wide aggregate revenue rather than
  one patient's billing in clinical context; operational reports stay open to every role.
- **Export**: CSV only, streamed via native `fputcsv` — no new dependency, no PDF, no scheduled/emailed
  reports, no ad-hoc query builder (all explicitly out of scope, see design doc §8/§9).
- **`DashboardService.monthly_revenue`**: previously hardcoded to `0` since Dashboard's original
  implementation; now calls `ReportService::collections()` for the current calendar month.
- **Frontend**: a new **Reports** top-level nav group (the existing `comingSoon` sidebar scaffold filled
  in), a role-filtered `ReportsHomeView.vue` card grid, six report views under `views/reports/`, a shared
  `ReportDateRangeFilter.vue` (using `frontend/src/lib/date.ts` only, per the Datetime Policy), and CSV
  download via a native `Blob`/anchor-element helper.
- **Real bugs found and fixed via CI** (not local inspection — local Playwright execution was blocked
  by this session's Alpine-based dev container, see `TECH_DEBT.md`): a `whereBetween` date-range
  filter on `issue_date`/`received_at` compared as strings against a bare `Y-m-d` upper bound, silently
  excluding rows whose stored value carried a time-of-day suffix (caught by a new end-of-month
  `DashboardTest` fixture); four genuine Larastan findings in `ReportService.php` (two unnecessary
  nullsafe operators, two return-type mismatches from `Collection`'s invariant generics); and a real,
  pre-existing bug shared by every module with role-gated sidebar children (Inventory, Laboratory,
  Dental Chart) — `AppSidebarItem.vue` never actually filtered `item.children` by role, only
  `AppSidebar.vue`'s top-level items, so a restricted nav link rendered for every role and only denied
  access on click.
- 855/855 backend tests (21 Reports-specific) + 652/652 frontend Vitest tests green, `vue-tsc`/ESLint/
  Pint/Prettier clean, production build green. Permanent Playwright suite
  (`frontend/e2e/reports.spec.ts`, 2 tests) confirmed via the GitHub Actions API across two
  `workflow_dispatch` runs — the first (`30323783949`) surfaced the bugs above, the second
  (`30326106755`) is fully green: **Backend 855/855, Frontend 652/652, E2E 29/29 — zero failures.** See
  `docs/modules/reports-design.md` and `TECH_DEBT.md` for the full diagnostic trail.

### Added — Imaging (design approved 2026-07-27, implemented 2026-07-28)
- **`PatientImage`**: per-patient diagnostic image (intraoral/extraoral photo, periapical/bitewing/
  panoramic/cephalometric X-ray, or other), optionally tagged to an FDI tooth number + surfaces (exact
  convention as `DentalChartEntry`), a `taken_at` date distinct from upload time, and optional one-way
  traceability links to a `TreatmentPlanItem`/`Appointment` (exact convention as `LabCase`).
- **Storage**: every read/write/delete goes through the `Storage` facade using a per-row `disk` column —
  never a hardcoded disk or raw filesystem path — so moving from `local` (V1 default) to `s3` (already
  configured, currently unused anywhere) is a config change, not a code change (design doc §7 decision 5).
  Images are served only through an authenticated, policy-checked streaming route
  (`GET /images/{id}/file`/`/thumbnail`) — never a public/static URL (design doc §9).
- **Thumbnails**: generated synchronously at upload time via PHP's built-in GD extension — no new Composer
  dependency (design doc §10).
- **Permissions**: `admin`+`dentist`+`receptionist` can view/upload/edit metadata — deliberately wider than
  Laboratory/Clinical Notes' clinical-only gating, since uploading/organizing images is largely a front-desk
  task in practice; `admin`-only delete, matching every other module.
- **Frontend**: a new **Imaging** tab on `PatientDetailView` (patient-scoped, not a top-level sidebar item —
  unlike Laboratory/Inventory), no Pinia store (direct-`api`-call pattern mirroring `LabCasesView.vue`,
  since a gallery is inherently paginated per-patient data). Upload dialog with drag-and-drop plus the
  standard HTML5 `capture` attribute for direct mobile camera access — no native app or extra library.
  Responsive thumbnail grid; a full-screen lightbox with non-destructive brightness/contrast/invert filters,
  zoom via CSS transform + native touch-scroll panning, prev/next navigation, and a two-image compare mode —
  none of these adjustments are ever persisted, matching the design's file-immutability rule.
  Authenticated images are fetched as blobs via `api` (never a plain cross-origin `<img src>`, which can't
  reliably carry Sanctum's session cookie) and exposed as object URLs via a small `useImageObjectUrl`
  composable that revokes them on cleanup.
- **Out of V1 scope, named explicitly** (design doc §7/§14): DICOM/CBCT support, direct sensor/TWAIN
  hardware capture, persistent annotation/measurement tools, and formal FMX/series grouping — the schema
  needs no reshaping to add any of these later (design doc §15).
- **Standing architectural principles applied** (first module built under them): checked explicitly against
  SaaS multi-tenant readiness (no single-clinic assumption in any storage path or business logic) and
  PWA/mobile-first UI (fully responsive, camera-capture-ready) per the design doc's dedicated closing
  section.
- **Real bug found and fixed while testing**: `App\Rules\BelongsToPatient` throws a genuine SQL error when
  validating `treatment_plan_item_id` (that model has no direct `patient_id` column) — fixed in Imaging's
  own Form Requests via a relation-based check; the identical pre-existing bug in Laboratory's
  `StoreLabCaseRequest`/`UpdateLabCaseRequest` (never exercised by that module's own tests) is flagged in
  `TECH_DEBT.md` rather than fixed here, to keep this branch's diff scoped to Imaging.

### Added — Laboratory (design approved and implemented same-day, 2026-07-27)
- **Admin-managed `Lab` vendor catalog**: contact info, notes, `default_turnaround_days` (drives the
  due-date auto-suggestion below), same `is_active` soft-disable convention as `Supplier`/`AppointmentType`
  — a deliberately separate model from `Supplier` rather than reused, since the two have unrelated relations
  (design doc §0/§3, competitive research point 6).
- **`LabCase`**: one record per case sent to a lab (no header+items split, unlike Purchase Orders — a lab
  case is a single prescription even when it spans multiple teeth). Fields: patient, lab, responsible
  dentist, `tooth_numbers` (JSON array of FDI codes), case type (free text), shade, material, fee
  (tracking-only, never wired into Billing/Payments), tracking number, instructions.
- **`LabCaseStatus` lifecycle**: `draft` → `sent` → `received` → `quality_checked`, plus `cancelled`
  (reachable only from `draft`/`sent`, blocked once `received_at` is set — mirrors
  `PurchaseOrder::cancel()`'s identical "blocked once anything is real" guard). `send()` auto-suggests
  `due_at` from the lab's `default_turnaround_days` unless already manually set, matching Open Dental's own
  turnaround-time-driven due date.
- **Traceability links**: `treatment_plan_item_id`/`appointment_id` are optional, one-way FKs — a case can
  be traced back to the plan item that prescribed it and the appointment it's meant to be ready for, without
  either of those modules knowing Laboratory exists (exact convention as
  `TreatmentPlanItem.diagnosis_entry_id`).
- **Permissions**: dentists (and admin) create/update/cancel a case — choosing lab/tooth/shade/material is a
  clinical prescription decision; admin+receptionist send/receive/quality-check — front-desk logistics once
  prescribed; Lab vendor catalog CRUD and Lab Case delete remain admin-only.
- **Frontend**: `LabsView.vue` (admin-only catalog CRUD, mirrors `SuppliersView.vue`), `LabCasesView.vue`
  (paginated list, mirrors `PurchaseOrdersView.vue`) + `LabCaseDetailView.vue` (overview card, status-action
  buttons, and a browser-printable slip — CSS print only, no new PDF dependency). New top-level
  **Laboratory** sidebar group and a `DueLabCasesWidget.vue` Dashboard card (cases due today/overdue). Full
  en/ar/tr i18n, zero missing/extra keys verified across all three locale files.
- **Verification**: backend `pint`/`phpstan analyse` clean, 815/815 backend tests green (58
  Laboratory-specific: Feature + Unit). Frontend `vue-tsc`/ESLint/Prettier clean, 626/627 Vitest tests green
  (13 Laboratory-specific; the one unrelated failure — a pre-existing, untouched `PatientDetailView.test.ts`
  file — confirmed flaky under that run's environment load, passes cleanly in isolation), production build
  green. A permanent Playwright E2E suite (`frontend/e2e/laboratory.spec.ts`) was written during this
  module's own implementation and **confirmed via the GitHub Actions API across two `workflow_dispatch`
  runs** on `feature/laboratory` (`30293175321` → `30294033562`) — the first surfaced one real bug (a
  duplicate-worded toast left visible from two rapid back-to-back status transitions, since all four
  actions shared one generic message), fixed by giving each action its own distinct toast text. The second
  run's remaining E2E failures/flakiness (`dental-chart.spec.ts`'s rate-limit collision,
  `inventory.spec.ts`/`patients.spec.ts`'s first-load timeouts) were proven — via Playwright's own
  sequential execution order, not assumed — to be pre-existing and unrelated to Laboratory. Final run
  (`30294033562`): **Backend success, Frontend success, all three `laboratory.spec.ts` tests green with no
  retries needed**. See `TECH_DEBT.md` for the full diagnostic trail.

### Added — Inventory (design approved and implemented same-day, 2026-07-26)
- **Admin-managed catalogs**: `Supplier` (contact info) and `Supply Category` (a real table, not a fixed
  enum — dental supply categories genuinely vary per clinic), both using the same `is_active` soft-disable
  convention as `AppointmentType`/`DentalCondition` rather than a hard delete or `Auditable` trail.
- **`Supply`**: the stock-item catalog (name, SKU, unit of measure, unit cost, reorder level/quantity,
  default supplier), with `quantity_on_hand`/`is_low_stock` always computed live from the ledger below —
  never a stored, independently-editable counter that can drift, a deliberate improvement over Open Dental's
  own mutable on-hand field (design doc §0 competitive research).
- **`stock_movements`**: an immutable, append-only ledger — every quantity change (`initial_stock`, `used`,
  `wasted`, `expired`, `correction`, or system-generated `received`) is a signed `quantity_delta` row, never
  edited or deleted; a correction is always a new offsetting row, mirroring `Payment`'s refund-is-a-new-row
  rule exactly. A negative movement is rejected server-side if it would take on-hand below zero
  (`InsufficientStockException`), row-locking the `Supply` first to close the same race
  `PaymentService::refund()` already guards against.
- **Purchase Orders**: `draft` → `placed` → `partially_received` → `received` lifecycle (plus `cancelled`
  from `draft`/`placed`). Items are add/edit/delete-able only while draft; receiving is per-item, hard-capped
  at `quantity_ordered` (genuine over-shipment becomes an explicit `correction` movement or a new order, never
  silent over-receipt); cancel is blocked the instant any item has a real receipt against it. `PurchaseOrder`
  gets `Auditable`; `order_number` (`PO-000001`) is assigned via the same lock-highest-row-and-increment
  pattern `PatientService::create()` already uses for `patient_code`.
- **Permissions**: dentists may record `used`/`wasted`/`expired` Stock Movements (a deliberate divergence
  from the admin+receptionist-only precedent every prior financial module — Billing, Payments — used, since
  dentists are the ones actually consuming supplies chairside); Supplier/Category management and Purchase
  Order procurement (create/place/receive/cancel) remain admin+receptionist; Purchase Order delete is
  admin-only, mirroring `InvoicePolicy`/`PaymentPolicy`'s identical stricter-than-everything-else precedent.
- **Frontend**: `SuppliersView.vue`/`SupplyCategoriesView.vue` (admin-only catalog CRUD, mirroring
  `AppointmentTypesView.vue`), a paginated `SuppliesView.vue` (mirroring `PatientsView.vue`'s direct-`api`-
  call pattern — Supplies/Purchase Orders deliberately have no Pinia store, only the two small catalog
  lookups do) + `SupplyDetailView.vue` (stock movement ledger, Record Usage/Adjustment dialog),
  `PurchaseOrdersView.vue` + `PurchaseOrderDetailView.vue` (full lifecycle actions). New top-level
  **Inventory** sidebar group and a `LowStockWidget.vue` Dashboard card. Full en/ar/tr i18n, zero
  missing/extra keys verified across all three locale files.
- **Verification**: backend `pint`/`phpstan analyse` clean, 771/771 backend tests green (68
  Inventory-specific: Feature + Unit). Frontend `vue-tsc`/ESLint/Prettier clean, 19 new Vitest tests
  (stores + typed-error service) green. A permanent Playwright E2E suite
  (`frontend/e2e/inventory.spec.ts`) was written during this module's own implementation and
  **confirmed via the GitHub Actions API across five `workflow_dispatch` runs** on `feature/inventory`
  (`30277023360` → `30280053248` → `30280937935` → `30281608486` → `30282195677`) — this dev machine's
  own local attempts hit the same Windows Docker Desktop networking latency already logged against Dental
  Chart/Clinical Notes (reproduced identically on the completely unrelated, pre-existing `auth.spec.ts`,
  ruling out an Inventory-specific cause) and never got far enough into the golden path to be conclusive,
  so CI's native runner did the real verification instead — surfacing and closing five genuine bugs one
  run at a time: a real PHPStan error (`PurchaseOrderService` assigning a plain string to a
  `Carbon|null`-cast property), a codebase-wide PrimeVue accessibility defect (`id` instead of `inputId`
  on every `InputNumber`/`DatePicker`/`Select`, silently breaking every affected field's label
  association — the same pre-existing mistake was found, unfixed, in `UsersView.vue` too, logged as its
  own follow-up), a missing confirm-dialog `acceptLabel`, two duplicate-toast bugs (child dialog and
  parent view each showing their own "saved"/"added"/"received" toast for one action), and one real E2E
  selector ambiguity (a hidden dialog's leftover `aria-label` colliding with a ledger cell's text). Final
  run (`30282195677`): **Backend success, Frontend success, E2E success — 20/20 E2E tests green**. See
  `TECH_DEBT.md` for the full diagnostic trail.

### Added — Clinical Notes (design approved 2026-07-25, backend + frontend implementation-complete
2026-07-26, closing the permanent-E2E-suite gap the prior three modules each deferred)
- **SOAP-structured clinical documentation**: one note per authoring session — chief complaint plus
  Subjective/Objective/Assessment/Plan sections, all optional/nullable — with a `note_type`
  (`progress`/`consultation`/`phone`/`referral`/`other`) and an optional link to a specific `Appointment`.
- **Draft → Signed lifecycle**: freely editable while `draft`; signing is wrapped in `DB::transaction()`,
  requires at least one non-empty content field (an entirely blank note cannot be signed —
  `invalid_clinical_note_operation`), and atomically freezes every content field. Any further write attempt
  against a signed note is rejected with a dedicated `ClinicalNoteLockedException`
  (`clinical_note_locked`) — mirroring `TreatmentPlanItemLockedException`/`InvoiceLockedException`'s exact
  enforcement pattern.
- **Addendums**: append-only corrections to an already-signed note. No update or delete endpoint exists for
  an addendum at any permission level, not even admin — enforced at the schema level too
  (`clinical_note_addendums` has no `updated_at`/`deleted_at`/soft-delete trait), not just the policy level.
- **Role-based access**: admin/dentist can author, update (draft only), sign, and addend; admin-only delete
  (soft, any status, fully `Auditable`-logged/recoverable). **Receptionist has no access at all** — a
  deliberate divergence from Dental Chart/Treatment Plans (which both grant receptionist read access) given
  the sensitivity of clinical narrative content — enforced at the policy layer, the frontend tab visibility,
  and the router's own `meta: { roles: [...] }` guard.
- **Frontend**: new "Clinical Notes" tab on `PatientDetailView.vue` (`PatientClinicalNotesPanel.vue`) plus a
  dedicated `ClinicalNoteDetailView.vue` (author/edit/sign/addendum), following the existing tab-list +
  dedicated-detail-route pattern from Treatment Plans/Invoices/Payments. Draft renders editable
  `Textarea`/`Select` fields; signed renders the same fields as plain read-only text (never the same input
  merely disabled) with a lock notice and an always-available Add Addendum action. Full en/ar/tr i18n
  (`clinicalNotes.*` namespace), verified zero missing/extra keys across all three locale files.
- **Permanent Playwright E2E suite built during this module's own implementation, not deferred** —
  `frontend/e2e/clinical-notes.spec.ts` closes the open item the prior three consecutive modules (Treatment
  Plans, Billing, Payments) each pushed to `TECH_DEBT.md`. Covers the full dentist/admin golden path (create
  draft → edit → save → sign → verify locked/read-only state → add two addendums → verify append-only, no
  edit/delete affordance on either), a blank-note sign-rejection case, and receptionist exclusion (tab absent
  + direct-URL navigation blocked by the router guard, not just hidden).
- **Verification**: backend `pint`/`phpstan analyse` (level 5) clean, 703/703 backend tests green (60 of
  them Clinical-Notes-specific: `ClinicalNoteServiceTest`, `ClinicalNoteTest` Feature suite,
  `ClinicalNotePolicyTest`, `ClinicalNoteStatusTest`, the three Form Request tests) — a complete backend
  Feature-test suite shipped within this module's own Phase 2, per the design doc's explicit requirement (not
  deferred, unlike Billing at its own completion). Frontend `vue-tsc`/ESLint clean, 595/595 Vitest tests green
  (31 Clinical-Notes-specific: store + API service + typed-error tests — no dedicated per-component tests,
  see Known Limitations below), production build succeeds. E2E suite could not be confirmed green from this
  dev machine (same Windows Docker Desktop networking latency already logged against Dental Chart's own
  suite — see `TECH_DEBT.md`); CI's native runner is the verification authority for this suite.
- **Known limitation relative to the design doc's own testing strategy (§18)**: §18 named dedicated
  "component tests for the detail view's draft/signed/addendum states" as in-scope; these were not written as
  separate Vitest component tests. The same states are instead exercised end-to-end by the new Playwright
  suite (draft editing, sign, locked read-only rendering, addendum add/append-only are all explicit E2E
  assertions), so the intended coverage exists, just at a different test layer than originally specified.

### Fixed — Payments migration: self-referencing FK on `refunded_payment_id` failed on real PostgreSQL (2026-07-26)
- `2026_07_25_000001_create_payments_table.php` declared the `refunded_payment_id` foreign key via
  `->constrained('payments')` inside the *same* `Schema::create()` as the table's own primary key. Laravel's
  `Blueprint` always appends the primary-key command after every explicit index/foreign-key command in the
  same blueprint (`addFluentIndexes()`, run once at compile time after the whole closure), so the
  self-referencing FK was always compiled *before* this table's own primary key existed yet — Postgres
  rejects that ("no unique constraint matching given keys for referenced table"). Found while working on the
  unrelated Clinical Notes migrations, when a fresh `migrate` against the real Postgres container was run for
  the first time in a while and failed on this migration.
- **Root cause this went unnoticed for a full day (2026-07-25 → 2026-07-26) despite 643 passing backend
  tests**: `backend/phpunit.xml` forces `DB_CONNECTION=sqlite`/`DB_DATABASE=:memory:` for the entire test
  suite, and SQLite does not enforce the same FK-vs-primary-key compile ordering Postgres does — every
  `RefreshDatabase` test run silently passed against a schema that could never actually be created on the
  real production database engine. Confirmed by direct reproduction: `php artisan migrate:fresh` against the
  real `dentalsuite_postgres` container failed on this exact migration before the fix, and completes cleanly
  (all migrations + all seeders) after it.
- Fixed by splitting the foreign key into its own follow-up migration,
  `2026_07_26_000001_add_refunded_payment_id_foreign_to_payments_table.php`, added against the already-fully-created
  `payments` table — the column itself (`$table->uuid('refunded_payment_id')->nullable()`) stays in the
  original migration; only the `->foreign()` constraint moves. No data migration needed: no environment had
  successfully completed the original migration against Postgres, so there is no pre-existing data to
  reconcile.
- Verified: `php artisan migrate:fresh --seed` against the real Postgres container now runs all 26 migrations
  and every seeder cleanly end-to-end; full backend test suite re-confirmed green after the change. Logged in
  `TECH_DEBT.md` as a broader gap — the test suite has no real-PostgreSQL migration verification step at all.

### Fixed — Payments concurrency race (PR #2, commit `7ac502e`, 2026-07-25)
- `PaymentService::apply()`/`refund()` performed a read-check-then-write on `Payment` with no row lock:
  concurrent refund (or apply) requests against the same payment could both read the same
  remaining-refundable amount before either committed, together over-refunding it. Found during PR #1's
  final review.
- Fixed with `DB::transaction()` + `Payment::lockForUpdate()`, mirroring `InvoiceService::issue()`/`void()`'s
  existing pattern for the same class of problem; `refund()` now re-fetches `withTrashed()` so the intended
  `InvalidPaymentOperationException` for an already-deleted payment still fires instead of being masked by
  `SoftDeletingScope` into an unrelated `ModelNotFoundException`.
- Verified: 27/27 `PaymentServiceTest` cases (2 new regression tests) and the full 643-test backend suite
  pass; `pint`/`phpstan analyse` clean.

### Added — Payments (`feature/treatment-plans`, 2026-07-25, backend + frontend implementation-complete same day as design approval)
- **Payment recording**: applied directly to an `issued` `Invoice` or left **unapplied** as an
  advance/patient-account credit (`invoice_id` nullable) — an unapplied payment can be **applied** to a
  specific issued invoice later, exactly once, full-amount only.
- **Refunds**: full or partial, capped at a payment's own remaining un-refunded balance
  (`original.amount + SUM(existing refunds) - requested >= 0`, enforced server-side). Modeled as a new
  linked `Payment` row with a negative `amount` and `refunded_payment_id` pointing at the original — the
  original is never edited. No time-based void window; refund is the only correction mechanism regardless
  of the payment's age.
- **`PaymentMethod`**: `cash`/`card`/`bank_transfer`/`other` — recording only, no processor integration.
- **`Invoice` gains real balance fields**: `amount_paid`/`balance_due`/`payment_status`
  (`unpaid`/`partially_paid`/`paid`) — purely additive on `InvoiceResource`, computed from the (now
  eager-loaded) `payments` relation, no new column, no reshape of the existing response envelope.
- **Role-based access**: `PaymentPolicy` mirrors `InvoicePolicy` exactly — admin/receptionist write
  (record/update-metadata/apply/refund), dentist read-only; delete (soft, data-entry-error correction only,
  blocked once any refund exists against the payment) is admin-only.
- **Frontend**: new dedicated **Payments** tab on `PatientDetailView.vue` (`PatientPaymentsPanel.vue`,
  sibling to the Invoices tab, not folded into it) plus a **Payments** panel on `InvoiceDetailView.vue`
  (`InvoicePaymentsPanel.vue`) with a balance readout next to Total. Record/Refund/Edit/Apply dialogs,
  `payments.*` i18n namespace — full en/ar/tr parity (verified: zero missing/extra keys across all three
  locale files).
- **Verification**: backend `pint`/`phpstan analyse` clean, 619/619 backend Unit tests green (Models,
  Policies, Service, the new `InvoiceResourceTest` covering `amount_paid`/`balance_due`/`payment_status`)
  plus 22/22 new `PaymentTest` Feature tests (HTTP-level: auth, permissions, validation, status-transition
  edge cases) — closing the Feature-test gap Billing itself is still carrying. Frontend `vue-tsc`/`eslint`
  clean; the 20 new tests (`stores/payments.test.ts`/`services/payments/errors.test.ts`) pass cleanly. A
  full-suite run (561 tests) showed 2 failures confined to the pre-existing `router/index.test.ts` —
  unrelated to Payments (imports nothing from this module) and confirmed passing 11/11 in isolation;
  logged separately in `TECH_DEBT.md` as environment flakiness, not attributed to this module. A permanent
  Playwright E2E spec is still open — see `TECH_DEBT.md`.

### Added — Billing (`feature/treatment-plans`, 2026-07-23–2026-07-25, backend + frontend implementation-complete)
- **Invoice lifecycle**: `draft` → `issued` → `void`, plus soft-delete (admin-only, draft-only) as a
  separate data-correction action. Issuing an invoice freezes every item's `description`/`unit_amount` and
  assigns a permanent, sequential `invoice_number` (`INV-000001`, concurrency-safe reservation mirroring
  `PatientService`'s existing pattern) — no field on an issued/void invoice or its items is ever mutated
  again outside `void` itself.
- **Invoice item CRUD**: `charge`/`discount`/`tax` kinds, each with its own snapshotted description/amount;
  charge items may optionally trace back to a completed `TreatmentPlanItem` (nullable FK, traceability only,
  never re-read once written) via a "not yet invoiced, completed" picker endpoint
  (`GET /patients/{patient}/treatment-plan-items/billable`, a derived read anti-joined against
  `invoice_items`, never a stored flag).
- **Role-based access**: `InvoicePolicy`/`InvoiceItemPolicy` — admin/receptionist can create, edit
  (draft-only), and issue/void; dentist is strictly read-only (billing is front-desk work, the inverse split
  from Treatment Plans/Dental Chart); delete (soft, data-correction) is admin-only.
- **Frontend**: new "Invoices" tab on `PatientDetailView.vue` (`PatientInvoicesPanel.vue`) plus a dedicated
  `InvoiceDetailView.vue` route (`/patients/:id/invoices/:invoiceId`) with itemized line table
  (charge/discount/tax visually distinguished), status-action buttons, manual "Add Charge" and "Add from
  Treatment Plan" (multi-select picker) item entry, and a notes/dates edit dialog. `invoices.*` i18n
  namespace — full en/ar/tr parity. Every mutation (invoice- and item-level, including item delete) returns
  the full updated `Invoice` so the frontend never re-fetches after a write.
- **Verification**: backend `vendor/bin/pint`/`phpstan analyse` clean, 65/65 backend unit tests (Models,
  Policies, Service) green; frontend `vue-tsc -b`/`eslint` clean, 541/541 frontend Vitest tests green (no
  regressions). Automated Feature/Request tests for the new Form Requests/Controllers, a permanent E2E
  suite, and the final `modules/billing.md` doc are still open — see `TECH_DEBT.md` and
  `docs/modules/billing-design.md`.

### Added — Treatment Plans (`feature/treatment-plans`, commit `0677128`, 2026-07-23)
- **Treatment plan lifecycle**: `draft` → `presented` → `accepted`/`rejected` → `in_progress` →
  `completed`, plus `cancelled` from any non-terminal status. Accepting a `presented` plan auto-rejects
  every other `presented` plan for the same patient; cancelling a plan cascades to cancel its non-terminal
  items. Revisions of a rejected plan are modeled as a new plan row linked via `superseded_by_plan_id`
  (`POST /api/treatment-plans/{plan}/revisions`), not a versioning subsystem.
- **Treatment plan item CRUD**: procedure, optional tooth/surfaces, quantity, cost — sourced from
  `dental_conditions` (extended with new `default_cost`/`description` columns), with cost/name/description
  snapshotted onto the item and frozen the moment the parent plan leaves `draft`
  (`TreatmentPlanItemLockedException`). Item-level lifecycle: `planned` → `completed`/`cancelled`.
  Optional read-only links to a `DentalChartEntry` (diagnosis traceability) and an `Appointment`
  (scheduling) — one-way references, no sync in either direction.
- **Role-based access**: `TreatmentPlanPolicy`/`TreatmentPlanItemPolicy` — admin/dentist can create, edit
  (draft only), and drive every status transition; receptionist is strictly read-only; delete (soft,
  data-correction) is admin-only, distinct from cancel.
- **Frontend**: new "Treatment Plans" tab on `PatientDetailView.vue` (`PatientTreatmentPlansPanel.vue`) plus
  a dedicated `TreatmentPlanDetailView.vue` route (`/patients/:id/treatment-plans/:planId`) with item
  list/timeline, status-action buttons, and add/edit item dialogs. `treatmentPlans.*` i18n namespace —
  84/84 keys, en/ar/tr parity confirmed.
- **Tests**: 505/505 backend (Pest), 541/541 frontend (Vitest) — full Feature/Unit/Policy/Request/Service
  coverage matching the design doc's testing strategy.

See `docs/modules/treatment-plans.md` for the full final module doc (architecture, database, API,
permissions, known limitations — including the one open gap relative to this project's usual bar: no
permanent Playwright E2E suite yet, see `TECH_DEBT.md`) and `docs/modules/treatment-plans-design.md` for the
original approved design and competitive research.

## v1.0.0-appointments — 2026-07-20

**Release summary**: Appointments module completed and promoted to Production Ready. Calendar views
(Day/Week/Month/List), full appointment CRUD with status-transition lifecycle, slot availability logic,
and dentist schedule/time-off management are all implemented end to end (backend + frontend), on top of
the application shell, design system, and audit-logging infrastructure also shipped in this release. A
system-wide Production Gate (seed-data environment guard, rate limiting, production Docker/nginx/SSL
topology, rehearsed backup/restore, CI/CD) closed on the same date. E2E verification: **13/13 passed on
GitHub Actions** (run `29763458360`, commit `3faf2d7`, alongside green Backend/Frontend jobs). See
`docs/modules/appointments.md` for the module's final architecture, decisions, and known limitations, and
the detailed entries below for the full change history this release comprises.

### Fixed — Appointments (Final QA pass, Phase 2 Step 10)
- **Silent fetch failures across every appointments surface**: `appointments.ts`'s `fetchRange` has
  always captured a network/server failure into a reactive `error` ref (translation key
  `appointments.loadError`, already present in all 3 locales) — but nothing ever read it. Confirmed
  directly: forcing the list endpoint to 500 rendered a completely empty Board/widget, visually
  indistinguishable from "nothing scheduled" — a real risk for a clinical scheduling app (a
  receptionist could mistake a failed fetch for a genuinely open day). Fixed by adding an
  `error`-watching toast in the four consumers of the shared cache: `AppointmentsView`,
  `TodayScheduleWidget`, `UpcomingAppointmentsWidget`, `PatientAppointmentsPanel`. Design doc §1.9
  actually calls for a fuller inline retry state replacing the grid on the Board's initial load, not
  just a toast — that richer treatment is **not** implemented here; see TECH_DEBT.md.
- **`getContrastTextColor()` picked the wrong (lower-contrast) text color for backgrounds in the
  luminance range ~[0.179, 0.5)**: its old rule was a flat `luminance > 0.5 ? black : white` split,
  but the actual black-vs-white WCAG crossover is at ~0.179, not 0.5. Confirmed with `#3b82f6` (a
  plausible clinic-picked appointment color): the old logic returned white at a 3.68:1 contrast
  ratio (fails WCAG AA's 4.5:1), when black scores 5.71:1 (passes) on the same background. Fixed by
  comparing both candidates' actual contrast ratios instead of a fixed threshold; regression test
  added. Affects every consumer (`AppointmentStatusChip`, calendar events, Appointment Types table).
- **Whole-page horizontal scroll on narrow (~390px) viewports on the Appointments Calendar**: traced
  through the full ancestor chain to `DefaultLayout.vue`'s main-content flex item, which had no
  `min-w-0` — a classic flexbox pitfall where a flex item's default `min-width: auto` refuses to
  shrink below its content's intrinsic width, so FullCalendar's Week grid forced the *entire app
  shell* wider than the viewport instead of scrolling within its own card. Fixed at the shared
  layout level (protects every current and future module, not just this one), plus two
  Appointments-local contributors: `AppointmentCalendar.vue`'s grid now has its own
  `overflow-x-auto` wrapper, and `CalendarToolbar.vue`'s view-switch/New-Appointment button group
  now wraps instead of forcing its parent wider. Confirmed fixed via Playwright at 390px/834px.
- **RTL chevron mirroring**: `AppointmentCalendar` already mirrors the day grid itself for RTL
  (Saturday...Sunday, right to left, so "forward in time" reads leftward) — but
  `CalendarToolbar`'s prev/next buttons kept hardcoded LTR icons (`pi-chevron-left`/`-right`)
  regardless of direction, so "next" pointed the opposite way from how the grid underneath it
  actually reads. Fixed by swapping both icons under `isRtl`; confirmed live (RTL desktop
  screenshot) and via a new unit test.

### Added — Appointments (Accessibility + Keyboard Shortcuts polish, Phase 2 Step 9)
- **Keyboard shortcuts** on the Appointments Board/List (design doc §2.10): `N` new appointment,
  `←`/`→` prev/next period, `T` today, `1`/`2`/`3`/`5` switch view (`4`, Dentists view, reserved but
  a no-op until that view itself ships), `/` focus the patient filter, `?` show a shortcuts-help
  dialog (also reachable via a visible `?` icon button). New `useCalendarKeyboardShortcuts()`
  composable never fires while an input/textarea/select has focus or any dialog is open — checked
  on every keystroke.
- **`KeyboardShortcutsHelp.vue`** (new) — the `?`-triggered reference dialog.
- **Focus management** across every dialog in the module: new `useDialogFocusRestore()` composable
  captures whatever had focus before a dialog opened and restores it on close (`AppointmentDialog`,
  `TimeOffFormDialog`, `AppointmentTypeFormDialog`, `WorkingHoursDayRow`'s copy-to dialog).
  `AppointmentDialog` additionally autofocuses the Patient search field on open, via a real HTML
  `autofocus` attribute — not a `.focus()` call, since PrimeVue `Dialog`'s own post-open focus
  logic unconditionally overrides anything set earlier unless the target already carries
  `[autofocus]` (a real bug, only caught by the real-browser verification pass; see Fixed below).
- **Keyboard-accessible `AppointmentCard`**: new opt-in `clickable` prop adds `tabindex="0"`,
  `role="button"`, and `Enter`/`Space` handling for its Dashboard-widget usage (`TodayScheduleWidget`,
  `UpcomingAppointmentsWidget`) — previously mouse-only despite emitting `click`.
- **`SlotPicker`** chips now carry `aria-pressed` (tracks the selected slot) and `aria-disabled`
  alongside the native `disabled` attribute.
- **`ConflictAlert`** now renders `role="alert"`/`aria-live="assertive"` for a hard-block conflict
  vs. `role="status"`/`aria-live="polite"` for an overridable soft warning (previously PrimeVue
  `Message`'s own hardcoded `role="alert"` for both), via its `pt` passthrough prop.
- **FullCalendar event text contrast**: every event now gets an explicit `textColor` from the
  existing luminance-based `getContrastTextColor()` helper — previously only `AppointmentStatusChip`
  used it; a light clinic-entered `appointment_type.color` could render illegible white-on-light
  event text on the Board.
- **Reduced motion**: a global `@media (prefers-reduced-motion: reduce)` override in `style.css`,
  since neither PrimeVue's `Dialog`/`Toast` transitions nor FullCalendar's view-switch animation
  respect the media query on their own. Benefits every module.
- Docs: `docs/modules/appointments-ui-design.md` §14 (Accessibility Checklist) and §2.10 (Keyboard
  shortcuts) updated to reflect what actually shipped.
- Full Vitest coverage for all new/changed behavior (two new composable test files, updated
  component tests); `vue-tsc`, ESLint, and Prettier all clean on every touched file.

### Fixed — real bug found via this step's mandatory real-browser verification
- **PrimeVue `Dialog` silently overrides a parent-set focus target after it opens**: a `.focus()`
  call made from `AppointmentDialog`'s own open-time logic (even via `nextTick`) was reliably
  overridden ~150–200ms later by `Dialog`'s own `onAfterEnter` hook, which always calls its internal
  `focus()` and falls back to its Close button unless the target already carries the native HTML
  `[autofocus]` attribute. Confirmed directly with a Playwright timing probe (focus was correctly on
  the Patient search field at t+100ms, then jumped to the Close button by t+200ms). Fixed by giving
  the intended field a real `autofocus` attribute instead of fighting the timing.

### Added — Appointments (Dashboard widgets + Patient Appointments panel, Phase 2 Step 8)
- **`TodayScheduleWidget.vue`** (new) — today's appointments on `DashboardView`, sorted by time.
  `scope="all"` (admin/receptionist) adds "Waiting" (checked in, not yet started) and "Late"
  (scheduled/confirmed, start time passed by >10 min, computed client-side against a
  once-a-minute-refreshed clock) tags plus a one-click Check In on the single next
  scheduled/confirmed appointment, reusing `StatusActionButton`. `scope="own"` (dentist) shows a
  plainer "My Schedule Today" list filtered to their own appointments — no Waiting/Late framing or
  Check-In action, since checking a patient in isn't a dentist-role permission either way.
- **`UpcomingAppointmentsWidget.vue`** (new) — next 5 appointments over the following 6 days,
  same `scope` prop, excluding cancelled/no-show (a cancelled appointment days out isn't "what's
  coming").
- **`PatientAppointmentsPanel.vue`** (new) — a new "Appointments" card section on
  `PatientDetailView` (added as another stacked card, matching the page's existing
  Demographics/Contact/.../History convention, not a new Tabs layout), reusing
  `AppointmentListTable` + `AppointmentDialog` (prefilled with the patient). "New Appointment" is
  gated behind `canManageAppointments` — a dentist gets a read-only list.
- Both widgets and the panel read `appointments.ts`'s existing shared range-cache directly (no new
  store/service code, no new API calls beyond what the Board already needed for the same range).
- Full Vitest coverage for all three new components (17/17 new, 243/243 whole suite); `vue-tsc`,
  ESLint, and Prettier all clean.

### Fixed — real bug found via this step's mandatory real-browser verification
- **`AppointmentDialog`'s `prefill.patient_id` had no way to show which patient was prefilled**:
  `PatientSearchSelect` only renders a patient summary card from an explicit patient object, not a
  bare id, so opening "New Appointment" from `PatientAppointmentsPanel` (the first real caller of
  `prefill.patient_id`) showed a confusing, blank-looking Patient tab — the search box hidden
  (since a patient *was* technically selected) but no name/summary shown either. Fixed by adding an
  optional, display-only `patient` field to `AppointmentPrefill`; `PatientDetailView` already has
  the full `Patient` record in memory, so this costs no extra fetch.

### Changed — Design System (Arabic typography, self-hosted fonts)
- **Arabic UI face changed from IBM Plex Sans Arabic to Alexandria** (`docs/design-system.md` §1) — a
  modern, Kufi-rooted geometric typeface, per explicit product request for a more distinctive Arabic
  identity. Noto Kufi Arabic was evaluated and rejected as the primary UI face (its own docs recommend it
  mainly for larger text sizes; this app's dense data tables need small-size legibility a traditional Kufi
  face trades away).
- **Both Inter and Alexandria moved from Google Fonts CDN to self-hosted `.woff2` files**
  (`frontend/src/assets/fonts/`) — 3 files total (~164 KB), each a variable-weight font covering 400–700 in
  one file. Zero external font requests now (confirmed via network trace); `index.html`'s CDN
  `<link>`/`preconnect` pair replaced with two local `rel="preload"` hints. No new npm package added (no
  `@fontsource`) — plain committed static assets + `@font-face` in `style.css`.
- Verified via Playwright against the real dev stack: no layout shift (card/sidebar positions
  pixel-identical before/after), Arabic/English × light/dark all checked, zero regressions.

### Added — Appointments (Appointment Types CRUD, Phase 2 Step 7)
- **`AppointmentTypesView.vue`** rebuilt from its Step-1 stub into the real admin-only CRUD screen (design
  doc §7): a `DataTable` over the already-cached, unpaginated `appointmentTypes.ts` list (client-side
  search/sort/"Show inactive" filter, no server round-trip — same reasoning as `AppointmentTypeSelect`'s
  dropdown source), with inline Edit/Delete actions mirroring `UsersView`'s convention.
- **`AppointmentTypeFormDialog.vue`** (new) — Create/Edit dialog for name, duration (`DurationInput.vue`
  reused), color, and active toggle. Color is a PrimeVue `ColorPicker` paired with a synced hex `InputText`
  so an admin can also paste an exact brand hex code, validated client-side against the backend's
  `^#[0-9A-Fa-f]{6}$` regex before submit. "Price" and "Default" are deliberately **not implemented** — no
  backend column exists for either (confirmed in the design doc), so they're flagged as out-of-scope rather
  than fabricated as frontend-only fields with nowhere real to persist them.
- Full Vitest coverage for both new files (12/12 passing, 226/226 for the whole suite); `vue-tsc`, ESLint,
  and Prettier all clean.

### Fixed — real bug found via this step's mandatory real-browser verification
- **Hex color codes bidi-reordered in the Arabic (RTL) table**: `#F97316` rendered as `F97316#` — the
  leading `#` is a bidi-weak character, and inside an RTL row the browser's bidi algorithm reordered it to
  the end of the numeral run. Not visible in English/LTR, only caught by verifying the actual Arabic
  locale against the real dev stack. Fixed by isolating the hex text in its own `dir="ltr"` span in the
  Color column, the same technique any non-Arabic technical token (codes, IDs) needs inside RTL layouts —
  no other screen in this module currently renders a leading symbol like `#` next to digits, so this is the
  first place the pattern was needed.

### Added — Appointments (Dentist Schedule View, Phase 2 Step 6)
- **`DentistScheduleView.vue`** (design doc §5-§6): a `DentistSelect`-driven page (admin can pick any
  dentist; a dentist-role user only ever sees their own, no selector) with two tabs.
  - **Working Hours** — `WorkingHoursEditor.vue` + `WorkingHoursDayRow.vue`: a 7-day weekly grid, each day
    supporting multiple shift rows (split-shift/lunch-break support), a per-day active toggle (bulk-sets
    every shift row's `is_active`), and a "Copy to…" action to duplicate a day's shifts onto other days.
    Since the backend exposes no update endpoint for `dentist_working_hours`, editing a shift is
    implemented as delete-old + create-new under the hood, presented as a single in-place edit. A
    dentist-role user sees the same grid read-only, with an explanatory note.
  - **Time Off** — `TimeOffCalendar.vue` (a chronological list, not a mini calendar — the Board's own
    time-off overlay already covers that visualization) + `TimeOffFormDialog.vue`. The category picker
    (Vacation/Conference/Sick Leave/Emergency/Other) is a client-side-only convenience that writes its
    label into the backend's free-text `reason` field (e.g. `"Vacation: Family trip"`) — the backend has
    no structured category column, so this is never treated as one downstream. The dialog cross-references
    the dentist's cached appointments for the proposed range and shows a non-blocking conflict warning
    list (the backend neither blocks nor cascade-cancels on an overlapping time-off entry).
- Full Vitest coverage for every new component/store and the wired view (214/214 passing); `vue-tsc`,
  ESLint, and Prettier all clean.

### Fixed — real bugs found via this step's mandatory real-browser verification
- **Upstream PrimeVue `DatePicker` defect**: with `show-time` + `hour-format="24"` (used here and by the
  already-shipped `AppointmentDialog`, Step 4), typing a full date/time string and tabbing away silently
  cleared the field — no error, just data loss. Root cause: `primevue/datepicker`'s `populateTime()`
  unconditionally calls `ampm.toLowerCase()` even in 24-hour mode, where `ampm` is `undefined`, throwing
  and discarding the parsed value. Since this is a defect in the vendored library itself, not our code,
  fixed via a permanent `patch-package` patch (`frontend/patches/primevue+4.5.5.patch`, applied on every
  `npm install` via a new `postinstall` script) rather than working around it at each call site. Confirmed
  this also silently affected the Step 4 `AppointmentDialog` date/time fields, since that step's browser
  verification had only exercised the calendar-click path, never manual typing.
- **Working-hours "Add shift" race condition**: if an unrelated day's edit (e.g. a "Copy to…" action)
  forced the whole working-hours list to refresh while a just-saved shift's create request was still in
  flight, the row's resync logic treated the still-`id`-less draft as "never persisted" and silently
  stranded it — a later delete on that row would then discard it locally without ever calling the API,
  leaving an orphaned row on the backend with no way to remove it from the UI. Fixed in
  `WorkingHoursDayRow.vue` by tracking which drafts are genuinely uncommitted (added but not yet Saved)
  versus merely awaiting their real server-issued id, and only guarding the resync against the former.
- **Read-only working-hours display leaked the backend's raw `HH:mm:ss`** (e.g. `"08:00:00 – 18:00:00"`)
  for a dentist viewing their own schedule, instead of the `HH:mm` format the editable admin view already
  showed. Fixed by formatting both consistently in `WorkingHoursDayRow.vue`.

### Added — Appointments (Appointment Detail View, Phase 2 Step 5)
- **`AppointmentDetailView.vue`** rebuilt from its Step-1 stub into the real detail screen (design doc §4):
  header summary, timeline, patient panel, action bar, and future-module placeholders, all wired to the
  `appointments` store's `fetchOne`/mutation actions.
- **New components**, each unit-tested in isolation:
  - `AppointmentCard.vue` — presentational summary card (type, patient, dentist, date/time range, duration,
    status, reason). Deliberately store-free (props/events only) so it can be reused by a future Dashboard
    or search-results row without pulling in any store or permission logic.
  - `AppointmentTimeline.vue` — vertical status stepper driven by a data-driven step-definition array keyed
    to each status's real timestamp column, not the current status enum alone; a cancelled/no-show
    appointment's chain terminates at the point it actually stopped rather than showing a ghost "Completed
    (pending)" step. See TECH_DEBT.md for the one documented exception (`confirmed` has no dedicated
    timestamp column yet).
  - `AppointmentActionsBar.vue` + `StatusActionButton.vue` — the six status-transition buttons
    (Confirm/Check In/Start/Complete/Cancel/No Show), gated by a status/role/ownership visibility table.
    This table decides visibility and UX messaging only; the backend's own state machine and policies
    remain the sole authority — a stale assumption still gets rejected by the real API call, and the
    component re-fetches and re-syncs the displayed appointment rather than trusting its own guess. The
    early-no-show conflict reuses the existing `ConflictAlert`/override pattern from the booking dialog.
  - `FutureFeaturePlaceholder.vue` — generic "coming soon" card, used for Treatment Plan/Invoices/Clinical
    Notes/Attachments plus the admin-only Audit History slot (§4.2 — no backend route exists yet, see
    TECH_DEBT.md).
- Edit now has a live call site: the Detail view's Edit button opens the existing `AppointmentDialog` in
  edit mode (built in Step 4, previously unwired).
- Every new datetime display goes through `frontend/src/lib/date.ts`'s shared helpers per the project's
  datetime policy (`docs/decisions.md`) — no new ad hoc date handling introduced.
- Full Vitest coverage for every new component and the wired view; `vue-tsc`, ESLint, and Prettier all clean.

### Fixed — real bugs found via this step's mandatory real-browser verification
- **Ambiguous "Cancel" button pair**: the Cancel-with-reason dialog showed two buttons both labeled
  "Cancel" — the dismiss button (close the dialog) and the destructive confirm button (actually cancel the
  appointment), since both reused the bare action verb. Renamed the dismiss button to "Keep Appointment"
  and the confirm button to the dialog's own full header text ("Cancel Appointment" / "Mark as No Show"),
  so the two are never textually identical (`StatusActionButton.vue`).
- **Empty "Actions" card for a terminal appointment**: a completed/cancelled/no-show appointment has no
  visible status-transition buttons by design, but the Actions card rendered as a blank box with no
  explanation. Added a "No actions available for this appointment" message in that state
  (`AppointmentActionsBar.vue`).
- Both found and fixed via the full Confirm→Check In→Start→Complete lifecycle, Cancel-with-reason, and
  No-Show early-conflict/override flows driven end to end against the real dev stack (Docker/Postgres) in
  English/Arabic × light/dark — see the design doc's §20 status table for Step 5.

### Added — Appointments (Appointment Dialog, Phase 2 Step 4)
- **`AppointmentDialog.vue`** (Patient / Appointment / Notes tabs, design doc §3) — the full Create flow end
  to end: patient search-and-select or inline creation, dentist/type selection with duration auto-fill
  (only while the user hasn't manually touched duration), a calendar-driven date/time picker with a live
  "Ends at" preview, an available-slots toggle, and reason/notes fields with character counters. Edit mode
  (`:appointment` prop) locks the Patient tab per the backend's "patient_id not editable" rule and shows a
  read-only status chip; not yet wired to a live call site (that's `AppointmentDetailView`, Step 5) but
  fully implemented and unit-tested.
- **New components**: `PatientSearchSelect.vue` (debounced typeahead + "Create New Patient", reusing the
  existing `PatientFormDialog.vue` rather than a second form), `PatientSummaryCard.vue` (shared by the
  search results and edit-mode display), `DentistSelect.vue`, `AppointmentTypeSelect.vue` (color swatch,
  still resolves a since-deactivated type on an existing appointment), `DurationInput.vue`, `SlotPicker.vue`
  (candidate slots computed from working hours, cross-referenced against `GET /available-slots`),
  `ConflictAlert.vue` (hard-stop `dentist_conflict` vs. soft/overridable `patient_conflict` /
  `outside_working_hours`, per §3.8).
- **Wired into the Board**: `AppointmentsView.vue`'s "New Appointment" button and clicking an empty calendar
  slot both open the dialog now (previously a "coming soon" toast); `CalendarFilters.vue` gained the Patient
  filter (deferred from Step 2/3 pending `PatientSearchSelect.vue`).
- Full Vitest coverage for every new component plus the create/conflict/override paths (150+ new assertions
  across the module); `vue-tsc`, ESLint, and Prettier all clean.

### Fixed — real bugs found via this step's mandatory real-browser verification
- **Date-time silently shifted by the browser's OS timezone** (the most significant finding): the dialog
  built `start_at` with `.toISOString()`, and the Board (`AppointmentCalendar.vue`) rendered `start_at`/
  `end_at` under FullCalendar's default `timeZone: 'local'`. DentalSuite is a single-clinic system with no
  real per-request timezone conversion (`config/app.php`'s `timezone` is `UTC` used as a neutral baseline,
  not a real UTC boundary) — every stored digit already **is** the clinic's own wall-clock time. Confirmed
  directly: booking "10:00" from a browser whose OS timezone wasn't UTC submitted `07:00:00.000Z`, and an
  existing 10:00 appointment rendered on the Board at 1:00 PM. Fixed by extending the project's existing
  `parseLocalDate`/`toLocalDateString` convention (already used for date-only fields) to date-*time* values:
  new `frontend/src/lib/date.ts` helpers `toLocalDateTimeString`/`parseServerDateTime`, used by
  `AppointmentDialog.vue` and `AppointmentsView.vue`'s slot-click prefill; `AppointmentCalendar.vue` now
  sets FullCalendar's `timeZone: 'UTC'` explicitly. Regression-tested (`lib/date.test.ts`, new
  `AppointmentCalendar.test.ts` assertion).
- **PrimeVue `MultiSelect` rendered its placeholder twice** (e.g. "DentistDentist") for the ~1-2s a
  `:loading` prop stayed `true` before its `options` populated — a real PrimeVue 4.5.5 rendering quirk
  specific to `display="chip"` + `loading` + empty `modelValue`/`options` all being true at once. Fixed by
  no longer passing `:loading` to `CalendarFilters.vue`'s Dentist/Type filters (never required by design
  doc §1.7; the Status filter already had no `loading` prop and never showed the bug).
- **Concurrent `fetchAll()` calls raced into duplicate network requests**: `providers.ts`/
  `appointmentTypes.ts` had no in-flight-request guard, so every consumer mounting at once (the Board, the
  filters, and now `DentistSelect`/`AppointmentTypeSelect` inside the new dialog) each fired their own `GET
  /api/users`/`GET /api/appointment-types`. Fixed with a shared in-flight-promise guard in both stores;
  regression tests added.
- **Buttons without an explicit `type` inside a `<form>` default to `type="submit"`**: clicking "Cancel", a
  patient search result's "Change" button, or `ConflictAlert`'s "Book Anyway" inside `AppointmentDialog`'s
  form additionally triggered a native form submission alongside the button's own `@click` handler (caught
  by a real double-`POST` in browser verification, not by unit tests, which don't exercise native form
  submission). Fixed by adding `type="button"` to every non-submit button inside the form, including
  `PatientFormDialog.vue`'s pre-existing Cancel button (same latent bug, same fix).
- **`ToggleSwitch`'s label text wasn't clickable** — "Show available slots" only responded to clicks on the
  switch itself, not the adjacent text (the native `<label>`/control association PrimeVue's own markup
  supports was never wired up). Fixed by wrapping both in a `<label>`.
- **PHP-FPM's pool (`pm.max_children = 5`, the base `php:8.4-fpm-alpine` image's default) saturates under
  a single page load's normal concurrency** — the Board alone fires 4-5 concurrent requests on mount, and
  opening the dialog adds more; requests past the limit queue or briefly appear to hang rather than the app
  actually being broken (confirmed via `pm.max_children` warnings in the container logs and Postgres
  showing no stuck queries). `docker/php/www.conf` now sets a larger local-dev pool
  (`max_children=20`), copied into the image by `docker/php/Dockerfile`. Local-dev tuning only, not a
  production sizing decision.
- Verified manually against the real dev stack (Docker/Postgres): full create flow (existing-patient search
  and inline patient creation, dentist/type/duration, calendar-driven date-time + available slots, notes),
  the hard-stop `dentist_conflict` banner, the overridable `outside_working_hours` banner and its "Book
  Anyway" resubmission, all across Arabic/English × light/dark. No remaining visual issues found.

### Fixed — project-wide datetime audit (requested before Step 4 sign-off, not narrower Appointments-only follow-up)
Before approving Step 4, the datetime fix above was required to be verified as an explicit, project-wide
policy rather than a local patch — see `docs/decisions.md`'s "Project-wide datetime policy" entry for the
full verified audit (database column types, Laravel serialization, every API Resource, every frontend call
site). That audit found and fixed real gaps the original Step 4 pass missed:
- **Board day-navigation landed on the wrong day, every day, for any positive-UTC-offset browser** — not
  the "few hours near midnight" edge case originally logged in `TECH_DEBT.md`. Once `AppointmentCalendar.vue`
  set `timeZone: 'UTC'` (fixing event rendering), its `gotoDate()`/`initialDate` calls still received
  `calendar.ts`'s genuinely-local `currentDate` unconverted. Confirmed directly in a real browser (clicking
  "Today" showed Thursday instead of the real Friday). Fixed with a new `toCalendarUtcDate` helper
  (`lib/date.ts`), the inverse of `parseServerDateTime`; regression-tested.
- **`SlotPicker.vue` silently showed "no available slots" for any dentist not currently selected in the
  Board's own Dentist filter** — it read `workingHours.byDentist`, a store only ever populated as a side
  effect of that unrelated filter, never by the dialog's own dentist selection. Fixed by having `SlotPicker`
  fetch working hours for its own `dentistId` itself; regression-tested.
- **List view's Date/Time column, the Board's `filteredAppointments` range filtering/cache eviction, and
  `PatientDetailView.vue`'s audit-log timestamp column** (this last one a pre-existing bug in the Patients
  module, predating Appointments entirely) all read a raw `new Date(apiValue)` instead of
  `parseServerDateTime` — same silent-shift bug as the original finding, just not yet swept into the fix.
  All four switched to `parseServerDateTime`.
- Whole-tree grep for every remaining date-construction/formatting call site as the closing check — no
  further gaps found. `lib/date.ts`'s four helpers are the sole approach in force project-wide going
  forward, not an Appointments-module convention.
- 160/160 Vitest passing, `vue-tsc` clean; re-verified manually against the real dev stack (day-alignment,
  slot-matching for a dentist not in the Board filter, full create/conflict flows all still correct).

### Added — Appointments (List View, Phase 2 Step 3)
- **`AppointmentListTable.vue`**: client-paginated (`:rows="20"`, no server round-trip — `GET
  /api/appointments` has no server-side pagination to hook into, per the backend design) DataTable, sortable
  by Date & Time, columns for Patient/Dentist/Type (color swatch)/Duration/Status
  (`AppointmentStatusChip`). Renders whatever range/filters the parent already fetched — fetches nothing
  itself, matching the documented `appointments`/`loading` props + `row-click` emit contract.
- **List toggle**: `CalendarToolbar.vue`'s view switcher gained a fourth "List" option alongside Day/Week/
  Month. `AppointmentsView.vue` now toggles between `AppointmentCalendar` and `AppointmentListTable` on the
  same shared `calendar.ts` filter/range state, so switching views never loses context.
- Added a view-agnostic range-fetch watcher in `AppointmentsView.vue`: the Board's own range-change signal
  (FullCalendar's `datesSet`) only fires while `AppointmentCalendar` is mounted, so prev/next/today
  navigation while the List view is active would otherwise never fetch the new range. `appointments.ts`'s
  `fetchRange` already no-ops on an already-cached range, so this and the Board's own trigger never cause a
  duplicate request.
- Verified manually against the real dev stack (Docker/Postgres) in Arabic/English × light/dark, including
  the List↔Board toggle and prev/next navigation while List is active — no bugs found this pass.

### Added — Appointments (Calendar Board, Phase 2 Step 2)
- **Design doc revised** (`docs/modules/appointments-ui-design.md`) against the current codebase before any
  component was built: corrected several stale assumptions (test tooling already installed, route guards now
  exist, nav already shipped as sidebar children not in-page tabs), resolved the Audit History open item
  definitively (no backend route exists — `FutureFeaturePlaceholder` used instead, `Auditable` trait already
  captures the data), and added a new §20 Implementation Sequence.
- **Docs synced before implementation, per the two-phase workflow**: `docs/architecture.md` (audit-log
  status corrected, `services/` layer documented, 409 error shape added), `docs/roadmap.md` (Appointments
  status updated from "Up next"), `TECH_DEBT.md` (new entry for the missing Appointments audit-log route).
- **Route guards applied**: `meta: { roles: ['admin'] }` on `/appointments/types`, `meta: { roles: ['admin',
  'dentist'] }` on `/appointments/schedule`, matching the `/users` precedent — router tests added for both.
- **`@fullcalendar/{core,vue3,daygrid,timegrid,interaction}` installed** (MIT, license-verified by reading
  each package's actual tarball, not just the registry field) — covers Day/Week/Month/List in full.
  `@fullcalendar/resource`/`resource-timegrid` (needed only for the Dentists resource-column view) turned
  out to be FullCalendar Premium (paid/non-commercial/GPLv3), not MIT as the original draft assumed — **not
  installed**; the Dentists view is deferred, decided with the user rather than assumed.
- **New components** (`frontend/src/components/appointments/`): `AppointmentCalendar.vue` (presentational
  FullCalendar wrapper, §2.12), `AppointmentEventContent.vue`, `AppointmentStatusChip.vue`,
  `CalendarToolbar.vue` (Day/Week/Month — List deferred to the next step), `CalendarFilters.vue`
  (Dentist/Status/Type — Patient filter deferred until `PatientSearchSelect.vue` exists). New
  `frontend/src/lib/color.ts` (WCAG luminance-based contrast helper for clinic-picked event colors).
  `AppointmentsView.vue`'s Board is now wired to real stores/data instead of the placeholder card.
- **Three real bugs found and fixed via manual browser verification against the real dev stack** (not
  caught by unit tests, which mock at the service boundary): `appointmentsApi.list()` assumed a `{data:
  [...]}` paginated envelope, but `GET /api/appointments` deliberately returns a bare array — its own unit
  test had mocked the wrong shape, masking the crash; FullCalendar's `eventSources: []` was silently
  suppressing the separately-passed `events` array (merged into one array instead); `initialView`/
  `initialDate` are FullCalendar "write-once" options — the toolbar's Day/Week/Month switch and prev/next/
  today now call the imperative `changeView()`/`gotoDate()` API instead.
- **RTL and dark-mode gaps closed**: FullCalendar's `direction` now follows the active locale (grid was
  staying LTR while the rest of the page mirrored); FullCalendar's own CSS variables now respect `.dark`
  (header/day cells were staying white).
- Fixed a pre-existing timezone-fragile test in `calendar.test.ts` (`.toISOString()` date comparison broke
  in positive-UTC-offset timezones) using the project's existing `toLocalDateString()` helper.
- Verified manually (headless-Chromium screenshots against the real `docker compose` stack, with a real
  appointment created via `AppointmentService::create()`) across Arabic/English × light/dark × Day/Week/
  Month views. `npm run build`, `vue-tsc`, `eslint`, `prettier`, and `vitest` (114/114) all pass.

### Added — Design System / Typography & Visual Polish
- Formalized the application shell's visual language into a documented design system
  (`docs/design-system.md`) — the shell is now considered **frozen** for all future modules.
- **Typography**: Inter (Latin) + IBM Plex Sans Arabic (Arabic, the app's default locale), loaded via Google
  Fonts (`index.html`), swapped by the existing `[dir]`-driven locale mechanism — no new JS. Replaces the
  previous generic system-font stack (`'Segoe UI'`/unsourced `'Cairo'` reference that had no actual font
  file behind it). `index.html`'s initial `lang`/`dir` now matches the default `ar` locale (no
  flash-of-wrong-direction before Vue mounts); `<title>` fixed from the Vite scaffold default `"frontend"`
  to `"DentalSuite"`.
- **Design tokens**: border-radius nudged via a PrimeVue Aura preset extension (`main.ts`'s
  `definePreset(Aura, ...)`: `md` 6px→8px, `xl` 12px→16px), `tabular-nums` utility for numeric/tabular data.
- **Visual polish**: sidebar active-item accent bar (`AppSidebarItem.vue`), dashboard stat cards upgraded
  from bare icons to tinted circular icon badges with a hover-lift shadow, sticky header gains `shadow-sm`,
  sidebar widened 256px→288px (`w-64`→`w-72`) to stop "Treatment Plans" truncating to "Treatment …".
- **Base CSS reset fix**: `style.css` imported Tailwind's `theme.css`/`utilities.css` but never
  `preflight.css`, so raw (non-PrimeVue) elements had no `box-sizing: border-box` and native `<button>`
  chrome (`border: 2px outset`) showed through on hand-rolled buttons like the sidebar's parent-toggle row.
  Fixed with a minimal, explicitly-scoped reset inside the `tailwind-base` cascade layer (not a full
  preflight import, so PrimeVue's and Tailwind utilities' own styling still wins where intended).
- **Bug fix**: a stray focus ring could stick to the sidebar's "Appointments" toggle immediately after
  login, because Vue's DOM patching reused the login submit `<button>`'s node across the Login→Dashboard
  route swap, carrying the browser's focus with it. Fixed with an explicit `blur()` in `LoginView.vue`
  after a successful login.
- **Bug fix**: `NotFoundView.vue` (404) was missing dark-mode text-color classes and hardcoded English text
  with no i18n, unlike the equivalent `ForbiddenView.vue` (403) it should mirror. Brought to parity; new
  `errors.pageNotFound.*` i18n keys added to `en`/`ar`/`tr`.
- Verified manually (headless-Chromium screenshots against the real `docker compose` stack) across
  Arabic/English × light/dark × desktop/tablet/mobile. `npm run build`, `vue-tsc`, `eslint`, and `vitest`
  (88/88) all pass.

### Added — Application Shell / Layout Architecture
- Replaced the informal top-nav `DefaultLayout.vue` with a permanent sidebar+header SaaS shell
  (`docs/modules/layout-architecture.md`, design approved 2026-07-16). New components under
  `frontend/src/components/layout/`: `AppSidebar.vue` (desktop-docked, collapsible icon rail, and — via a
  `variant: 'desktop' | 'drawer'` prop — reused unmodified inside the mobile PrimeVue `Drawer`, so nav markup
  and role-filtering logic are never duplicated), `AppHeader.vue` (hamburger on mobile, notifications
  popover, locale/theme toggles moved from the old header, user menu with logout), `AppSidebarItem.vue`
  (single nav row: active/disabled/coming-soon states, one level of expandable children).
- `frontend/src/config/navigation.ts` — configuration-driven single source of truth for the sidebar. Each
  entry declares `labelKey`/`icon`/optional `routeName`/optional `roles`/optional `comingSoon`. Unbuilt
  modules (Dental Chart, Treatment Plans, Billing, Reports, Settings) render visible but disabled with a
  "Soon" badge — no placeholder routes or fake pages. Adding a real module later is a two-line change.
- `stores/ui.ts` gained `sidebarCollapsed` (persisted to `localStorage`, same pattern as theme/locale) and
  `mobileSidebarOpen` + open/close actions for the mobile drawer.
- **Route-level authorization**, closing a pre-existing gap where hiding a nav item was the only thing
  stopping a non-admin from reaching `/users` by URL: `router/index.ts` adds a `RouteMeta.roles?: UserRole[]`
  module augmentation, `users` now carries `meta: { roles: ['admin'] }`, and `router.beforeEach` redirects to
  a new `forbidden` route when the authenticated user's role isn't allowed — centralized in the router, not
  duplicated per-view.
- `frontend/src/views/ForbiddenView.vue` (403) — new route `forbidden`.
- Full RTL support (logical Tailwind utilities throughout; the mobile `Drawer`'s slide edge follows locale
  direction) and dark-mode parity, verified in `ar`/`en` and both themes.
- `nav.*`/`common.*`/`errors.forbidden.*` i18n keys added to `en`/`ar`/`tr` (parity-verified).
- First round of **component-level tests** for the project (previously store/service-only): 24 new tests
  across `AppSidebarItem`, `AppSidebar` (role-based visibility, coming-soon rendering, mobile drawer close),
  `AppHeader` (hamburger opens the drawer), `stores/ui.ts`, and the router's role guard. `src/test/setup.ts`
  now globally registers the PrimeVue plugin, the `Tooltip` directive, and a `matchMedia` polyfill (jsdom
  doesn't implement it) — permanent test infra, not just for this module.

### Added — Appointments (Frontend Infrastructure, Phase 2 Step 1)
- `frontend/src/types/appointment.ts` — `Appointment`/`AppointmentType`/`DentistWorkingHour`/`DentistTimeOff`/conflict-error/payload types, matching every backend Resource/Request shape field-for-field (no `any`).
- New **API Services layer** (`frontend/src/services/appointments/`): `appointmentsApi`, `appointmentTypesApi`, `workingHoursApi`, `timeOffApi`, `providersApi`, plus `errors.ts` normalizing 409/422 `code` responses into a typed `AppointmentConflictError` (`docs/modules/appointments-ui-design.md` §11.1/§17) — the layer between the new Pinia stores and `lib/api.ts`.
- Six new Pinia stores: `appointments` (range cache with interval merge/eviction, post-mutation rehydration), `appointmentTypes`, `workingHours`, `timeOff` (per-dentist caches), `calendar` (pure UI state — view mode/date/filters, drives `appointments.fetchRange`), `providers` (temporary dentist-list workaround, explicitly documented as such per §10.2).
- `auth.ts` gained `isDentist`/`isReceptionist`/`canManageAppointments` getters.
- Routes + minimal placeholder views wired for `/appointments`, `/appointments/:id`, `/appointments/types`, `/appointments/schedule`; `nav.appointments` link added to `DefaultLayout.vue` (the i18n key already existed, unused until now). Static routes ordered before the `:id` wildcard.
- `appointments.*` i18n namespace added to `en`/`ar`/`tr` (parity-verified — 111 leaf keys each).
- **New permanent frontend toolchain**: Vitest + `@vue/test-utils` + jsdom + `@vitest/coverage-v8` (`vitest.config.ts`, kept separate from `vite.config.ts` — merging them hit a type conflict between this project's rolldown-based `vite` and the (different) `vite` Vitest bundles internally); ESLint (flat config, `eslint-plugin-vue` + `@vue/eslint-config-typescript` + `@vue/eslint-config-prettier`) and Prettier, matching the existing code style exactly. `npm run test`/`test:coverage`/`lint`/`format` scripts added.
- 64 new frontend tests (10 service/store files) covering the range-cache/eviction logic, post-mutation rehydration, conflict-error propagation, per-dentist caching, the provider pagination workaround, and the new auth getters.

### Added — Demo Environment (system validation checkpoint)
- `backend/database/seeders/AppointmentTypeSeeder.php` — 6 named default types (Consultation, Cleaning, Filling, Root Canal, Crown, Extraction) with realistic durations/colors, `firstOrCreate`-based so it's safe to re-run. Closes the gap where `GET /api/appointment-types` returned `[]` on a fresh install.
- `DatabaseSeeder` now seeds three clearly-named demo accounts (`admin@example.com`, `dentist@example.com`, `receptionist@example.com`, password `password`) plus 8 demo patients, replacing the single generic `test@example.com` admin account.
- `docs/demo-guide.md` — how to start the stack, login credentials, available screens, a recommended walkthrough, and the exact sample data seeded.
- Repository git history initialized (previously uncommitted since project inception); root `.gitignore` hardened for backend/frontend dependency and secret exclusions.

### Added — Appointments (API Layer)
- `AppointmentController`: `GET/POST /api/appointments`, `GET/PUT/DELETE /api/appointments/{id}`, plus six dedicated transition endpoints (`confirm`, `check-in`, `start`, `complete`, `cancel`, `no-show`) — no generic `/status` endpoint, matching the originally-approved design. `PUT /api/appointments/{id}` handles both plain edits and in-place reschedules; there's no separate reschedule endpoint. `GET /api/available-slots` is a top-level route.
- `AppointmentTypeController` (admin-only CRUD, any-role reads), `DentistWorkingHourController` (admin-only self-service), `DentistTimeOffController` (admin-any + dentist-own self-service) under `GET/POST/DELETE /api/dentists/{user}/working-hours` and `/time-off`.
- New `409`/`code`/`overridable` error shape for domain conflicts (`DentistConflictException` 409 hard block, `PatientConflictException` 409 soft/overridable, `OutsideWorkingHoursException`/`EarlyNoShowException`/`InvalidStatusTransitionException` 422) — each exception renders its own JSON response, documented in `docs/api-guidelines.md`.
- `AppointmentPolicy` gained `confirm`/`checkIn`/`start`/`complete` abilities, including the `start`/`complete` dentist-ownership IDOR check.
- `AppointmentService` gained `search()` (date-range-bounded list, not paginated) and `delete()`; new thin `AppointmentTypeService`, `DentistWorkingHourService`, `DentistTimeOffService`.
- 61 new Feature tests (`AppointmentTest`, `AppointmentTypeTest`, `DentistWorkingHourTest`, `DentistTimeOffTest`) — full suite now 188/188.

### Added — Patients
- Full CRUD for patient records (`/api/patients`): demographics, national ID, emergency contact, blood type, allergies, medical history, insurance info.
- Auto-generated human-readable `patient_code` (`P-00001`, ...).
- `GET /api/patients/{id}/audit-logs` (admin only) — who created/changed a patient record and what changed.
- `PatientsView.vue` (list, search, create/edit), `PatientDetailView.vue` (full record + audit history panel).
- Dashboard's `total_patients` stat now reflects real data (the counting logic already existed, waiting for this table).

### Added — Audit Logging (generic infrastructure)
- `audit_logs` table + `Auditable` trait + `AuditObserver` + `AuditLogService` — any model can opt in with `use Auditable;`. First adopter: `Patient`.

### Fixed
- Guest requests to protected `/api/*` endpoints without an `Accept: application/json` header (e.g. plain `curl`, misconfigured clients) crashed with a 500 instead of a 401 — Laravel's default guest-redirect targeted a non-existent `login` route. Fixed in `bootstrap/app.php`. Affected all protected endpoints (Users, Dashboard, Patients), not just this module.
- `storage/logs/laravel.log` was unwritable by the php-fpm worker due to root-owned bind-mounted volumes, so no exception had ever been logged. Fixed in `docker/php/entrypoint.sh`.
- **Patient/User search was case-sensitive on Postgres** (SQLite's `LIKE` is case-insensitive by default, masking this in the test suite) — a search for "layla" would not match "Layla" in the real database. Fixed using Laravel's cross-database `whereLike()`/`orWhereLike()` in both `PatientService` and `UserService`.
- Patient search had no usable database index for its leading-wildcard `LIKE '%term%'` queries — added `pg_trgm` GIN indexes (Postgres-only).
- Patient form: silent failure on non-422 save errors (no toast shown) — now consistent with the Users form.
- Patient form: date-of-birth could be off by a day in negative-UTC-offset timezones due to `Date`'s UTC-based ISO-string parsing — fixed with local-date-safe helpers.

### Changed
- Installed and configured Larastan (PHPStan for Laravel) at level 5, per `PROJECT_CONTEXT.md`'s coding standards requirement. Fixed all 13 issues it found (traced to one root cause: `parseModelCastsMethod` needed enabling for Laravel 11+'s method-based `casts()` to be inferred correctly — see `docs/decisions.md`).

### Documentation
- Established root-level `docs/` structure (`architecture.md`, `database-design.md`, `api-guidelines.md`, `coding-standards.md`, `decisions.md`, `roadmap.md`, `deployment.md`, `modules/`), plus `CHANGELOG.md` and `TECH_DEBT.md`. Migrated existing module docs from `backend/docs/modules/`.
- `docs/modules/patients.md`, including a Final Review section.

## 2026-07-11

### Added — Dashboard
- `GET /api/dashboard/summary` returning clinic-wide stats, gracefully defaulting to zero for tables that don't exist yet.

### Added — Authentication
- Sanctum SPA (cookie/session) authentication: `GET /sanctum/csrf-cookie`, `POST /api/login`, `POST /api/logout`, `GET /api/user`.
- Rate limiting on login (5 attempts/60s per email+IP).
- `users`/`sessions` primary keys converted to UUID.

### Added — Users
- Full CRUD for staff accounts (`/api/users`), search, soft delete, self-delete protection.
- `UsersView.vue` (PrimeVue DataTable + Dialog CRUD).

### Added — Roles & Permissions
- `UserRole` backed enum (`admin`, `dentist`, `receptionist`).
- `UserPolicy`: `create`/`update`/`delete` restricted to `admin`; `viewAny`/`view` open to any authenticated user.
