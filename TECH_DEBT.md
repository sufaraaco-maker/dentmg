# Technical Debt

Postponed work, tracked deliberately rather than forgotten. Each item names the module it affects and the condition under which it should be revisited.

## Open

### Backend test suite never exercises real PostgreSQL — migrations can silently be un-runnable in production
`backend/phpunit.xml` forces `DB_CONNECTION=sqlite`/`DB_DATABASE=:memory:` for every test run (`RefreshDatabase`
included). Found 2026-07-26: `2026_07_25_000001_create_payments_table.php` declared a self-referencing foreign
key (`refunded_payment_id`) inside the same `Schema::create()` as its own primary key — a shape Postgres
rejects ("no unique constraint matching given keys for referenced table") because Laravel's `Blueprint` always
compiles the primary-key command after every explicit FK/index command in the same blueprint. SQLite doesn't
enforce that same ordering constraint, so 643 backend tests passed for a full day (2026-07-25 → 2026-07-26)
against a migration that had never actually succeeded on the real `dentalsuite_postgres` container — confirmed
directly: `php artisan migrate:fresh` against real Postgres failed before the fix, and now completes cleanly.
See CHANGELOG's "Payments migration" fix entry for the full root-cause writeup and the fix itself
(`2026_07_26_000001_add_refunded_payment_id_foreign_to_payments_table.php`).
**Revisit**: add a CI job (or a documented pre-merge step) that runs `php artisan migrate:fresh` against a
real Postgres service container — not just the SQLite-backed Feature/Unit suite — so a migration-ordering bug
like this one fails fast instead of only surfacing whenever someone happens to run a fresh migrate against a
real Postgres database by hand. Doesn't need to run the full test suite against Postgres (SQLite is fine and
faster for the Unit/Feature suite itself), just the migration path.

### `router/index.test.ts` flaky under full-suite parallel load (Windows Docker)
Running the complete frontend Vitest suite (561 tests, 2026-07-25, verifying the Payments module) showed 2
failures confined to `src/router/index.test.ts` (including `allows a dentist to reach their own Dentist
Schedule route`), with a stack trace pointing at a `useAuthStore()`/`auth.initialized` timing race. The same
file passes 11/11 cleanly when run in isolation immediately after. The full run's own Vitest timing
breakdown showed `environment: 3274.60s` exceeding the run's total `Duration: 1715.12s` — clear evidence of
heavy resource contention during the full parallel run, not a logic defect. Matches the same class of local
Windows Docker Desktop networking/resource pathology already logged in `PROJECT_CONTEXT.md`'s CI-gate
history. Confirmed unrelated to the Payments module: `router/index.test.ts` imports nothing from
`stores/payments.ts`/`services/payments/*`.
**Revisit**: if this recurs consistently (not just under full-suite parallel load) or starts failing in CI
(which runs on native, non-Windows-Docker runners and has not shown this), investigate
`useAuthStore()`'s initialization ordering in that test directly. Not blocking — the file is correct and
passes reliably in isolation.

