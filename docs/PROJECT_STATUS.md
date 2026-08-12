# DentalSuite — Project Status Report & Engineering Journal (Living Document)

> **This file is the single source of truth for "what is the current state of DentalSuite," and the
> entry point into the rest of the project's documentation.** It is not a one-time snapshot and not a
> report you regenerate from scratch — it is a running journal, updated every time something material
> happens (a module starts or finishes, a PR merges, an architecture/DB/roadmap decision is made, a UI
> change ships, a feature is added/removed, a bug is found or fixed, a dev phase ends, a real Technical
> Debt item is discovered or resolved, or the project's CI/production-readiness posture changes).
> Anyone — human or Claude — should be able to read this file alone and, within 5 minutes, know: where
> the project stands, what's done, what's left, why current decisions were made, what to do next, what's
> currently broken, what the risks are, and what changed most recently. It deliberately does **not**
> duplicate `CHANGELOG.md`/`TECH_DEBT.md`/`docs/decisions.md`/`docs/roadmap.md` in full — it summarizes
> and points to them. See **§15 "How to keep this file alive"** at the very end — read it, and follow it
> without being asked, before editing this file or considering any task finished.

| | |
|---|---|
| **Last updated** | 2026-08-12 |
| **Updated by** | Claude Code — Phase 1 release verification + close-out (PR #16/#17), Phase 2 (Patient Profile Redesign) design approval, sub-phases 2.1-2.5 (Foundation/Billing/Medical History/Laboratory/Documents) all designed, implemented, and merged via PR #18-#28 — see §4's PR history table and §12's narrative for full per-phase detail. **Phase 2.6 (Timeline)**, the final sub-phase: design doc (`docs/modules/patient-timeline-redesign-design.md`) produced by auditing the actual codebase (confirmed zero event-driven infrastructure existed anywhere), all 7 §16 decisions approved by the user as recommended, merged via **PR #29** (`e8e2cba`); a real category-taxonomy conflict found before writing any code (verifying each candidate policy's `viewAny()` showed the approved `clinical` category wrongly grouped `TreatmentPlan`/`ClinicalNote`/Medical History despite different permission rules) was fixed and confirmed with the user, merged via **PR #30** (`47e0c59`); **2.6a (Foundation) implemented** on `feature/patient-profile-phase2-6a-timeline-foundation` — `PatientActivity` model, one generic event + one synchronous listener (Laravel's event system introduced to this codebase for the first time, confirmed genuinely auto-discovered end-to-end by a non-faked integration test), 24 dispatch call sites across all 9 candidate services, `PatientActivityPolicy`'s per-category policy-reuse mechanism, the `/activities` endpoint — Backend 1054/1054 tests green (Pint clean, PHPStan clean on every new file), **merged via PR #31** (`cb7b52d`), post-merge CI on `main` re-verified. **2.6b (UI) implemented** on `feature/patient-profile-phase2-6b-timeline-ui` — `ActivityTimeline.vue` (one embeddable component backing the new `timeline` tab, Billing's Payment History sub-section, and an Overview recent-activity preview, per the design doc's §9.4/§15.2 single-component requirement), a `patientActivities` Pinia store keyed by patient+category+page-size (a deliberate deviation from every sibling store, required because up to three `ActivityTimeline` instances mount simultaneously on one patient page — `PatientDetailView.vue`'s `Tabs` isn't `lazy`), "Load more" pagination, a category-filter chip row (built from scratch — the design doc's own citation of an existing mobile chip-row pattern turned out not to exist in this codebase, confirmed with the user before building it), i18n for all 3 locales, **merged via PR #32** (`70b6ba6`). Frontend: all 933 tests green (27 new, including the security-critical E2E assertion that a receptionist session never sees `clinical_notes`-category rows even when explicitly filtering for them), `vue-tsc`/ESLint/Prettier clean. Post-merge CI on `main` (which, unlike a PR-triggered run, actually executes the E2E job) caught two real bugs in `e2e/timeline.spec.ts` itself — a lab case number read before its async fetch completed, and an unscoped `getByText()` hitting a strict-mode violation because `PatientDetailView.vue`'s non-`lazy` `Tabs` keep the Overview preview's and the Timeline tab's `ActivityTimeline` instances mounted simultaneously — both fixed and **merged via PR #33** (`83c584b`); post-merge CI re-run, all three jobs (Backend/Frontend/E2E) green. **Phase 2 (Patient Profile Redesign) is now complete in full**, closed out via **PR #34**. **Phase 3 (Dashboard 2.0)** — the next phase in the 8-phase roadmap — design phase reconciled two competing candidates for "next" (the roadmap's own Phase 3 vs. the still-unfinished Premium Visual Redesign's own Dashboard-restyle plan) into one design doc, `docs/modules/dashboard-2.0-design.md`, approved by the user as "Functional 2.0": restyle (absorbing `frontend-visual-redesign-design.md` §6 in full, now marked superseded there) plus new data-backed widgets reusing existing `ReportService` methods. The design-phase audit found a real, pre-existing security gap — `/dashboard/summary` returned `monthly_revenue` to every role with zero authorization, the same figure `reports/collections` already restricts to admins — fixed as part of this phase, not a separate one; see `docs/decisions.md`'s 2026-08-09 entries for both that fix and the "trend, not goal" scope decision. **Implemented on `feature/dashboard-2-0`** in the 4 sequential steps the design doc's §8 specifies (backend → frontend data layer → frontend widgets/restyle → E2E/i18n/docs), each verified before the next: Backend 1069/1069 tests green (26 new), Pint clean, PHPStan clean (targeted run on new files, cross-checked against the same pre-existing local-only false-positive pattern `TECH_DEBT.md` already documents on `casts()`-style models). Frontend 966/966+ tests green (33 new: `dashboard.ts` store, `FinancialSnapshotWidget.vue`, `UnscheduledTreatmentWidget.vue`, `auth.canViewFinancials`), `vue-tsc`/ESLint/Prettier clean. Manually verified in a real browser (admin sees both new widgets with real seeded data — 118 patients, real revenue/production/A-R figures; dentist correctly never sees Financial Snapshot) since this session's local Windows Docker environment reproduced the same pre-existing per-HTTP-request latency this file's own §12 (Phase 2.6b) already documented — generous timeouts, not application bugs, were the fix. New `e2e/dashboard.spec.ts` (first for this module) written covering the security-critical case per this project's established §9A-style precedent — asserted against rendered DOM **and** that the network request itself is never made for a non-admin session **and** that direct access is server-side blocked (403) regardless of the UI. **PR #35 merged to `main` 2026-08-09** (`0f04b35`); post-merge CI (which, unlike the PR-triggered run, actually executes the E2E job) re-run and **fully green — Backend, Frontend, and E2E all `success`**, confirming `e2e/dashboard.spec.ts` genuinely passes, not just reviewed. **Phase 3 (Dashboard 2.0) is now complete.** **Phase 4 (Advanced Permissions & Audit)** — design phase: a ground-truth audit (direct full reads of all 27 Policy classes, the `Auditable` trait/`AuditObserver`/`AuditLogService`, and `AuthController`/`AuthService`) found two critical, previously-undocumented gaps — the `User` model itself is not audited (role/account changes leave no trail) and there is zero logging of authentication events (login success/failure, logout) — plus confirmed `Auditable` and `PatientActivity` are intentionally separate systems, not a gap. User approved three scope decisions: fine-grained permissions layered on the current 3 roles (not a full role-hierarchy rewrite — that stays flagged, not decided, see `docs/decisions.md`), a full audit-trail overhaul closing both gaps, and simple immutability now with retention explicitly deferred. Full design doc: `docs/modules/phase4-permissions-audit-design.md`. **Implementing on `feature/phase4-permissions-foundation`**, per the design doc's §14 5-step sequence, each verified before the next: **Step 1 (Permissions Foundation)** — a 68-entry permission catalog derived 1:1 from a full line-by-line read of all 27 Policies (methods sharing an identical role-set within one Policy collapse into one key), the `permissions`/`role_permissions` tables, `User::hasPermission()` with a per-role cache, 3 new admin-only endpoints gated by a hardcoded `manage-permissions` Gate (never routed through the matrix itself, making self-lockout structurally impossible, not just validated-against), and a server-side rule protecting `users.manage` from ever being revoked from Admin. Catalog verified two ways: the seeded per-role grant counts (admin=68, dentist=36, receptionist=37) matched an independent manual derivation exactly. **Step 2 (Policy refactor)** — all 27 Policies converted from raw `UserRole` comparisons to `hasPermission()` calls, with every identity/ownership/target-role check (e.g. `Appointment::start()`'s `$actor->is($appointment->dentist)`, `DentistTimeOff`'s target-role validation, `User::delete()`'s self-delete block) preserved verbatim; `Tests\TestCase` now seeds the permission catalog automatically for every `RefreshDatabase` test class; 15 new baseline Policy tests added for the previously-untested policies (35 new tests). **Backend 1121/1121 tests green** (1086 + 35 new), **zero regressions** across every pre-existing Feature/Policy test — the strongest available evidence the refactor is behavior-preserving. Pint clean (524 files); targeted PHPStan clean on all 27 Policies + 15 new test files (3 pre-existing local-only false positives surfaced on untouched identity-check lines, confirmed unrelated). **Step 3 (Audit Overhaul), implemented same day**: closes both critical gaps the design-phase audit
found — `User` made `Auditable`; `AuditObserver` now captures real before/after diffs (`old_values`, not just
new values) and a full pre-deletion snapshot on delete; `AuthService` logs `login_succeeded`/`login_failed`/
`logged_out` (attempted email only on a failed login, never the password); `RolePermissionService::updateMatrix()`
audits its own before/after matrix; an Eloquent-level immutability guard on `AuditLog`; a new general
(non-patient-scoped) `GET /audit-logs` viewer; `bootstrap/app.php` gained `trustProxies(at: '*')` (a real gap
closed — without it, captured IPs would silently have been the reverse proxy's own address in production).
Backend 1145/1145 tests green (24 new), zero regressions. **Step 4 (Frontend), implemented same day** on
`feature/phase4-permissions-foundation`: new admin-only `PermissionsView.vue` (the role×permission matrix —
68 catalog entries grouped by module, toggle switches, explicit Save/Discard over a local draft, the
`users.manage`/Admin cell rendered disabled/checked to match `UpdateRolePermissionsRequest`'s server-side
self-lockout rule instead of letting the UI suggest something the API will reject; a stacked per-role
Accordion replaces the matrix table on narrow viewports, per the design doc's own §10 mobile treatment) and
`AuditLogsView.vue` (the general `GET /audit-logs` viewer — user/resource-type/action/date-range filters,
server-paginated table, per-row old/new diff expansion, a `config/auditableTypes.ts` map translating every
raw backend FQCN/action value before it ever reaches the UI). New `permissions.ts`/`auditLogs.ts` stores,
admin-only Sidebar entries + routes, ~300 new i18n keys across all 3 locales (3-locale key-set parity
verified programmatically: 1453 keys in each of `en`/`ar`/`tr`, zero missing either way). Frontend
969/969 tests green (20 new), `vue-tsc`/ESLint/Prettier clean. Manually verified in a real browser against
real seeded audit-trail data: the full matrix and a live Audit Log both render correctly in English, Arabic
(RTL), and at a mobile viewport; a dentist session has neither Sidebar entry and gets the app's standard 403
page on direct navigation to either route. **Step 5 (E2E + final docs), implemented same day**: new
`role-permissions.spec.ts` (the Admin/`users.manage` self-lockout cell stays disabled+checked in a real
browser; an admin toggling a permission off persists across reload, takes effect for a live receptionist
session — the New Patient button disappears — and is audited as `role_permissions_updated` with a real
before/after diff, verified by filtering the Audit Log and expanding the row; restores the shared global
matrix afterward via a direct API call so no other E2E spec is affected; non-admin gets 403 on
`GET /permissions`, `GET`/`PUT /role-permissions`) and `audit-log.spec.ts` (filtering by action, a row
expanding to show no field diff, a failed login audited with the attempted email as both its Actor and in
its `context`, non-admin 403 on `GET /audit-logs`); `permissions.spec.ts` gained admin-can-reach-both-screens
and non-admin-blocked-with-no-sidebar-entry cases (its existing, unmodified pre-Phase-4 tests already double
as the design doc's required "receptionist retains today's effective permissions after the Policy refactor"
proof, since they still pass unchanged). Iterating locally surfaced and fixed 4 real bugs in the tests
themselves (a PUT missing its XSRF header returning 419 instead of 403; a click flaking under this
environment's per-request latency; a locale-forcing gap; a wrong expected error string) plus a real
fetch-ordering race between the page's initial unfiltered load and an immediately-applied filter — every
test has passed cleanly at least once locally, with remaining local flakiness matching this environment's
own already-documented per-request-latency pattern (§9), not application behavior. i18n parity re-verified
after Step 5 (still 1453/1453/1453, zero drift). `vue-tsc`/ESLint/Prettier clean. Pushed to
`origin/feature/phase4-permissions-foundation`, CI triggered via `workflow_dispatch` for the authoritative
Backend/Frontend/E2E signal, then a full 14-point final diff review (103 files, +5543/-215) against `main`
found zero blockers — two non-blocking notes recorded (`docs/decisions.md`'s `trustProxies` entry addendum;
`TECH_DEBT.md`'s new migration-rollback item). **PR #37 merged to `main` 2026-08-09** (`0bdf3d8`); post-merge
CI on `main` (which, unlike the PR-triggered run, actually executes the E2E job) re-run and **fully green —
Backend, Frontend, and E2E all `success`** (run `31333946387`). **Phase 4 (Advanced Permissions & Audit) is
now complete**, closed out via PR #38.

**Phase 5 (Notification System)** — design phase produced `docs/modules/notifications-design.md` from a
ground-truth audit that found no notification system existed anywhere (no `notifications` table, no
`app/Notifications`, no `app/Mail`, no `config/broadcasting.php`, no route) **but** that the project was far
closer than that suggested: Phase 2.6's `PatientActivityOccurred` already fires from 21 call sites across 9
services covering 24 event types, Phase 4's `PatientActivityPolicy` already established the category-filtering
security pattern, and `User` already carried Laravel's `Notifiable` trait. The same audit surfaced a real,
**previously-untracked** infrastructure hazard: `QUEUE_CONNECTION=redis` had been configured since the
project's first `.env`, yet **no `queue:work` process, Horizon, or supervisor existed in either compose
file**, and nothing was scheduled — so anything `ShouldQueue` would have been enqueued and silently never
run. User approved the design with decisions D1-D8 (Laravel's own notifications table + 4 additive columns;
partial unread index; `medical_history.allergy_added` deferred to Phase C; `lab_case.quality_checked`
excluded; read-only + prune, no dismiss endpoint; no permission catalog entry — self-scoped like My Account;
60s visibility-gated poll; **Phase A + Phase B in this cycle**).
**Implemented on `feature/phase5-notifications`.** **Phase A**: the `notifications` table (Laravel's stock
schema + `category`/`subject_*`/nullable `patient_id`, written by a ~20-line `DatabaseChannel` subclass bound
in `AppServiceProvider` — the same additive-columns move Phase 4 Step 3 made on `audit_logs`);
`SendsNotifications` as a **second listener on the existing event, so zero new dispatch call sites were
needed**; `NotificationRules` as an explicit allow-list where only 8 of the 24 live event types notify and
the other 16 stay silent by design; `RecipientResolver` as the single multi-tenant seam; three authorization
layers (structural ownership → another user's row 404s because it is never in scope; a read-time category
re-check on both list *and* count, closing the permission-drift case; and a send-time check); 5 endpoints;
a `NotificationBell`/`NotificationCenter`/`NotificationItem` frontend replacing the inert header bell
`TECH_DEBT.md` had tracked (popover on desktop, drawer under `md:`, badge capped at `9+`, 60s poll gated on
`document.visibilityState`); localization that stores **translation keys + raw params, never rendered text**,
so switching language re-renders existing rows correctly — 32 new keys × 3 locales, parity verified
programmatically at **1486/1486/1486, zero drift**. **Phase B**: `queue` and `scheduler` containers added to
both compose files, a `RUN_MIGRATIONS` guard in `docker/php/entrypoint.sh` so only `app` migrates rather than
three containers racing, `SendsNotifications` flipped to `ShouldQueue` **only after** a real job was observed
going `RUNNING` → `DONE` in `dentalsuite_queue`, `$afterCommit = true` (load-bearing — three dispatching
services fire the event inside `DB::transaction()`), and `Notification` made `MassPrunable` with the
project's first-ever scheduled task. Verification: Backend **1186/1186** green (41 new, zero regressions),
Frontend **1003/1003** green (34 new), Pint clean, `vue-tsc`/ESLint/Prettier clean, E2E types clean,
migration verified against real Postgres including the partial unread index. Phases C (scheduled/admin
notifications) and D (email) are designed but not started; Web Push is deferred to roadmap Phase 6 (it needs
the PWA/service-worker foundation that does not exist), and patient-facing SMS/WhatsApp reminders to their
own future module — the unwired `appointment_reminders` table was deliberately left untouched rather than
half-wired.
**Pre-PR review (same day) found and fixed 5 issues before either phase was ever opened as a PR** — 2 marked
SECURITY, treated as blockers rather than deferred: (1) the notifications Pinia store's `reset()` existed
but nothing called it on logout, so a same-tab login as a different user could render the previous session's
notifications — now wired into `auth.ts`'s `logout()`; (2) `PatientActivityOccurred`'s `readonly` `Model`
properties had no serialization contract, so the queued listener's Redis payload carried a dentist's
password hash/`remember_token` and (whenever a relation was preloaded) patient PHI — fixed with
`Illuminate\Queue\SerializesModels`, verified both by a new test asserting the serialized payload's content
and by a live run against the real `dentalsuite_queue`/Redis (payload inspected via `redis-cli` while the
worker was paused, confirmed clean, then observed going `RUNNING` → `DONE` with a real notification written);
(3) `POST /notifications/{id}/read` 500'd on a malformed id (Postgres's `uuid` column rejects non-UUID input
with an uncaught `QueryException`; SQLite never caught this) — fixed with `Route::whereUuid()`; (4)
`NotificationCenter`'s `onMounted` guard (`items.length === 0`) skipped every refetch after the very first
open, since `Popover`/`Drawer` remount the panel on every open but the Pinia store survives — guard removed,
now refetches every open; (5) i18n parity was documented as `1485/1485/1485`; re-running the check found the
real, already-correct count is `1486/1486/1486` — corrected in both this file and `CHANGELOG.md`. Full
before/after evidence and decision rationale: `docs/decisions.md`'s 2026-08-11 "readonly event properties"
entry and `CHANGELOG.md`'s "Fixed — Phase 5 pre-PR review findings" entry. Re-verified after the fixes:
Backend **1191/1191** green (+5, zero regressions), Frontend **1005/1005** green (+2, zero regressions).
**PR #39 opened to `main`.** CI caught two further real, pre-existing (Phase 5B) issues before any merge —
a Pint import-ordering violation and a genuine PHPStan generics mismatch in `Notification::prunable()` —
both fixed in follow-up commits on the same PR. Before merging, per the user's explicit request, ran a
pre-merge E2E gate: real `workflow_dispatch` CI on the branch (the E2E job skips on `pull_request` by
design), which found and fixed two more genuinely real, pre-existing bugs from the original Phase 5A/5B
implementation — a `notifications.spec.ts` fixture bug (wrong assumption about `/appointment-types`'s
unpaginated response shape) and a `ci.yml` gap (the E2E job never started a queue worker despite
`SendsNotifications` being `ShouldQueue` since Phase 5B). Confirmed along the way that the local `login()`
failure (`TECH_DEBT.md`) never reproduces on CI — genuinely environment-specific, not a masked regression.
Final pre-merge run: Backend/Frontend `pass`, **E2E 56/57 passed, 1 flaky-then-passed** (a notification-
lookup timing race against the queue worker, resolved by Playwright's own retry). **PR #39 merged to
`main` 2026-08-11** (merge commit `204194f`); **post-merge CI re-run and fully green — Backend, Frontend,
and the real E2E run all `success`** (run `31545464355`; 56/57 passed, the same single flaky-then-passed
test, confirming it is a transient timing flake, not deterministic). **Phase 5 (Notification System) is
now complete.** |
| **Repo HEAD at time of writing** | `main` (Phase 5: Notification System, Phases A + B, plus pre-PR review fixes and a pre-merge E2E gate — **merged via PR #39**, merge commit `204194f`). Post-merge CI on `main` fully green — Backend, Frontend, and the real E2E run all `success` (run `31545464355`, 56/57 passed, 1 flaky-then-passed) — see `docs/modules/notifications-design.md`. |
| **Milestone** | **Phase 1 — Foundation Complete** (2026-08-07) — baseline for all future development; see §2 for the verification this milestone rests on. **Phase 2.1-2.5 (Foundation/Billing/Medical History/Laboratory/Documents) all merged 2026-08-07/08 via PR #18/#20/#22/#24/#27.** **Phase 2.6 (Timeline) design-approved 2026-08-08; 2.6a (Foundation) merged 2026-08-09 via PR #31; 2.6b (UI) merged 2026-08-09 via PR #32** (E2E fix via PR #33) — see `docs/modules/patient-timeline-redesign-design.md`. **Milestone: "Phase 2 — Patient Profile Redesign Complete" (2026-08-09)**, closed out via PR #34 — all 7 sub-phases (2.1-2.6) merged to `main`, post-merge CI green including the real E2E run. **Milestone: "Phase 3 — Dashboard 2.0 Complete" (2026-08-09)**, closed out via **PR #35** (`0f04b35`) — post-merge CI on `main` fully green (Backend/Frontend/E2E) — see `docs/modules/dashboard-2.0-design.md`. **Milestone: "Phase 4 — Advanced Permissions & Audit Complete" (2026-08-09)**, closed out via **PR #37** (`0bdf3d8`) — post-merge CI on `main` fully green (Backend/Frontend/E2E, run `31333946387`) — see `docs/modules/phase4-permissions-audit-design.md`. **Milestone: "Phase 5 — Notification System Complete" (2026-08-11)**, closed out via **PR #39** (merge commit `204194f`) — a real in-app Notification Center plus the queue worker and scheduler that had never existed; pre-PR review fixed 5 further issues same day (2 marked SECURITY); a pre-merge `workflow_dispatch` E2E gate found and fixed 2 more genuinely real, pre-existing bugs before merging — post-merge CI on `main` fully green (Backend/Frontend/E2E, run `31545464355`, 56/57 E2E passed) — see `docs/modules/notifications-design.md`. **Phase 5C (scheduled/administrative notifications, types 9-13) implemented 2026-08-12** — design doc `docs/modules/notifications-phase-c-d-design.md`, full implementation account in its §19-20 — Backend 1211/1211 green; both verification gaps left open at implementation time (full frontend Vitest, real-browser/E2E) closed same day — full frontend Vitest 1003/1005 (2 pre-existing environment flakes, re-verified in isolation), real-browser/E2E closed via new CI-run `notifications.spec.ts` coverage rather than local Playwright (confirmed infeasible in this Alpine dev container). **PR #41 opened to `main`**; pre-merge `workflow_dispatch` gate fully green on the second attempt (run `31584698456` — Backend/Frontend/E2E all `success`, E2E 59/59; the first attempt, run `31583701772`, caught one real CI-only Pint violation, fixed in a follow-up commit). **Not yet merged — awaiting review.** Phase 5D (email) remains deferred to its own future cycle — see §12's "Immediate next step." |
| **Confidence** | High — sourced directly from `gh pr`/`gh run`/`git log` and this session's own CI runs, not carried forward from the prior version without verification. |

---

## 0. Known documentation staleness found while building this report — RESOLVED 2026-08-07

This file's first version (2026-08-04) found 3 real discrepancies in the older docs, from cross-checking
`PROJECT_CONTEXT.md`/`docs/roadmap.md`/`CHANGELOG.md` against actual `git log`/GitHub PR state. All three
are now closed, as part of Phase 1 (Stabilization)'s own close-out — this section is kept as a record of
what the drift looked like and how it happened, since the whole point of this file is to stop it recurring
silently:

1. **`CHANGELOG.md`'s "Unreleased" section was stale by 3 merged PRs** (PR #11-13, merged 2026-08-02, had no
   entries). **Fixed 2026-08-07**: entries added for PR #11-15.
2. **`docs/roadmap.md` and `PROJECT_CONTEXT.md` both said Reports and Settings were "not yet merged to
   `main`"** — false; both merged 2026-07-28/30. **Fixed 2026-08-07**: both docs corrected (`PROJECT_CONTEXT.md`
   via an appended status-update paragraph, matching its own established "don't rewrite history, append
   corrections" convention).
3. **CI on `main` was red** (E2E job failing since 2026-08-01, Backend/Frontend green) — root cause was
   already correctly diagnosed in `TECH_DEBT.md` but not yet fixed. **Fixed 2026-08-07** via PR #15 — see
   §2/§9/§12 for the full fix (it took 2 separate backend bugs, found one at a time as each unmasked the
   next, confirmed via 3 `workflow_dispatch` runs).

**Root cause of the drift itself**: all 3 PRs behind item 1, plus PR #14/#15, landed in a short, high-velocity
window (2026-08-01 → 2026-08-07) — exactly the kind of burst this file's own §15 protocol exists to keep
docs synchronized through, rather than after the fact.

---

## 1. Executive Summary

**DentalSuite** is a modern, professional Dental Clinic Management System, scoped deliberately to dental
clinics only (explicitly *not* a general Hospital Management System). Product philosophy: simple, fast,
modern, scalable, maintainable — enterprise-quality UX without unnecessary complexity, benchmarked
throughout development against real competitors (Open Dental, Dentrix, CareStack, etc.) and, for the
frontend-only redesign work, against modern SaaS products (Linear, Notion, Stripe, Vercel, Raycast).

**Goal**: give a single dental clinic a complete, self-hosted operational system — scheduling, clinical
charting/notes, treatment planning, billing/payments, inventory, lab case tracking, imaging, reporting, and
an optional AI assistant layer — without the bloat of a multi-specialty hospital suite.

**Technology stack** (confirmed from `backend/composer.json`/`frontend/package.json`, not assumed):

| Layer | Technology |
|---|---|
| Backend | Laravel `^12.0`, PHP `^8.2` (project docs specify PHP 8.4+ target; Docker image is `php:8.4-fpm-alpine`) |
| Frontend | Vue `^3.5`, TypeScript `~6.0`, PrimeVue `^4.5.5` (Aura preset), TailwindCSS `^4.3`, Vite `^8.1` |
| Database | PostgreSQL 17 (UUID primary keys everywhere, soft deletes, audit logs on sensitive models) |
| Cache/Queue | Redis |
| Storage | Local disk (V1 default) / S3-compatible (config-ready, not activated) |
| Auth | Laravel Sanctum, SPA cookie/session-based (not API tokens) |
| Testing | PHPUnit/Pest (backend), Vitest (frontend unit/component), Playwright (permanent E2E suite, `frontend/e2e/`) |
| CI/CD | GitHub Actions (`.github/workflows/ci.yml`) — Backend / Frontend / E2E jobs on every push/PR |
| Deployment | Docker Compose (dev), separate hardened `docker-compose.prod.yml` + nginx + SSL topology |
| i18n | Arabic (RTL, default), English (LTR), Turkish (LTR) — parity-checked on every module |
| AI | Anthropic Claude API, optional, decision-support only (see §7) |

**Current status**: **Implementation Phase, post-original-roadmap.** Every module on the original 17-module
roadmap (Dashboard → AI Assistant) is merged to `main`; 12 of them meet this project's own "Production
Ready ✅" bar (backend + frontend + full test suite + CI-confirmed permanent Playwright E2E spec). Three
modules (Treatment Plans, Billing, Payments) are functionally complete and merged but still lack a permanent
E2E suite, so they sit just below that bar (see §3, §9). Two post-roadmap initiatives are now running in
parallel: a frontend-only visual/UX redesign (mid-flight, see §6) and a new 8-phase product roadmap toward
SaaS readiness — **Phase 1 (Stabilization) closed 2026-08-07, Phase 2 (Patient Profile Redesign) closed
2026-08-09 via PR #34, Phase 3 (Dashboard 2.0) closed 2026-08-09 via PR #35, Phase 4 (Advanced Permissions
& Audit) closed 2026-08-09 via PR #37, Phase 5 (Notification System) closed 2026-08-11 via PR #39** —
`main`'s CI is **fully green** (Backend/Frontend/E2E, including a real E2E run — 56/57 passed, 1
flaky-then-passed) as of the Phase 5 post-merge run (see §2, §9, §12). Phase 5 shipped: a real in-app
Notification Center (Laravel's own notifications table extended with 4 additive columns, an 8-of-24
event-type allow-list riding the *existing* `PatientActivityOccurred` event so no new dispatch call sites
were needed, three authorization layers, keys-not-text localization across en/ar/tr), plus the queue worker
and scheduler containers that had never existed in this project despite `QUEUE_CONNECTION=redis` being
configured since day one — plus 5 pre-PR review fixes (2 marked SECURITY) and 2 more pre-merge E2E-gate
fixes, all merged. Notification Email, Web Push, and patient-facing reminders are designed and explicitly
deferred (see the Phase 5 section before §13, and `TECH_DEBT.md`). **Phase 5C (scheduled/administrative
notifications) implemented 2026-08-12** over roadmap Phase 6 (the user's chosen direction) — Backend
1211/1211 green, not yet merged; 3 real bugs found and fixed during implementation, see §12 and
`docs/modules/notifications-phase-c-d-design.md` §19. Phase 5D (email) remains deferred to its own future
cycle.

---

## 2. Timeline

Dates are sourced from `git log`, `ARCHITECTURE_REVIEW.md`, `CHANGELOG.md`, and `docs/decisions.md`.
The repository had **no git history at all** until 2026-07-16 (confirmed by `ARCHITECTURE_REVIEW.md`'s own
2026-07-11 note: "لا يوجد أي commit بعد" / "no commit exists yet") — everything before that date happened,
but wasn't committed until the 2026-07-16 checkpoint squashed it into initial history.

| Date | Milestone |
|---|---|
| 2026-07-11 | Architecture/stack approved (Laravel 12 + Vue 3 + PrimeVue + Postgres). Dashboard, Authentication, Users, Roles & Permissions built (pre-git). Sanctum SPA auth, UUID PKs, simple role enum decided with user. |
| 2026-07-14 | Patients module: standard clinical intake fields, generic `Auditable` audit-log infrastructure introduced, `patient_code` scheme, front-desk write / dentist read-only policy. Two infra bugs fixed (guest-request 500, unwritable log file). |
| 2026-07-15 | Pre-Appointments review: case-insensitive Postgres search fix, `pg_trgm` trigram indexes, Larastan (PHPStan) installed at level 5. |
| 2026-07-16 | **First git commit** (`9a74ffb`). Design system (Inter/IBM Plex Sans Arabic, later Alexandria for Arabic), ESLint/Prettier added, project-wide datetime policy established (`frontend/src/lib/date.ts` — later elevated to a hard Architecture Violation rule on 2026-07-17). Appointments frontend infrastructure begins. |
| 2026-07-16 → 2026-07-20 | Appointments module built end to end (Calendar Board, Appointment CRUD/lifecycle, Types, Dentist Working Hours/Time Off, Dashboard widgets, a11y/keyboard-shortcuts pass). |
| 2026-07-18 | System-Wide Production Gate (demo-account env guard, `app:create-admin`, API rate limiting, production Docker/nginx/SSL topology, backup/restore scripts, CI/CD pipeline). |
| 2026-07-20 | Production Gate closed (CI-confirmed via GitHub Actions API). **Appointments tagged `v1.0.0-appointments`** — Production Ready ✅. |
| 2026-07-22 | SaaS Architecture Checkpoint (multi-tenant readiness review — no blockers, V1 stays single-org by design). **Dental Chart merged to `main`** — Production Ready ✅. |
| 2026-07-23 | Treatment Plans design approved and implemented same window as Billing/Payments (`feature/treatment-plans`). |
| 2026-07-25 | **PR #1** merges Treatment Plans + Billing + Payments to `main`. **PR #2** same-day fixes a Payments concurrency race. Payments migration self-referencing-FK-on-Postgres bug found (masked by SQLite-only test DB — logged as open tech debt, see §9). |
| 2026-07-26 | **PR #3** merges Clinical Notes (SOAP notes, Draft→Signed lifecycle, append-only addendums) — first of the "implementation-complete" trio to also ship a permanent E2E suite. |
| 2026-07-27 | **PR #4** merges Inventory (immutable stock ledger, Purchase Order lifecycle). **PR #5** merges Laboratory (Lab vendor catalog, Lab Case lifecycle) same day. |
| 2026-07-28 | **PR #6** merges Imaging (per-patient diagnostic image gallery). **PR #7** merges Reports (6 live-query reports, CSV export). |
| 2026-07-30 | **PR #8** merges Settings (Practice Settings, Billing Settings, My Account self-service). |
| 2026-07-31 | **PR #9** merges AI Assistant (Dashboard Insights / Smart Search / Writing Reports enabled-eligible; Clinical Notes draft-assist / Treatment Suggestions built but disabled-by-default behind a BAA-acknowledgment gate). Original 17-module roadmap now fully merged to `main`. Frontend UX & Navigation Redesign initiative kicks off. |
| 2026-08-01 | **PR #10** merges Frontend Nav Shell Phase 1 (Sidebar redesign, Header breadcrumbs, Command Palette, keyboard shortcuts, a small approved `GET /invoices` backend exception). `DemoDataSeeder` (110 Arabic-named patients across every module's scenarios) merged same day — this later becomes the root cause of the still-open E2E regression on `main` (see §9, §12). |
| 2026-08-02 | **PR #11** merges AI Assistant Settings-managed Anthropic API key. **PR #12** merges Treatment Plans clinic-wide list (fixes the long-standing `comingSoon` sidebar placeholder). **PR #13** merges Premium Visual Redesign Steps 1–3 (design tokens, Sidebar section-grouping + Lucide icon migration begun, Header/Command Palette polish) — supersedes the original Phase 2 (Dashboard) plan and pulls pieces of Phase 4 forward as mandatory acceptance criteria. |
| 2026-08-02 | **PR #14** opened — docs-only: logs a real, newly-found `DashboardService.today_appointments` bug (unfiltered `COUNT(*)`, not scoped to today) and marks the Treatment Plans `comingSoon` tech-debt item resolved (by PR #12). |
| 2026-08-04 | This file's first version authored. `main`'s last 5 CI runs all E2E-red (Backend/Frontend green) — found and diagnosed, not yet fixed. Repo-root `CLAUDE.md` added as this file's auto-loaded enforcement mechanism. |
| 2026-08-06 → 2026-08-07 | **Phase 1: Stabilization** (first phase of a new 8-phase product roadmap: Stabilization → Patient Profile → Dashboard 2.0 → Permissions/Audit → SaaS Multi-Tenant → PWA/Mobile → AI Expansion → Launch Prep) implemented on `fix/stabilization-phase-1`. Fixed `appointments.spec.ts` (navigates by the created appointment's real id, not List view's first row) and, across 3 `workflow_dispatch` runs, two separate `reports.spec.ts` root causes found one at a time as each unmasked the next: `ReportService::collections()` had no `ORDER BY` (added most-recent-first + a `created_at` tie-break, since `received_at` is deliberately date-only), then `newPatients()` turned out to sort ascending instead of descending. Also fixed `DashboardService.today_appointments` (PR #14's finding) and a frontend loading/empty-state/error-handling consistency pass (4 stores standardized on the store-owns-error-state pattern, `InvoicesView.vue`'s previously-silent fetch failure closed). Final CI run: **Backend 932/932, Frontend 759/759, E2E 39/39 — zero failures.** |
| 2026-08-07 | **PR #15** merged (Phase 1 code + this file + `CLAUDE.md`), then **PR #14** merged. `main`'s CI fully green for the first time since 2026-08-01. `CHANGELOG.md`/`docs/roadmap.md`/`PROJECT_CONTEXT.md` staleness from §0 reconciled; `TECH_DEBT.md` updated with both resolutions. This file updated for Phase 1 close-out per its own §15 protocol. |
| 2026-08-07 | **PR #16** merged after an independent final release verification (Backend + Frontend CI re-confirmed green firsthand, not taken on faith; `git merge-tree` confirmed no conflicts with `main`; diff checked for introduced TODO/FIXME — none found). **Milestone: "Phase 1 — Foundation Complete."** Immediate next step: Phase 2 (Patient Profile Redesign) design phase — see §12. |
| 2026-08-07 | **Phase 2: Patient Profile Redesign — design phase begins.** Step 1 analysis (current hub + all 8 related modules + design system + permissions) approved by the user. Step 2 design doc written: `docs/modules/patient-profile-redesign-design.md` — merges Invoices+Payments into one Billing tab (backend stays split), structured Medical History (Allergies/Conditions/Medications/Notes), a V1 Documents foundation, and a dedicated `PatientActivity` event architecture for Timeline with per-category role-filtering as an explicit security requirement. No implementation code written yet — awaiting design approval per §12. |
| 2026-08-08 | **Phase 2.3 (Medical History) implemented and opened as PR #22** on `feature/patient-profile-phase2-3-medical-history` — see §12 for full detail and the new `CHANGELOG.md` entry. Confirmed the "(proposed)" permission default the design doc's §10/§19 flagged for Medical History (view = all staff, write = Admin + Dentist) by implementation, matching `DentalChartEntryPolicy`'s closest existing pattern rather than inventing a new one. Backend 998/998 tests green (Pint clean); frontend 867/867 tests green, type-check/ESLint clean. PR #22's first CI run caught a real Pint `php_unit_method_casing` issue and 7 genuinely non-Prettier-compliant new files that an earlier flawed local `git stash`-based verification had missed — both fixed, branch re-verified to match `origin/main`'s exact 235-file pre-existing baseline (via a proper `git worktree` comparison), pushed as a follow-up commit. **PR #22's CI is fully green on re-run** (Backend pass, Frontend pass, E2E skipping as expected on `pull_request`). Not merged — stopped for the user's review per their explicit instruction. |
| 2026-08-08 → 2026-08-09 | **Phase 2.4-2.6 (Laboratory, Documents, Timeline) and Phase 2 close-out** — see §4's PR history table (PR #24-#34) and §12 for full per-phase detail; not individually rowed here (this table's own update cadence lapsed after Phase 2.3 — §4/§12 remained current throughout and are the authoritative record for this window). **Phase 2 (Patient Profile Redesign) complete, closed out via PR #34.** |
| 2026-08-09 | **Phase 3 (Dashboard 2.0) — design phase.** Two competing "next phase" candidates (roadmap's own Phase 3 vs. finishing the still-open Premium Visual Redesign) reconciled into one design doc, `docs/modules/dashboard-2.0-design.md`, approved by the user as "Functional 2.0" (restyle + new data-backed widgets, reusing existing `ReportService` methods rather than new report logic). Design-phase audit found a real, pre-existing security gap in `/dashboard/summary` (see §5/`docs/decisions.md`). **Implemented on `feature/dashboard-2-0`** in the design doc's 4 sequential steps, each verified before the next — see §12 for full detail. Opened as **PR #35**. |
| 2026-08-09 | **PR #35 merged to `main`** (`0f04b35`). Post-merge CI re-run: Backend, Frontend, and E2E (the real run, unlike the PR-triggered one) all green — `e2e/dashboard.spec.ts` confirmed passing. **Milestone: "Phase 3 — Dashboard 2.0 Complete."** Next: Phase 4 (Advanced Permissions & Audit) design phase. |
| 2026-08-09 | **PR #36 merged** (`14416e9`) — docs-only Phase 3 close-out (no code). **Phase 4 (Advanced Permissions & Audit) — design phase.** Ground-truth audit of the actual permission/audit-trail code (all 27 Policies, `Auditable`/`AuditObserver`/`AuditLogService`, `AuthController`/`AuthService`) found `User` is not audited and no authentication events are logged — both previously undocumented. User approved: fine-grained permissions over the current 3 roles (not a role-hierarchy rewrite), a full audit-trail overhaul, simple immutability now with retention deferred. Design doc: `docs/modules/phase4-permissions-audit-design.md`. |
| 2026-08-09 | **Phase 4 Step 1 (Permissions Foundation) implemented** on `feature/phase4-permissions-foundation` — 68-entry permission catalog derived 1:1 from all 27 Policies, `role_permissions` matrix, `User::hasPermission()` with per-role cache, 3 admin-only endpoints, `users.manage` self-lockout protection. Catalog independently verified (manual derivation matched seeded grant counts exactly). **Step 2 (Policy refactor) implemented** same day — all 27 Policies converted to `hasPermission()`, every identity/ownership/target-role check preserved verbatim; `Tests\TestCase` seeds the catalog for every `RefreshDatabase` test; 15 new baseline Policy tests added. **Backend 1121/1121 tests green, zero regressions.** Pint clean, targeted PHPStan clean. **Step 3 (Audit Overhaul) implemented** same day — closes the two critical gaps the design-phase audit found (`User` unaudited, no authentication-event logging): additive `audit_logs` columns (`old_values`, `ip_address`, `user_agent`, `context`; `auditable_id` now nullable for targetless events), `AuditLogService` rewritten to capture real before/after diffs (not just new values) and IP/UA, `User` made Auditable, `AuthService` logs `login_succeeded`/`login_failed`/`logged_out` (attempted email only on failure, never the password), `RolePermissionService::updateMatrix()` now audits its own before/after matrix (closing a gap Step 1 itself had left open), an immutability guard on `AuditLog`, and a new general `GET /audit-logs` viewer (admin-only, filtered, paginated). Audit writes fail open for the business operation but fail closed on sensitive data (a broken write never breaks a login/save, and its own error log never carries the payload). `bootstrap/app.php` gained `trustProxies(at: '*')` — a real gap closed, not just documented: `docs/deployment.md`'s host-level reverse proxy already sets `X-Forwarded-For` correctly, but Laravel had zero trusted-proxy config, so captured IPs would have silently been the proxy's own loopback address in production. 24 new tests (old/new diffs, delete snapshots, redaction, IP/UA, fail-open behavior, auth events including the unknown-email case, matrix-update auditing, immutability, endpoint authorization/filters/pagination). **Backend 1145/1145 tests green, zero regressions.** Pint clean (531 files); targeted PHPStan clean after 2 real fixes, remaining noise confirmed pre-existing via cross-check against untouched files. Not yet merged — stopped for review per the user's explicit instruction; Steps 4-5 (Frontend, E2E/docs) remain before requesting merge. |
| 2026-08-09 | **Phase 4 Step 4 (Frontend) implemented** on `feature/phase4-permissions-foundation` — new admin-only `PermissionsView.vue` (68-entry catalog matrix, module-grouped rows, one `ToggleSwitch` per role, explicit Save/Discard over a local draft rather than per-toggle autosave; the `users.manage`/Admin cell renders disabled/checked, matching `UpdateRolePermissionsRequest`'s server-side self-lockout rule exactly, not just a client-side guess; a stacked per-role `Accordion` replaces the matrix table under `md:`, per the design doc's §10 mobile-first requirement) and `AuditLogsView.vue` (the general `GET /audit-logs` viewer — user/resource-type/action/date-range filters, server-side pagination, a `DataTable` row-expansion showing the old/new field diff or the event's `context`, e.g. the attempted email on a `login_failed` row rendered as that row's Actor since no `user` resolves). New `permissions.ts`/`auditLogs.ts` Pinia stores + `services/permissions`/`services/auditLogs` API layers (store-owns-error-state pattern, matching `dashboard.ts`); new `config/auditableTypes.ts` mapping every raw `auditable_type` FQCN (21 `Auditable` models + `RolePermission`) to an i18n label so no raw PHP class name ever reaches the UI. New admin-only Sidebar entries/routes (`/permissions`, `/audit-logs`), gated the same `roles: ['admin']` way as `Users`/`Settings`. ~300 new i18n keys (`permissions.groups.*`/`permissions.catalog.*`/`auditLog.*`) across all 3 locales — parity verified programmatically (1453 keys in each of `en`/`ar`/`tr`, zero missing either direction). 20 new frontend tests (2 stores + `PermissionsView`/`AuditLogsView` component tests: matrix toggle, locked-cell rendering and save-payload correctness, filter controls, row expansion, the no-resolved-user actor case) — **Frontend 969/969 tests green**, `vue-tsc`/ESLint/Prettier clean. Manually verified in a real browser against real seeded audit-trail data (not just component tests): the full matrix and a live, paginated Audit Log both render correctly in English and Arabic (RTL, via the app's real language switcher) and at a 390px mobile viewport; a dentist session has neither Sidebar entry and is server-side blocked (the app's standard 403 page) on direct navigation to either route. No backend behavior changed — Step 4 consumes the Step 1-3 API surface as-is. |
| 2026-08-09 | **Phase 4 Step 5 (E2E + final docs) implemented** on `feature/phase4-permissions-foundation`, closing out all 5 steps — new `role-permissions.spec.ts` (self-lockout cell stays disabled+checked; an admin toggling a permission off persists across reload, takes effect for a live receptionist session, and is audited as `role_permissions_updated` with a real before/after diff; restores the shared global matrix afterward since it's used by every other E2E spec; 403s on all 3 permission-matrix endpoints) and `audit-log.spec.ts` (action filtering, no-diff row expansion, a failed login audited with the attempted email as both Actor and `context` — made from a genuinely unauthenticated session, not an already-logged-in one, since `Auth::id()` reflects the current session regardless of what a login attempt claims; 403 on `GET /audit-logs`); `permissions.spec.ts` gained admin-reaches-both-screens and non-admin-blocked-with-no-sidebar-entry cases, alongside its existing untouched pre-Phase-4 tests which already double as the design doc's required behavior-preservation proof. Iterating locally surfaced and fixed 4 real test bugs (a PUT missing its XSRF header returning 419 not 403; a click destabilized by this environment's per-request latency; a locale-forcing gap; a wrong expected error string) plus a real fetch-ordering race between a page's initial unfiltered load and an immediately-applied filter — every test passed cleanly at least once locally, remaining flakiness matching this environment's own already-documented latency pattern (§9), not application behavior. i18n parity re-verified (1453/1453/1453). `docs/decisions.md` and `TECH_DEBT.md` updated; `docs/modules/roles-permissions.md` formally marked superseded. Pushed to `origin/feature/phase4-permissions-foundation`; first CI run (`31328029404`) caught one real ordering bug local runs never had — a `page.waitForResponse()` registered *after* the `page.goto()` that triggers the response, a race a fast environment (CI) loses and a slow one (this local Docker setup) happens to win — fixed and **re-run (`31328615397`) fully green: Backend, Frontend, and E2E (53/53) all `success`.** Not yet merged — no PR opened per the user's explicit instruction, awaiting their own full-diff review. |
| 2026-08-09 | **Final diff review** (103 files, 14 commits, +5543/-215 against `main`, 14 points checked: scope, temp/debug artifacts, migrations-from-clean, secrets, `hasPermission()` consistency, audit architecture, API contracts, real-vs-weakened test assertions, E2E shared-state restoration, i18n parity, doc/code consistency, Notification-System scope exclusion, diff-size/refactor risk, regression risk) found **zero blockers** — two non-blocking notes recorded (`docs/decisions.md`'s `trustProxies` addendum; `TECH_DEBT.md`'s migration-rollback item). User approved; **PR #37 opened**, then **merged to `main`** (`0bdf3d8`). Post-merge CI on `main` (the real run, unlike the PR-triggered one) re-confirmed fully green — Backend, Frontend, and E2E all `success` (run `31333946387`). **Milestone: "Phase 4 — Advanced Permissions & Audit Complete."** Next: Notification System design phase. |
| 2026-08-11 | **Phase 5 (Notification System) — design phase.** Ground-truth audit found no notification system existed anywhere (no `notifications` table, no `app/Notifications`/`app/Mail`, no `config/broadcasting.php`, no route, no frontend store) — but also that Phase 2.6's `PatientActivityOccurred` already fires from 21 call sites across 9 services (24 event types), Phase 4's `PatientActivityPolicy` already established the category-filtering security pattern, and `User` already carried `Notifiable`. The same audit surfaced a **previously-untracked** hazard: `QUEUE_CONNECTION=redis` configured since the project's first `.env`, but **no worker process in either compose file** and nothing scheduled — any `ShouldQueue` would have been enqueued and silently never run. Design doc: `docs/modules/notifications-design.md`; user approved decisions D1-D8, scoping **Phase A + Phase B** to this cycle. |
| 2026-08-11 | **Phase 5A (In-App Notification Center) implemented** on `feature/phase5-notifications` — Laravel's stock `notifications` table + 4 additive columns via a `DatabaseChannel` subclass (Decision D1); `SendsNotifications` as a **second listener on the existing event, so zero new dispatch call sites**; an 8-of-24 allow-list (`NotificationRules`) with per-type exclusion reasoning recorded; `RecipientResolver` as the single multi-tenant seam; three authorization layers (structural ownership → 404 not 403; read-time category re-check on list *and* count; send-time check); 5 endpoints; `NotificationBell`/`NotificationCenter`/`NotificationItem` replacing the inert header bell `TECH_DEBT.md` had tracked since the layout work; keys-not-text localization, 32 keys × 3 locales, parity **1486/1486/1486**. Backend 1179/1179 green (34 new, zero regressions), Frontend 34 new tests green. |
| 2026-08-11 | **Phase 5B (Queue + Scheduler) implemented** — `queue` and `scheduler` containers added to **both** compose files, a `RUN_MIGRATIONS` guard in `docker/php/entrypoint.sh` so only `app` migrates rather than three containers racing. `ShouldQueue` adopted **only after** a real job was observed going `RUNNING` → `DONE` in `dentalsuite_queue` (the user's explicit condition), with `$afterCommit = true` — load-bearing, since `InvoiceService::void()`/`PaymentService::refund()`/`LabCaseService::receive()` all fire the event inside a `DB::transaction()`. `Notification` made `MassPrunable` (90-day retention, unread never pruned) and `routes/console.php` gained the project's first scheduled task. Backend **1186/1186** green (41 new total), Frontend **1003/1003** green. **Not merged — no PR opened, awaiting the user's own review.** |
| 2026-08-11 | **Pre-PR review of Phase 5A/5B found and fixed 5 issues** before either phase was opened as a PR — 2 marked SECURITY: (1) notifications Pinia store leaked across logout/login on the same tab (`reset()` existed, untriggered — now called from `auth.ts`'s `logout()`); (2) the queued `SendsNotifications` listener's Redis payload carried a dentist's password hash/`remember_token` and (whenever a relation was preloaded) patient PHI, because `PatientActivityOccurred`'s `readonly` `Model` properties had no serialization contract — fixed with `Illuminate\Queue\SerializesModels`, verified compatible with `readonly` properties (this framework version's `__serialize()`/`__unserialize()` implementation, not the older mutate-in-place `__sleep()`/`__wakeup()`) via both a payload-content test and a live Redis/queue-worker run; (3) `POST /notifications/{id}/read` 500'd on a malformed id on real Postgres (SQLite never caught it) — fixed with `Route::whereUuid()`; (4) `NotificationCenter` never refetched after its first-ever open, since its `items.length === 0` guard survived across the `Popover`/`Drawer` remounts that happen on every open/close — guard removed; (5) i18n parity docs said `1485/1485/1485`; re-running the check found the real count is `1486/1486/1486` — corrected. Backend **1191/1191** green (+5), Frontend **1005/1005** green (+2), zero regressions either side. New commits on `feature/phase5-notifications`; **still no push, no PR**. Full detail: `CHANGELOG.md`, `docs/decisions.md`. |
| 2026-08-11 | **PR #39 opened** to `main`. Real CI (fresh Ubuntu runner) caught two further real, pre-existing (Phase 5B) issues local runs had missed or mis-attributed: a Pint import-ordering violation in `routes/console.php`/`NotificationQueueTest.php`, and a genuine PHPStan generics mismatch in `Notification::prunable()` (`Builder<$this>` docblock vs. the actual `Builder<static>` `static::query()` returns) — the latter notable as proof that this project's known local-only "480ish false-positive `undefined property` errors" PHPStan artifact doesn't swallow real bugs; they surface with a distinct message shape and CI still catches them. Both fixed in follow-up commits on the same PR. CI re-run fully green: Backend (tests, PHPStan, Pint) `pass`, Frontend (type-check, lint, tests, build) `pass`, E2E `skipping` (by design — only runs on push to `main`). **Not yet merged** — awaiting the user's review of CI results. |
| 2026-08-11 | **Pre-merge E2E gate, per explicit user request**: ran real `workflow_dispatch` CI on `feature/phase5-notifications` (the mechanism that actually executes the E2E job) before allowing a merge. Found and fixed 2 more genuinely real, pre-existing bugs from the original Phase 5A/5B implementation, neither caused by the pre-PR review fixes: (1) `notifications.spec.ts`'s `findAppointmentTypeId()` assumed `GET /appointment-types` returns a paginated `{data: [...]}` envelope, but that endpoint is unpaginated and `JsonResource::withoutWrapping()` is global, so it's a bare array — fixed the helper; (2) the E2E job's `ci.yml` never started a queue worker despite `SendsNotifications` being `ShouldQueue` since Phase 5B, so every notification-producing action enqueued and was never consumed — added a `php artisan queue:work &` step. Also confirmed the local `login()` failure (`TECH_DEBT.md`) never reproduces on CI — `auth.spec.ts` and every login-dependent spec passed cleanly, so it is genuinely environment-specific, not a masked regression. Final run: Backend/Frontend `pass`, **E2E 56/57 passed, 1 flaky-then-passed** (a notification-lookup timing race against the now-actually-running queue worker, resolved by Playwright's own retry — the same "flaky, not failure" class already recorded for `dental-chart.spec.ts`). |
| 2026-08-11 | **PR #39 merged to `main`** (merge commit `204194f`). **Post-merge CI re-run and fully green — Backend, Frontend, and the real E2E run all `success`** (run `31545464355`; 56/57 E2E passed, the same single flaky-then-passed test, confirming a transient timing flake rather than a deterministic problem). **Milestone: "Phase 5 — Notification System Complete."** `docs/roadmap.md`, `TECH_DEBT.md`, and `CHANGELOG.md` updated to close out the phase. Next up: Phase 5C/5D (scheduled + email notifications) or roadmap Phase 6 (SaaS Multi-Tenant Prep), per the user's prioritization. |
| 2026-08-12 | **User chose Phase 5C/5D over roadmap Phase 6.** Design-phase audit against the parent doc's §5.2/5.3 sketch (`docs/modules/notifications-phase-c-d-design.md`) found `security` needs a new Gate-backed `NotificationPolicy` branch (no Eloquent model), and confirmed types 12-13 can be reactive off `AuditLog::created()` rather than scheduled. Verifying Decision D11's own condition ("clinic timezone, not blind UTC") found `config('app.timezone')` was a hardcoded `'UTC'` literal, not `env()`-driven — new **Decision D14**. User approved D9/D11/D12 as recommended, deferred D10/D13 to a future Phase D cycle, and confirmed the clinic's real zone as **`Africa/Cairo`** (D14). |
| 2026-08-12 | **Phase 5C implemented** the same day. All 5 types (9-13), 2 new categories, `env()`-driven `APP_TIMEZONE`. Testing surfaced 2 further real bugs beyond D14 itself: `NotificationIndexRequest` rejected `category=security` (fixed via a new `NotificationPolicy::allCategories()` single source of truth), and — found immediately after rebuilding the Docker image for a defensive `tzdata` addition — `docker/php/entrypoint.sh`'s CRLF line endings (Windows `core.autocrlf=true`) crash-looped `app`/`queue`/`scheduler` once the cache-invalidated `COPY` picked up the working-tree file fresh; fixed by normalizing to LF and adding a new `.gitattributes` so it can't recur. Full account: design doc §19-20. **Backend 1211/1211 green** (1191 + 20 new), run twice including once against the rebuilt image; PHPStan/Pint/`vue-tsc`/ESLint clean; i18n **1498/1498/1498** zero drift; `schedule:list` confirmed. Full frontend Vitest run and real-browser/E2E confirmation not completed this pass (environment constraints, not silently skipped — see `TECH_DEBT.md`). **Not yet merged.** `CHANGELOG.md`/`TECH_DEBT.md`/`docs/decisions.md`/`docs/roadmap.md` all updated same pass. Phase 5D (email) remains deferred to its own future cycle. |

---

## 3. Modules

Status column reflects **actual verified state** (git/GitHub) — matches `docs/roadmap.md`/`PROJECT_CONTEXT.md`
as of the 2026-08-07 reconciliation, see §0.

| Module | Status | Backend | Frontend | Tests | Docs | Notes |
|---|---|---|---|---|---|---|
| Dashboard | **Dashboard 2.0 — Production Ready ✅** (2026-08-09, merged via PR #35) | ✅ `DashboardService` (split operational/financial) | ✅ `DashboardView` + `FinancialSnapshotWidget`/`UnscheduledTreatmentWidget` + restyled stat cards | `DashboardTest` + `DashboardFinancialSummaryTest` (26 tests); frontend store/component tests + `e2e/dashboard.spec.ts` (new) — post-merge CI on `main` confirmed all three jobs (Backend/Frontend/E2E) green | `docs/modules/dashboard-2.0-design.md` (`docs/modules/dashboard.md` is the stale pre-implementation V1 scope doc, superseded in substance though never formally marked — pre-existing gap, not touched this phase) | `today_appointments` was an unscoped `COUNT(*)` — **fixed 2026-08-07** (PR #15). **`monthly_revenue` had zero authorization — fixed 2026-08-09** (Dashboard 2.0): moved to a new admin-gated `/dashboard/financial-summary` endpoint, see `docs/decisions.md`. |
| Authentication | Done | ✅ Sanctum SPA cookie auth | ✅ `LoginView` | ✅ | `docs/modules/authentication.md` | Rate-limited (5/60s per email+IP). |
| Users | Done | ✅ CRUD, soft delete | ✅ `UsersView` | ✅ | `docs/modules/users.md` | Self-delete blocked; admin-only write. |
| Roles & Permissions | **Phase 4 — Production Ready ✅** (2026-08-09, merged via PR #37) | ✅ `UserRole` backed enum + new `permissions`/`role_permissions` catalog-and-matrix, all 27 Policies on `hasPermission()`, full `audit_logs` overhaul (old/new diffs, IP/UA, auth events, immutability) | ✅ `PermissionsView.vue` (matrix, self-lockout-aware, responsive) + `AuditLogsView.vue` (general viewer, filters, diff expansion) — admin-only Sidebar entries/routes | ✅ 1145/1145 backend, 969/969 frontend, `role-permissions.spec.ts`/`audit-log.spec.ts`/`permissions.spec.ts` E2E — post-merge CI on `main` confirmed all three jobs (Backend/Frontend/E2E) green (run `31333946387`) | `docs/modules/roles-permissions.md` (superseded in substance by `docs/modules/phase4-permissions-audit-design.md`, formally marked superseded 2026-08-09) | 3 roles (admin/dentist/receptionist) unchanged — deliberately not a full role hierarchy (see `docs/decisions.md` 2026-08-09), fine-grained permissions layered on top instead. |
| Patients | Done | ✅ CRUD + `Auditable` | ✅ `PatientsView`/`PatientDetailView` | ✅ | `docs/modules/patients.md` | `patient_code` (`P-00001`), `pg_trgm` search indexes. |
| Appointments | **Production Ready ✅** (`v1.0.0-appointments`, 2026-07-20) | ✅ full CRUD + status-machine + slot logic | ✅ Calendar Board (Day/Week/Month/List) | 13/13 E2E at 2026-07-20 tag; `appointments.spec.ts`'s `DemoDataSeeder` regression **fixed 2026-08-07** (PR #15) | `docs/modules/appointments.md` | Was CI-red since 2026-08-01, now green — see §9/§12. |
| Dental Chart | **Production Ready ✅** (merged 2026-07-22) | ✅ odontogram entries, condition catalog | ✅ 52-tooth FDI schematic, Accessible List view | 347/347 backend + 428/428 frontend + 16/16 E2E | `docs/modules/dental-chart.md` | 98/98 en/ar/tr parity at closure. |
| Treatment Plans | Merged (PR #1, 2026-07-25; clinic-wide list added PR #12, 2026-08-02) — **not yet at "Production Ready" bar** | ✅ plan/item lifecycle | ✅ per-patient tabs + new clinic-wide `TreatmentPlansView` | 505/505 backend + 541/541 frontend at original merge; **no permanent E2E suite** (open, §9) | `docs/modules/treatment-plans.md`, `-design.md` | Reuses `dental_conditions` as V1 pricing catalog — flagged as revisit-when-multi-tenant (§9). |
| Billing | Merged (PR #1, 2026-07-25) — **weakest module on test coverage** | ✅ Invoice lifecycle | ✅ Invoice tab/detail | 65/65 backend **unit-only** (no Feature-test suite); **no E2E suite** | `docs/modules/billing-design.md` — **final `modules/billing.md` doc still missing** | Both gaps open in `TECH_DEBT.md`, not silently dropped. |
| Payments | Merged (PR #1, 2026-07-25) + concurrency fix (PR #2, same day) | ✅ record/apply/refund | ✅ Payments tab + Invoice Payments panel | 619/619 backend Unit + 22/22 Feature; **no E2E suite** | `docs/modules/payments-design.md` | Refund is a new signed-negative row, never edits the original. |
| Clinical Notes | **Production Ready ✅** (PR #3, 2026-07-26) | ✅ SOAP, Draft→Signed, addendums | ✅ tab + detail view | 703/703 backend (60 module-specific) + 595/595 frontend + 19/19 E2E | `docs/modules/clinical-notes-design.md` | Receptionist has **no access at all** — deliberate divergence from every other clinical module. |
| Inventory | **Production Ready ✅** (PR #4, 2026-07-27) | ✅ catalogs + immutable stock ledger + PO lifecycle | ✅ Suppliers/Supplies/PO views + Low Stock widget | 771/771 backend (68 module-specific) + 19 new frontend + 20/20 E2E | `docs/modules/inventory-design.md` | On-hand quantity always computed live from the ledger, never stored. |
| Laboratory | **Production Ready ✅** (PR #5, 2026-07-27; patient-scoped integration added PR #24, 2026-08-08) | ✅ Lab catalog + LabCase lifecycle | ✅ Labs/LabCases views + printable slip + patient-scoped `laboratory` tab | 1007/1007 backend (58+13 module-specific) + 894/894 frontend + E2E green | `docs/modules/laboratory-design.md`, `docs/modules/patient-laboratory-redesign-design.md` | Former latent `BelongsToPatient` SQL-error bug **fixed 2026-08-08** (PR #24) — see §11's Resolved history / `TECH_DEBT.md`. |
| Imaging | **Production Ready ✅** (PR #6, 2026-07-28) | ✅ `PatientImage`, authenticated streaming | ✅ Imaging tab + non-destructive lightbox | 834/834 backend (19 module-specific) + 637/637 frontend + 27/27 E2E | `docs/modules/imaging-design.md` | DICOM/CBCT, hardware capture, persistent annotation explicitly out of V1 scope. |
| Reports | **Production Ready ✅** (PR #7, merged 2026-07-28) | ✅ `ReportService`, 6 live reports, CSV export | ✅ Reports nav group + 6 views | 855/855 backend + 652/652 frontend + 29/29 E2E at merge; `reports.spec.ts`'s `DemoDataSeeder` regression (2 separate ordering bugs: Collections, New Patients) **fixed 2026-08-07** (PR #15) | `docs/modules/reports-design.md` | Was CI-red since 2026-08-01, now green — see §9/§12. |
| Settings | **Production Ready ✅** (PR #8, **merged** 2026-07-30 — corrects stale "not merged" prose elsewhere, §0) | ✅ `ClinicSetting`, `BillingSetting` API, My Account | ✅ Settings views + header avatar menu | 877/877 backend (22 module-specific) + 672/672 frontend + 32/32 E2E | `docs/modules/settings-design.md` | Multi-branch, clinic logo upload, notification settings deliberately out of V1. |
| AI Assistant | **Production Ready ✅** (PR #9, 2026-07-31; extended by PR #11, 2026-08-02) | ✅ gated service layer, `AiInteractionLog` | ✅ Dashboard Insights/Smart Search/Report writing + Settings API-key UI | 905/905 backend + 694/694 frontend + 34/34 E2E at PR #9; +additional tests in PR #11 | `docs/modules/ai-assistant-design.md`, `-settings-api-key-design.md` | See §7 for full detail — fails closed with no API key configured; PHI-touching features shipped disabled by design. |
| **Notifications** | **Phase 5A/5B Production Ready ✅ (merged via PR #39, `204194f`); Phase 5C implemented 2026-08-12, not yet merged** | ✅ Laravel `notifications` table + 4 additive columns, custom `DatabaseChannel`, `NotificationService`/`RecipientResolver`/`NotificationRules`, `SendsNotifications` (queued, `SerializesModels`) on the existing `PatientActivityOccurred` event, `NotificationPolicy` category map (+ `inventory`/`security`, Phase C), `AuditLogPolicy`, `AuditLogObserver`, 3 scheduled Commands, 5 endpoints (`markAsRead` `whereUuid`-constrained) | ✅ `NotificationBell` (badge + visibility-gated 60s poll, popover/drawer), one embeddable `NotificationCenter` (refetches on every open) shared by popover/drawer/`/notifications` page, `NotificationItem`, `notifications` store (reset on logout); 2 new category chips (Phase C, config-only) | 1211/1211 backend (66 new total) + 1005/1005 frontend (unchanged this pass — targeted 14/14 on touched files) + `e2e/notifications.spec.ts` **CI-confirmed passing** (Phase A/B only, 56/57, post-merge run `31545464355`); Phase C not yet E2E-confirmed, see `TECH_DEBT.md` | `docs/modules/notifications-design.md`, `docs/modules/notifications-phase-c-d-design.md` | In-app only. Email = Phase D (deferred to its own future cycle, Decision D12), Web Push = roadmap Phase 6 (needs the absent PWA foundation), patient-facing SMS/WhatsApp = its own future module; `appointment_reminders` deliberately left unwired. Phase B closed a previously-untracked hazard: no queue worker had ever existed despite `QUEUE_CONNECTION=redis`. Phase C found and fixed 3 more real bugs (category validation, `AuditLog.created_at` clock mismatch, `entrypoint.sh` CRLF crash-loop) — see design doc §19. |

**Post-roadmap initiative — Frontend UX & Navigation Redesign / Premium Visual Redesign** (see §6 for the
full narrative):

| Phase | Status |
|---|---|
| Phase 1: Navigation Shell | **Production Ready ✅**, merged PR #10 (2026-08-01) |
| Premium Visual Redesign Steps 1–3 (supersedes original Phase 2, pulls Phase 4 forward) | **Merged PR #13 (2026-08-02)** — foundation tokens, Sidebar redesign + partial Lucide icon migration, Header/Command Palette polish |
| Remaining icon migration (module-by-module, ~62 files still on PrimeIcons as of PR #13) | Not started |
| Dashboard redesign (per the Premium doc's own §6, replaces old Phase 2 scope) | Not started |
| Phase 3: Data Tables System | Not started |
| Phase 4: Cross-cutting Polish | Partially absorbed into Premium Visual Redesign's per-step acceptance criteria; not a separate remaining phase |

---

## 4. Pull Requests History

Sourced directly from `gh pr list --state all` (31 PRs total as of this update; #31 open, all others merged) — not from prose in other docs.

| # | Branch | Goal | Key changes | Merge status |
|---|---|---|---|---|
| 1 | `feature/treatment-plans` | Treatment Plans, Billing, Payments modules | Plan/item lifecycle, Invoice lifecycle, Payment record/apply/refund | **Merged** 2026-07-25 (`f41cda5`) |
| 2 | `fix/payment-concurrency-race` | Close a refund/apply race condition | `DB::transaction()` + `lockForUpdate()` on `PaymentService` | **Merged** 2026-07-25 (`58ebfa9`) |
| 3 | `feature/clinical-notes` | Clinical Notes module | SOAP notes, Draft→Signed, addendums, permanent E2E suite | **Merged** 2026-07-26 (`fe54196`) |
| 4 | `feature/inventory` | Inventory: Suppliers, Supplies, Purchase Orders | Immutable stock ledger, PO lifecycle, CI-confirmed | **Merged** 2026-07-27 (`bf2592f`) |
| 5 | `feature/laboratory` | Laboratory module | Lab catalog, LabCase lifecycle, printable slip | **Merged** 2026-07-27 (`bac6ae1`) |
| 6 | `feature/imaging` | Per-patient diagnostic image gallery | `PatientImage`, authenticated streaming, non-destructive lightbox | **Merged** 2026-07-28 (`2b1fb45`) |
| 7 | `feature/reports` | 6 reports (Production, Collections, A/R Aging, Appointment Analytics, TP Acceptance, New Patients) | `ReportService`, CSV export, `DashboardService.monthly_revenue` wired to real data | **Merged** 2026-07-28 |
| 8 | `feature/settings` | Practice Settings, Billing Settings, My Account | `ClinicSetting`, first-ever `BillingSetting` API/UI, self-service profile | **Merged** 2026-07-30 |
| 9 | `feature/ai-assistant` | AI Assistant module | Dashboard Insights, Smart Search, Reports, gated Clinical Notes/Treatment Suggestions | **Merged** 2026-07-31 (`644fed6`) |
| 10 | `feature/frontend-nav-shell` | Nav Shell Phase 1 | Sidebar redesign, breadcrumbs, Command Palette, keyboard shortcuts, `GET /invoices` | **Merged** 2026-08-01 (`f45110a`) |
| 11 | `feature/ai-assistant-api-key` | Settings-managed Anthropic API key | Encrypted key column, `AiAssistantService::client()` prefers it over `.env` | **Merged** 2026-08-02 |
| 12 | `feature/treatment-plans-clinic-index` | Clinic-wide Treatment Plans list | `GET /treatment-plans`, `TreatmentPlansView.vue`, fixes stale `comingSoon` nav | **Merged** 2026-08-02 (`c94c9f3`) |
| 13 | `feature/premium-visual-redesign` | Premium Visual Redesign Steps 1–3 | Design tokens, Sidebar/Header/Command Palette polish, Lucide icon migration begun | **Merged** 2026-08-02 |
| 14 | `docs/dashboard-today-appointments-bug` | Doc-only: log the Dashboard bug + close the Treatment Plans tech-debt item | No code changes | **Merged** 2026-08-07 |
| 15 | `fix/stabilization-phase-1` | Phase 1 (Stabilization) of the new 8-phase roadmap | Restored fully green `main` CI (2 `reports.spec.ts` root causes + `appointments.spec.ts`), fixed `Dashboard.today_appointments`, frontend loading/empty/error-handling consistency pass, added this file + `CLAUDE.md` | **Merged** 2026-08-07 (`f3644ec`) |
| 16 | `docs/phase-1-closeout` | Docs-only: reconcile `CHANGELOG.md`/`docs/roadmap.md`/`PROJECT_CONTEXT.md` staleness, full Phase 1 close-out in this file | No code changes | **Merged** 2026-08-07 (`82c6cb7`), after independent CI/conflict/TODO re-verification — see §2 |
| 17 | `docs/phase-1-closeout` (cont.) | Docs-only: tag Phase 1 — Foundation Complete, close out PR #16 | No code changes | **Merged** 2026-08-07 (`cccc2c6`) |
| 18 | `feature/patient-profile-phase2-1-foundation` | Phase 2.1 — Patient Profile Foundation | New Patient Detail tabbed shell, design doc approved this phase | **Merged** 2026-08-07 (`21af360`) |
| 19 | `docs/phase2.1-closeout-pr18` | Docs-only: close out PR #18, record Phase 2.2 scope | No code changes | **Merged** 2026-08-08 (`d9b277f`) |
| 20 | `feature/patient-profile-phase2-2-billing` | Phase 2.2 — Billing tab | Merges Invoices/Payments tabs into one Billing tab, `GET /patients/{patient}/billing-summary`, pagination debt resolved | **Merged** 2026-08-08 (`43ca5c7`) |
| 21 | `docs/phase2.2-closeout-pr20` | Docs-only: close out PR #20, record Phase 2.3 scope | No code changes | **Merged** 2026-08-08 (`d4addcb`) |
| 22 | `feature/patient-profile-phase2-3-medical-history` | Phase 2.3 — Medical History | Structured Allergies/Medical Conditions/Medications tab, `MedicalHistoryService`/`Policy`/`Controller`, legacy-column backfill | **Merged** 2026-08-08 (`aa704b6`) |
| 23 | `docs/phase2.3-closeout-pr22` | Docs-only: close out PR #22, record Phase 2.4 design-phase start | No code changes | **Merged** 2026-08-08 (`f417f05`) |
| 24 | `feature/patient-profile-phase2-4-laboratory` | Phase 2.4 — Laboratory | Patient-scoped `Patient::labCases()`/`forPatient` scope, `PatientLabCasesPanel.vue` (read/write), fixed the dormant `BelongsToPatient` SQL bug in Laboratory's Form Requests | **Merged** 2026-08-08 (`9de50fb`) |
| 25 | `docs/phase2.4-closeout-pr24` | Docs-only: close out PR #24, record post-merge CI confirmation | No code changes | **Merged** 2026-08-08 (`21f594a`) |
| 26 | `docs/phase2.5-documents-design` | Docs-only: Phase 2.5 (Documents) design phase — audit + drill-down design doc | No code changes | **Merged** 2026-08-08 (`b9adab8`) |
| 27 | `feature/patient-profile-phase2-5-documents` | Phase 2.5 — Documents | `PatientDocument` model/service/policy/controller (patient-scoped only), `PatientDocumentsPanel.vue` (read/write), `DocumentCategory` enum | **Merged** 2026-08-08 (`98ae299`) |
| 28 | `docs/phase2.5-closeout-pr27` | Docs-only: close out PR #27, record post-merge CI confirmation | No code changes | **Merged** 2026-08-08 (`933ee90`) |
| 29 | `docs/phase2.6-timeline-design` | Docs-only: Phase 2.6 (Timeline) design phase — audit + drill-down design doc | No code changes | **Merged** 2026-08-08 (`e8e2cba`) |
| 30 | `docs/phase2.6-category-fix` | Docs-only: fix a real category-taxonomy conflict in the approved Timeline design (found pre-implementation) | No code changes | **Merged** 2026-08-08 (`47e0c59`) |
| 31 | `feature/patient-profile-phase2-6a-timeline-foundation` | Phase 2.6a — Timeline foundation | `PatientActivity` model, one generic event + one listener, 24 dispatch call sites across 9 services, `PatientActivityPolicy`'s per-category mechanism, `/activities` endpoint — no UI | **Merged** 2026-08-09 (`cb7b52d`) |
| 32 | `feature/patient-profile-phase2-6b-timeline-ui` | Phase 2.6b — Timeline UI | `ActivityTimeline.vue`, `patientActivities` store, `timeline` tab, Billing's Payment History wiring, Overview preview, category chips, i18n | **Merged** 2026-08-09 (`70b6ba6`) |
| 33 | `fix/timeline-e2e-multi-instance-scoping` | Fix 2 real bugs `e2e/timeline.spec.ts` itself had, caught by post-merge CI's real E2E run (PR-triggered runs skip it by design) | Lab case number read before async fetch completed; unscoped `getByText()` hit a strict-mode violation across 2 simultaneously-mounted `ActivityTimeline` instances | **Merged** 2026-08-09 (`83c584b`) — post-merge CI fully green, closing out Phase 2 |
| 34 | `docs/phase2-complete-closeout` | Docs-only: close out Phase 2 (Patient Profile Redesign) | No code changes | **Merged** 2026-08-09 (`54d9bf8`) |
| 35 | `feature/dashboard-2-0` | Phase 3 — Dashboard 2.0 | Split `/dashboard/summary` (operational, all roles) from new `/dashboard/financial-summary` (admin-only) — fixes a real pre-existing leak (`monthly_revenue` had no gate); production/collections trend + A/R aging snapshot (reuses `ReportService`, no new report logic); `unscheduled_accepted_treatment` widget data (new query); `FinancialSnapshotWidget.vue`/`UnscheduledTreatmentWidget.vue`; restyled stat cards/`AiQuestionBox` gradient/`gap-6` spacing (absorbs Premium Visual Redesign §6, now superseded); `PatientDetailView.vue` `?tab=` deep-link; new `e2e/dashboard.spec.ts` | **Merged** 2026-08-09 (`0f04b35`) — post-merge CI on `main` fully green (Backend/Frontend/E2E), closing out Phase 3 |
| 36 | `docs/phase3-closeout` | Docs-only: close out Phase 3 (Dashboard 2.0) | No code changes | **Merged** 2026-08-09 (`14416e9`) |
| 37 | `feature/phase4-permissions-foundation` | Phase 4 — Advanced Permissions & Audit (all 5 steps) | 68-entry permission catalog + `permissions`/`role_permissions` matrix, all 27 Policies on `hasPermission()`, full `audit_logs` overhaul (old/new diffs, IP/UA, auth events, immutability), `PermissionsView.vue`/`AuditLogsView.vue`, `trustProxies`, 3 new E2E specs | **Merged** 2026-08-09 (`0bdf3d8`) — post-merge CI on `main` fully green (Backend/Frontend/E2E, run `31333946387`), closing out Phase 4 |
| 38 | `docs/phase4-close-out` | Docs-only: close out Phase 4 (record PR #37 merge + post-merge CI) | No code changes | **Merged** 2026-08-09 (`75cf60b`) |
| **#39** | `feature/phase5-notifications` | Phase 5 — Notification System, Phases A + B | `notifications` table (Laravel's + 4 additive columns) + custom `DatabaseChannel`; `SendsNotifications` as a second listener on the existing `PatientActivityOccurred` (zero new dispatch sites); `NotificationRules` 8-of-24 allow-list; `RecipientResolver`; `NotificationPolicy`; 5 endpoints; `NotificationBell`/`NotificationCenter`/`NotificationItem` + `/notifications` page + store; 32 i18n keys × 3 locales; `queue`+`scheduler` containers in both compose files; `entrypoint.sh` `RUN_MIGRATIONS` guard; `MassPrunable` + first scheduled task; `e2e/notifications.spec.ts`; plus 5 pre-PR review fixes (2 SECURITY) and 2 pre-merge E2E-gate fixes | **Merged 2026-08-11** (`204194f`); post-merge CI fully green including the real E2E run (56/57, 1 flaky-then-passed) |

---

## 5. Architecture Decisions

Full chronological log lives in `docs/decisions.md` (backfilled from `ARCHITECTURE_REVIEW.md`, 2026-07-11
onward) — this is a summary of the decisions with the widest blast radius:

| Decision | Reasoning | Status |
|---|---|---|
| Sanctum SPA (cookie) auth, not API tokens | First-party same-site SPA; Laravel-native, no token-in-`localStorage` risk | Agreed with user |
| UUID primary keys on every table | Explicit `PROJECT_CONTEXT.md` requirement; converted before any FK dependency existed | Agreed with user |
| Roles as a PHP backed enum, not `roles`/`permissions` tables | Single-org V1, 3 roles cover the real need; avoids an unneeded package | Agreed with user |
| Soft deletes as the record-bearing-table default | Explicit `PROJECT_CONTEXT.md` text | Implemented, not a deviation |
| `JsonResource::withoutWrapping()` globally | Consistent response shape across modules | Assistant decision, never explicitly re-confirmed (low-priority open item, §9) |
| Rate limiting on `/login`, later generalized to all API routes | Baseline brute-force protection → System-Wide Production Gate | Implemented as a security default |
| **Project-wide datetime policy** (`frontend/src/lib/date.ts` is the *only* sanctioned way to touch a datetime that crossed the API boundary) | An audit found the same silent-timezone-shift bug in 4 separate places; elevated to a hard **Architecture Violation** rule for all future code review | Standing rule since 2026-07-17, applies to every module |
| `dental_conditions` reused as the Treatment Plans pricing catalog (V1 only) | Avoids building a speculative pricing table before multi-tenant/insurance pricing is a real need | "Approved with caution," explicitly flagged for revisit (§9) |
| FullCalendar: MIT packages only, "Dentists" resource view dropped | `@fullcalendar/resource*` turned out to be Premium-licensed (paid/non-commercial/GPLv3), discovered by reading the actual `LICENSE.md`, not the npm registry field | Agreed with user, permanent unless a commercial license is separately purchased |
| **SaaS multi-tenant readiness** (standing principle, 2026-07-27) | Every new schema/service/API decision must stay compatible with a future multi-clinic model; V1 stays single-org | Applies to every module's design phase going forward |
| **PWA & mobile-first UI** (standing principle, 2026-07-27) | Every new screen responsive/touch-ready/PWA-installable from first implementation | Applies to every module's design phase going forward |
| Google Fonts CDN → self-hosted `.woff2` (2026-07-20-ish) | Removed the last external network dependency on every page load | Implemented |
| Icon system: PrimeIcons → Lucide, app-wide (Premium Visual Redesign, 2026-08-01) | Restraint + a single consistent icon family, benchmarked against Linear/Raycast | In progress, module-by-module (§6, §9) |
| **Flagged for Phase 4** (2026-08-07): `UserRole`'s flat 3-value enum will need to become a real role-hierarchy/permission-matrix model (`Owner → Clinic Admin → {Dentist, Assistant, Receptionist, Accountant}`) | This is exactly the "real requirement" `docs/decisions.md`'s 2026-07-11 entry said would justify revisiting the flat-enum decision | **Reconsidered at Phase 4's design phase (2026-08-09): user chose a fine-grained permission layer on top of the existing 3 roles instead — see the next row. Full role hierarchy stays flagged, not decided, revisit if Phase 5 (SaaS Multi-Tenant) gives it a concrete reason** |
| **Fine-grained permissions over the current 3 roles, not a role-hierarchy rewrite** (2026-08-09, Phase 4 design phase) | A 68-entry permission catalog derived 1:1 from all 27 Policies' actual pre-Phase-4 behavior, admin-configurable via a new `role_permissions` matrix — covers "advanced permissions" without the wider blast radius/schema change a full `Owner/Clinic Admin/...` hierarchy would need; full detail in `docs/decisions.md` and `docs/modules/phase4-permissions-audit-design.md` §1 | **Steps 1-2 implemented 2026-08-09 on `feature/phase4-permissions-foundation` — Backend 1121/1121, zero regressions** |
| **Security Architecture Decision** (2026-08-07): Patient Timeline is built on a dedicated `PatientActivity` event model, never the `Auditable` trail, with Timeline permissions enforced **server-side, per-category**, at query time — a receptionist's `/activities` request must never return `category=clinical` rows, regardless of frontend hiding | Aggregation features are exactly where a stricter per-module read rule (e.g. `ClinicalNotePolicy` barring receptionists) can silently leak if not re-checked at the aggregation point; full detail in `docs/modules/patient-profile-redesign-design.md` §9A, full decision text in `docs/decisions.md` | **Approved with the Phase 2 design (2026-08-07) — binding on Phase 2.6 and any future cross-module aggregation feature** |
| One `MedicalHistoryPolicy` class registered against 3 models (`PatientAllergy`/`PatientMedicalCondition`/`PatientMedication`) via explicit `Gate::policy()` calls in `AppServiceProvider`, not 3 near-identical policy classes | Laravel's naming-convention auto-discovery only maps one policy per model; the design doc explicitly calls for one policy since the 3 entities are one logical feature — full detail in `docs/decisions.md` | **Implemented with Phase 2.3, merged via PR #22 (2026-08-08) — sets the precedent for any future entity cluster that should share one policy** |
| **Dashboard split by financial/operational sensitivity**: `/dashboard/summary` (open, all roles) vs. new `/dashboard/financial-summary` (`view-financial-reports`, admin-only) | Found a real, pre-existing leak — `monthly_revenue` had zero authorization, unlike the identical figure on `reports/collections`; full detail in `docs/decisions.md`'s 2026-08-09 entry | **Implemented with Dashboard 2.0 (PR #35, 2026-08-09) — sets the precedent that any dashboard/aggregation endpoint mixing financial and operational data must be split along the same boundary Reports already draws** |
| Dashboard 2.0 financial widgets show period-over-period trend, not actual-vs-goal | No goal/target concept exists anywhere in the schema; trend reuses `ReportService` with zero new storage vs. goal-setting needing a new Settings field + UI — full detail in `docs/decisions.md` | **Implemented with Dashboard 2.0 (PR #35, 2026-08-09) — goal-setting explicitly deferred, not dropped** |

---

## 6. UI/UX Evolution

1. **Pre-history (2026-07-11)**: bare Vue scaffold, generic system-font stack, an empty top-nav header —
   functional only, no design system.
2. **Design System v1 (2026-07-16)**: Inter (Latin) + IBM Plex Sans Arabic loaded via Google Fonts,
   PrimeVue Aura preset tokens nudged (border-radius), sidebar/stat-card visual polish. Declared "frozen"
   for all future modules at this point.
3. **Application Shell / Layout Architecture (2026-07-16)**: the informal top-nav layout replaced with a
   permanent sidebar+header SaaS shell (`AppSidebar`/`AppHeader`), config-driven navigation
   (`config/navigation.ts`), route-level role authorization (previously nav-hiding was the *only* protection).
4. **Datetime & accessibility hardening (through Appointments' Phase 2, 2026-07-16 → 2026-07-20)**: WCAG
   contrast-ratio fix for event/status colors, full keyboard-shortcut layer, focus-restore composable,
   `prefers-reduced-motion` support, RTL chevron-mirroring fixes — established as patterns every later module
   reused rather than reinvented.
5. **Arabic typography change (2026-07-20-ish)**: Arabic UI face swapped IBM Plex Sans Arabic → **Alexandria**
   (a modern Kufi-rooted geometric face) for a more distinctive Arabic identity; both faces later self-hosted
   as `.woff2` instead of Google Fonts CDN (zero external font requests).
6. **Frontend UX & Navigation Redesign, Phase 1 (PR #10, 2026-08-01)**: benchmarked explicitly against
   Linear/Notion/Stripe/Vercel rather than dental-EMR competitors. Collapsible sidebar section grouping,
   Favorites, breadcrumb header, global Command Palette (`Ctrl+K`), Linear-style `g`-then-X keyboard chords.
7. **Premium Visual Redesign, Steps 1–3 (PR #13, 2026-08-02)** — the current frontier. Explicitly amends
   Phase 1's plan: removes "Recent Items" entirely (decided with user), replaces the old Phase 2 (Dashboard)
   scope with a fully-specified premium redesign not yet built, and starts an app-wide **PrimeIcons → Lucide**
   icon migration (68 distinct icon classes across ~66 files) done module-by-module rather than in one pass to
   contain risk. Competitive research this round added Raycast and modern PrimeVue/Tailwind admin templates;
   the stated design thesis is that "premium" comes from **restraint + consistent spacing/motion + one
   accent treatment**, not decoration — and stays within the existing "100% PrimeVue semantic tokens, no
   hardcoded hex" rule rather than relaxing it.
8. **What's still unbuilt in this initiative**: the Dashboard redesign itself (Premium doc §6), the
   remaining ~62-file icon migration, and Phase 3 (Data Tables System) — see §3's phase table and §10.

---

## 7. AI Assistant Progress

**Status: Production Ready ✅** (PR #9, merged 2026-07-31; extended by PR #11, merged 2026-08-02). Deliberately
the **last** module built on the original roadmap — it depends on data/workflow completeness across every
other module to add real value.

**What's implemented and enabled-eligible (zero PHI sent to Claude)**:
- **Dashboard Insights** — reads aggregate report data only.
- **Smart Search** — the user's own query text only.
- **Writing Reports** — same, aggregate data only.

**What's implemented but shipped disabled-by-default, absent from the UI**:
- **Clinical Notes draft-assist** and **Treatment Suggestions** — both touch patient-identified clinical
  content, so both are gated behind an explicit admin acknowledgment
  (`ai_assistant_phi_features_acknowledged` on `ClinicSetting`) that a signed BAA with Anthropic is in place.
  This is a **hard product requirement**, not a soft default — the admin must actively opt in per-clinic.

**Safety architecture**:
- Every AI suggestion routes through the **existing Policy-gated service layer**
  (`ClinicalNoteService`, `TreatmentPlanService`) — nothing is auto-created, auto-signed, or
  auto-persisted; explicit user acceptance is required before any write.
- An append-only `AiInteractionLog` table records every prompt/response and every acceptance decision.
- AI-generated content stays visually/programmatically distinguishable ("AI-suggested, unreviewed" tag)
  until a human accepts it.
- **Fails closed at the infrastructure level**: with no `ANTHROPIC_API_KEY` configured (env or, as of PR
  #11, the Settings-stored encrypted key), every AI endpoint returns `503` rather than silently no-op'ing.

**PR #11 addition (2026-08-02)**: admins can now set/replace/remove the Anthropic API key from
**Settings → AI Assistant** instead of only via `backend/.env`. The key is `encrypted` at rest, the API only
ever returns a `configured` boolean + last-4 characters (never the key itself), and it's explicitly excluded
from the audit trail (`AuditLogService::EXCLUDED_KEYS`). `AiAssistantService::client()` prefers the
Settings-stored key over `.env` when both are present, so `.env` remains a working fallback, not a hard
requirement.

**Verification at last merge**: 905/905 backend + 694/694 frontend tests green at PR #9, plus additional
`ClinicSettingTest`/`AiAssistantGatingTest` coverage in PR #11 (16/16 for the new API-key cases); 34/34 E2E
green at PR #9's final CI run. A real bug was found and fixed along the way: `AiAssistantService`'s search
tool called `->getCollection()` on a `LengthAwarePaginator` typed against the *interface*, which doesn't
expose that method — fixed by switching to `collect($paginator->items())`.

**What's explicitly not built**: any AI feature beyond the five named above (no autonomous scheduling,
no auto-diagnosis, no patient-facing chatbot) — consistent with `PROJECT_CONTEXT.md`'s "AI is an assistant
only... never allow AI to make medical decisions" instruction, and with the separate long-term vision (API-
first, event-ready, a dedicated future integrations layer) that is explicitly **not** current scope.

---

## 8. Documentation Index

### Root-level

| File | Description |
|---|---|
| `PROJECT_CONTEXT.md` | The original architecture/stack/philosophy brief plus a "Current Status" narrative, frozen as a historical record as of a 2026-08-07 status-update paragraph pointing to this file for anything current (§0's drift, now corrected). |
| `ARCHITECTURE_REVIEW.md` | A single point-in-time architecture review (dated 2026-07-11, pre-first-commit), covering Dashboard/Auth/Users/Roles. |
| `CHANGELOG.md` | Full chronological, per-module change history. Current through PR #15 as of 2026-08-07. |
| `TECH_DEBT.md` | Deliberately-deferred work, one entry per item, each naming its own revisit condition. The single most detailed, most current diagnostic record in the repo — actively maintained through PR #15-era entries. |
| `README.md` | Quick-start: Docker Compose commands, service endpoints. |
| This file (`docs/PROJECT_STATUS.md`) | Since 2026-08-04 — the living, continuously-updated single source of truth for project status; enforced every session by repo-root `CLAUDE.md`. |

### `docs/`

| File | Description |
|---|---|
| `architecture.md` | System architecture reference (modular monolith, service layer, thin controllers). |
| `database-design.md` | Schema/DB conventions (UUID, soft deletes, audit logs). |
| `api-guidelines.md` | REST/API conventions (error shapes, resource wrapping, pagination). |
| `coding-standards.md` | PSR-12/Pint/PHPStan/testing conventions. |
| `decisions.md` | The full chronological Architecture Decisions log (§5 is a summary of this). |
| `roadmap.md` | Per-module status table; points to this file for the new 8-phase roadmap rather than duplicating it. |
| `deployment.md` | Production runbook: Docker/nginx/SSL topology, backup/restore procedure (rehearsed end-to-end 2026-07-20). |
| `design-system.md` | Typography, tokens, visual language — marked "frozen" pre-Premium-Redesign; that redesign is the anticipated exception to the freeze. |
| `demo-guide.md` | How to run the stack, demo credentials, seeded sample data walkthrough. |

### `docs/modules/`

| File | Description |
|---|---|
| `dashboard.md`, `authentication.md`, `roles-permissions.md`, `users.md`, `patients.md` | Final module docs for the five earliest, pre-git modules. |
| `layout-architecture.md` | Design doc for the sidebar+header application shell. |
| `appointments-design-draft.md`, `appointments-ui-design.md`, `appointments.md` | Draft → UI design → final doc, for Appointments (Production Ready ✅). |
| `dental-chart-design-draft.md`, `dental-chart-implementation-plan.md`, `dental-chart-rendering-design.md`, `dental-chart.md` | Draft → implementation plan → rendering design → final doc, for Dental Chart (Production Ready ✅). |
| `treatment-plans-design.md`, `treatment-plans.md` | Design → final doc. Final doc predates PR #12's clinic-wide list addition — likely needs a follow-up update (§11). |
| `billing-design.md` | Design doc only — **the final `billing.md` doc is still missing**, a known open gap (§3, §9). |
| `payments-design.md` | Design doc for Payments (no separate final doc). |
| `clinical-notes-design.md`, `inventory-design.md`, `laboratory-design.md`, `imaging-design.md`, `reports-design.md`, `settings-design.md` | Design docs doubling as the de facto final reference for each Production Ready ✅ module. |
| `ai-assistant-design.md` | Design doc for the original AI Assistant module (PR #9). |
| `ai-assistant-settings-api-key-design.md` | Design doc for the Settings-managed API key addition (PR #11) — was still stamped "Design Phase" despite being fully implemented until PR #11 itself fixed that. |
| `frontend-ux-redesign.md` | The original 4-phase Frontend UX & Navigation Redesign plan. Partially superseded by the doc below. |
| `frontend-visual-redesign-design.md` | Premium Visual Redesign design doc — supersedes `frontend-ux-redesign.md`'s Phase 2, adds the icon-migration workstream, pulls Phase 4 forward. **Currently the active design doc** for ongoing frontend work. |

---

## 9. Technical Debt

Full detail lives in `TECH_DEBT.md` (one dated entry per finding, each with its own revisit condition) —
this is the current **Open** set, prioritized by this report's own judgment of real-world impact, not by the
order they appear in that file. The two former top items — **CI red on `main`** and **`DashboardService.
today_appointments`** — were both resolved 2026-08-07 via PR #15 (see §2, §12, and `TECH_DEBT.md`'s own
RESOLVED notes on both entries); nothing has replaced them as High priority.

| Item | Priority | Why deferred |
|---|---|---|
| No permanent E2E suite for Billing, Payments, or Treatment Plans | **Medium** — these 3 modules are otherwise fully functional and unit/feature-tested | Every other module got its E2E suite built in its own implementation pass; these three were built before that convention solidified |
| Billing has no backend Feature-test suite (unit-only) and no final `modules/billing.md` doc | **Medium** | Same root cause as above — predates the now-standard "tests + final doc before Done" bar |
| Backend test suite never exercises real PostgreSQL (forced to SQLite via `phpunit.xml`) | **Medium** — already caused one real production-breaking migration bug to go undetected for a full day (Payments' self-referencing FK) | No CI job runs `migrate:fresh` against a real Postgres service container yet |
| `App\Rules\BelongsToPatient` throws a real SQL error against `TreatmentPlanItem` | **Medium** — fixed in Imaging's own Form Requests, but the **identical bug still exists, unfixed, in Laboratory's** `Store/UpdateLabCaseRequest` | Deliberately not touched during Imaging's PR to keep that branch's diff scoped |
| `dental_conditions` reused as the Treatment Plans pricing catalog | **Medium**, growing — blocks clinic-specific/insurance/regional pricing | "Approved with caution" for V1 only; needs a dedicated pricing catalog once multi-tenant or insurance pricing is a real requirement |
| PWA manifest / service worker doesn't exist app-wide yet | **Medium** — every module's "PWA-installable" bar has meant responsive/touch-ready, not actually installable today | Cross-cutting work, not any single module's responsibility |
| No S3/offsite backup configured (local-disk-only) | **Medium**, becomes **High** before real clinic onboarding | `backup.sh`'s S3 sync line exists but is commented out — no bucket provisioned yet |
| PrimeVue `id` vs `inputId` accessibility defect | **Low-Medium**, codebase-wide | Confirmed in `UsersView.vue` and pre-Inventory-fix files; fixed piecemeal per module, not yet swept project-wide |
| `vue-i18n` ships its full compiler+runtime build (~84 KB gzip) on every route | **Low** — real but not urgent bundle-size cost | Needs `@intlify/unplugin-vue-i18n` + a runtime-only swap, its own dedicated verified pass across all 3 locales |
| `router/index.test.ts` flaky under full-suite parallel load (local Windows Docker only) | **Low** | Confirmed environment-induced, not a logic defect; passes 11/11 in isolation |
| Two icon systems (PrimeIcons + Lucide) temporarily coexist | **Low**, self-resolving | Expected mid-migration state (§6); resolves as Premium Visual Redesign's remaining steps land |
| `JsonResource::withoutWrapping()` — global convention never explicitly re-confirmed with user | **Low** | Flagged since 2026-07-11, no actual problem caused |
| Phase 5C (scheduled/administrative notifications) not yet E2E/real-browser verified | **Low**, temporary — backend verification is thorough (1211/1211, PHPStan clean); UI risk is low (config-only additions to unmodified components) | Playwright browsers aren't pre-installed in this dev container; resolves once a `workflow_dispatch` CI run or a manual browser check confirms it, matching Phase 5A/B's own pre-merge E2E gate |
| Multi-branch not implemented | **Low**, by design | Deferred until a real second-location requirement appears — deliberate, not an oversight |

---

## 10. Current Project Health

Percentages are this report's own qualitative assessment, weighing what's verified (tests, CI, docs) against
what's known-incomplete (§9) — **not** output from an automated coverage/quality tool, and should be read as
directional, not precise.

| Axis | Assessment | % |
|---|---|---|
| **Backend** | 17/17 roadmap modules implemented, layered consistently (Migration → Model → Service → Policy → Controller), Larastan level 5 clean on every CI run. Weakest points: no real-Postgres migration CI check, one known unfixed SQL-error bug in Laboratory, `dental_conditions` pricing catalog is a known-temporary shape. | **85%** |
| **Frontend** | Consistent component/store/service layering across every module, full i18n parity discipline, a real (if mid-flight) design system. Bundle-size and remaining icon-migration work are the visible gaps; component-test depth varies by module (some modules only have store/service tests, no per-component tests). | **80%** |
| **Testing** | Backend and frontend unit/feature suites are extensive and consistently green; CI's E2E job is now fully green too (39/39, as of 2026-08-07). Remaining gap: 3 modules (Billing, Payments, Treatment Plans) still lack any E2E coverage. | **80%** |
| **Documentation** | Unusually thorough (a design doc + final doc pattern, a dedicated decisions log, a dedicated tech-debt log); the 3 staleness gaps §0 found (2026-08-04) were reconciled 2026-08-07, and this file's §15 protocol + `CLAUDE.md` now exist specifically to keep it from recurring. | **85%** |
| **Security** | Sanctum SPA auth, rate limiting (login + general API), encrypted-at-rest API key, audit logging on sensitive models, policy-gated authorization checked at API + frontend router layers, AI module fails closed. Gaps: no malware/virus scanning on uploaded images, no S3/offsite backup yet. | **80%** |
| **Architecture** | Modular monolith, consistent service-layer discipline, a real (and enforced) datetime-handling standard, explicit SaaS multi-tenant and PWA/mobile-first principles checked at every module's design phase since 2026-07-27. | **90%** |
| **Maintainability** | PSR-12/Pint/ESLint/Prettier all CI-enforced; `TECH_DEBT.md` and `docs/decisions.md` actively prevent debt from being silently forgotten. Some documentation-sync discipline has slipped recently (§0) — the direct motivation for this file. | **80%** |
| **Production Readiness** | System-Wide Production Gate closed 2026-07-20 (rate limiting, hardened Docker/nginx/SSL, rehearsed backup/restore, CI/CD). `main`'s CI is fully green again (2026-08-07) and the Dashboard number is fixed. Remaining gaps: no offsite backup, 3 modules below the project's own E2E bar. Not yet ready to onboard a real clinic without addressing those. | **72%** |

---

## 11. Remaining Work

Ordered by priority (High → Low), each with why it matters, its likely impact, a rough time estimate, and
dependencies. Estimates are this report's judgment, not a formal estimation exercise.

| # | Item | Why it matters | Impact | Est. effort | Dependencies |
|---|---|---|---|---|---|
| 1 | **Write permanent E2E suites for Billing, Payments, Treatment Plans** | Closes the last gap between these 3 modules and this project's own "Production Ready" bar | Medium | Medium (3 specs, design docs already name the scenarios to cover) | None |
| 2 | **Give Billing a backend Feature-test suite + final `modules/billing.md` doc** | Billing is the only module still missing both | Medium | Small–Medium | None |
| 3 | **Continue Premium Visual Redesign**: remaining icon migration (~62 files), Phase 3 Data Tables | The active frontend-only initiative — Dashboard redesign (former overlap with roadmap Phase 3) resolved 2026-08-09, see "Resolved this pass" below | Medium (UX quality, not correctness) | Large (multi-step, own design doc already scopes it) | None blocking, but should follow the two-phase design→implementation workflow per file/step |
| 4 | **Add a real-Postgres migration-verification CI step** | Already caused one production-breaking bug to hide behind 643 passing SQLite tests for a full day | Medium | Small (one new CI job step, not the full suite) | None |
| 5 | **Provision S3/offsite backup** | Local-disk-only backup doesn't survive VPS loss | Medium, escalates to High before real clinic onboarding | Small (config-only — code path already exists, commented out) | Choice of provider (Backblaze B2 / Wasabi / AWS S3) |
| 6 | **App-wide PWA manifest + service worker** | Every module has been built "PWA-ready" but nothing is installable yet; also roadmap Phase 6 | Low–Medium | Medium | None |
| 7 | **`vue-i18n` runtime-only build + message precompilation** | ~84 KB gzip bundle-size win, app-wide | Low | Small–Medium (needs careful 3-locale regression pass) | None |
| 8 | **Codebase-wide PrimeVue `id`→`inputId` accessibility sweep** | Real but narrow a11y gap, fixed piecemeal so far | Low | Small | None |

**Resolved this pass**: the `BelongsToPatient` SQL-error bug in Laboratory's Form Requests (former item #4) was fixed as part of Phase 2.4 (PR #24, 2026-08-08) — see `TECH_DEBT.md`'s Resolved section. **Phase 2.6b: Timeline UI** (former item #1) merged via PR #32, then PR #33 fixed 2 real bugs post-merge CI's E2E job caught in the E2E spec itself — see §12. This closes out **Phase 2 (Patient Profile Redesign) in full.** **Dashboard redesign** (former item #3's overlap between roadmap Phase 3 and the Premium Visual Redesign's own §6 plan) resolved 2026-08-09 via Dashboard 2.0 (PR #35), which absorbed and implemented it in full — see §12.

---

## 12. Immediate Next Step

**Current (2026-08-09): Phase 4 (Advanced Permissions & Audit) is complete — merged to `main` via PR #37,
post-merge CI fully green.** Permissions Foundation, Policy refactor, Audit Overhaul, Frontend, and
E2E/docs closure are all done (Backend 1145/1145, Frontend 969/969, full E2E coverage of the design doc's
§9 required scenarios — behavior-preservation via the untouched pre-existing `permissions.spec.ts` tests
still passing, self-lockout, matrix-edit-persists-and-audits, all 4 endpoints' 403s; see §2's 2026-08-09 rows
and §4/CHANGELOG.md for full per-step detail). Final 3-locale i18n parity re-verified after Step 5
(1453/1453/1453, zero drift). `docs/decisions.md`'s role-hierarchy-still-deferred note and `TECH_DEBT.md`'s
Appointment-audit-log item both updated. Pushed to `origin/feature/phase4-permissions-foundation`;
`workflow_dispatch` CI run (`31328029404`) caught one real ordering bug local runs couldn't (a
`page.waitForResponse()` registered *after* the `page.goto()` that triggers the response — a race a fast
environment loses and a slow one happens to win, which is exactly why this environment's own local Docker
latency isn't this project's verification authority for E2E); fixed and re-run (`31328615397`) fully green —
Backend, Frontend, and E2E (53/53) all `success`. Per the user's explicit instruction, a full final diff
review followed — 103 files / 14 commits / +5543/-215 against `main`, checked against 14 points (scope,
temp/debug artifacts, migrations-from-clean, secrets, `hasPermission()` consistency, audit architecture
consistency, API contracts, real vs. weakened test assertions, E2E shared-state restoration, i18n parity,
doc/code consistency, Notification-System scope exclusion, diff size/unnecessary-refactor risk, regression
risk) — **zero blockers found**, two non-blocking notes recorded (`docs/decisions.md`'s `trustProxies` entry
gained a rate-limiter-blast-radius addendum; `TECH_DEBT.md` gained a migration-rollback item). User approved;
**PR #37 opened, then merged to `main`** (`0bdf3d8`). Post-merge CI on `main` (the real run, unlike the
PR-triggered one, which skips E2E) re-confirmed **Backend, Frontend, and E2E all `success`**
(run `31333946387`). **Milestone: "Phase 4 — Advanced Permissions & Audit Complete."** No new feature work
was added to Phase 4's scope during close-out (docs-only). **Next: Notification System design phase** —
In-App + Appointment notifications; WhatsApp explicitly flagged as a future channel to design for, not to
build now.

*(The narrative below, through the "Phase 3 — Dashboard 2.0" section, is this file's historical record of
Phase 2/Phase 3/Phase 4's own Immediate-Next-Step reasoning at the time each was written — preserved as
history, not current status; §2's Timeline and the paragraph above are the current source of truth.)*

**Phase 2: Patient Profile Redesign — design approved 2026-08-07. Phase 2.1 (Foundation) merged to
`main` via PR #18 (2026-08-07); Phase 2.2 (Billing) merged via PR #20 (2026-08-08); Phase 2.3
(Medical History) merged via PR #22 (2026-08-08); Phase 2.4 (Laboratory) merged via PR #24
(2026-08-08, `9de50fb`); Phase 2.5 (Documents) merged via PR #27 (2026-08-08, `98ae299`) — see
`docs/modules/patient-documents-redesign-design.md` for the full spec.
Phase 2.6 (Timeline) — the final sub-phase — completed its Design Phase** (2026-08-09, PR #29)
— see `docs/modules/patient-timeline-redesign-design.md` for the full spec, produced by auditing
the actual codebase (confirmed zero event-driven infrastructure exists anywhere in this project
today) and resolving 6 structural gaps the umbrella doc's already-detailed Timeline sketch left
open: the event-dispatch mechanism (explicit inline `event()` calls, one synchronous listener, no
queue — none actually runs today), a 24-event first-cut inventory across the 9 candidate services,
the `PatientActivityPolicy` category-to-policy lookup mechanism (a static category→model map checked
once per category per request, reusing each category's real owning policy — resolves the
correctness-vs-performance tension without a drift-prone duplicate role map), and three scope calls
(no historical backfill, `patient.updated` excluded to keep the Security Architecture Decision's
"not merged" boundary unambiguous, Appointments-tab retirement deferred out of this already-highest-risk
phase). **All 7 §16 decisions approved by the user as recommended.** Before starting 2.6a
implementation, verifying each candidate policy's real `viewAny()` rule against the approved category
table found a genuine conflict (the `clinical` category conflated three subject types with different
permission rules — see PR #30) — fixed and confirmed with the user before writing any code, per this
project's established protocol for design-vs-implementation conflicts.

**Phase 2.6a (Timeline foundation), implemented 2026-08-09** on
`feature/patient-profile-phase2-6a-timeline-foundation` (design doc:
`docs/modules/patient-timeline-redesign-design.md`, Implementation Summary section): `PatientActivity`
model/migration (append-only) + `Patient::activities()`; **one generic `PatientActivityOccurred`
event**, not 24 near-identical classes — a refinement of the design doc's original per-event-class
phrasing, not a reopening of §16 decision 1 (which was about mechanism, not file-per-type
granularity) — dispatched via `event()` at all 24 call sites across `AppointmentService`,
`TreatmentPlanService`, `ClinicalNoteService`, `InvoiceService`, `PaymentService`,
`MedicalHistoryService`, `LabCaseService`, `PatientImageService`, `PatientDocumentService`; one
synchronous `RecordsPatientActivity` listener relying on Laravel's default auto-discovery —
**confirmed genuinely wired, not assumed**, by a dedicated integration test that calls a service
method without faking events and asserts a real database row. `PatientActivityPolicy`'s
`CATEGORY_SUBJECT_MAP` implements §7's mechanism exactly. `GET /patients/{patient}/activities` —
patient-scoped, paginated, category/date-range filterable. **The security-critical test §9A itself
mandates is implemented and passing**: a receptionist's request never returns `clinical_notes`-category
rows, asserted directly against the response body. One real PHPStan finding fixed (a generic-`Model`-
typed magic-property access switched to `getAttribute()`) — confirmed via a targeted run on the new
files, not the full known-noisy local run. Backend: 1054/1054 tests green (1020 + 34 new: 25 dispatch
tests, 9 controller/security tests), Pint clean. **Merged to `main` via PR #31 (`cb7b52d`,
2026-08-09), post-merge CI re-verified.**

**Phase 2.6b (Timeline UI), implemented 2026-08-09** on
`feature/patient-profile-phase2-6b-timeline-ui`: `ActivityTimeline.vue` — one embeddable component
(props `patientId`/`category`/`pageSize`/`compact`) backing all three call sites the design doc's
§9.4/§15.2 requires as a single component, not three: the new `timeline` tab (unfiltered, category
chips shown), Billing's Payment History sub-section (`category="billing"`, replacing its
`FutureFeaturePlaceholder`), and an Overview recent-activity preview card (`compact`, a "View
Timeline" button that switches `PatientDetailView.vue`'s active tab). A `patientActivities` Pinia
store keyed by `patientId::category::perPage`, not patient id alone like every sibling store — a
deliberate, necessary deviation: `PatientDetailView.vue`'s `Tabs` isn't `lazy`, so PrimeVue's
`TabPanel` keeps every tab mounted via `v-show`, meaning up to three `ActivityTimeline` instances
are live simultaneously on one patient page, each wanting a different slice; a patient-id-only cache
would let one instance's fetch silently overwrite another's. "Load more" pagination (appends pages,
never replaces — the design doc's explicit choice over `Paginator`-style page-jumping) and a
category-filter chip row. The chip row was built from scratch, not copied from an existing pattern:
the design doc cited Laboratory's status filter as the mobile chip-row precedent to match, but that
turned out to be a plain `Select` dropdown on every viewport with no chip-row component anywhere in
the codebase — flagged and confirmed with the user before implementation (the same design-vs-
codebase-conflict protocol as 2.6a's category-taxonomy fix), who chose to build the chip row as
originally specified rather than fall back to a dropdown. i18n added for all 3 locales. Frontend:
933/933 tests green (27 new — the store's query-isolation logic, the component's filter/pagination/
compact-mode behavior, and updated assertions in `PatientBillingPanel.test.ts`/
`PatientDetailView.test.ts` for the real wiring), `vue-tsc`/ESLint/Prettier clean. Playwright E2E
written (`e2e/timeline.spec.ts`) covering cross-module aggregation, category filtering, the Overview
preview's tab-jump, and the security-critical case design doc §18 mandates — a receptionist session
never sees `clinical_notes`-category rows even when explicitly filtering for them — but could not be
run live in this session: the local dev Docker stack exhibited a pre-existing, unrelated 5-9-second
per-HTTP-request latency (reproduced against unmodified `auth.spec.ts` and via raw `curl` against
trivial endpoints, with every container otherwise idle), consistent with this project's own
documented stance that CI, not the local Windows Docker environment, is the verification authority
for this suite. **Merged via PR #32 (`70b6ba6`).**

**Post-merge CI on `main` caught what local verification couldn't**: unlike a PR-triggered run,
a push to `main` actually executes the E2E job (not skipped), and it found 2 real bugs in
`e2e/timeline.spec.ts` itself — (1) the lab case's `case_number` was read from `<h1>` immediately
after navigation, before the async fetch replaced its `"Lab Cases"` fallback text with the real
value; (2) an unscoped `getByText('Clinical note signed')` hit Playwright's strict-mode violation,
because the Overview preview's `ActivityTimeline` instance and the Timeline tab's own instance are
both mounted simultaneously (§13's "up to three instances" point above, not merely theoretical —
it's exactly what broke this test) and both render the identical summary text. Fixed by waiting for
the lab case's status tag before reading `case_number`, and scoping every Timeline/Overview
assertion to its own `tabpanel` via `getByRole('tabpanel', { name: ... })`. **Merged via PR #33**
(`83c584b`); post-merge CI re-run, all three jobs (Backend/Frontend/E2E) green.

**Phase 2 (Patient Profile Redesign) is complete**: all 7 sub-phases (2.1 Foundation, 2.2 Billing,
2.3 Medical History, 2.4 Laboratory, 2.5 Documents, 2.6a Timeline foundation, 2.6b Timeline UI) are
merged to `main`, each with its own post-merge CI confirmation, closed out via **PR #34**
(2026-08-09).

## Phase 3 — Dashboard 2.0

**Design phase (2026-08-09)**: with Phase 2 closed, two candidates competed for "next" — the
8-phase roadmap's own Phase 3 (Dashboard 2.0), and finishing the still-unfinished Premium Visual
Redesign initiative, whose own §6 already scoped a Dashboard restyle that had never been built. The
user chose "Functional 2.0": absorb the restyle into Dashboard 2.0's scope rather than treat them as
two separate efforts. Step 1 analysis audited the actual current `DashboardService`/`DashboardView`
code (3 stat cards, no trends, no role gating) and researched competing dental PM systems (Dentrix,
CareStack, Jarvis Analytics, Adit) — the recurring pattern across real competitors is
production/collections-vs-goal, A/R aging at a glance, and unscheduled-accepted-treatment tracking,
not just prettier static counters, which shaped the "Functional 2.0" direction the user then
approved over a restyle-only alternative.

A backend ground-truth audit (Explore-agent-assisted, all findings file:line-cited) before writing
the design doc found two things that changed scope from the initial proposal:

1. **A real, pre-existing security gap**: `DashboardController::summary()` had no Form Request, no
   policy, no Gate check of any kind — any authenticated role could call it, and it already returned
   `monthly_revenue`, the same figure `reports/collections` restricts to admins via
   `view-financial-reports`. This predates Dashboard 2.0 and is fixed as part of it, not introduced
   by it — see `docs/decisions.md`'s 2026-08-09 entry.
2. **No goal/target concept exists anywhere in the schema** (confirmed by a full-backend search) —
   building "actual vs. goal" would mean a new `ClinicSetting` field + Settings UI + migration, real
   new scope for a phase otherwise scoped around reusing existing services. The user chose
   period-over-period trend instead (this month vs. last, computed by calling
   `ReportService::production()`/`collections()` twice with different date ranges) — zero new
   storage, goal-setting explicitly deferred rather than dropped.

Design doc: `docs/modules/dashboard-2.0-design.md`. Approved by the user 2026-08-09 ("لا أرى حاليًا
سببًا لتعديل التصميم قبل التنفيذ" — no reason to change the design before implementing), with two
explicit conditions: verify after every step before moving to the next, and land the whole phase as
one PR with any deviations documented before requesting merge.

**Implementation (2026-08-09), on `feature/dashboard-2-0`**, in the design doc's §8 sequence, each
step verified before the next:

1. **Backend**: `DashboardService::summary()` now returns `unscheduled_accepted_treatment` (a new
   query — `TreatmentPlan.status=Accepted` + `TreatmentPlanItem.status=Planned` +
   `appointment_id` null or pointing to a cancelled `Appointment`, since
   `TreatmentPlanItemStatus`'s own docblock defines "scheduled" exactly that way) instead of
   `monthly_revenue`. New `DashboardService::financialSummary()` (production/collections trend +
   A/R aging, each a thin wrapper around an existing `ReportService` method) backs a new
   `GET /dashboard/financial-summary`, gated by a new `DashboardFinancialSummaryRequest` calling
   the same `view-financial-reports` Gate `reports/production`/`collections`/`ar-aging` already use
   — no new authorization concept. Backend: **1069/1069 tests green** (1043 + 26 new: 17 in
   `DashboardTest`, 9 in the new `DashboardFinancialSummaryTest`), Pint clean. A real Pint
   `php_unit_method_casing` miss (`test_ar_aging_reflects_report_service_arAging_summary_directly`
   — the exact class of bug Phase 2.3's `MedicalHistoryPolicyTest` hit before) was caught locally
   before pushing, not by CI. PHPStan targeted run on the new files surfaced the same class of
   `casts()`-model false positive `TECH_DEBT.md` already documents (confirmed by running the same
   targeted analysis against unmodified `ReportService.php`, which shows the identical error
   pattern — not a regression).
2. **Frontend data layer**: `dashboard.ts` Pinia store (`fetchSummary`/`fetchFinancialSummary` as
   two independent fetches, not one — matching the backend split), `auth.canViewFinancials`
   (mirrors `canManageAppointments`'s existing naming convention). `DashboardView.vue` calls
   `fetchFinancialSummary()` only `if (auth.canViewFinancials)` — skips the network call entirely
   for a role that would get a `403`, not just hides the result.
3. **Frontend widgets + restyle**: `FinancialSnapshotWidget.vue` (admin-only, one card not three)
   and `UnscheduledTreatmentWidget.vue` (all roles, reuses Phase 2.1's `EmptyState.vue` — confirmed
   generic enough to reuse as-is, not patient-profile-specific) on `DashboardView.vue`; restyled
   stat cards (`h-12 w-12` tinted badges, `text-2xl`, only 2 universal cards now since
   `monthly_revenue` moved behind the admin gate and can't be a plain stat card everyone sees — a
   deviation from the design doc's literal "revenue=purple 3rd card" phrasing, superseded by the
   security fix itself); gradient-styled `AiQuestionBox.vue`; `gap-6` spacing throughout — absorbs
   `frontend-visual-redesign-design.md` §6 in full, now marked superseded there and in
   `docs/roadmap.md`. One small addition beyond the design doc's literal text:
   `PatientDetailView.vue` now reads an optional `?tab=` query param on first load (validated
   against the same tab-key list every visibility check already uses) so
   `UnscheduledTreatmentWidget.vue`'s "View plan" button can deep-link to that patient's Treatment
   Plans tab — the existing "View Timeline" button could set `activeTab` directly since it's the
   same component instance; an external navigation from the Dashboard has no such instance to reach
   into, so it needed a real (if narrow) entry point. Frontend: **966+/966+ tests green** (933 + 33
   new: `dashboard.ts` store tests, `auth.canViewFinancials` test, `FinancialSnapshotWidget.vue`/
   `UnscheduledTreatmentWidget.vue` component tests), `vue-tsc`/ESLint/Prettier clean.
4. **E2E, i18n, docs**: new `e2e/dashboard.spec.ts` — the first for this module — covering the
   security-critical case this project's §9A precedent established: a non-admin session never shows
   Financial Snapshot in the DOM, **never even issues the network request** for
   `/dashboard/financial-summary` (asserted via `page.on('request')`, not just DOM absence), and a
   direct fetch to that endpoint from a receptionist session gets a real `403` regardless of what
   the UI does. i18n: 8 new keys × 3 locales (`ar`/`en`/`tr`), parity verified programmatically
   (1284 → 1292 keys, identical count across all three). This file, `CHANGELOG.md`, and
   `docs/decisions.md` updated in the same pass, `frontend-visual-redesign-design.md` §6 marked
   superseded.

**Verification**: local Windows Docker environment reproduced the same pre-existing
per-HTTP-request latency this file already documented for Phase 2.6b (a single `sanctum/csrf-cookie`
request measured ~11s via raw `fetch`, confirmed independent of Playwright or this phase's code) —
worked around with generous timeouts rather than treated as a regression. Manually verified in a
real browser: admin sees real seeded data (118 patients, real production/collections/A-R figures,
correctly *no* trend badge shown since the seeded demo data has no prior-month activity — the "don't
fabricate a trend" rule working as designed, not a bug); dentist correctly never sees Financial
Snapshot, sees Unscheduled Treatment (empty state, correctly rendered via `EmptyState.vue`); no
console errors beyond the expected pre-login `401` on `/api/user`. `e2e/dashboard.spec.ts` itself
was written and reviewed but not confirmed green against a live run in this session (the same
environment latency affected the E2E harness's own fixed 25s login timeout on a heavily-loaded
container) — CI is the verification authority for this suite, per this project's established
stance. PR #35's own PR-triggered CI run skipped E2E by design (same convention as every prior
phase); confirmation came from **post-merge CI on `main`**, which does run it.

**Merged as PR #35** (`0f04b35`, 2026-08-09) — see §4. Post-merge CI on `main` re-run:
**Backend, Frontend, and E2E all `success`**, including `e2e/dashboard.spec.ts` genuinely passing
end to end. **Phase 3 (Dashboard 2.0) is complete.** Next: Phase 4 (Advanced Permissions & Audit)
design phase.

Step 1 (analysis) and Step 2 (design document,
`docs/modules/patient-profile-redesign-design.md`) are both approved. Governing decisions: merge
Invoices+Payments into one Billing tab (backend stays split), a structured Medical History foundation
(Allergies/Conditions/Medications/Notes, not free text), a V1 Documents foundation (no versioning/sharing/
OCR yet), and a dedicated `PatientActivity` event architecture for Timeline — formally elevated to a
**Security Architecture Decision** (design doc §9A, `docs/decisions.md` 2026-08-07 entry): Timeline
permissions are enforced server-side, per category, never client-side.

Implementation is sequenced into 7 sub-phases (design doc §17), each its own PR, low-risk-first.

**Phase 2.1 (Foundation), merged via PR #18**: `patients.ts` Pinia store (patient-level state only, not
a replacement for domain stores like `treatmentPlans.ts`/`invoices.ts`), `patientImages.ts` store
(Imaging retrofit), pagination added to Treatment Plans/Clinical Notes' patient-scoped endpoints
(15/page), a config-driven tab list in `PatientDetailView.vue`, `EmptyState.vue` (first component in a
new `components/common/` folder) retrofitted into the touched panels, and the Patients-module Lucide
migration. One scope adjustment found during implementation: Invoices/Payments pagination was deferred
to Phase 2.2 — see that entry below for the resolution.

**Phase 2.2 (Billing), merged 2026-08-08 via PR #20**: the former separate Invoices/Payments tabs collapse
into one **Billing** tab (`PatientBillingPanel.vue`) with an Outstanding Balance hero + summary row
(`BillingSummaryCard.vue`, fed by a new `GET /patients/{patient}/billing-summary` aggregate endpoint —
`BillingSummaryService`, SQL-aggregate-only per design doc §11.4) and a `SelectButton` switching
between Invoices/Payments (both **reused as-is**, zero edits, per §5.1) and a Payment History
placeholder (`FutureFeaturePlaceholder.vue` — the real Timeline-backed feature isn't buildable until
Phase 2.6). Resolves Phase 2.1's deferred pagination debt: `InvoiceController::index()`/
`PaymentController::index()` now paginate (15/page); the new `GET /invoices/{invoice}/payments`
endpoint replaces `InvoicePaymentsPanel.vue`'s former client-side filter; `ApplyPaymentDialog.vue`'s
invoice picker now uses a dedicated `?status=issued` fetch instead of the paginated store getter. Also
adds a mobile (`<768px`) dropdown tab switcher to `PatientDetailView.vue` (previously had none).
`InvoiceService`/`PaymentService` were **not** modified — the new aggregate lives in a standalone
`BillingSummaryService` instead, and the new endpoint queries `Invoice::payments()` directly. No DB
migration needed. Backend: 952 tests green (Pint clean). Frontend: 832 tests green, type-check/lint
clean. **One new deliberate trade-off logged in `TECH_DEBT.md`**: since `PatientInvoicesPanel.vue`/
`PatientPaymentsPanel.vue` are reused unchanged, they show only page 1 (no `Paginator`) inside the
Billing tab.

**Phase 2.3 (Medical History), merged 2026-08-08 via PR #22 (`aa704b6`)**: three new tables (`patient_allergies`, `patient_medical_conditions`, `patient_medications` —
UUID PK, `SoftDeletes`, `Auditable`, matching every other clinical-adjacent table), one `MedicalHistoryService`
and one `MedicalHistoryPolicy` for all three entities (design doc §6.3/§6.4 — avoids three near-identical
services/policies for what is one logical feature; the policy is registered against all three models via
`Gate::policy()` in `AppServiceProvider`, since Laravel's naming-convention auto-discovery only maps one
policy per model), one `MedicalHistoryController` (12 endpoints, paginated at 15/page), a backfill migration
that migrates any non-empty legacy `patients.allergies` text into one best-effort `patient_allergies` row per
patient (`patients.allergies` kept, deprecated, not dropped this phase — design doc §7), a `medicalHistory.ts`
Pinia store + `MedicalHistoryPanel.vue` (three presentational list components + one create/edit dialog per
entity), a new `medicalHistory` tab in `PatientDetailView.vue` right after Overview (design doc §4 tab
order), and full `ar`/`en`/`tr` i18n parity (verified programmatically: 48/48 keys match across `ar`/`en`/`tr`).
Backend: 998/998 tests green (Pint clean; PHPStan's `forPatient()`/`User::$role`/`$name` findings on the new
files are the same pre-existing local-only false positives already confirmed on unmodified files like
`ClinicalNoteController` — see `TECH_DEBT.md`). Frontend: 867/867 tests green, type-check/ESLint clean.

**One real formatting gap found by CI, not local checks, and fixed**: PR #22's first CI run failed two jobs
in under a minute — Backend Pint flagged `tests/Unit/Policies/MedicalHistoryPolicyTest.php` for
`php_unit_method_casing` (three test method names mixed `viewAny` mid-identifier into otherwise-snake_case
names, e.g. `test_allergy_viewAny_and_view_are_open_to_all_roles`), and Frontend's Format check flagged 7 of
this phase's own new files as genuinely non-Prettier-compliant. **The earlier local verification claiming
these were all "pre-existing, confirmed via `git stash`" was itself flawed**: `git stash` without `-u` does
not stash untracked files, so the new `medicalHistory` files stayed present in both the "before" and "after"
comparisons, making that comparison meaningless for judging whether *those specific new files* were
compliant — it only validly covered modifications to already-tracked files (e.g. `PatientDetailView.vue`,
which CI's Format check did **not** flag, confirming that file's pre-existing-drift claim was actually
correct). Both issues fixed directly: the three test methods renamed to pure snake_case
(`test_allergy_view_any_...`), the 7 files run through `prettier --write`. Re-verified with a proper
`git worktree add ... origin/main` baseline check: this branch's `format:check` now reports **exactly 235**
files — identical to real `origin/main`'s own count — confirming zero net-new Prettier issues remain, not
just an assumption. All local checks (998/998 backend, 867/867 frontend, type-check, ESLint, Pint) re-run
clean after the fix; pushed as a follow-up commit to PR #22. **CI re-ran fully green**: Backend pass, Frontend pass, E2E skipping (expected — this project's E2E job only runs on push-to-main, not the `pull_request` trigger, per this file's own established convention). The review was then independently re-confirmed against GitHub's live PR/CI state (not taken on faith from a pasted summary) — `state: OPEN`, `mergeable: MERGEABLE`, `mergeStateStatus: CLEAN`, both CI checks `SUCCESS`. **PR #22 merged 2026-08-08 (`aa704b6`)**; `main`'s own post-merge CI run (`31248165133`) confirmed fully green — Backend, Frontend, and E2E (all three run on the push-to-main trigger) each `success`.

**Phase 2.4 (Laboratory), merged 2026-08-08 via PR #24 (`9de50fb`)**, implemented on
`feature/patient-profile-phase2-4-laboratory` (design doc: `docs/modules/patient-laboratory-redesign-design.md`,
produced by re-auditing the actual Laboratory code on `main` rather than trusting this file's/the umbrella
doc's earlier outline — that audit found the outline incomplete, see that doc's §0): `Patient::labCases()`
+ `LabCase::scopeForPatient()` (mirrors `TreatmentPlan::scopeForPatient()` exactly), new
`GET /patients/{patient}/lab-cases` (paginated 15/page via `LabCaseController::forPatient()`), the flat
`GET /lab-cases` endpoint's dead `?patient_id=` filter removed (confirmed unused by any frontend caller),
`patientLabCases.ts` store (same Map-cache/pagination shape as `treatmentPlans.ts`), and a new **read/write**
`laboratory` tab (`PatientLabCasesPanel.vue`, inserted right after `imaging`) — approved as read/write over
read-only specifically so Laboratory isn't the one clinical tab that can't be acted on without leaving the
page; `CreateLabCaseDialog.vue` gained an optional `patientId` prop (skips `PatientSearchSelect`, routes the
mutation through the store) rather than a duplicate dialog. **A required fix, not deferred**: the
pre-existing `App\Rules\BelongsToPatient` SQL bug against `TreatmentPlanItem` (open in `TECH_DEBT.md` since
PR #6/2026-07-28, never fixed in Laboratory because no test ever exercised it) was fixed in
`StoreLabCaseRequest`/`UpdateLabCaseRequest` using Imaging's already-proven `whereHas('treatmentPlan', ...)`
pattern, with 4 new regression tests — this phase made that dormant bug's code path newly reachable, so
shipping without the fix was ruled out. No new policy, no new permissions, no DB migration — `LabCasePolicy`/
`LabPolicy` reused entirely unchanged. Backend: 1007/1007 tests green (998 + 13 new, Pint clean). Frontend:
894/894 tests green (867 + 27 new: `patientLabCases.ts` store, `PatientLabCasesPanel.vue`,
`CreateLabCaseDialog.vue`'s patientId behavior, +1 `PatientDetailView.test.ts` tab-rendering assertion),
type-check/ESLint/Prettier clean on every file this phase touched. One i18n key
this phase's own design doc didn't name (`laboratory.labCases.loadError`, needed by the store's error path)
was caught by the frontend test suite locally, before CI — added in the same pass, `ar`/`en`/`tr` parity
verified. Opened as **PR #24**; its CI ran green (Backend pass, Frontend pass, E2E skipping — expected
on the `pull_request` trigger per this file's established convention). The review was independently
re-confirmed against GitHub's live PR/CI state before merging (not taken on a pasted summary alone) —
`state: OPEN`, `mergeable: MERGEABLE`, `mergeStateStatus: CLEAN`, both CI checks `SUCCESS`. **PR #24
merged 2026-08-08 (`9de50fb`)**; `main`'s own post-merge CI run (`31268707960`) confirmed fully green —
Backend, Frontend, and E2E (all three run on the push-to-main trigger) each `success`.

**Phase 2.5 (Documents), design-approved and implemented 2026-08-08** on
`feature/patient-profile-phase2-5-documents` (design doc: `docs/modules/patient-documents-redesign-design.md`,
produced by auditing the actual codebase — confirmed nothing Documents-related existed yet, and unlike
Billing/Treatment Plans there wasn't even a `comingSoon` tab stub to build on): `PatientDocument` model
(UUID PK, `SoftDeletes`, `Auditable`) + `Patient::documents()` relation, a new `DocumentCategory` enum
(`consent_form | insurance | referral | clinical_summary | correspondence | other` — drops the
originally-proposed `lab_report` in favor of `clinical_summary` per §16 decision 1, to avoid overlap
with the now-shipped Laboratory module), patient-scoped-only routes (`GET/POST patients/{patient}/documents`,
`GET documents/{id}/file`, `PUT/DELETE documents/{id}` — no flat `GET /documents` counterpart, per §16
decision 4: unlike Laboratory there was no pre-existing flat endpoint to reconcile with),
`PatientDocumentService` cloning `PatientImageService`'s Storage-disk convention exactly minus thumbnail
generation, `PatientDocumentPolicy` cloning `PatientImagePolicy` exactly (auto-discovered, no
`Gate::policy()` registration — per §16 decision 2), `patientDocuments.ts` store (same Map-cache/pagination
shape as `patientLabCases.ts`), and a new **read/write** `documents` tab (`PatientDocumentsPanel.vue`,
appended last after `billing` — per §16 decision 3), with upload via `AttachmentUpload.vue` (generalized
from `UploadImagesDialog.vue`'s dropzone, **one file per upload, not a batch** — a document's title is
naturally per-file, unlike Imaging's shared-metadata batch of same-visit exposures) and a row-list
`AttachmentList.vue` (mirrors `PatientLabCasesPanel.vue`'s card-row convention rather than Imaging's photo
grid, since a document's title/category/filename metadata is the primary identifying information). No new
roles — `PatientDocumentPolicy` is the only new authorization surface, fully specced and approved. Backend:
1020/1020 tests green (1007 + 13 new, Pint clean). Frontend: 915/915 tests green (894 + 21 new: 10
`patientDocuments.ts` store tests, 10 `PatientDocumentsPanel.vue` tests, +1 `PatientDetailView.test.ts`
tab-rendering assertion), type-check/ESLint/Prettier clean (re-verified against a stashed clean baseline: 249 pre-existing
flagged files codebase-wide, this branch nets to 247 after also fixing 2 pre-existing drift files it
happened to touch — no regression). i18n: `patients.tabs.documents`, `patients.documentsPanel.*`, and a
top-level `documents.*` namespace, 31/31 keys verified programmatically across `ar`/`en`/`tr`. Opened as
**PR #27**; its CI ran green (Backend pass, Frontend pass, E2E skipping — expected on the `pull_request`
trigger). The review was independently re-confirmed against GitHub's live PR/CI state before merging —
`state: OPEN`, `mergeable: MERGEABLE`, `mergeStateStatus: CLEAN`, both CI checks `SUCCESS`. **PR #27
merged 2026-08-08 (`98ae299`)**; `main`'s own post-merge CI run (`31278768366`) confirmed fully green —
Backend, Frontend, and E2E (all three run on the push-to-main trigger) each `success`.

**Timeline remains out of scope** until its own sub-phase (2.6). Tags/labels, in-record search, and PDF
export remain named as deferred backlog (design doc §19.4), not dropped.

**Why this, not something else, right now**: Phase 1's whole point was to make it safe to build on top of
`main` again — that's done. The roadmap's own stated execution priority puts Patient Profile Redesign
immediately after Stabilization (§11 item 1), and it's large enough (new tabs, IA changes, several genuinely
new features) that starting implementation without a design-approval round first would break from the
two-phase workflow every prior module has followed. Competing candidates (Payments/Treatment-Plans
E2E gap, remaining Premium Visual Redesign steps, S3 backup) are all real but lower-priority per the
roadmap's own stated order — see §11 for the full list.

---

## Phase 5 — Notification System (2026-08-11)

**Status: COMPLETE. Merged to `main` via [PR #39](../../pull/39)** (merge commit `204194f`) **on
2026-08-11.** Phases A + B implemented, a pre-PR review fixed 5 further issues (2 marked SECURITY), and a
pre-merge `workflow_dispatch` E2E gate found and fixed 2 more genuinely real, pre-existing bugs before
merging. Post-merge CI on `main` fully green — Backend, Frontend, and the real E2E run all `success` (run
`31545464355`, 56/57 passed, 1 flaky-then-passed). Design doc: `docs/modules/notifications-design.md`
(approved, decisions D1-D8).

### What the design-phase audit actually found

Two findings shaped everything else, and neither was assumed — both came from reading the real files:

1. **No notification system existed at all**, confirmed by search: no `notifications` migration, no
   `app/Notifications/`, no `app/Mail/`, no `app/Jobs/`, no `config/broadcasting.php`, no route, no frontend
   store. `TECH_DEBT.md` had tracked the inert header bell since the layout work, predicting it would need
   "a data source filled in later, not a redesign."
2. **The project was far closer than that suggested.** Phase 2.6's `PatientActivityOccurred` already fires
   from **21 physical call sites across 9 services, covering 24 event types**; Phase 4's
   `PatientActivityPolicy` had already established the per-category, filter-in-the-query security pattern;
   and `User` already carried Laravel's `Notifiable` trait (inert, since no table stood behind it). This is
   why Phase 5A needed **zero new event dispatch call sites** — a second listener on an event that already
   fires everywhere, versus Phase 2.6's own 24 hand-placed sites.

The audit also surfaced a real, **previously-untracked** infrastructure hazard (now recorded in
`TECH_DEBT.md`'s Resolved section): `QUEUE_CONNECTION=redis` had been set since the project's first `.env`
and Redis ran in both compose files, but **no `queue:work` process, Horizon instance, or supervisor existed
anywhere**, and `routes/console.php` held nothing but the stock `inspire` command. Anything `ShouldQueue`
would have been enqueued and silently never executed. Phase 2.6's `RecordsPatientActivity` had been written
synchronously specifically to sidestep this — the hazard was known locally, in a docblock, but had never
been escalated to a tracked item.

### Phase A — In-App Notification Center

- **Table (Decision D1)**: Laravel's stock `notifications` schema plus four additive columns
  (`category`, `subject_type`, `subject_id`, nullable `patient_id`), populated by a ~20-line subclass of
  Laravel's own `DatabaseChannel` bound in `AppServiceProvider`. Keeps `markAsRead()`,
  `unreadNotifications`, and `Prunable` for free while making the security filter a real indexed `WHERE`
  rather than a JSON probe. The same additive-columns move Phase 4 Step 3 made on `audit_logs`.
- **Volume control**: `NotificationRules` is an explicit allow-list — **8 of the 24 live event types
  notify**, the other 16 stay silent by design, with per-type reasoning recorded in the design doc's §5.1.
  A Notification Center that fills with routine lifecycle events gets ignored, which would make the
  genuinely urgent ones worthless.
- **Three authorization layers**: structural ownership (every route resolves from
  `$request->user()->notifications()`, so another user's row **404s because it is never in scope** — no
  permission catalog entry needed, Decision D6, matching My Account); a read-time category re-check applied
  to the list **and the count** (a badge that counted invisible rows would leak that they exist); and a
  send-time check, so a notification is never created for someone who could not open its target.
- **Localization**: stores translation keys + raw params, never rendered text — so switching language
  re-renders existing notifications correctly with no backfill, and coverage stays verifiable by the same
  programmatic parity check. 32 new keys × 3 locales; **1486/1486/1486, zero drift**.

### Phase B — Queue & Scheduler

`queue` and `scheduler` containers added to **both** compose files, plus a `RUN_MIGRATIONS` guard in
`docker/php/entrypoint.sh` (found by reading the entrypoint before adding the containers — without it,
three containers would race `migrate --force` at boot).

Per the user's explicit instruction, `ShouldQueue` was adopted **only after** the worker was proven: a real
job was observed going `RUNNING` → `DONE` in `dentalsuite_queue`, and then a real `InvoiceService::void()`
was driven end-to-end and its notification confirmed written by the worker. Two consequences worth
recording: `$afterCommit = true` is load-bearing (three dispatching services fire the event inside a
`DB::transaction()`, so without it a worker can pick the job up before the commit and find no row), and the
listener keeps its `try/catch` even when queued — trading automatic retries for a fail-open guarantee that
holds under every queue driver including `sync`. `RecordsPatientActivity` stays synchronous: a Timeline row
is part of the record, a notification is a delivery side effect.

### Verification

| Check | Result |
|---|---|
| Backend tests | **1186/1186 green** (41 new: 18 dispatch, 16 endpoint, 7 queue/scheduler) — 1145 Phase 4 baseline + 41, **zero regressions** |
| Frontend tests | **1003/1003 green**, 150 files (34 new) — 969 Phase 4 baseline + 34, **zero regressions** |
| Pint | Clean (553 files) |
| PHPStan | Only the "undefined property on `casts()` models" pattern `TECH_DEBT.md` already documents as local-only — confirmed by reproducing it identically on untouched, CI-green files (`InvoiceService.php`, `PatientActivityResource.php`) |
| `vue-tsc` / ESLint / Prettier / E2E types | Clean |
| i18n parity | 1486/1486/1486, zero drift either direction |
| Migration | Verified against real Postgres, including the partial `notifications_unread_idx` |
| Queue worker | Verified by observation, not by the container merely starting |
| **E2E (`notifications.spec.ts`)** | **Now verified — 56/57 passed, 1 flaky-then-passed, via real `workflow_dispatch` CI runs on 2026-08-11.** Could not be run locally (see below for why); see the "E2E — could not be verified locally, but now genuinely verified on real CI" section further down for the full story, including 2 real pre-existing bugs (fixture + CI queue-worker gap) found and fixed along the way. |

### Pre-PR review fixes (same day, before either phase was opened as a PR)

5 issues found and fixed — see `CHANGELOG.md`'s "Fixed — Phase 5 pre-PR review findings" entry for full
detail and `docs/decisions.md`'s matching 2026-08-11 entry for the `SerializesModels`/`readonly` rationale.
Two (notification-store leak across logout, PHI/password hash in the queued payload) were treated as
SECURITY blockers, not deferred.

| Check | Result |
|---|---|
| Backend tests | **1191/1191 green** (+5: 3 serialization, 2 malformed-id) — 1186 baseline + 5, **zero regressions** |
| Frontend tests | **1005/1005 green** (+2: logout-reset, reopen-refetch) — 1003 baseline + 2, **zero regressions** |
| Pint | Clean on new/touched files (pre-existing, unrelated drift in `routes/console.php` and the untouched `NotificationQueueTest.php` left as-is) |
| PHPStan | Clean on every new/touched file — only the same pre-existing local-only `casts()` false-positive already documented above and in `TECH_DEBT.md` |
| `vue-tsc` / ESLint | Clean |
| Prettier | Pre-existing repo-wide CRLF vs. LF mismatch (Windows `core.autocrlf=true`) flags 326+ files identically, including every file this pass touched — confirmed byte-identical content aside from line endings; not introduced by this pass, not fixed (out of scope, global git config) |
| i18n parity | Corrected doc references only (`1485` → `1486`); no key changed |
| A2 live verification | Real dispatch through `AppointmentService::cancel()` while `dentalsuite_queue` was paused; raw Redis payload read via `redis-cli`, confirmed free of the actor's password hash/`remember_token`/patient PHI while still containing the correct appointment id; worker resumed, observed `RUNNING` → `DONE`, real `notifications` rows confirmed written; test data cleaned up afterward |
| B1 live verification | `Router::getRoutes()->match()` against the real Postgres-backed app: malformed id → `NotFoundHttpException` (404) pre-route; valid UUID still matches. Reproduced the pre-fix 500 first (`QueryException: 22P02 invalid input syntax for type uuid`) |
| E2E | Not re-attempted locally at the time — pre-existing, unrelated `login()` failure (see below) still blocks the whole local suite; nothing in this pass touches login. **Verified afterward via real CI — see next section.** |
| Git | New commits on `feature/phase5-notifications`; PR #39 opened, then two further CI-only bugs found and fixed (below) |

### E2E — could not be verified locally, but now genuinely verified on real CI (2026-08-11)

Stated plainly rather than glossed: for most of this phase, **`e2e/notifications.spec.ts` had never been
observed passing.** The local `login()` failure blocking every spec in the suite on this dev machine was
isolated rather than assumed to be Phase 5's doing:

- `e2e/auth.spec.ts` — untouched by this phase, and green in CI — **fails identically** locally.
- Checking out **`main`** (none of Phase 5's code present), restarting the dev server, and re-running
  `auth.spec.ts` **reproduces the same failure**. Decisive: the cause predates this branch, confirmed
  local-environment-only, not a real regression (see `TECH_DEBT.md`).

**Resolved by running the real thing**, per the user's explicit request before merging PR #39: a
`workflow_dispatch` run of `.github/workflows/ci.yml` on `feature/phase5-notifications` — the same
mechanism this project has always used to get the authoritative E2E signal, since that job never runs on
`pull_request`. Three runs, each surfacing and fixing one genuinely real, pre-existing issue — none of them
the `login()` problem, none of them caused by the pre-PR review fixes:

1. **First run**: past `login()` cleanly (auth works fine on CI — confirms the local failure really is
   environment-specific). 53/57 passed; all 4 `notifications.spec.ts` tests failed identically with
   `TypeError: Cannot read properties of undefined (reading 'find')`. Root cause: the spec's
   `findAppointmentTypeId()` helper (added in the original Phase 5A fixture fix, commit `61df2c28`) assumed
   `GET /appointment-types` returns a paginated `{ data: [...] }` envelope, matching this same file's
   `/users`/`/notifications` calls — but that endpoint is unpaginated and `JsonResource::withoutWrapping()`
   is global, so it returns a bare array. Fixed the helper to read the array directly.
2. **Second run**: past the fixture bug, but all 4 tests now failed on `expect(locator).toBeVisible()` —
   the unread badge, the "mark all read" button, and the deep-linked notification itself never appeared.
   Root cause: `QUEUE_CONNECTION=redis` by default and `SendsNotifications` has been `ShouldQueue` since
   Phase 5B, but **the E2E job's workflow never started a queue worker** — only Postgres/Redis service
   containers. Every notification-producing action enqueued and was never consumed, so no notification was
   ever created — a real, previously-invisible gap in `ci.yml` itself, not caught before because this was
   the first `workflow_dispatch` run to ever reach this spec's assertions. Fixed by adding a
   `php artisan queue:work &` step to the E2E job.
3. **Third run — fully green**: **56/57 passed, 1 flaky** (`one user can never see or act on another
   user's notification` failed once on its first attempt — an empty notification-id lookup, i.e. a timing
   race between the test's own lookup and the now-actually-running queue worker consuming the job — then
   passed on Playwright's automatic retry). This is the same "flaky, not failure" class of transient CI
   timing issue already recorded elsewhere in this file for `dental-chart.spec.ts`, not a new problem
   category. Backend and Frontend jobs `pass`.

**Net result**: `e2e/notifications.spec.ts` is now genuinely, CI-confirmed passing — including the exact
scenarios the user asked to see specifically verified: a cross-user notification (front desk cancels →
dentist is notified), notification authorization (one user structurally cannot see another's row), mark-all,
and the RTL/mobile smoke check. Two real, pre-existing bugs from the *original* Phase 5A/5B implementation
were found and fixed only because this review pushed all the way through to a real `workflow_dispatch` run
— something no prior local or CI attempt had done. Neither was caused by, or related to, the A1/A2/B1/B2/B3
pre-PR review fixes.

### What was deliberately NOT built

Recorded so "why isn't this here" is answered once: **Email** (Phase D — blocked on `MAIL_MAILER=log`, no
`users.locale` column, no backend `lang/` files, and on preferences existing first); **Web/PWA Push**
(roadmap Phase 6 — needs a service worker, which needs the PWA foundation this project does not have);
**patient-facing SMS/WhatsApp/email reminders** (their own future module — needs transport, contact
preferences, consent records, and a delivery-failure model; the `appointment_reminders` table was left
untouched rather than half-wired, because a table that looks live but silently drops reminders is worse
than a visibly unwired one); **realtime websockets** (no broadcast layer exists; polling is adequate at
clinic scale); **per-user preferences** (meaningless while in-app is the only channel); and **Phase C's
scheduled/administrative types** (lab overdue, low-stock digest, unconfirmed appointments, repeated-login
alerts) — all designed, none started. See the design doc's §13 and `TECH_DEBT.md`.

### Immediate next step

**Resolved 2026-08-12**: user chose **Phase 5C/5D** over roadmap Phase 6 (SaaS Multi-Tenant Prep) as the
next direction. Design-phase audit of the real (now Phase A/B-implemented) codebase against the parent
design doc's §5.2/5.3/§13 sketch found two structural gaps the sketch hadn't resolved in implementation
detail — `NotificationRules` is keyed by `PatientActivityOccurred`'s event type and cannot hold Phase C's
time-triggered types as-is, and `NotificationPolicy::CATEGORY_SUBJECT_MAP` cannot hold `security` as-is
since it has no Eloquent Policy (Gate-based, Phase 4 §1.4) — both resolved in the new dedicated design doc.
That audit also found types 12-13 (repeated login failures, permission-matrix change) don't need the
scheduler at all: `AuditLog::create()` is a real Eloquent `create()`, so both can be reactive via a new
`AuditLogObserver` rather than a daily cron, catching a security event same-day instead of up to 24h late.
Full design: `docs/modules/notifications-phase-c-d-design.md`.

**User decided the same day**: **Phase C only, now — Phase D (email) explicitly deferred to its own
separate future cycle**, not bundled in. Decisions D9 (5 failures/15min), D11 (03:30/08:00/17:00 run times),
and D12 (C-only scope) **approved as recommended**. D10 (email opt-out default) and D13 (mail provider)
**approved in principle but deferred untouched to the future Phase D cycle** — no email code, no preference
row, nothing email-related ships as part of Phase C.

**New finding while verifying D11's own explicit condition** ("must be clinic timezone, not blind UTC"):
confirmed a real, previously-latent gap — `config('app.timezone')` is `'UTC'` and **no `TZ` is set anywhere**
in either compose file or `.env.example`. This never mattered before Phase C because every prior phase only
stored/redisplayed wall-clock digits (`frontend/src/lib/date.ts`'s documented single-clinic,
no-real-conversion convention) and never compared them against `now()`. Phase C is the first feature to do
that (`due_at`/`start_at` vs. `now()`, plus `dailyAt()`'s absolute run times) — so it's the first to expose
that the container's real system clock (UTC) and the clinic's own wall-clock digits are two different
clocks unless the clinic is literally in UTC+0. Recommended fix is config-only (set a real `TZ`/
`APP_TIMEZONE` to the clinic's actual IANA zone in both compose files) — but the zone string itself is a
fact only the user has, not something to guess. **New Decision D14, blocking implementation start**: full
detail and the multi-tenant follow-up note in the design doc's new §3.3a. **Awaiting the user's answer on
D14 before writing any code** — everything else needed to start Phase C is approved.

**Resolved same day**: clinic timezone confirmed as **`Africa/Cairo`**. **Design fully approved — Phase C
implementation now starting.** Phase D remains deferred to its own future cycle (D10/D13 untouched). Full
approval log: design doc's new §16a.

**Implementation correction to the paragraph above**: "set `TZ`/`APP_TIMEZONE` in both compose files" turned
out to be the wrong location once actually verified — neither compose file sets `APP_*` vars directly;
`app`/`queue`/`scheduler` all load `backend/.env` from the mounted volume, and CI copies `.env.example`
itself. The real fix: `config/app.php`'s `'timezone'` key was a hardcoded `'UTC'` string (not `env()`-driven
at all, confirmed via `LoadConfiguration::bootstrap()`) — changed to `env('APP_TIMEZONE', 'UTC')`;
`APP_TIMEZONE=Africa/Cairo` added to `backend/.env.example` (mirrored into the real local `backend/.env`);
`tzdata` added to both `docker/php/Dockerfile` and `Dockerfile.prod` for full IANA resolution on Alpine.

**Phase 5C — IMPLEMENTED 2026-08-12.** All 5 types (9-13), the 2 new categories, and the D14 timezone fix
landed the same session. Two further real bugs surfaced only by testing/running (not by reading) and were
fixed in the same pass: `NotificationIndexRequest` rejected `category=security` as invalid (fixed via a new
`NotificationPolicy::allCategories()` single source of truth); and — found immediately after rebuilding the
Docker image to add `tzdata` — `docker/php/entrypoint.sh`'s CRLF line endings (this Windows checkout's
`core.autocrlf=true`) crash-looped `app`/`queue`/`scheduler` the moment the cache-invalidated `COPY` picked
up the working-tree file fresh; fixed by normalizing to LF and adding a new `.gitattributes` so it can't
recur. Full account, including the `AuditLog.created_at` clock-mismatch bug D14 itself exposed: design doc's
§19. **Verification**: Backend **1211/1211** green (1191 + 20 new), run twice including once against the
rebuilt image; PHPStan/Pint/`vue-tsc`/ESLint clean; i18n **1498/1498/1498** zero drift; `schedule:list`
confirmed the 3 new Commands.

**Both open verification gaps closed same day, 2026-08-12**: Playwright browsers were confirmed
un-installable in this dev container at all (Alpine — `playwright install --with-deps` fails outright,
`apt-get: not found`), not merely slow, so real-browser confirmation was closed with real CI coverage
instead, matching the Phase 5A/B precedent. Full frontend Vitest run completed: **1003/1005** (2 pre-existing
`router/index.test.ts`/`PatientDetailView.test.ts` full-suite-load timeout flakes, already on record in
`TECH_DEBT.md`; both re-verified 31/31 passing in isolation). Two new `notifications.spec.ts` tests added —
`security` (5 real failed logins through the real login form, deterministically crossing Decision D9's
5-in-15-minutes threshold) and `inventory` (a new CI step seeds one guaranteed-low-stock `Supply` and runs
`notifications:low-stock-digest` before the frontend starts, since that type has no user-facing trigger).
**Branch pushed, PR #41 opened to `main`.** A pre-merge `workflow_dispatch` gate was run twice: the first
(`31583701772`) already showed Frontend/E2E fully green including both new tests, but caught one real,
CI-only Pint violation (an unused import + two fully-qualified class references) in the new backend test
file — local Pint hadn't flagged it, the same class of gap PR #39 hit — fixed in a follow-up commit; the
re-run (`31584698456`) is **fully green: Backend, Frontend, and E2E all `success`** (E2E 59/59, no flakes).
`CHANGELOG.md` and `TECH_DEBT.md` updated in the same pass per Definition of Done. **PR #41 not yet
merged — awaiting review before merge.** **Phase D (email) remains deferred to its own future
cycle**, per Decision D12 — D10/D13 untouched.

---

## 13. Risks

| Risk | Likelihood | Impact | Mitigation |
|---|---|---|---|
| **Local-disk-only backup + VPS loss** before a real clinic is onboarded | Low (no real clinic yet) → rising | High if it happens | Provision S3/offsite storage before onboarding (§11 item 7) |
| **Documentation drift recurring** the same way it did 2026-08-01→07 (§0), silently, if this file isn't actually kept current through the next high-velocity burst | Medium without discipline | Medium (erodes trust in docs, wastes future re-derivation effort) — already happened once, in the exact window this file itself was being built | §15's protocol + repo-root `CLAUDE.md` (auto-loaded every session) — the real test is whether Phase 2's own PRs keep this file current *during*, not after |
| **`dental_conditions` reused as a global pricing catalog** becomes a real constraint once multi-tenant/insurance pricing is needed | Low near-term (V1 is single-org by design) | Medium, contained (a planned, not accidental, migration) | Already flagged with its own revisit condition (§9); don't build the dedicated catalog speculatively before then |
| **Billing's thinner test coverage** (unit-only, no E2E) hides a regression a later module's refactor introduces | Medium | Medium | Prioritize Billing's Feature-test suite + E2E spec (§11 items 2–3) |
| **PHI exposure via AI Assistant** if a clinic enables Clinical Notes draft-assist/Treatment Suggestions without an actual signed BAA | Low (requires explicit admin acknowledgment) | High if it happens | Gate is already enforced in code (`ai_assistant_phi_features_acknowledged`); this is a process/legal risk (admin must actually have a BAA before checking that box), not a code gap |

---

## 14. Overall Assessment

Speaking as a technical lead reviewing this codebase cold: **this is an unusually disciplined project for
its stage.** Seventeen modules were built in under a month (2026-07-11 → 2026-07-31 for the original
roadmap), each one following the same design-doc → implementation → test → CI-confirm → final-doc workflow
without visibly cutting corners — the tech-debt log alone (1,143 lines) is more thorough self-accounting than
most production codebases ever produce, and it's clearly been *used*, not just written (the same categories
of bug — PrimeVue `id`/`inputId`, the datetime-handling class of bug, local-Docker-networking false timeouts
— get correctly recognized and dismissed faster each time they recur, rather than re-investigated from
scratch). The datetime-policy elevation to a hard Architecture Violation rule, and the repeated discipline of
treating "verified locally" as provisional until CI's own API/logs confirm it, are both the kind of standard
that's easy to state and unusually rare to actually hold to for a month straight.

The honest gaps are proportionate to the pace, not a sign of carelessness: three modules (Billing, Payments,
Treatment Plans) shipped ahead of the E2E-suite convention solidifying and haven't been backfilled yet;
Billing in particular is thinner than everything built after it. The Laboratory `BelongsToPatient` bug and
the `dental_conditions`-as-pricing-catalog choice are both *known* debt with named revisit conditions, not
silent gaps — which is exactly the right way to carry debt, but they're still debt.

**Update, 2026-08-07**: the crack named below when this file was first written has been closed. In the rush
of landing PR #11, #12, and #13 within one calendar day (2026-08-02), the documentation-sync discipline that
held for the prior three weeks visibly slipped: `CHANGELOG.md` stopped being updated, `roadmap.md` and
`PROJECT_CONTEXT.md` both asserted things that were no longer true, and CI on `main` sat red for six days on
a diagnosed-but-unfixed issue. Phase 1 (Stabilization) closed all of it — `main`'s CI is fully green again,
and the documentation gaps are reconciled. Worth being honest about the fix itself, not just claiming
success: the E2E fix alone took **three separate `workflow_dispatch` runs** to actually land, because the
first attempted fix (an `ORDER BY` on `received_at`) was real but incomplete — it took CI's own signal to
surface that `received_at` is date-only and ties needed a secondary sort key, and then a *third* run to find
`newPatients()`'s independent, previously-masked ordering bug. That's the system working as designed, not a
failure: each fix was verified on real CI before being trusted, exactly the discipline this project's own
history (§5, `TECH_DEBT.md`) has repeatedly shown pays off. The real test of whether the underlying lesson
stuck isn't this cleanup — it's whether Phase 2's own PRs keep `docs/PROJECT_STATUS.md` current *during*
the work, not after, the next time velocity picks up. `CLAUDE.md` and this file's §15 exist specifically to
make that automatic rather than a hope.

**Original assessment (2026-08-04), preserved for context**: the documentation-sync discipline that held for
the prior three weeks visibly slipped — `CHANGELOG.md` stopped being updated, `roadmap.md` and
`PROJECT_CONTEXT.md` both asserted things that were no longer true, and CI on `main` had been quietly red
for three straight days on a diagnosed-but-unfixed issue. None of this was a crisis; the underlying
application code was fine, and the root causes were already understood. But it was the first visible crack
in a pattern that had otherwise been remarkably consistent, worth naming plainly rather than smoothing
over: **the project's own quality bar slipped exactly when velocity peaked.**

---

## 15. How to keep this file alive

**This section is instructions for future Claude Code sessions (and human contributors) working in this
repository — read it before editing this file, and follow it without being asked.** Repo-root `CLAUDE.md`
points here specifically so this isn't missed at session start.

### 15.1 At the start of every development session, before starting any requested task

1. Read this file in full.
2. Check what's happened since its "Last updated" date: latest commits (`git log`), latest PRs
   (`gh pr list --state all --limit 10`), latest CI run status (`gh run list --branch main --limit 5`).
3. If you find anything material that isn't reflected here yet — a merge, a status change, a new tech-debt
   item, a CI change — **update this file first**, then proceed to the requested task. Don't defer the
   update to "later" or to whoever asks about status next; an out-of-date status file is worse than no
   status file, because it's trusted.
4. Only then start the actual task. This ordering (verify → update → proceed) is what let this file's first
   version catch 3 real discrepancies in older docs (§0) — treat that as the standard, not a one-time audit.

### 15.2 During the session — what triggers an update, immediately, not batched for later

- A module **starts** a new phase (Design approved, Implementation begins) — not just when it finishes.
- A module **finishes** or changes status (Implementation Complete → Production Ready, etc.).
- A Pull Request is opened, updated, or merged.
- An architecture decision is made (including database/schema decisions).
- The roadmap changes (a module added, reprioritized, or dropped).
- A UI/UX-affecting change ships.
- A feature is added or removed.
- A real, non-trivial bug is found — or fixed.
- A Technical Debt item is added — or resolved.
- A development phase ends.
- CI passes or fails in a way that changes the project's actual status (not routine transient failures —
  the kind of thing that belongs in §9/§13, like the currently-open `main` E2E regression).
- Production-readiness posture changes in either direction.

Map each event to the section it belongs in: §2 (Timeline) and the relevant status table (§3, or a new PR
row in §4) for almost everything; §5 for architecture/DB decisions; §6/§7 for UI or AI-specific changes; §9
for tech debt; §10/§13 for readiness/risk shifts. Bump the "Last updated"/"Updated by" line at the top on
every edit — no separate in-file changelog needed, the sections already carry their own dates.

### 15.3 Anti-duplication rule

**Never paste the full content of `CHANGELOG.md`, `TECH_DEBT.md`, `docs/decisions.md`, or `docs/roadmap.md`
into this file.** Those remain the detailed, authoritative logs — this file's job is a short summary (1-3
sentences, or one table row) plus an explicit pointer (`see CHANGELOG.md's "X" entry`, `see TECH_DEBT.md`,
`see docs/decisions.md`). If you notice a section here growing into a near-copy of one of those files, that's
a sign to trim it back to a summary + pointer, not a sign it's thorough. This file is the **entry point**,
not a replacement.

**Keep those other files in sync too**, in the same pass, not as a follow-up: if an event belongs in
`CHANGELOG.md`/`TECH_DEBT.md`/`docs/decisions.md` per their own existing conventions, add it there *and*
summarize it here — don't let this file become the only place an event is recorded, and don't let it drift
out of sync with them the way `CHANGELOG.md` drifted by 3 PRs before this file existed (§0).

### 15.4 Before considering any task finished — part of Definition of Done

A task is not complete while the documentation is out of sync with the code. Before finishing any task that
touched code, architecture, tests, or scope, confirm:

- [ ] This file (`docs/PROJECT_STATUS.md`) reflects the change (per §15.2's trigger list).
- [ ] `CHANGELOG.md` updated, if the change is the kind that file tracks (new/changed functionality).
- [ ] `TECH_DEBT.md` updated, if a debt item was created or resolved.
- [ ] `docs/decisions.md` updated, if a new architectural decision was made.
- [ ] The relevant module design doc under `docs/modules/` updated, if it's now inconsistent with what was
      actually implemented.

Do not report a task as done with any of these left inconsistent with the code — surface it instead.

### 15.5 Answering status questions

When asked (in any phrasing, Arabic or English) "what's the project status," "what did we accomplish,"
"what's next," "is this production-ready," "what's the completion percentage," etc.: apply §15.1's
verify-then-update flow first, then answer **from this file**, not by re-deriving the answer from scratch
each time. Don't guess — if something is uncertain, say so explicitly, the same way this file flags its own
health percentages as qualitative (§10) and names exactly which prior-doc claims required verification
rather than trust (§0). A wrong confident claim here is worse than an honest "unverified."