### No permanent E2E suite for Billing or Payments
Every other production-ready module (Appointments, Dental Chart) has a CI-verified, permanent Playwright
spec. Billing and Payments (`feature/treatment-plans`, both implementation-complete 2026-07-25) shipped
with backend Unit test coverage — Payments additionally with Feature-test coverage (`PaymentTest`, 22
tests) Billing itself still lacks — and frontend Vitest coverage (stores/services only; no per-component
tests exist for either module's Vue components yet, matching the level Billing itself shipped at), but no
`frontend/e2e/billing.spec.ts` or `frontend/e2e/payments.spec.ts` — confirmed absent directly, not assumed.
Payments' own design doc (`docs/modules/payments-design.md` §16) named the scenarios in advance: record
payment against an invoice → verify `payment_status` updates → partial refund → verify balance recalculates
→ record unapplied credit → apply it to a different invoice → delete-blocked-once-refunded verification →
receptionist-write/dentist-read-only check → RTL/dark-mode/currency-formatting smoke check. A Billing E2E
spec would similarly need: draft → add items (manual + from-plan) → issue → verify frozen snapshot → void →
receptionist/dentist permission check → RTL/dark-mode smoke check.
**Revisit**: write and CI-verify `frontend/e2e/billing.spec.ts` and `frontend/e2e/payments.spec.ts` before
either module is considered to meet the project's usual "Production Ready" bar as closely as
Appointments/Dental Chart do. Not blocking V1 use — both modules are otherwise fully functional and tested
at the Unit/Feature/store level.

### No permanent E2E suite for Treatment Plans
Every other production-ready module (Appointments, Dental Chart) has a CI-verified, permanent Playwright
spec (`frontend/e2e/appointments.spec.ts`, `dental-chart.spec.ts`). Treatment Plans (`feature/treatment-plans`,
commit `0677128`) shipped with full backend (505/505) and frontend (541/541) unit/feature test coverage but
no `frontend/e2e/treatment-plans.spec.ts` — confirmed absent directly, not assumed. The design doc
(`docs/modules/treatment-plans-design.md` §19) specified one: golden path (create → add items → present →
accept → link appointment → complete items → complete plan), reject path, multi-plan sibling-auto-reject
scenario, cancel-cascade scenario, receptionist read-only verification, RTL/dark-mode smoke check.
**Revisit**: write and CI-verify `frontend/e2e/treatment-plans.spec.ts` covering the scenarios above,
mirroring `dental-chart.spec.ts`'s structure, before this module is considered to meet the project's usual
"Production Ready" bar as closely as Appointments/Dental Chart do. Not blocking V1 use.

### `dental_conditions` reused as the Treatment Plans pricing catalog (V1 only)
Treatment Plans' design review (2026-07-22, Decision 5) approved reusing the existing `dental_conditions`
table (extended with `default_cost`/`description`) as the procedure/pricing catalog rather than building a
dedicated one — explicitly "approved with caution," not a permanent architectural decision. A single global
`default_cost`/`description` per procedure cannot support: clinic-specific pricing (once multi-tenant, the
same procedure priced differently per clinic), regional pricing (currency/market variation), insurance
pricing (contracted-rate schedules per payer), dentist-level price overrides, or historical pricing (what a
procedure cost as of a given past date, independent of any one treatment plan's own frozen snapshot — see
`docs/modules/treatment-plans-design.md` §15 Q2/§16 item 4 for why plan *items* already snapshot their own
price and don't depend on this).
**Revisit**: when a real multi-clinic/multi-tenant requirement appears, or when insurance/regional pricing
is actually requested — design a dedicated procedure-pricing catalog then (likely a `clinic_id`-scoped
pricing table referencing a tenant-shared procedure list), rather than continuing to extend
`dental_conditions`. Do not build speculatively before then, per the same "don't build ahead of a real need"
principle already applied to Multi-Branch below.

### Sidebar "Treatment Plans" item is still `comingSoon` (no patient-agnostic index page)
`config/navigation.ts`'s top-level "Treatment Plans" nav entry (`labelKey: 'nav.treatmentPlans'`) is
marked `comingSoon: true` even though the module itself is implemented and reachable — Treatment
Plans lives on `PatientDetailView`'s own tab (`PatientTreatmentPlansPanel.vue`, routed via
`treatment-plan-detail`), the same pattern Dental Chart uses for its chart view. There is no
patient-agnostic Treatment Plans list/reporting screen for the sidebar item to point to, so it stays
a placeholder rather than linking to a route that doesn't exist.
**Revisit**: once there's a defined need for a clinic-wide Treatment Plans list (e.g. "all pending
plans awaiting patient decision" or a reporting view spanning patients), design and build a
dedicated index page and flip this entry from `comingSoon: true` to a real `routeName`, mirroring
how Dental Conditions' catalog screen was added under Dental Chart. Not implemented speculatively
now — no such cross-patient view has been requested yet.

### Header notifications is inert UI (no notification backend)
`AppHeader.vue`'s bell icon opens a popover that always reads "No notifications yet" — there is no
notification system in the backend. Scaffolded deliberately as inert UI (per the layout architecture design,
`docs/modules/layout-architecture.md`) rather than wired to fake data, so the header only needs a data source
filled in later, not a redesign.
**Revisit**: when a real notification system exists on the backend.

### `AppSidebarItem.vue` supports only one level of nested children
`config/navigation.ts`'s `NavItem.children` type technically allows arbitrary nesting, but the renderer
(`AppSidebarItem.vue`) only renders one level (used today only by "Appointments" → Calendar/Types/Working
Hours). Deliberately not built recursive since no current or near-term IA item needs a second level, and
premature recursion would add complexity with no current benefit.
**Revisit**: only if a future module's navigation genuinely needs two levels of nesting; extend
`AppSidebarItem.vue` to render itself recursively at that point.

### `national_id` uniqueness survives soft delete
A soft-deleted patient's `national_id` can't be reused by a newly-registered patient, because the DB-level unique constraint doesn't exclude soft-deleted rows. Simpler than scoping the constraint, and arguably safer for data integrity, but worth knowing.
**Revisit**: only if re-registering a previously-deleted patient with the same national ID becomes a real workflow need.

### Appointment types have no `price` or `is_default` column
The original task brief for the Appointment Types screen (Phase 2 Step 7) called for a "Price" field and a
way to mark a type as default, but `appointment_types` has neither column — confirmed from the
migration/model, not assumed. Not implemented as a frontend-only field with nowhere real to persist it; see
`docs/modules/appointments-ui-design.md` §7 for the full reasoning.
**Revisit**: pricing-per-type belongs in the Billing module's design where pricing concepts actually live
(not bolted onto Appointments); "default type" is a small additive migration + a "set as default" action if
ever requested, but is still a backend change outside this frontend-only step's scope.

### `PatientAppointmentsPanel.vue` shows a bounded ±3/6-month window, not full history
The Patient Detail "Appointments" panel (Phase 2 Step 8, design doc §9) fetches the shared,
unfiltered `appointments.ts` range cache (the same technique `TimeOffFormDialog` already uses)
and filters client-side to the patient — 3 months back to 6 months forward from today. A patient's
appointment older than 3 months or booked further than 6 months out won't appear here. Deliberate:
`GET /api/appointments` has no `patient_id`-scoped, unbounded query (it's a required-date-range,
clinic-wide endpoint by design — see the backend design doc §16/§19), and an unfiltered fetch of a
much wider range would pull every patient's appointments in that span just to show one patient's
history.
**Revisit**: if a genuine "full appointment history" need appears, add a dedicated
`GET /api/patients/{patient}/appointments` backend endpoint (paginated, patient-scoped) rather than
widening this frontend range fetch.

### Multi-branch
Documented in `PROJECT_CONTEXT.md`, not implemented. No table is branch-scoped.
**Revisit**: when a real second-location requirement appears. Do not build speculatively.

### `JsonResource::withoutWrapping()` — global convention, not re-confirmed with user
Applied globally by the assistant during the Authentication module to keep response shapes consistent; flagged in `ARCHITECTURE_REVIEW.md` for review but not explicitly re-approved since.
**Revisit**: low priority — only if a future need for a structured envelope (e.g. API versioning metadata) arises.

### `patch-package` patch on `primevue` is pinned to 4.5.5
`frontend/patches/primevue+4.5.5.patch` fixes a real upstream `DatePicker` bug (`populateTime()` crashes
parsing a typed 24-hour value with no AM/PM suffix, silently discarding it — see CHANGELOG, Phase 2 Step
6). `patch-package` re-applies it automatically on every `npm install` at the *currently installed*
version, but the patch file itself won't match a different `primevue` version's source and will fail to
apply (loudly, via `postinstall`, not silently) if `primevue` is ever bumped.
**Revisit**: whenever `primevue` is upgraded (including the eventual 5.x major), re-check whether this bug
still exists in the new version — if fixed upstream, delete the patch file; if not, regenerate it against
the new version's `datepicker/index.mjs`.

## Open (new from Appointments frontend design review, 2026-07-16)

### No dedicated dentists/providers listing endpoint
`GET /api/users` is paginated at a fixed 15/page with no `role` filter and the controller doesn't forward a `per_page` query param to `UserService::paginate()`. The Appointments frontend needs a full list of dentists for filters/dropdowns (Calendar filters, Appointment dialog, Working Hours/Time Off dentist selector), so `frontend/src/stores/providers.store.ts` works around this by paginating through every page of `GET /api/users` once per session and filtering `role === 'dentist'` client-side — acceptable at realistic clinic-staff scale (a handful of requests, once, cached indefinitely), but a real workaround, not the intended shape.
**Revisit**: add a dedicated `GET /api/dentists` (or `/api/providers`, matching wherever the future Providers module lands — see `docs/modules/appointments-ui-design.md` §4 "Dentist/Provider Store") endpoint, or at minimum a `?role=` filter plus an uncapped `per_page` on the existing `GET /api/users` for this specific staff-directory use case. Low risk, small change — do it whenever backend capacity allows; not blocking.

### Appointment audit-log route not yet exposed
`Appointment` already uses the `Auditable` trait (`backend/app/Models/Appointment.php:6,19`), so every
create/update/status-transition is already being recorded in `audit_logs`, exactly like `Patient` — the data
exists. But no route/controller/policy exposes it: unlike Patients' `GET
/api/patients/{patient}/audit-logs` (`routes/api.php:25`, `PatientController::auditLogs()`,
`PatientPolicy::viewAuditLogs`), there is no `appointments/{appointment}/audit-logs` route,
`AppointmentController` has no `auditLogs()` method, and `AppointmentPolicy` has no `viewAuditLogs` ability.
Confirmed absent by reading the code directly, not just unconfirmed. The frontend's `AppointmentDetailView`
renders a `FutureFeaturePlaceholder` in that slot instead of a real audit panel (see
`docs/modules/appointments-ui-design.md` §4.2).
**Revisit**: add `GET /api/appointments/{appointment}/audit-logs` + `AppointmentController::auditLogs()` +
`AppointmentPolicy::viewAuditLogs` (admin only), mirroring the Patients pattern exactly. Small, low-risk —
the write-side capture already works, only the read-side route is missing. Not blocking.

### Appointment mutation endpoints don't eager-load relations
`AppointmentResource` responses from `store`/`confirm`/`check-in`/`start`/`complete`/`cancel`/`no-show`/`update` don't eager-load `patient`/`dentist`/`appointment_type` (only `index`/`show` do), so the frontend's `appointments.store.ts` issues a follow-up `GET /api/appointments/{id}` after every mutation to re-hydrate those nested fields before updating its cache — see `docs/modules/appointments-ui-design.md` §11 "Post-Mutation Rehydration."
**Revisit**: if these controller actions eager-load the same three relations before returning their `AppointmentResource` (mirroring what `index`/`show` already do), the frontend's extra round-trip can be deleted entirely. Small, low-risk backend change; not blocking initial implementation.

## Open (new from Appointments Phase 2 Step 5 — Appointment Detail View, 2026-07-17)

### No `confirmed_at` column — `AppointmentTimeline`'s Confirmed step is a status-order approximation
Every other step in `AppointmentTimeline.vue` (Scheduled/Checked In/In Progress/Completed) reads a real,
dedicated timestamp column (`created_at`/`checked_in_at`/`started_at`/`completed_at`) — confirmed directly
against the `appointments` migration. `confirmed` has no such column, and Check-In can happen directly from
`scheduled` (per the backend's state machine), so once an appointment has moved past `confirmed`, whether it
was ever actually confirmed can no longer be proven from `status` alone. The component documents this
explicitly (`TimelineStepDef.timestampField: null` for this one step) and never lets the approximation gate
later, ground-truth steps for a cancelled/no-show appointment's chain.
**Revisit**: if a real near-term need arises for reporting on confirmation lag/rate, add a `confirmed_at`
column + set it in the `confirm()` service action. The frontend change is a one-line fix — flip `STEPS`'
`confirmed` entry to `timestampField: 'confirmed_at'` — no other code change needed. Not blocking; low
priority.

## Open (new from Appointments Phase 2 Step 1 — Infrastructure, 2026-07-16)

### Pre-existing Patients/Users files are not yet Prettier-clean
Introducing ESLint + Prettier this step (see Resolved section below) surfaced pre-existing issues in files this step didn't touch: `PatientsView.vue` has one `vue/attributes-order` warning, and a handful of files (`PatientFormDialog.vue`, `DefaultLayout.vue`, `style.css`, `LoginView.vue`, `PatientDetailView.vue`, `PatientsView.vue`) have minor Prettier formatting drift. The `@typescript-eslint/no-explicit-any` errors in `PatientFormDialog.vue`/`UsersView.vue` (`catch (err: any)`) were fixed during the 2026-07-18 Production Gate — `npx eslint .` is fully clean now, matching the pattern already used in `AppointmentDialog.vue` (`catch (err: unknown)` + narrowed inline casts). Only the Prettier-formatting/attribute-order drift remains, deliberately not touched to keep unrelated diffs out of feature commits.
**Revisit**: a small, dedicated, easy-to-review follow-up pass — `npm run format` across the whole `frontend/src/` tree — whenever convenient. Purely mechanical, low risk.

### `providers.ts` store filename doesn't match the design doc's suggested `providers.store.ts`
`docs/modules/appointments-ui-design.md` §10.2 (written per explicit user instruction) names the file `providers.store.ts`. Implemented as `frontend/src/stores/providers.ts` instead, for consistency with every other store in the directory (`auth.ts`, `ui.ts`, `calendar.ts`, `appointments.ts`, `appointmentTypes.ts`, `workingHours.ts`, `timeOff.ts` — none use a `.store.ts` suffix). The store's actual intent (temporary, explicitly documented as not a permanent domain model, §10.2) is preserved via a doc-comment in the file itself.
**Revisit**: not a real gap — purely a naming note so the design doc and implementation aren't read as silently contradicting each other. No action needed unless the project later adopts a `.store.ts` suffix convention project-wide.

## Open (new from Design System / Visual Polish pass, 2026-07-16)

### `style.css` still doesn't import Tailwind's full `preflight.css`
This pass fixed the two gaps that actually surfaced visually (missing `box-sizing: border-box`, native
`<button>` chrome) with a minimal, explicitly-scoped reset inside the `tailwind-base` cascade layer — not a
full preflight import, to avoid it out-ranking PrimeVue's own component base styles. No other raw-HTML-element
gaps (headings, lists, forms, tables) surfaced during manual review, but a full preflight audit wasn't
performed. See `docs/design-system.md` §7.
**Revisit**: only if a future raw (non-PrimeVue) element shows unexpected native browser styling.

### Docker Desktop bind-mount + Vite dev server: edits sometimes require a container restart
While verifying this pass's changes, the frontend dev server (`dentalsuite_frontend`, Windows host →
Docker Desktop → Linux container bind mount) intermittently kept serving a stale compiled module for an
edited `.vue`/`.ts` file — confirmed by comparing the file Vite's own transform endpoint returned (fresh) against
what the running page actually rendered (stale), with a `docker compose restart node` reliably fixing it. Very
likely a known class of Docker Desktop issue (inotify file-change events not propagating reliably across the
Windows↔WSL2↔container boundary for bind mounts), not an application bug.
**Revisit**: not urgent — a workaround (`docker compose restart node`) exists. Only worth investigating
further (e.g. switching to polling-based file watching in `vite.config.ts`) if it starts noticeably slowing
down day-to-day frontend development.

## Open (new from Dental Chart frontend infrastructure, 2026-07-20)

### `DentalChartEntry` mutation endpoints don't eager-load relations
Same shape as the Appointments item above: `DentalChartEntryResource` responses from `store`/`update`/`complete`/`cancel` don't eager-load `dentalCondition`/`dentist`/`createdBy`/`updatedBy` (only `index` does, via `DentalChartService::listForPatient()`). Unlike Appointments, there is no `GET /api/dental-chart-entries/{id}` endpoint to re-fetch a single entry (deliberate — see backend plan §1.9), so `stores/dentalChartEntries.ts` re-fetches the *whole* patient list after every mutation instead of a single-record rehydration.
**Revisit**: if these controller actions eager-load the same four relations before returning their `DentalChartEntryResource` (mirroring what `index` already does), the frontend's extra full-list re-fetch after every mutation can be replaced with an in-place cache update. Small, low-risk backend change; not blocking — a per-patient chart is a small, bounded list, so the extra round-trip is cheap.

## Open (new from Dental Chart Step 9 — ChartEntryDialog / ChartEntryListTable, 2026-07-22)

### No UI path for the backend-allowed `active → planned` transition
The backend's status-transition matrix (`docs/modules/dental-chart-implementation-plan.md` §1.10) allows
`active → planned`, and `DentalChartEntryTest` covers it. `ChartEntryDialog.vue` only renders
transition actions (Cancel/Complete) when `entry.status === 'planned'` (`ChartEntryDialog.vue:389`) — an
`active` entry has no button to move it to `planned`. Deliberately out of scope for Step 9, which focused
on the dialog/list table themselves, not on closing every gap in the transition surface.
**Revisit**: decide, with the user, whether `active → planned` is a real clinical workflow (e.g. "started
work, now deferring to a future visit") worth a UI affordance, or whether it should be dropped from the
backend's allowed matrix instead if it turns out to be unused. Small, low-risk addition either way — one
more conditional action button in `ChartEntryDialog.vue`, mirroring the existing Cancel/Complete pattern.

## Open (new from Dental Chart Step 11 — Accessibility/Keyboard-Nav + Final QA + E2E, 2026-07-22)

### Known limitation: local Playwright CRUD E2E verification blocked by Windows Docker Desktop networking latency
`frontend/e2e/dental-chart.spec.ts`'s Create → Edit → Complete → Cancel → Delete flow could not be fully
confirmed green from this dev machine — the same Windows Docker Desktop networking pathology already
documented above (see the Appointments E2E entry's "Lesson captured", `permissions.spec.ts` hit the
identical symptom) inflates tiny localhost request latency enough to produce non-deterministic local
timeouts unrelated to the application code. Odontogram view, Accessible List view, receptionist
permission checks, and real-browser keyboard navigation are all covered by the spec; only the local
run's reliability is in question, not the coverage itself.
**Revisit**: not a regression and not blocking — CI's native runner has none of this host's networking
overhead (confirmed for Appointments' equivalent suite, run `29763458360`). CI verification is required
for final confirmation before this item can be marked resolved.

### `ConfirmDialog`'s accept/reject buttons are never translated (always "Yes"/"No" in English)
Found while root-causing a real CI failure in `dental-chart.spec.ts` (its delete-confirmation step,
expecting a button labeled with the dialog's own header text, actually needed to target PrimeVue's
default accept button instead). `App.vue`'s global `<ConfirmDialog />` and every `confirm.require()`
call across the app (Patients, Appointment Types, Time Off, Dental Conditions, and now Dental Chart
entries) — except `StatusActionButton.vue`, which explicitly sets `acceptLabel`/`rejectLabel` — rely on
PrimeVue's own built-in locale defaults (`accept: 'Yes'`, `reject: 'No'`, `@primevue/core/config`).
Neither `main.ts`'s `app.use(PrimeVue, ...)` nor `locales/index.ts`'s `setLocale()` (vue-i18n only) ever
sets PrimeVue's own `config.locale`, so these two buttons stay hardcoded English regardless of the
active ar/en/tr locale — a real, systemic i18n gap, not specific to Dental Chart.
**Revisit**: either call PrimeVue's `usePrimeVue().config.locale = {...}` (or pass `locale` to
`app.use(PrimeVue, ...)`) with translated `accept`/`reject` strings synced to `setLocale()`, or set
explicit `acceptLabel`/`rejectLabel` on every `confirm.require()` call as `StatusActionButton.vue`
already does. Not blocking Dental Chart's closure (matches existing app-wide behavior, not a
regression this module introduced), but worth a dedicated pass before an Arabic/Turkish production
rollout — see also the i18n parity note in `docs/modules/dental-chart-*` and the Step 11 review's
98/98 en/ar/tr parity confirmation, which evidently didn't cover PrimeVue's own internal strings.

## Open (new from Billing Step 4 — Frontend Invoice UI, 2026-07-25)

### Pre-existing missing Arabic i18n keys under `dentalChart.chart.*`/`dentalChart.status.*`
Surfaced by the Billing frontend's full Vitest regression run (unrelated to the Invoice UI work itself —
confirmed by reading the failing assertions' source files, all under `dental-chart` component tests): several
`dentalChart.chart.*` keys (`noEntries`, `toothAriaLabel`, `surface.{M,D,F,L,O,I}`, `surfaceAriaLabel`,
`onSurfaces`, `entriesSummary`, `entryLine`) and `dentalChart.status.*` keys (`active`, `existing`, `planned`,
`cancelled`) exist in `locales/en.json` but are missing from `locales/ar.json`, so `vue-i18n` silently falls
back to English for an Arabic-locale user viewing the Dental Chart — a real, if narrow, gap in the "98/98
en/ar/tr parity" figure `docs/modules/dental-chart.md` reported at that module's own closure. Predates this
session's Billing work; not introduced by it, and not blocking Billing's own Step 4 closure per explicit user
direction.
**Revisit**: audit `locales/ar.json` against `locales/en.json` for every `dentalChart.chart.*`/
`dentalChart.status.*` key, add the missing Arabic translations (mirroring the terminology already
established elsewhere in `ar.json`'s own `dentalChart.*` namespace), and re-run the Dental Chart Vitest
suite to confirm the `[intlify] Not found` warnings are gone. Small, low-risk, translation-only change — not
blocking; worth doing before an Arabic-locale production rollout.

## Open (new from Clinical Notes Step 5 — Playwright E2E Suite, 2026-07-26)

### Known limitation: local Playwright verification for Clinical Notes blocked by the same Windows Docker Desktop networking latency already logged against Dental Chart — RESOLVED 2026-07-26 (CI confirmed 19/19)
`frontend/e2e/clinical-notes.spec.ts` (golden path: create draft → edit → save → sign → verify locked state →
add addendums → verify append-only; plus a blank-note sign-rejection test and a receptionist-exclusion test)
could not be confirmed green from this dev machine — same pathology as the Dental Chart E2E entry above, not
a new issue. Diagnosed directly rather than assumed: a bare `curl` to a trivial endpoint
(`GET /api/up`/`GET /api/users`, both effectively instant server-side) took ~4.7–5.0s round-trip through this
host's Docker networking path while `docker stats` showed every container under 2% CPU — i.e. genuine
host↔container network latency, not resource contention or a slow backend. Every run reached a different
step before timing out (login itself, or `DentistSelect`'s single `GET /api/users` call in the "New Clinical
Note" dialog never populating options in time), consistent with latency that varies run-to-run rather than a
deterministic logic bug — the same "non-deterministic local timeouts unrelated to the application code"
symptom already described for `dental-chart.spec.ts`. One real, unrelated issue *was* found and fixed along
the way: the frontend dev container had been running 10+ hours since the Clinical Notes frontend files were
added, and Vite was serving a stale bundle missing the Clinical Notes tab entirely (even for admin) — resolved
by `docker compose restart node` (see the Vite/Docker staleness note already known from prior sessions), not
a code change. After the restart, a screenshot confirmed the tab, dialog, and form fields all render exactly
as designed; only the option-list-population timing under load remained unreliable.
**Revisit**: not a regression and not blocking — CI's native runner has none of this host's networking
overhead (already confirmed for Appointments' and Dental Chart's equivalent suites). CI verification is
required for final confirmation before this item can be marked resolved. If `DentistSelect`/`providers.ts`'s
silent (uncaught) `fetchAll()` failure mode (see `providers.ts`: no `.catch()` at the `onMounted` call site,
so a failed fetch leaves the picker permanently empty with no visible error or retry affordance) proves to be
a real, not just latency-induced, gap in practice, it's a pre-existing issue shared by every consumer
(Appointments, Dental Chart, Treatment Plans, Clinical Notes), not specific to this module — worth its own
follow-up if it recurs outside this networking-latency context.

**RESOLVED 2026-07-26**: PR #3 (`feature/clinical-notes` → `main`) confirmed via the GitHub Actions API —
`workflow_dispatch` run `30188850793` on commit `74ed97b` (Backend success, Frontend success) surfaced one
real, CI-native (not environment-flake) E2E failure: the golden-path test logged in as `dentist` and then
called `POST /patients` directly to set up its fixture patient, but Patients is admin/receptionist-write,
dentist-read-only (same matrix as Dental Chart/Appointments) — a genuine bug in the test's own setup, not a
Clinical Notes application bug, not an environment issue. Fixed in commit `31b20f3` by switching that test to
log in as `admin` instead (mirroring `dental-chart.spec.ts`'s identical choice for the identical reason —
admin can still exercise the full note lifecycle, since `ClinicalNotePolicy` grants admin the same
create/update/sign/addendum abilities as dentist). Re-run via `workflow_dispatch` on the fixed commit
(run `30189070147`): **Backend success, Frontend success, E2E success — 19/19 E2E tests green**, including
`clinical-notes.spec.ts`'s three tests with no retries needed. One unrelated, pre-existing flake was observed
on the same run — `dental-chart.spec.ts`'s own golden-path test failed once on the exact same
`li.p-select-option:visible` timeout symptom described above, then passed on Playwright's automatic retry
(marked "flaky," not "failure," in the job's annotations) — confirming this class of transient timing issue
is real (if rare) even on CI's native runner, not unique to the local Windows Docker environment, and entirely
pre-existing/unrelated to this module.

## Resolved

### System-Wide Production Gate (resolved 2026-07-18)
Full pre-launch audit and hardening pass across the whole system (not scoped to one module), per
explicit user request after Appointments' Step 10 Final QA was approved. See
`docs/deployment.md` for the full production runbook this pass produced, and the Production Gate
report delivered in-conversation for the complete verified/fixed/risk breakdown. Highlights:

- **`DatabaseSeeder` demo-account gate** (closes the "no environment gate on demo accounts" item
  above): demo users/patients now only seed under `app()->environment('local')`;
  `AppointmentTypeSeeder` (real reference data) always runs. New `php artisan app:create-admin`
  interactive command creates the real first production admin with no known/default credential.
- **General API rate limiting**: `bootstrap/app.php` now calls `throttleApi()`, backed by a
  `RateLimiter::for('api', ...)` (120 req/min per user/IP) registered in `AppServiceProvider` —
  previously only `/login` had any throttling; every other endpoint (patients, users,
  appointments) had none.
- **Production Docker topology**: new `docker-compose.prod.yml`, `docker/php/Dockerfile.prod` +
  `entrypoint.prod.sh` (refuses to boot on a missing `.env`, unset `APP_KEY`, or
  `APP_ENV=local`), `docker/nginx/default.prod.conf` (serves the built frontend + proxies
  `/api`/`/sanctum`/`/up` to php-fpm from one origin), `backend/.env.production.example`,
  `frontend/.env.production.example`. Postgres/Redis no longer publish host ports in production.
- **Backup & recovery**: `docker/scripts/backup.sh` (nightly Postgres dump + storage tar, 14-day
  local retention, optional S3 sync) and `restore.sh` (explicit two-argument, confirmation-gated
  restore). See the "No S3/offsite backup configured yet" and "Restore procedure has not yet been
  exercised" items above — written and reviewed, not yet exercised against real data.
- **CI/CD quality gate**: new `.github/workflows/ci.yml` — backend (Pint, Larastan, `php artisan
  test`) and frontend (`vue-tsc`, ESLint, Prettier check, Vitest, `npm run build`) jobs on every
  push/PR.
- **Frontend fixes**: `PatientFormDialog.vue`/`UsersView.vue`'s `catch (err: any)` → `catch (err:
  unknown)` with narrowed casts (matching `AppointmentDialog.vue`'s existing pattern) — closes the
  ESLint-error half of the "pre-existing files not lint-clean" item above. `vite.config.ts` now
  sets `build.sourcemap: false` explicitly (was relying on an implicit default).
- **Verification performed**: 188/188 backend tests, Pint clean, PHPStan/Larastan 0 errors (level
  5), `vue-tsc` clean, ESLint clean, 259/259 frontend Vitest tests (one router-guard test's
  apparent flake under heavy session-local CPU contention was confirmed, via isolated re-run, to
  be environment-induced, not a real bug — see the isolated 11/11-pass result), production
  frontend build succeeds with no source maps and reasonable chunk sizes. Live-browser Playwright
  verification covered login, dashboard, patients CRUD (cross-checked against the database
  directly), Calendar Board + Day/Week/Month/List views, RTL↔LTR switching, 390px mobile viewport
  (no horizontal overflow), and all four permission-boundary scenarios (dentist/receptionist/guest/
  admin) — all confirmed passing. See the "Playwright E2E coverage exists only as ad hoc scratchpad
  scripts" item above for what wasn't committed as a durable test suite.

### Board day-navigation could land on the wrong calendar day (resolved 2026-07-16)
Originally logged during Appointments Phase 2 Step 4 as a narrow "few hours near local midnight" edge
case. A closer, user-requested audit of the whole datetime stack found that assessment was **wrong** — it
was not narrow at all: once `AppointmentCalendar.vue` set FullCalendar's `timeZone: 'UTC'` (fixing a
separate, confirmed event-rendering bug), passing `calendar.ts`'s genuinely-local `currentDate` straight
into `gotoDate()`/`initialDate` selected the wrong day **every single day**, for any positive-UTC-offset
browser — confirmed directly in a real browser (clicking "Today" showed Thursday instead of the real
Friday). Fixed with a new `lib/date.ts` helper, `toCalendarUtcDate`, applied at both call sites in
`AppointmentCalendar.vue`; regression-tested (`lib/date.test.ts`, `AppointmentCalendar.test.ts`). See
`docs/decisions.md`'s "Project-wide datetime policy" entry for the full audit this came out of.

### Frontend had no linting/formatting configuration (resolved 2026-07-16)
Added ESLint (flat config, `eslint-plugin-vue` + `@vue/eslint-config-typescript` + `@vue/eslint-config-prettier`) and Prettier (`semi: false, singleQuote: true, printWidth: 110, trailingComma: "all"`, matching the codebase's existing style exactly) as permanent frontend tooling, alongside the Vitest toolchain added in the same step. `npm run lint` / `npm run format` scripts added. See the note above re: pre-existing files not yet reformatted.

### Appointment types had no default-data seeder (resolved 2026-07-16)
`docs/modules/appointments-design-draft.md` §4 stated appointment types should be seeded with a sensible default set on install; verified during the 2026-07-16 checkpoint that `GET /api/appointment-types` returned `[]` on a fresh install — no seeder existed. Fixed by adding `backend/database/seeders/AppointmentTypeSeeder.php` (Consultation/Cleaning/Filling/Root Canal/Crown/Extraction, each with a duration and color, `firstOrCreate`-based so it's safe to re-run), wired into `DatabaseSeeder`. Verified live against the real Postgres container after `migrate:fresh --seed`: `GET /api/appointment-types` now returns all 6 types. See `docs/demo-guide.md` §5 for the exact seeded values.

### Root `.gitignore` was incomplete prior to the first commit (resolved 2026-07-16)
The repository had no git history until the 2026-07-16 checkpoint. The root `.gitignore` present at that point only excluded OS/editor cruft (`.DS_Store`, `.idea`, etc.) — it did not exclude `backend/vendor/`, `frontend/node_modules/`, `.env` files, or Laravel's runtime `storage/`/`bootstrap/cache/` artifacts at the root level. In practice this was harmless because `backend/.gitignore` and `frontend/.gitignore` (Laravel's and Vite's own scaffolded ignore files) already excluded those paths independently — but the root file was still incomplete on its own terms. Fixed by expanding the root `.gitignore` with explicit backend/frontend dependency, build-output, and secret-file exclusions before staging the first commit, as a belt-and-suspenders measure.
**Status**: resolved as part of that checkpoint's git initialization (commit `9a74ffb`).

### Audit logs (resolved 2026-07-14)
Implemented as generic infrastructure (`Auditable` trait + `AuditObserver` + `AuditLog` model) as part of the Patients module, since Patients was the first module to touch sensitive PII. See [docs/modules/patients.md](docs/modules/patients.md).

### Guest requests crashing without `Accept: application/json` (resolved 2026-07-14)
Fixed in `bootstrap/app.php`. See [docs/decisions.md](docs/decisions.md).

### `storage/logs/laravel.log` unwritable by php-fpm worker (resolved 2026-07-14)
Fixed in `docker/php/entrypoint.sh`. See [docs/decisions.md](docs/decisions.md).

### PHPStan not installed (resolved 2026-07-15)
Larastan (`larastan/larastan ^3.10`) installed at level 5, configured in `backend/phpstan.neon`. 0 errors after fixing the issues it found (see `docs/decisions.md`). Level 5 is a deliberate starting point, not a ceiling — see `phpstan.neon` comments.

### Patient/User search was case-sensitive on Postgres (resolved 2026-07-15)
Fixed by switching to Laravel's `whereLike()`/`orWhereLike()`. See [docs/decisions.md](docs/decisions.md).

### Patient search had no usable index for `LIKE '%term%'` queries (resolved 2026-07-15)
`pg_trgm` GIN indexes added on the searched columns (Postgres-only migration). See [docs/decisions.md](docs/decisions.md).

## Open (new from Appointments Phase 2 Step 10 — Final QA, 2026-07-18)

### Board's fetch-failure UX is a toast, not the inline retry state the design doc calls for
Design doc §1.9 ("If the Board's range fetch itself fails... show an inline retry state in place of
the calendar grid — a broken calendar with no data and no way to retry is a dead end") describes a
richer treatment than what Step 10 actually shipped: `AppointmentsView`/the three Dashboard-adjacent
widgets now show an error toast when `appointments.error` is set (previously showed nothing at all —
see CHANGELOG), but the calendar/widget still renders its ordinary empty state underneath, and there
is no in-place "Retry" affordance.
**Revisit**: if silent/toast-only failure proves insufficient in practice, replace the Board's empty
render with a dedicated error state (message + retry button) when `appointments.error` is set,
matching §1.9 as originally specified. Not blocking — the toast closes the "totally silent" gap that
existed before; this is about matching the fuller documented design, not fixing a currently-broken
experience.

### Mobile Week/Month view is legible but cramped; design doc's "auto-select Day view on narrow
viewports" isn't implemented
Design doc §1.11 states Day view "is auto-selected when the viewport is narrow on first load (still
user-overridable)." No such logic exists anywhere in `calendar.ts`/`AppointmentsView.vue` — confirmed
by reading both files. Verified directly at a 390px viewport: Week view (the default) still renders
all 7 day columns, each only a few pixels wide once time labels are accounted for — readable, and no
longer causes the whole-page horizontal scroll fixed this step (see CHANGELOG), but a poor first
impression compared to what the design doc describes.
**Revisit**: add a `window.innerWidth` (or a CSS-driven) check on first mount that defaults
`calendar.viewMode` to `timeGridDay` below a mobile breakpoint, only when the user hasn't already
picked a view this session. Small, self-contained change; not blocking.

### `vue-i18n`'s full compiler+runtime build ships on every page (~84 KB gzipped)
Confirmed via `npm run build`: `vue-i18n` is the second-largest chunk in the whole app (273 KB /
84 KB gzip), eagerly loaded on every route since `main.ts` installs it globally. This project has no
`@intlify/unplugin-vue-i18n` (or equivalent) build step — locale JSON is passed straight to
`createI18n({ messages })` and compiled to render functions **at runtime** via `t()`, which is exactly
why the full (not runtime-only) build is required. Simply aliasing to
`vue-i18n/dist/vue-i18n.runtime.esm-bundler.js` without also adding message precompilation would break
every translation in the app — not attempted this step; the fix has real setup cost and its own
regression surface across all 3 locales.
**Revisit**: add `@intlify/unplugin-vue-i18n` to `vite.config.ts` to precompile `locales/*.json` into
message functions at build time, then switch the `vue-i18n` import to its runtime-only build. Worth
doing given the app-wide (not just Appointments) bundle-size win, but needs its own dedicated,
carefully-verified pass across `en`/`ar`/`tr`.

### Keyboard-shortcut-triggered dialog close doesn't restore focus anywhere meaningful
`useDialogFocusRestore()` correctly returns focus to the triggering element when a dialog is opened
by a **click** (confirmed directly: closing a mouse-opened "New Appointment" dialog returns focus to
that exact button). But when the same dialog is opened via the `N` keyboard shortcut, there was no
specific focused element to begin with (the shortcut is a global `window` keydown listener, not tied
to any button) — closing it restores focus to `<body>`, forcing a keyboard-only user to tab from the
very top of the page again. This is the composable behaving exactly as designed ("restore whatever
had focus"); the gap is that "whatever had focus" is meaningless for this specific entry path.
**Revisit**: if this proves a real friction point for keyboard users, have the `N`/`?` shortcut
handlers move focus to a stable anchor (e.g. the page's `<h1>` or the New Appointment button itself)
immediately before opening the dialog, so there's something meaningful for the restore to return to.
Low priority, narrow fix.

## Open (new from 2026-07-15 review)

### PHPStan level 5, not stricter
Chosen as a pragmatic starting point for a codebase with no prior static analysis (Larastan's own recommended default). Levels 6-9 add generic-type-hint strictness that would need broader retrofitting across the codebase.
**Revisit**: raise incrementally (6, then 7, ...) as new modules are built and the team has bandwidth to address the stricter findings — not urgent, no known bugs are being masked by staying at 5 today.

## Open (new from System-Wide Production Gate, 2026-07-18)

### No S3/offsite backup configured yet — local-disk-only
`docker/scripts/backup.sh` dumps Postgres + `storage/app/private` to a local directory on the VPS
host (`/var/backups/dentalsuite`) with a 14-day retention prune. The `aws s3 sync` offsite-copy line
is present but commented out — no S3-compatible bucket has been provisioned yet. A local-disk-only
backup does not survive VPS loss, disk failure, or provider-level incident.
**Revisit**: before onboarding the first real clinic — provision an S3-compatible bucket
(Backblaze B2, Wasabi, or AWS S3) and uncomment the sync line in `backup.sh`. See
`docs/deployment.md` "Backup & Recovery".

### Restore procedure — RESOLVED (2026-07-20): rehearsed end-to-end, not just written
Updated same pass: ran the full runbook for real, not just reviewed it. Added
`docker-compose.rehearsal.yml` (isolated, disposable Postgres + app stack — its own network, no
host ports, anonymous volume) plus `COMPOSE_FILE`/`ENV_FILE` overrides on `backup.sh`/`restore.sh`
so the *actual*, unmodified production scripts could target it. Took a real backup from the dev
stack, restored it into the fresh rehearsal target, and verified: every table's row count matched
the source exactly (9 patients, 3 users, 6 appointment types, 0 appointments), the first patient
record matched field-by-field including the UUID, and the storage tar extracted correctly — while
confirming the source dev stack was completely unaffected throughout. See `docs/deployment.md`
"Testing restore" for the exact commands and full result. Only remaining gap: this proved the
mechanics work, not a run against real production data (none exists yet) — re-run once live.

### `.github/workflows/ci.yml` — CONFIRMED running, all three jobs green, e2e 13/13 (RESOLVED 2026-07-20)
Updated 2026-07-20 (later same pass): the workflow's first real pushes to `main` (commits
`b2459de` onward) surfaced and closed several genuine CI-harness-only bugs — none were
application bugs, all confirmed via reading actual job logs/screenshots/backend logs, not
assumed:
- `php artisan serve` needs `PHP_CLI_SERVER_WORKERS` (single-threaded by default; a real browser
  fires several concurrent requests per navigation) — fixed (`ba2bc1c`).
- The E2E job pointed the browser/Vite at `127.0.0.1` while `backend/.env.example`'s
  `FRONTEND_URL`/`SANCTUM_STATEFUL_DOMAINS` say `localhost` — different strings, so every
  cross-origin request failed Sanctum/CORS's origin check and was silently blocked by the
  browser before reaching the server (this was the single biggest fix — took the suite from
  12/13 *failing* to 11/13 *passing*) — fixed (`f3447cf`).
- `frontend/src/views/AppointmentsView.test.ts` (Vitest, pre-existing, unrelated to this
  session's E2E work): a fixture appointment hardcoded to `2026-07-15` only stayed inside the
  Board's "current week" filter (computed from the real `new Date()`) as long as wall-clock time
  hadn't drifted past it — broke once it finally did, mid-session. Fixed with
  `vi.useFakeTimers()`/`setSystemTime()` (`33eb84d`).
- Backend (Pint, Larastan, 188 tests) and Frontend (`vue-tsc`, ESLint, Prettier, 259 Vitest
  tests, production build) jobs are both **fully green** as of `33eb84d`.

**RESOLVED 2026-07-20** — the E2E job's "creates, reschedules, and cancels an appointment" test
took four rounds to actually close, each one root-caused from real evidence (GitHub's REST API
for job annotations/logs — `gh` CLI wasn't available in-session, but the API needs no auth for a
public repo) rather than assumed from local runs alone, after commit `3ca7c30`'s first "verified
locally, should be green" claim turned out wrong on real CI (a lesson in itself — see below):

1. (`3d684fe`, superseded) Dismissing the DatePicker's popover by clicking the dialog title broke
   when the time-picker view is tall enough to flip and render *above* the input — the popover
   overlaps the title and swallows the click. `3ca7c30` fixed this by clicking the modal mask
   instead (`Dialog` here has no `dismissable-mask`, so it can't close the Dialog, but it's still
   a real "outside click" for the popover's own listener).
2. (`3ca7c30`) Both save-success checks used `Locator.isVisible({ timeout })`, which does **not**
   wait/retry — it checks once and returns immediately, racing the save request and reporting
   failure before the toast rendered. Replaced with `expect(...).toBeVisible()`, which polls.
3. (`9bcf078`, found by reading `3ca7c30`'s own CI run via the Actions API, not by re-guessing)
   Two further bugs, both real: (a) `AppointmentDialog.vue`'s `visible` watcher unconditionally
   resets `activeTab` to `'patient'` on open, edit mode included, so the reschedule step's
   `startAtInput.click()` was timing out on a field that literally wasn't rendered yet — fixed
   with the missing "Appointment" tab click. (b) The suite computed a hardcoded "tomorrow 9am"
   slot by hand; Playwright's CI-only `retries: 1` re-runs a failed test from scratch, so when
   attempt 1 booked that slot and then failed *later* (on bug (a)), the automatic retry's fresh
   attempt collided with attempt 1's own leftover appointment — a genuine
   `DentistConflictException`, but caused by the test never asking the backend what was actually
   free, not by any bug in `AppointmentService::availableSlots`/`busyRangesForDentist` (read and
   confirmed correct). Fixed by driving the app's own "Show available slots" toggle
   (`SlotPicker.vue`, backed by the real `GET /available-slots` endpoint) and clicking a real free
   slot on every attempt — the same thing a real user would do, and immune to retries because it
   always reflects current state.
4. (`3faf2d7`, again found by reading `9bcf078`'s own CI run) A cancellation renders "Cancelled"
   in two places at once — the status chip and the Timeline's own step label — so
   `page.getByText('Cancelled')` was a strict-mode violation the instant the page updated in time
   to show both. No prior run (local or CI) had ever reached a real successful cancellation before
   `9bcf078`'s fixes, so this had been latent and unreachable the entire time. Scoped to
   `getByLabel('Cancelled')` (the status chip's own `aria-label`).

**Lesson captured**: local verification alone was insufficient and produced a false "should be
green" claim twice (this dev machine's Docker Desktop networking adds multi-second latency to
tiny localhost requests — confirmed directly via Playwright trace timing data, e.g. a 660-byte
`/cancel` response taking 5.3s to first byte + 8.2s to receive — which reliably masked timing-
dependent bugs, including bug 4 above, that only a fast runner like CI's would ever actually
reach). Treat "pushed, verified locally" as provisional until the *actual* CI run is read back
via its API/logs, not assumed from local behavior, whenever CI and local environments materially
differ in speed or topology.

Confirmed via the GitHub Actions API (not just workflow-level "success" — the individual E2E job
and its own Playwright summary annotation): run `29763458360` on commit `3faf2d7`, all three jobs
(Backend, Frontend, E2E) green, **13 passed (36.2s)**.

## Open (new from Inventory module, 2026-07-26)

### Local `phpstan analyse` (level 5) is broken in this dev container independent of the Inventory module — pre-existing, not a regression
While verifying Inventory, `./vendor/bin/phpstan analyse` inside `dentalsuite_app` reported errors
on pre-existing, already-shipped models (`TreatmentPlan`/`TreatmentPlanItem` — e.g. "Access to an
undefined property `App\Models\TreatmentPlan::$status`") with **zero** Inventory code present.
Confirmed by `git stash -u` (removing every Inventory file, back to the exact `main` state) and
re-running: **410 errors**, all in pre-Inventory files this project's own CHANGELOG previously
recorded as PHPStan-clean. Re-applying the stash reproduces the same failure plus a handful more
in the new Inventory files, all of the identical "undefined property" shape. Root cause not fully
isolated (Larastan's model-property resolution appears to depend on some warmed state — a
bootstrap cache or a live, successfully-migrated DB connection at analysis time — that this
container's `phpstan analyse` invocation isn't reliably getting; clearing `storage/phpstan`'s
result cache made it *worse*, not better, ruling out a stale-cache explanation). One genuinely
real, unrelated-to-this-flake finding was fixed regardless while investigating:
`Supply::scopeLowStock()` called `Supply::scopeWithQuantityOnHand()` via `$query->
withQuantityOnHand()` — PHPStan/Larastan cannot statically resolve one magic `scope*` method
calling another through the generic `Illuminate\Database\Eloquent\Builder` return type. Fixed by
factoring the shared join into a plain `public static function applyQuantityOnHandJoin()` that
both scopes call directly (`app/Models/Supply.php`).
**Revisit**: this is a local/environment issue, not a code defect — the same "local ≠ CI" caution
already logged above for E2E/Docker-networking timing. CI's own `phpstan analyse` step (run by a
container that's presumably warmed/configured differently than this ad hoc `docker exec`) is the
verification authority for whether Inventory's own new code is actually PHPStan-clean; this
couldn't be locally confirmed one way or the other for the *existing* codebase either, so it isn't
a new gap Inventory introduced.

### Known limitation: local Playwright verification for Inventory blocked by the same Windows Docker Desktop networking latency already logged against Dental Chart/Clinical Notes — not yet CI-confirmed
`frontend/e2e/inventory.spec.ts` (admin golden path: create Category → Supplier → Supply → record
initial stock → record usage down to below reorder level → confirm Low Stock flag and Dashboard
widget → create/place/receive a Purchase Order fully through `received` → confirm final on-hand;
plus a draft-cancel test and a dentist-permission-boundary test) could not be confirmed fully green
from this dev machine — same pathology as the Dental Chart/Clinical Notes entries above, not a new
issue, and diagnosed directly rather than assumed:
- **First, a real self-inflicted issue, found and fixed**: an earlier `php artisan migrate:fresh
  --force` run against this session's *real* dev Postgres (missing `--env=testing`, meant only to
  sanity-check the new migrations) wiped every table, including `users` — silently deleting the
  demo admin/dentist/receptionist accounts every E2E spec logs in as. Diagnosed by tracing a 422 on
  `POST /api/login` back to `User::count() === 0`, not assumed. Fixed by re-running
  `php artisan db:seed --force`, restoring the three demo accounts. Worth naming explicitly as a
  lesson: never run a bare (non-`--env=testing`) `migrate:fresh` against a shared dev database
  without immediately reseeding it, even for a "just checking the migration runs" sanity check.
- **Two real bugs in the E2E spec itself, found and fixed**: `dialog.getByLabel('Name')` for the
  Supplier form matched *two* elements (Playwright's `getByLabel` does substring matching by
  default, and "Name" is a substring of "Contact Name") — fixed with `{ exact: true }`, mirroring
  the Supply form field's own already-`exact: true` field. A handful of post-mutation assertions
  used Playwright's 5s default `expect` timeout instead of an explicit longer one, too tight under
  this host's latency — widened to `{ timeout: 10_000 }` to match the pattern already used
  elsewhere in this same spec.
- **The remaining flakiness is 100% the pre-existing networking-latency class, confirmed by
  reproducing the identical symptom on `auth.spec.ts`** — a completely unrelated, unmodified,
  previously-passing spec — run in isolation on this same machine: `page.waitForFunction` timing
  out waiting for the post-login redirect in `fixtures.ts`'s shared `login()` helper, intermittently,
  with no code path anywhere near Inventory involved. This conclusively rules out an
  Inventory-specific defect as the cause of the remaining flakiness.
- **Direct manual verification, not just automated-test evidence**: a standalone Playwright script
  (login → hard-navigate to `/inventory/suppliers`) confirmed the full page renders correctly —
  sidebar "Inventory" nav group (Supplies/Purchase Orders/Suppliers/Categories), the Suppliers
  DataTable, "New Supplier" button, and all form fields — once given `waitUntil: 'networkidle'`
  instead of the default, consistent with a first-visit Vite dev-server cold-compile delay
  (every Inventory route/component is being requested for the first time all session) compounding
  with the already-documented host networking latency, not a rendering defect.
- Once past login, the *dentist permission-boundary* test (no data mutations, the fastest of the
  three) passed reliably on every one of several consecutive runs. The two data-mutation tests
  reached progressively further on each retry (culminating in a run that got all the way through
  category/supplier/supply creation, initial-stock recording, and into the Purchase Order flow)
  without hitting a single Inventory-specific assertion failure — only the shared login step's
  timing remained unreliable.
**Revisit**: not a regression and not blocking, matching the Dental Chart/Clinical Notes precedent
exactly — CI's native runner has none of this host's networking overhead. Unlike Clinical Notes,
this has **not yet been confirmed via an actual CI run** (this branch has not been pushed to a PR
yet) — do that before marking this resolved, following the exact same `workflow_dispatch` /
GitHub Actions API confirmation process used for every prior module.

**Update (2026-07-27)**: pushed and run via `workflow_dispatch` (run `30277023360`). CI's own
native runner — unaffected by this host's networking latency — surfaced genuine, reproducible
bugs the local flakiness had been masking: (1) a real PHPStan error (`PurchaseOrderService`
assigning a plain string to a `Carbon|null`-cast property) fixed by assigning `now()` directly
instead of `now()->toDateString()`; (2) every `InputNumber`/`DatePicker`/`Select` in the Inventory
components used a plain `id` prop, which PrimeVue applies to the component's root wrapper, not its
inner focusable input — the paired `<label for="...">` never actually associated with anything
focusable, a genuine accessibility defect (label-click-to-focus and screen readers both silently
broken), not just a test-selector inconvenience. Fixed everywhere in Inventory using PrimeVue's own
`input-id` prop, which correctly forwards to the real input. (3) `PurchaseOrderActionsBar`'s cancel
confirmation never set `acceptLabel`/`rejectLabel`, defaulting to PrimeVue's generic "Yes"/"No"
instead of the contextual label `InvoiceActionsBar.vue`'s own confirm dialogs already use. All
three fixed and re-verified locally (Pint, 35/35 `PurchaseOrder` tests, `vue-tsc`/ESLint/Prettier,
19/19 Inventory Vitest tests) before pushing again for re-confirmation.

**Codebase-wide gap noted, not fixed here (out of scope for this module)**: the same `id` (instead
of `inputId`) mistake on a PrimeVue form control already exists elsewhere — e.g. `UsersView.vue`'s
role `Select` (`id="role"` paired with `<label for="role">`) — confirming this is a pre-existing,
systemic gap across the codebase's PrimeVue form usage, not something introduced by Inventory.
**Revisit**: worth a dedicated accessibility-focused pass across every module's forms at some
point, auditing every `Select`/`InputNumber`/`DatePicker`/similar wrapped-input PrimeVue component
for a plain `id` that should be `inputId` instead — but not blocking, and deliberately not fixed
opportunistically here to keep this module's diff scoped to Inventory.

**Two further rounds of real, CI-native bugs found and fixed** (each caught by the *next* run after
the previous fix, since a fresh CI run was needed to reach each subsequent point in the golden
path): (4) `SuppliesView.vue`'s `onSaved()` and `PurchaseOrderDetailView.vue`'s
`onItemAdded()`/`onItemReceived()` each showed their own success toast on top of the one their
child dialog (`SupplyFormDialog`/`AddPurchaseOrderItemDialog`/`ReceivePurchaseOrderItemDialog`)
already displays for the same action — a real double-toast bug on every Supply save/item add/item
receive, caught by Playwright's strict-mode violation (two identical toast nodes), not flakiness.
Same bug, third instance: `SupplyDetailView.vue`'s `onRecorded()` duplicated
`RecordStockMovementDialog.vue`'s own "Movement recorded" toast. All three fixed by keeping only
the state-refresh call each handler still needs. (5) The E2E spec itself had a real selector
ambiguity: `page.getByText('Used')` matched both the Stock Movements ledger's own cell and the
just-closed Record Movement dialog's Reason combobox (its `aria-label` still carried "Used" from
its last interaction even while hidden) — fixed by scoping ledger-reason assertions to
`.p-datatable`, and proactively fixed the identical landmine at the end of the test (two "Received"
rows exist in the ledger by then) with `.first()` before it was ever hit.

**RESOLVED 2026-07-27**: confirmed via the GitHub Actions API across five `workflow_dispatch` runs
on `feature/inventory` (`30277023360` → `30280053248` → `30280937935` → `30281608486` →
`30282195677`), each surfacing and closing exactly one more real, CI-native bug than the last — a
textbook case for why "verified locally" is provisional until CI itself confirms it (this dev
machine's own local E2E attempts hit the pre-existing Windows Docker networking latency described
above and never got far enough into the golden path to surface bugs 4/5 at all). Final run
(`30282195677`): **Backend success, Frontend success, E2E success — 20/20 E2E tests green**,
including all three `inventory.spec.ts` tests with no retries needed.

## Open (new from Laboratory module, 2026-07-27)

### Local `phpstan analyse` container issue recurred, same root cause as Inventory's — confirmed pre-existing again, not a Laboratory regression
Same symptom as the Inventory entry above: `docker exec dentalsuite_app vendor/bin/phpstan analyse`
reported 427 errors, nearly all "undefined property" on pre-existing, unrelated models
(`TreatmentPlan`/`TreatmentPlanItem`). Re-isolated the same way: `git stash -u` back to the exact
`main` state (Laboratory's design-doc-only commit removed) reproduced **420** errors with zero
Laboratory code present; re-applying the stash added exactly 7 more, all the identical
"undefined property" shape on `Lab`/`LabCase` files (`User::$role`, `User::$name` — the same class
of noise, not a real defect). Also tried `php artisan migrate` (the two new Laboratory migrations
were still `Pending` against this container's real dev Postgres) and clearing
`storage/phpstan`'s result cache — neither changed the error count, ruling out both as the cause.
**Revisit**: same as the Inventory entry — local/environment issue, not a code defect. CI's own
`phpstan analyse` step confirmed clean for Laboratory (see RESOLVED note below).

### Pre-existing `dental-chart.spec.ts` 429 rate-limit collision surfaced during Laboratory's own CI runs — confirmed unrelated to Laboratory
Both `workflow_dispatch` runs for `feature/laboratory` (`30293175321`, `30294033562`) failed the
same two `dental-chart.spec.ts` tests with an identical `POST /patients` `429 Too Many Attempts`
(`AppServiceProvider`'s `RateLimiter::for('api', ...)`, 120 requests/minute per authenticated
user). **Proven unrelated to Laboratory, not just assumed**: `npx playwright test --list` shows
Playwright's actual (alphabetical-by-file, `workers: 1`, strictly sequential) execution order —
`dental-chart.spec.ts` runs and fails at position 10-12 in the suite, `laboratory.spec.ts` at
position 16-18, *after* it. Laboratory's own requests cannot retroactively cause an error that
already happened earlier in a sequential run — the 429 is cumulative admin-authenticated request
volume from `appointments.spec.ts`/`clinical-notes.spec.ts`/`dental-chart.spec.ts`'s own earlier
tests hitting the per-minute limiter, reproduced identically in both runs regardless of Laboratory's
presence. `inventory.spec.ts`/`patients.spec.ts` also showed the same pre-existing first-load
button-timeout flakiness already logged elsewhere in this file (passed on retry both times).
**Revisit**: raise the `api` rate limiter's per-minute allowance for the `testing`/CI environment
specifically (or reduce redundant admin-authenticated requests across E2E specs), so the cumulative
request volume across the full E2E suite — which only grows as more modules add their own specs —
stops intermittently tripping `dental-chart.spec.ts`. Not blocking Laboratory: its own three E2E
tests passed cleanly with no retries in the second run (see RESOLVED note below), and this is a
suite-wide capacity issue predating Laboratory, not a defect in it.

**Fixed 2026-07-27 (same day, before opening the Laboratory PR)**: rather than leave this for a
future module to trip over again, made the limit configurable — `RateLimiter::for('api', ...)` in
`AppServiceProvider` now reads `config('api.throttle_per_minute')` (new `backend/config/api.php`,
`env('API_THROTTLE_PER_MINUTE', 120)`) instead of a hardcoded `120`. Production behavior is
unchanged (default stays 120; `.env.example` documents the var at its existing default). Only
`.github/workflows/ci.yml`'s E2E job env now sets `API_THROTTLE_PER_MINUTE=1000`, so the full
Playwright suite (single-worker, sequential, one demo admin) has enough headroom regardless of how
many more specs future modules add.

**RESOLVED 2026-07-27**: confirmed via `workflow_dispatch` run `30295881535` on commit `33de932` —
**Backend success (815/815 tests), Frontend success (627/627 Vitest tests), E2E success: 25/25
passed, 0 failed, 0 flaky** (previously-flaky `inventory.spec.ts`/`patients.spec.ts` also passed
clean this run, with no retries needed). `dental-chart.spec.ts` and `laboratory.spec.ts` both
green, closing this item for good rather than leaving it to trip the next module too.

### Real bug found and fixed via CI: duplicate-worded toast on rapid back-to-back Lab Case status transitions
First `workflow_dispatch` run (`30293175321`) caught a genuine strict-mode violation, not
flakiness: `LabCaseActionsBar.vue` used one generic toast message ("Lab case updated") for all four
transitions (send/receive/qualityCheck/cancel). The E2E golden-path test runs send → receive →
quality-check in quick succession; since each toast has a 3s life, two identically-worded toasts
were genuinely on screen at once — the same ambiguity a real user would see, not just a test
artifact. Fixed by giving each action its own distinct message ("Case sent to lab" / "Case marked
received" / "Quality check completed" / "Lab case cancelled"), updated in all three locale files
(938/938/938 key parity re-verified) and the E2E spec's own assertions.

**RESOLVED 2026-07-27**: confirmed via the GitHub Actions API across two `workflow_dispatch` runs
on `feature/laboratory` (`30293175321` → `30294033562`), the second closing the one real bug the
first surfaced (the duplicate-toast issue above). Final run (`30294033562`): **Backend success,
Frontend success**; E2E: all three `laboratory.spec.ts` tests passed with no retries needed (the
run's only remaining failures/flakiness — `dental-chart.spec.ts`, `inventory.spec.ts`,
`patients.spec.ts` — are the pre-existing, proven-unrelated issues documented above).

**Fully green 2026-07-27**: the suite-wide 429 rate-limit item above was then fixed (not just
documented) before opening the PR. Re-run via `workflow_dispatch` (`30295881535`, commit `33de932`)
confirms the whole CI pipeline clean: **Backend 815/815, Frontend 627/627, E2E 25/25 — zero
failures, zero flakes.**

## Open (new from Imaging module, 2026-07-28)

### `App\Rules\BelongsToPatient` throws a real SQL error when used against `TreatmentPlanItem` — pre-existing bug, also present (unexercised) in Laboratory
Discovered while writing Imaging's own Feature tests for `treatment_plan_item_id` traceability:
`BelongsToPatient` queries `$modelClass::query()->where('id', $value)->where('patient_id',
$patientId)->exists()`, but `treatment_plan_items` has **no `patient_id` column** — confirmed
directly (`Schema::getColumnListing('treatment_plan_items')` and the migration itself,
`2026_07_22_000003_create_treatment_plan_items_table.php`, only has `treatment_plan_id`, not
`patient_id`). Reproduced directly against real Postgres via `tinker`: the query throws
`SQLSTATE[42703]: Undefined column`. `Lab CaseTest`/`LabCase`'s own
`StoreLabCaseRequest`/`UpdateLabCaseRequest` use this exact same `BelongsToPatient(TreatmentPlanItem::class,
...)` call and have the identical latent bug — never caught because no Laboratory test ever set
`treatment_plan_item_id` on a request. In production, this means **any attempt to create or update a
Lab Case with a `treatment_plan_item_id` set would 500-crash** instead of validating normally.
**Fixed for Imaging only** (`StorePatientImageRequest`/`UpdatePatientImageRequest` use an inline
closure querying via the `treatmentPlan` relation instead — see those files) — deliberately not
touched in `LabCase`'s own Form Requests here, to keep this branch's diff scoped to Imaging.
**Revisit**: apply the identical fix to `StoreLabCaseRequest`/`UpdateLabCaseRequest` (swap
`BelongsToPatient(TreatmentPlanItem::class, ...)` for the same `whereHas('treatmentPlan', ...)`
closure), and add a Feature test exercising `treatment_plan_item_id` to `LabCaseTest` so this class
of gap can't recur silently. Low risk, small change — should be done before Laboratory's
`treatment_plan_item_id` field is ever actually used in practice.

### DICOM/CBCT, persistent annotation tools, and formal FMX/series grouping are out of V1 scope
Named explicitly, not silently skipped — design doc §7/§14 (`docs/modules/imaging-design.md`) covers
the reasoning for each: DICOM/CBCT is a materially larger, separate undertaking (dedicated viewer,
study/series/instance data model) not needed for general-practice day-to-day workflow; persistent
annotation/measurement tools need a calibrated canvas layer, deferred pending real demand; FMX/series
grouping is covered well enough in V1 by the existing type/tooth/date-range filters.
**Revisit**: only if a real need for any of the three appears in practice — see the design doc's
§15 confirmation that the schema needs no reshaping to add any of them later.

### Thumbnail generation is synchronous (GD, on the upload request), not queued
Design doc §10: acceptable for V1 given realistic per-clinic upload volume (a handful of images per
visit, not a bulk-import workflow). Redis-backed queues are already configured project-wide.
**Revisit**: only if real upload volume makes the upload request noticeably slow in practice — move
`PatientImageService`'s thumbnail generation into a queued job at that point, keeping the upload
response return the image row immediately with `thumbnail_path` filled in by the job shortly after.

### No malware/virus scanning on uploaded images
Design doc §9: no such infrastructure exists anywhere in this codebase yet. Named as a known gap,
not silently ignored — matches the same class of gap other modules' file-adjacent features would
eventually need too (Laboratory's deferred file/photo/STL attachments, TECH_DEBT.md's Laboratory
entry above).
**Revisit**: before onboarding real multi-tenant SaaS clinics with broad staff upload access, add a
scanning step (e.g. ClamAV via a queued job) between upload and the image becoming visible/
downloadable to other staff.

### Known limitation: local Playwright verification for Imaging blocked by the same Windows Docker Desktop networking latency already logged against Dental Chart/Clinical Notes/Inventory — RESOLVED 2026-07-27 (CI confirmed 27/27)
`frontend/e2e/imaging.spec.ts` could not be run locally — both tests failed at `login()` itself
(`page.waitForFunction` exceeded 25s), the identical symptom already documented for every prior
module on this dev machine. Diagnosed directly, not assumed: `curl http://localhost:8000/api/ping`
took 2.4s for a trivial, effectively-instant server-side endpoint. `docker exec dentalsuite_app`
migration (`2026_07_27_000003_create_patient_images_table`) applied cleanly against the real dev
Postgres.
**RESOLVED 2026-07-27**: confirmed via the GitHub Actions API across three `workflow_dispatch` runs
on `feature/imaging` — the first surfaced two real PHPStan errors (`PatientImageService`: a dead
`?? 0` on `UploadedFile::getSize()`'s non-nullable return, a dead `=== false` check on
`ob_get_clean()` right after `ob_start()`, and a `$patient->id` int/string type mismatch needing an
explicit cast) plus a real E2E selector bug (`imaging.spec.ts`'s dialog-open assertion matched both
the dialog title and its own submit button, both reading "Upload Images" — fixed by giving the
submit button its own distinct "Upload" label, en/ar/tr, and scoping the title assertion to
`.p-dialog-title`). The second run surfaced two more real E2E selector bugs: a page-wide
`getByText('16')` matched an unrelated element instead of the tooth dropdown's own option (fixed by
scoping to `.p-select-option:visible`, mirroring `laboratory.spec.ts`'s established pattern), and
unscoped "Edit"/"Delete" button locators collided with `PatientDetailView`'s own header buttons for
the patient record (fixed by scoping to the specific thumbnail's `.group` container). Final run
(`30310705267`): **Backend success (834/834), Frontend success (637/637), E2E success — 27/27
passed, 0 failed, 0 flaky.**

### Local-disk image storage is a known V1 limitation for horizontal scaling
Design doc §7 decision 5 / §11: every image read/write goes through the `Storage` facade using the
disk stored on each row (never hardcoded), so moving to `s3` (already configured in
`config/filesystems.php`, currently unused anywhere) is a config change, not a code change — but V1
itself still defaults to `local`, which doesn't survive/replicate across multiple app servers.
**Revisit**: switch `API`-style env config (`FILESYSTEM_DISK`/`AWS_*`) to `s3` before any production
deployment that runs more than one app server instance.

## Open (new from Reports module, 2026-07-28)

### Local `phpstan analyse` container issue recurred again, same root cause as Inventory's/Laboratory's — confirmed pre-existing, not a Reports regression
Same symptom, isolated the same way as the two entries above: a full `docker exec dentalsuite_app
vendor/bin/phpstan analyse` reported 467 errors, nearly all "undefined property" on pre-existing,
unrelated models (`TreatmentPlan`/`TreatmentPlanItem`/`User`). `git stash -u` back to the exact
pre-Reports state reproduced **430** errors with zero Reports code present; restoring the stash
added exactly 37 more, all the identical "undefined property" shape in `ReportService.php` (which
legitimately reads `TreatmentPlanItem`/`User` properties for the Production/Treatment-Plan-
Acceptance reports). **Revisit**: same as the Inventory/Laboratory entries — a local/environment
issue, not a code defect. CI's own `phpstan analyse` step is the authoritative check.

### Known limitation: local Playwright verification for Reports blocked by an Alpine/musl vs. glibc Chromium mismatch in this dev container — a new root cause, not the Windows Docker networking issue logged against Dental Chart/Clinical Notes/Inventory/Imaging
`frontend/e2e/reports.spec.ts` (2 tests) is written and statically clean (`eslint`/`prettier --check`
both pass), but could not be executed in this session's `dentalsuite_frontend` container: Playwright's
downloaded Chromium/Chrome-for-Testing build is glibc-only, while this container's base image is
Alpine Linux (musl libc) — `npx playwright install chromium` succeeds (downloads a glibc build with a
"BEWARE: your OS is not officially supported" warning) but the browser fails to launch
(`ENOENT`/dynamic-linker failure). Installing Alpine's own native `chromium` package
(`apk add chromium`) did not resolve it either — Playwright Test's `chromium` project still resolves
to its own downloaded (incompatible) binary rather than the system one via
`PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH`. **Revisit**: not a code defect — GitHub Actions' `ci.yml` E2E
job runs on an `ubuntu-latest` runner, which Playwright fully supports; that `workflow_dispatch` run
is the authoritative E2E check for this module, per this project's established practice for every
prior local-environment limitation (Windows Docker networking latency, the recurring local PHPStan
container quirk above). If local E2E runs against this container are wanted again in the future,
switching its base image away from Alpine (e.g. to a Debian/Ubuntu-based Node image) would remove
this specific blocker.
