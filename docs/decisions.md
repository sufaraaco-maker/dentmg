# Architectural Decisions

Chronological log. Entries before this file existed are backfilled from `ARCHITECTURE_REVIEW.md` (2026-07-11) to keep a single source of truth going forward.

## 2026-07-11 — Sanctum SPA (cookie) auth over API tokens

Frontend (`:5173`) and backend (`:8000`) are first-party, same-site in dev — the standard Laravel-recommended setup for this case. Avoids storing tokens in `localStorage`.
**Status**: Agreed with user.

## 2026-07-11 — UUID primary keys on every table

Required explicitly by `PROJECT_CONTEXT.md`. Converted `users`/`sessions` before any other table depended on `bigint` IDs, since retrofitting later is more expensive.
**Status**: Agreed with user.

## 2026-07-11 — Roles as a PHP backed enum, not `roles`/`permissions` tables

Single organization, no multi-tenancy in V1, three roles (`admin`, `dentist`, `receptionist`) cover the real need. Avoids pulling in `spatie/laravel-permission` per the "no unnecessary packages / prefer Laravel native" rule. Revisit only if granular, per-module custom permissions become a real requirement.
**Status**: Agreed with user.

## 2026-07-11 — Soft deletes on `users`, and as the default for future record-bearing tables

`PROJECT_CONTEXT.md` states "Soft Deletes" as a database principle. Implemented without a separate confirmation round at the time — justified directly by the document's explicit text rather than treated as a new architectural choice.
**Status**: Implemented, consistent with documented architecture (not treated as a deviation).

## 2026-07-11 — `JsonResource::withoutWrapping()` enabled globally

Makes single-resource Auth/Users responses match the already-unwrapped Dashboard response shape. Affects every future module's API shape.
**Status**: Implemented by the assistant; flagged for review in `ARCHITECTURE_REVIEW.md`, not yet explicitly re-confirmed by user. Carried forward as current convention in [api-guidelines.md](api-guidelines.md) unless overridden.

## 2026-07-11 — Rate limiting on `/api/login`

Baseline brute-force protection, implemented with Laravel's native `RateLimiter` facade (5 attempts/60s per email+IP). Not explicitly requested but treated as a non-negotiable security baseline rather than an optional feature.
**Status**: Implemented by the assistant as a security default.

## 2026-07-11 — Audit logs and Multi-branch deferred

Both are named in `PROJECT_CONTEXT.md`'s Database section but not implemented yet. Deferred deliberately until a module has a concrete need (Patients/Billing for audit logs; a real second-location requirement for multi-branch) rather than built speculatively.
**Status**: Open — revisit at the start of the Patients module (see entry below).

## 2026-07-14 — Patients module: field scope, audit logging, patient code, no photo upload

Discussed with user via explicit options before implementation:

- **Field scope**: standard clinical intake (demographics, national ID, emergency contact, blood type, allergies, medical history, insurance) — not minimal, not full/extended.
- **Audit logging**: implemented now (not deferred further) since Patients is the first module handling sensitive PII/clinical data. Built as a generic, reusable mechanism (`Auditable` trait + `AuditObserver` + `AuditLog` model) rather than Patient-specific, so it costs nothing extra for future modules to opt in.
- **`patient_code`**: added (`P-00001` style), generated from an internal `sequence_number` column, not the UUID primary key.
- **Photo/avatar upload**: deferred to the future Imaging module, which will need file storage (Local/S3) wired up properly anyway — avoids building storage plumbing twice.

**Status**: Agreed with user.

## 2026-07-14 — Patient policy: front-desk (admin/receptionist) write access, dentist read-only

Registering/editing patient demographic and administrative data is front-desk work. Dentists can view but not edit patient records through this module; clinical write access (updating allergies/history during a visit) is left for the future Clinical Notes module rather than opened up here. Delete restricted to admin only, mirroring the Users module.
**Status**: Assistant judgment call, flagged explicitly to user during planning; not explicitly re-confirmed. Revisit if it doesn't match real clinic workflow.

## 2026-07-14 — Two infrastructure bugs found and fixed during Patients verification

Manual end-to-end testing against the real Docker/Postgres stack (beyond the SQLite-backed automated test suite) surfaced two pre-existing bugs, unrelated to Patients specifically:

1. Guest requests to any protected `/api/*` endpoint without an `Accept: application/json` header crashed with a 500 (Laravel's default guest-redirect targets a `route('login')` that doesn't exist in this API-only app). Invisible to the test suite because `postJson()`/`getJson()` and axios both set that header automatically. Fixed in `bootstrap/app.php`.
2. `storage/logs/laravel.log` was unwritable by the `www-data` php-fpm worker (root-owned bind-mounted volume), so no exception had ever actually been logged since the project's inception. Fixed in `docker/php/entrypoint.sh`.

**Status**: Fixed as part of this module rather than deferred, since both are small, unambiguous bug fixes (not architecture changes) with a real security/observability impact. See `docs/modules/patients.md` for detail and the regression test (`tests/Feature/ApiExceptionHandlingTest.php`).

## 2026-07-15 — Final Patients module review: case-insensitive search fix, trigram indexes, Larastan

Requested by user before starting Appointments: UX review, DB scaling review, API consistency review, and installing PHPStan.

- **Case-sensitive search bug (Postgres) found and fixed**: `PatientService`/`UserService` built search queries with `where($col, 'like', "%$term%")`. SQLite's `LIKE` is case-insensitive by default, so the test suite (SQLite) never caught that Postgres's `LIKE` is case-sensitive by default — a receptionist searching "layla" would find nothing for a patient named "Layla" in the real database. Confirmed via direct query against the dev Postgres instance, then fixed using Laravel's cross-database `whereLike()`/`orWhereLike()` (case-insensitive by default, native since Laravel 11, no extra package). Fixed in both `PatientService` and `UserService` — the bug was in a pattern copied from Users into Patients, so leaving Users unfixed would reintroduce the same defect. Added a regression test, `test_patient_search_is_case_insensitive`.
- **Trigram search indexes added** (`pg_trgm`, Postgres-only, guarded in the migration by driver check): the search queries use leading-wildcard `LIKE '%term%'`, which a standard B-tree index cannot serve — without this, search degrades to a full table scan as the patient list grows. `pg_trgm` is a built-in Postgres contrib extension (not a third-party package), confirmed available and the DB user has privilege to enable it. SQLite (tests) doesn't need or support this, so the migration no-ops there.
- **Larastan (PHPStan for Laravel) installed**, per explicit user request and `PROJECT_CONTEXT.md`'s existing requirement. See `phpstan.neon` for the configured level and rationale.

**Status**: Reviewed and implemented per explicit user request for a final pre-Appointments review.

## 2026-07-16 — Design system: Google Fonts CDN over self-hosted `@fontsource` packages

Presented both options (self-hosted npm packages, offline-safe, vs. Google Fonts `<link>`, simpler setup but
an external network dependency on every page load). User explicitly chose the Google Fonts CDN link,
trading offline-safety for setup simplicity.
**Status**: Agreed with user. Revisit if DentalSuite ever needs a fully offline/on-prem deployment — swapping
to self-hosted fonts at that point requires only a `<link>`→`@font-face` change, no other code changes.

## 2026-07-16 — FullCalendar: MIT packages only, Dentists resource view deferred

While installing the Appointments Calendar Board's FullCalendar dependencies (per the frontend design doc's
§15a/"New Dependencies" section), discovered by downloading and reading the actual package `LICENSE.md`
files (not just trusting the npm registry's `license` field) that `@fullcalendar/resource` and
`@fullcalendar/resource-timegrid` — needed only for the "Dentists" resource-column view — are **not** MIT.
Both are FullCalendar Premium, tri-licensed as (a) a paid Commercial License, (b) Creative Commons
BY-NC-ND (non-commercial use only, not viable for a commercial SaaS product), or (c) GPLv3 (copyleft,
incompatible with a closed-source commercial codebase). The rest of the FullCalendar set actually used
(`core`/`vue3`/`daygrid`/`timegrid`/`interaction`) is confirmed MIT.

Presented three options to the user: (a) purchase a FullCalendar Premium commercial license and build the
Dentists view as designed, (b) drop the Dentists resource-column view from V1 (Day/Week/Month/List plus the
existing Dentist filter already cover "see everyone at once"), or (c) build a custom, non-premium
side-by-side day view. User chose **(b)** — continue with the MIT set only, no Premium dependency, no custom
workaround either. The Dentists view is deferred until a commercial-license purchase is separately evaluated
and approved.

**Status**: Agreed with user, confirmed as a permanent decision unless explicitly revisited. See
`docs/modules/appointments-ui-design.md` §20 ("FullCalendar decision") and its closing summary item 8.

## 2026-07-14 — Documentation restructured to project root

Moved `backend/docs/modules/*.md` → `docs/modules/`, and added `docs/architecture.md`, `docs/database-design.md`, `docs/api-guidelines.md`, `docs/coding-standards.md`, `docs/roadmap.md`, `docs/deployment.md`, plus root `CHANGELOG.md` and `TECH_DEBT.md`. Root-level docs cover both `backend/` and `frontend/`, so they don't belong nested under `backend/`. No content was lost — module docs were moved, not rewritten.
**Status**: Housekeeping, not a product/architecture change — proceeding without a separate approval round.

## 2026-07-16 — Project-wide datetime policy: naive "UTC-labeled digits = wall-clock time"

**This entry documents a project-wide policy, not an Appointments-module fix.** It started as a narrower
Step 4 (Appointment Dialog) fix, but the user requested explicit, verified answers — not assumptions —
about every layer of the stack before approving that step, and the resulting audit found the same bug
class in three more places outside Appointments' own new code (the already-shipped Board's event
rendering and day navigation, the List view, and the pre-existing Patients audit-log timestamp display).
The verified findings below are the actual policy now in force everywhere in the frontend.

### The canonical format, verified end-to-end (not assumed)

DentalSuite is a **single-clinic system with no real per-request timezone conversion**. Every layer was
checked directly:

- **Database**: every timestamp column, in every table (`appointments`, `patients`, `dentist_time_off`,
  `audit_logs`, and by the same `$table->timestamps()` convention `users`/`appointment_reminders`/etc.), is
  Postgres `timestamp(0) WITHOUT TIME ZONE` — confirmed via `\d` on each table directly. Postgres performs
  **zero** timezone conversion on these columns regardless of session timezone; the stored digits are
  exactly the digits the application wrote, with no timezone semantics attached at the database layer.
- **`config/app.php`**: `'timezone' => 'UTC'`. No connection-level override exists in `config/database.php`
  (checked directly — the `pgsql` connection array has no `'timezone'` key), and no app code overrides
  Carbon's default serialization (checked directly — no `serializeUsing`/`Carbon::use...` call anywhere in
  `app/`, `bootstrap/`, or `config/`). This "UTC" is a neutral label Carbon attaches for formatting, not a
  real conversion boundary — nothing in the stack ever converts a wall-clock instant *to* or *from* a
  different zone.
- **Every Laravel API response** (checked every `Http/Resources/*.php` with a datetime field —
  `AppointmentResource`, `AppointmentTypeResource`, `AuditLogResource`, `DentistTimeOffResource`,
  `PatientResource`): all explicitly call `->toIso8601String()`, consistently, producing e.g.
  `2026-07-16T10:00:00+00:00`. The `+00:00` is Carbon's label for "this app's configured timezone," not a
  claim that a real UTC conversion happened — the digits are the naive, unconverted wall-clock value.
  (`DentistWorkingHourResource`'s `start_time`/`end_time` are plain `"HH:mm"` strings from a `time` column,
  not full timestamps — a different, timezone-agnostic case that needs no fix.)

**So: every datetime value flowing through the API — request or response — is a naive wall-clock instant
wearing a UTC label it hasn't earned.** The frontend's job is to read and write those digits as-is, never
letting a browser's own OS timezone silently re-derive them.

### The one official frontend approach — `frontend/src/lib/date.ts` (already shared, no module owns it)

This file already lived outside any single module's component/store folder before this decision, so no
file move was needed to make it "shared" — the fix was making every consumer actually use it consistently:

| Helper | Direction | Use when |
|---|---|---|
| `parseServerDateTime(iso)` | API → local Date, digits preserved | Reading any `start_at`/`end_at`/`created_at`/etc. the backend returned |
| `toLocalDateTimeString(date)` | Local Date → API string, digits preserved | Writing any datetime field into a request payload |
| `parseLocalDate(str)` / `toLocalDateString(date)` | Date-*only* fields (`date_of_birth`, `date_from`/`date_to` range params) | Pre-existing (Patients module), unaffected by this decision |
| `toCalendarUtcDate(date)` | Local Date → FullCalendar-compatible instant | Only when feeding a local Date into a FullCalendar instance running `timeZone: 'UTC'` (`initialDate`/`gotoDate()`) — the inverse of `parseServerDateTime`, needed because FullCalendar itself becomes a second "UTC-labeled" boundary once it's configured this way |

A plain `new Date(iso)` or `.toISOString()`/`.getHours()` is **only** safe when the Date didn't come from
the API or from a UTC-mode FullCalendar instance (e.g. `new Date()` for "right now," a native date-picker's
own emitted value). Every other case must go through the table above.

### Verified: is this uniformly applied today, or are there other places doing it differently?

At the time of this decision, **yes, uniformly** — every remaining inconsistency found during the audit was
fixed in the same pass, not left as a known gap:

- `AppointmentDialog.vue` (read + write), `AppointmentsView.vue` (slot-click prefill, range-fetch from
  FullCalendar's `datesSet`), `AppointmentCalendar.vue` (`timeZone: 'UTC'` + `initialDate`/`gotoDate()` via
  `toCalendarUtcDate`), `AppointmentListTable.vue` (List view's Date/Time column), `SlotPicker.vue`
  (available-slot matching), `stores/appointments.ts` (range-membership filtering/cache eviction) — all
  fixed and using the table above.
- `PatientDetailView.vue`'s audit-log timestamp column — a **pre-existing bug outside the Appointments
  module entirely**, predating Phase 2 — was found by this same audit and fixed the same way.
- Grepped the whole `frontend/src/` tree for every date-construction/formatting pattern
  (`new Date(`, `.toISOString(`, `.toLocaleString(`, `.getHours()`, etc.) as the closing check — no
  remaining site touches a datetime field outside this table's contract.

Two bugs were caught only because this rigor was demanded, not because they were anticipated: (1)
`AppointmentCalendar.vue`'s `gotoDate()`/`initialDate` still received a genuinely-local `currentDate`
un-converted, which — once FullCalendar was put in `timeZone: 'UTC'` mode to fix event rendering — made
**every single day** display one day off for any positive-UTC-offset browser (not the "few hours near
midnight" edge case originally assumed; confirmed directly: clicking "Today" showed Thursday instead of
the real Friday). (2) `SlotPicker.vue` read `workingHours.byDentist`, a store only ever populated as a side
effect of the Board's own Dentist filter — opening the dialog for a dentist not currently selected in that
unrelated filter left available-slot matching silently empty. Both are fixed (see `CHANGELOG.md`) and
covered by regression tests (`lib/date.test.ts`, `AppointmentCalendar.test.ts`, `SlotPicker.test.ts`).

### Decision, going forward

Any new frontend code that reads or writes a date-*time* field — in any module, not just Appointments —
must go through this file's helpers per the table above. Code review for any future module (Dental Chart,
Billing, Treatment Plans, etc.) should check for a raw `new Date(apiValue)` or `.toISOString()` on a value
that came from or is going to the API, the same way this audit did.

**Status**: Fixed and shipped as part of Step 4, expanded project-wide per this decision. No known remaining
gaps as of this audit — `calendar.ts`'s own range/day boundary math (`startOfWeek`/`startOfMonth`/etc.)
correctly stays in local-time semantics (it's pure UI navigation state that never touches FullCalendar's
UTC-mode boundary directly: `appointments.fetchRange()` reads it via matching local getters, and the one
place it's handed to FullCalendar — `AppointmentCalendar.vue`'s `initialDate`/`gotoDate()` — now goes
through `toCalendarUtcDate` first). The `TECH_DEBT.md` entry that originally tracked the day-navigation bug
as a "narrow edge case" has been corrected and closed — it turned out to be a confirmed, non-narrow bug,
fixed here, not deferred.

## 2026-07-17 — Step 4 approved; datetime policy elevated to a hard Architecture Violation rule

Step 4 (`AppointmentDialog` and its sub-components) is approved and closed. The user additionally elevated
the datetime policy above (2026-07-16 entry) from "the current convention" to a permanent, general system
rule with an explicit enforcement mechanism: **any future Code Review that finds a raw `new Date(apiValue)`,
`.toISOString()`, `.toLocaleString()`, `.getHours()`, etc. touching a value that came from or is going to the
API, in any module, is an Architecture Violation** — not a style nitpick — unless the deviation is
explicitly documented with a reason here, the same way this file already documents `calendar.ts`'s
legitimate local-time exception. Every new module must use `frontend/src/lib/date.ts`'s helpers directly;
no module may invent its own date/timezone handling.
**Status**: Agreed with user. Standing rule for every module from Step 5 onward.

## 2026-08-07 — Role hierarchy flagged for Phase 4, not yet decided

While mapping a new 8-phase product roadmap (Phase 1: Stabilization → ... → Phase 8: Launch Prep) onto the
existing codebase before starting Phase 1's implementation, Phase 4 ("Advanced Permissions & Audit")
requests a role hierarchy — `Owner → Clinic Admin → {Dentist, Assistant, Receptionist, Accountant}` — that
`UserRole`'s current flat 3-value backed enum (`admin`/`dentist`/`receptionist`, see the 2026-07-11 entry
above) cannot represent. This is exactly the "real requirement" that entry named as the condition for
revisiting the flat-enum decision.

**Status**: Flagged, not decided at the time. **Reconsidered 2026-08-09 when Phase 4 actually started** —
see that date's entry below: the user chose a fine-grained permission layer over the current 3 roles
instead of this hierarchy. This entry's own flag stays open for a future Phase 5 (SaaS Multi-Tenant)
revisit, not resolved by that choice. See `docs/PROJECT_STATUS.md` §5 for current roadmap-phase status.

## 2026-08-07 — Patient Timeline: dedicated event model, not `Auditable`; permissions enforced server-side, per-category

While designing Phase 2 (Patient Profile Redesign)'s Timeline tab — a cross-module activity feed spanning
Appointments, Treatment Plans, Clinical Notes, Billing, Payments, Imaging, Laboratory, and Documents — two
decisions were made and explicitly designated as binding beyond this one feature, not local implementation
detail:

1. **Timeline is built on a dedicated `PatientActivity` event table, not the existing `Auditable`
   trail/`PatientAuditLog`.** `Auditable` answers "what changed on this row" (field-level diffs, JSON-based,
   already noted elsewhere as inefficient for aggregate queries); Timeline needs to answer "what happened to
   this patient" (cross-module, filterable, paginated, and a plausible future input to notifications and AI
   summaries) — a materially different shape that a change-diff log can't cleanly serve. `PatientActivity`
   rows are written by domain events each module's service dispatches at its key lifecycle moments, kept
   decoupled from the 8 module services themselves via a single listener.
2. **Timeline permissions are enforced server-side, per category, at query time — never client-side, never
   inherited from "the user could already open the patient's hub."** A naive "show all activity for this
   patient" query would leak `category=clinical` entries (Clinical Notes-derived events) to a receptionist,
   even though `ClinicalNotePolicy` already bars receptionists from Clinical Notes entirely — a real
   permission leak, not a hypothetical one, since the underlying data already has a stricter read rule than
   the hub itself. `GET /patients/{patient}/activities` must filter out any category the requesting user's
   role fails that category's owning Policy for, before rows leave the database.

**Why elevated to a decisions-log entry** (rather than left as design-doc detail): both rulings generalize —
any future feature that aggregates or surfaces cross-module patient data (a search-everything feature, a
cross-module dashboard widget, a future AI summarizer reading `PatientActivity` rows) inherits the same
per-source, server-side permission-recheck requirement. Full detail, including the enforcement/testing
requirement, lives in `docs/modules/patient-profile-redesign-design.md` §9-§9A (design authority for this
feature) — this entry is the durable pointer for future modules that hit the same class of problem.

**Status**: Approved with the design (design review approved 2026-08-07). Binding on all Phase 2.6 (Timeline)
implementation and on any later feature that aggregates cross-module patient data.

## 2026-08-08 — One Policy class registered against multiple models via `Gate::policy()`, for Medical History

Phase 2.3 (Medical History)'s design doc explicitly calls for **one** `MedicalHistoryPolicy` governing all
three new entities (`PatientAllergy`/`PatientMedicalCondition`/`PatientMedication`) — they're one logical
feature (allergies/conditions/medications are all edited from the same tab, by the same roles), and three
near-identical policy classes would just be duplication, the same "avoid over-engineering" reasoning already
behind `MedicalHistoryService` being one service for the same three entities.

The problem: every existing Policy in this codebase relies purely on Laravel's naming-convention
auto-discovery (`Model` → `App\Policies\{Model}Policy`), confirmed by grepping the whole codebase for
`Gate::policy(`/`protected $policies` — there is no existing precedent either way, since auto-discovery only
ever maps **one** policy class per model. Three near-identical policy classes would satisfy auto-discovery
without any new registration code, but would contradict the design doc's explicit "one policy" call and
duplicate the same `in_array($actor->role, [Admin, Dentist], true)` logic three times.

**Decision**: keep the single `MedicalHistoryPolicy` class the design doc calls for, and register it
explicitly for all three models via three `Gate::policy(Model::class, MedicalHistoryPolicy::class)` calls in
`AppServiceProvider::boot()` (the same file/method that already defines the two Reports `Gate::define()`
abilities for a similarly no-natural-single-model case). This is additive, explicit registration — it
doesn't change how any *other* module's policy resolves, and doesn't introduce a new authorization
abstraction (no base class, no trait, no generic "multi-model policy" concept) — just three lines pointing
three models at one class.

**Status**: Implemented with Phase 2.3, merged via PR #22 (2026-08-08) — see `docs/PROJECT_STATUS.md` §12. Establishes the
precedent for any future entity cluster (à la Medical History's three tables) that the design phase decides
should share one policy: register it explicitly in `AppServiceProvider`, don't split into N near-identical
policy classes just to keep auto-discovery working.

## 2026-08-09 — Security fix: `/dashboard/summary` split by financial/operational sensitivity

Design-phase audit for Dashboard 2.0 (`docs/modules/dashboard-2.0-design.md` §0) found a real, pre-existing
authorization gap: `DashboardController::summary()` had no Form Request, no policy check, and no Gate call of
any kind — any authenticated role could call it. It returned `monthly_revenue`, the same figure
`reports/collections` already restricts to admins via `Gate::define('view-financial-reports', ...)`
(`AppServiceProvider.php`). So a receptionist or dentist could already see monthly revenue through the
Dashboard that they were explicitly blocked from seeing through Reports — the exact class of
aggregation-point permission leak this project's §9A Security Architecture Decision (Timeline, 2026-08-07)
was written to guard against, just found in an older endpoint that predates that decision.

**Decision**: split the dashboard payload by the same financial/operational boundary Reports already uses,
not by adding conditional fields to one endpoint. `GET /dashboard/summary` stays open to every role but now
carries only operational data (patient/appointment counts, unscheduled-accepted-treatment) — `monthly_revenue`
is removed from it entirely. A new `GET /dashboard/financial-summary` carries `monthly_revenue` plus new
production/collections trend and A/R aging data, gated by a `DashboardFinancialSummaryRequest` Form Request
calling `$this->user()->can('view-financial-reports')` — the same Gate, same reasoning, same pattern as
`ProductionReportRequest`/`CollectionsReportRequest`/`ArAgingReportRequest`, not a new authorization concept.
This is a breaking response-shape change for `monthly_revenue` (removed from the old field for every role,
including admin, who now reads it from the new endpoint instead) — deliberate, not a compatibility concern,
since no external consumer of this endpoint exists yet.

**Status**: Implemented with Dashboard 2.0 — see `docs/PROJECT_STATUS.md` §12 for the PR. Establishes the
precedent that any dashboard/aggregation endpoint mixing financial and operational data must be split (or
otherwise gated) along the same boundary Reports already draws, not left ungated by default.

## 2026-08-09 — Dashboard 2.0 financial widgets show period-over-period trend, not actual-vs-goal

The Dashboard 2.0 design phase considered showing production/collections progress against a goal (a common
pattern in competing dental PM systems — Dentrix/CareStack both support goal-tracking dashboards). A
full-backend search confirmed no goal/target/quota concept exists anywhere in this codebase's schema
(`ClinicSetting`/`BillingSetting` checked directly) — building it would mean a new settings field, a new
Settings UI, and a migration: real new scope, not reuse.

**Decision**: ship period-over-period trend instead (this calendar month vs. last calendar month), computed
by calling the existing `ReportService::production()`/`collections()` methods twice with different date
ranges — zero new storage, consistent with this phase's "reuse existing services" discipline
(`DashboardService::monthlyRevenue()` already established the pattern of delegating to `ReportService` rather
than reimplementing). `change_pct` is `null`, never `0`, when the previous period had no activity — the same
"don't fabricate a trend the data doesn't support" rule the Premium Visual Redesign doc's §6 already set for
this dashboard's stat cards.

**Status**: Implemented with Dashboard 2.0. Goal-setting itself remains explicitly deferred (named, not
dropped — see the design doc's §7) — revisit if period-over-period trend proves insufficient in practice.

## 2026-08-09 — Phase 4: fine-grained permissions over the current 3 roles, not a role-hierarchy rewrite

Phase 4 ("Advanced Permissions & Audit") design phase revisited the 2026-08-07 role-hierarchy flag above.
A ground-truth audit (direct full reads of all 27 Policy classes, not grep-level) found every authorization
check already funnels through Policies (zero route-level `can:` middleware, 31 controllers calling
`authorize()`), so the blast radius of a permission-model change is contained rather than scattered. Two
options were on the table: (a) build the flagged `Owner → Clinic Admin → {Dentist, Assistant, Receptionist,
Accountant}` hierarchy now, or (b) keep the current 3-role `UserRole` enum and add a fine-grained,
admin-configurable permission layer on top of it.

**Decision**: (b). A 68-entry permission catalog was derived 1:1 from every Policy's actual current
behavior (methods sharing an identical role-set within one Policy collapse into one key, e.g.
`AppointmentPolicy`'s create/update/cancel/confirm/check-in all share `appointments.manage`), stored in a
new `role_permissions` matrix an admin can edit via a new UI — with zero effective permission change on
day 1 (the seeded matrix mirrors today's Policies exactly, verified by cross-checking the seeded per-role
grant counts against an independent manual derivation: admin=68, dentist=36, receptionist=37, all matching).
Identity/ownership/target-role checks that aren't role checks (e.g. `Appointment::start()`'s
`$actor->is($appointment->dentist)`, `DentistTimeOff`'s "target user must actually be a dentist"
validation, `User::delete()`'s self-delete block) are explicitly NOT part of the permission catalog — they
stay hardcoded in each Policy, unchanged, since they're not role decisions. Two new "meta" capabilities this
phase introduces — managing the matrix itself, and (Step 3) the general Audit Log viewer — are deliberately
checked via a hardcoded `isAdmin()` Gate rather than routed through the matrix they themselves gate, and
`users.manage` can never be revoked from Admin through the matrix API — both close the self-lockout risk
structurally, not just by validation. Full design: `docs/modules/phase4-permissions-audit-design.md` §1.

**Status**: All 5 steps implemented 2026-08-09 on `feature/phase4-permissions-foundation` — Backend
1145/1145 tests green (Steps 1-3), Frontend 969/969 tests green (Step 4's `PermissionsView.vue` matrix UI
+ `AuditLogsView.vue`), full E2E coverage (Step 5) with every scenario passing at least once locally, zero
regressions across every pre-existing Feature/Policy/component/E2E test. The 2026-08-07 role-hierarchy flag
stays open, not resolved by this choice — revisit if Phase 5 (SaaS Multi-Tenant Prep) gives it a concrete
multi-clinic reason to exist. Pushed with CI triggered via `workflow_dispatch`, then a full final diff
review against `main` (per the user's explicit instruction) found zero blockers — **merged via PR #37**
(`0bdf3d8`, 2026-08-09); post-merge CI on `main` fully green (Backend/Frontend/E2E).

## 2026-08-09 — Phase 4 Step 3: audit writes fail open for the operation, fail closed on sensitive data

Before Step 3 (Audit Overhaul) touched `AuditLogService`, a broken audit write (a DB error, a future bug)
would propagate as an uncaught exception through the `AuditObserver` model event straight into whatever
business action triggered it — a database outage on `audit_logs` specifically could have taken down
logins, permission changes, and every Auditable model's create/update/delete alongside it. The user
required this be fixed explicitly during Step 3, not left as-is.

**Decision**: `AuditLogService::write()` wraps the `AuditLog::create()` call in a try/catch. On failure,
the exception is logged (`Log::error('Audit log write failed', [...])`, action/auditable_type only — never
the actual payload, so a redaction bug can't leak sensitive values into the general application log as a
side effect of the failure path) and swallowed — the underlying login/save/permission-update completes
normally. This applies uniformly to both the pre-existing model-observer path (20+ Auditable models) and
the new event path (auth events, `role_permissions_updated`), since both funnel through the same `write()`
method. The `AuditLog` model's separate immutability guard (`static::updating()`/`static::deleting()`
throwing `LogicException`) is deliberately NOT swallowed anywhere — it exists specifically to fail loudly
if code ever tries to mutate an audit row, unlike a failed *write*.

Verified in `tests/Feature/AuditLogTest.php::test_a_failed_audit_write_does_not_break_the_underlying_business_operation`
by dropping the `audit_logs` table mid-test — the more obvious approach (an actor with a non-existent
`user_id` to trigger the FK constraint) turned out unreliable, since the test suite runs on SQLite, which
doesn't enforce FK constraints by default unlike the production Postgres.

**Status**: Implemented with Phase 4 Step 3, 2026-08-09.

## 2026-08-09 — Phase 4 Step 3: Laravel `trustProxies(at: '*')` configured for the documented reverse-proxy topology

Step 3 needed IP capture on `audit_logs` to be meaningful, not decorative — the user's explicit condition
from Phase 4's design approval was "don't present a forwarded IP as trustworthy if trusted-proxy handling
isn't actually configured." Auditing `bootstrap/app.php` found zero `trustProxies()` call. Cross-checking
`docs/deployment.md`'s documented production topology (`host-level nginx --proxy_pass--> dockerized nginx,
bound exclusively to 127.0.0.1:8000 --fastcgi_pass--> php-fpm`) confirmed the host-level nginx already sets
`X-Real-IP`/`X-Forwarded-For`/`X-Forwarded-Proto` correctly (its own documented config), and that FastCGI
forwards all incoming headers through automatically — so the real client IP genuinely reaches PHP today,
Laravel just never reads it: `Request::ip()` without `trustProxies()` returns `REMOTE_ADDR` only, which at
the dockerized nginx is always the host-level proxy's own loopback address.

**Decision**: `$middleware->trustProxies(at: '*')` in `bootstrap/app.php`. Trusting `'*'` (not a specific
IP) is safe specifically because of the documented bind — `nginx` binds exclusively to `127.0.0.1:8000`,
never directly internet-facing, so the only possible peer connecting to this app is the host-level reverse
proxy; there is no untrusted network path `'*'` could be exploited through in this topology.

**Status**: Implemented with Phase 4 Step 3, 2026-08-09. Verified against the local dev topology (single
dockerized nginx, no host-level proxy layer) via `tests/Feature/AuditLogTest.php`'s IP/UA capture test;
the *production* double-proxy topology itself (`docs/deployment.md`'s host nginx + Certbot) could not be
exercised from this session — recommend a smoke check (confirm a real client IP, not a loopback address,
appears in `audit_logs.ip_address`) after the first real production deploy following this change.

**Addendum (2026-08-09, final diff review before PR #37)**: `trustProxies(at: '*')` is app-wide middleware,
not audit-log-scoped — `AppServiceProvider`'s `RateLimiter::for('api', ...)` also keys off `$request->ip()`,
so the per-IP API throttle now trusts the same forwarded header the audit log does. Accepted consciously,
not an oversight: the same single-trusted-proxy bind that makes the IP trustworthy for audit logging makes
it equally trustworthy for rate-limiting. No action needed unless the deployment topology in
`docs/deployment.md` ever changes to allow an untrusted path to the app.

## 2026-08-11 — Phase 5: notifications extend Laravel's own `notifications` table rather than a bespoke model

Phase 5's design brief explicitly asked to review Laravel Notifications before choosing an implementation
and to prefer Laravel conventions over an unnecessary abstraction. Laravel's `DatabaseNotification` already
provides the entire Notification Center feature list — `read_at`, `markAsRead()`, `unreadNotifications`,
`Prunable` — and the `User` model already used the `Notifiable` trait (Laravel skeleton default, previously
inert since no table stood behind it).

The stock schema alone was not sufficient, for two concrete reasons: the read-time authorization re-check
(below) needs `WHERE category IN (...)` on an indexed column, not a JSON probe into `data`; and deep links
need a stable `subject_type`/`subject_id`.

**Decision**: use Laravel's stock `notifications` table plus four additive columns (`category`,
`subject_type`, `subject_id`, nullable `patient_id`), populated by a subclass of Laravel's own
`Illuminate\Notifications\Channels\DatabaseChannel` bound over it in `AppServiceProvider`. This overrides one
documented extension point (`buildPayload`) and inherits everything else. It is the same
additive-columns-on-a-framework-table move Phase 4 Step 3 already made on `audit_logs`, so it follows an
in-repo precedent rather than inventing one. A parallel bespoke `Notification` model was rejected as exactly
the unnecessary abstraction the brief warned against.

**Status**: Approved by the user as Decision D1 of `docs/modules/notifications-design.md`; implemented
2026-08-11.

## 2026-08-11 — Phase 5: no permission catalog entry for notifications; access is structurally self-scoped

Every other module's endpoints go through the Phase 4 permission matrix. Notifications deliberately do not.

Every notification is addressed to exactly one `notifiable` user, and every route resolves its target from
`$request->user()->notifications()` — never from a route-model-bound `{notification}` looked up globally. A
notification belonging to someone else is therefore not "found and then rejected"; it is never in the result
set, so those routes return 404 rather than 403. There is no `where user_id = ?` to forget and no policy to
misconfigure. This is the same structural guarantee `ProfileController` (My Account) already relies on — and
My Account likewise has no permission key.

Adding a `notifications.view` catalog entry would imply an admin could meaningfully revoke a role's ability
to read *its own* notifications, which is not a real operation.

**Layered on top**, because ownership alone is not enough: every list *and* count query also filters
`whereIn('category', NotificationPolicy::allowedCategories($user))`, re-derived per request from each
category's real owning policy. This closes permission drift — a dentist notified of a `lab_case.received` on
Monday stops seeing it if `lab_cases.view` is revoked from their role on Tuesday, even though the row still
exists. `payments` is mapped to `Payment`/`PaymentPolicy` rather than folded into `billing`/`Invoice`,
because `payments.view` and `invoices.view` are separately grantable and §8.2's rule is one category per real
policy.

**Status**: Approved by the user as Decision D6 of `docs/modules/notifications-design.md`; implemented
2026-08-11 and covered by `NotificationEndpointTest` (both the IDOR case and the revoked-after-the-fact case)
and by `e2e/notifications.spec.ts` (asserted three ways: rendered DOM, network response, direct API access).

## 2026-08-11 — Phase 5B: a queue worker and scheduler now actually exist; `ShouldQueue` is safe to use

Phase 5's design-phase audit found that `QUEUE_CONNECTION=redis` had been configured since the project's
first `.env`, and Redis ran in both dev and production compose — but **neither compose file contained a
`queue:work` process, a Horizon instance, or a supervisor**, and `routes/console.php` contained nothing but
Laravel's stock `inspire` command. Anything `ShouldQueue` would have been enqueued to Redis and silently
never executed. Phase 2.6's `RecordsPatientActivity` had been written synchronously specifically to sidestep
this ("no queue worker actually runs in this project today (confirmed by audit)"), so the hazard was known
locally but had never been escalated to a tracked item.

**Decision**: add dedicated `queue` and `scheduler` containers to both compose files, reusing the existing
app image with a different command; guard `docker/php/entrypoint.sh` with `RUN_MIGRATIONS` so only the `app`
container migrates rather than three containers racing `migrate --force`; and adopt `ShouldQueue` only after
observing a real job go `RUNNING` → `DONE` in the worker container.

Two consequences worth recording:
- `SendsNotifications` sets `$afterCommit = true`. `InvoiceService::void()`, `PaymentService::refund()` and
  `LabCaseService::receive()` all fire `PatientActivityOccurred` from inside a `DB::transaction()`; without
  it a worker can pick the job up before the commit and find no row — a race that only appears under load.
- The listener keeps its `try/catch` even though it is now queued. This forgoes automatic retries in exchange
  for the fail-open guarantee holding under *every* queue driver, including `sync` (which the test suite
  uses), where an uncaught throw would propagate straight back into the caller's request. For a clinical
  system, a lost notification is a better failure than a blocked cancellation.

`RecordsPatientActivity` deliberately stays synchronous — a Timeline row is part of the record, whereas a
notification is a delivery side effect.

**Status**: Approved by the user as Decision D8 (Phase A + B in one cycle); implemented 2026-08-11 and
guarded by `NotificationQueueTest`.

## 2026-08-11 — Phase 5 pre-PR review: `readonly` event properties are compatible with `SerializesModels`, but only via `__serialize()`/`__unserialize()`

A pre-PR review of Phase A/B found that `PatientActivityOccurred`'s `subject`/`actor` — plain `readonly`
promoted `Model`/`?User` properties — had no serialization contract, so once `SendsNotifications` became
`ShouldQueue` (Phase B), the full `Model` (bcrypt password hash, `remember_token`, and, whenever a relation
happened to be preloaded, patient PHI) serialized straight into the Redis job payload. The standard fix,
`Illuminate\Queue\SerializesModels`, was suspected at first to be incompatible with `readonly` properties —
PHP forbids writing to an already-initialized `readonly` property, and older Laravel versions' equivalent
mechanism (`__sleep()`/`__wakeup()`) mutates the *live* object's properties in place, which would indeed
fail here.

**Decision**: apply `SerializesModels` anyway, after verifying (not assuming) it actually works with this
framework version's implementation. It does, because this codebase's `SerializesModels` (`illuminate/queue`)
uses the newer `__serialize()`/`__unserialize()` magic methods instead: `__serialize()` only *reads* the live
properties (never mutates them) and returns a `ModelIdentifier`-substituted array; `__unserialize()` writes
each property for the first time on a freshly-allocated, not-yet-constructed object — the one case PHP's
readonly rules permit regardless of which scope the write happens from. Confirmed three ways before trusting
it: an isolated PHP script proving Reflection-based writes succeed on an uninitialized `readonly` property;
a `NotificationEventSerializationTest` asserting the serialized payload contains no password hash/PHI and
that the listener still resolves and notifies correctly after a real `serialize()`/`unserialize()` round
trip; and a live run against the real `dentalsuite_queue` container — payload read directly from Redis via
`redis-cli` while the worker was paused (clean), then the worker resumed and observed carrying the job
`RUNNING` → `DONE` with a real `notifications` row written.

**Consequence for future event/job classes carrying `Model` properties in this codebase**: `readonly`
promoted properties do not need to be avoided for `ShouldQueue` compatibility — `SerializesModels` should
still be added (it also shrinks a loaded relation to its name rather than its full data, which a plain
`readonly` property does not), but never assumed to work without a payload-content test, since the *specific
mechanism* a given Laravel version uses for it is what determines readonly-compatibility, not the trait name
alone.

**Status**: Fixed 2026-08-11 on `feature/phase5-notifications`, before either Phase A or B was opened as a
PR. See `TECH_DEBT.md`/`CHANGELOG.md` for the sibling findings from the same review (notification-store
reset on logout, malformed-UUID 404, Notification Center refetch-on-reopen, i18n parity doc drift).

## 2026-08-12 — Phase 5C: `config('app.timezone')` is now genuinely configurable, set to the clinic's real zone

Phase 5C's design-phase audit, in response to the user's explicit condition on Decision D11 ("must be clinic
timezone, not blind UTC"), found `config/app.php`'s `'timezone'` key was a hardcoded `'UTC'` **literal**, not
`env()`-driven — no environment variable could ever have changed it. This had never mattered before: every
prior phase only stored and re-displayed wall-clock digits verbatim (`frontend/src/lib/date.ts`'s documented
single-clinic, no-real-conversion convention) and never compared `now()` against a stored timestamp column.
Phase 5C is the first to do both — `LabCase::dueOrOverdue()`/tomorrow's-appointments comparisons, and
`Schedule::command(...)->dailyAt(...)`'s absolute run times — so it is the first to expose that the
container's real system clock and the clinic's own wall-clock digits are two different clocks unless the
clinic happens to be in UTC+0.

**Decision**: change `config/app.php` to `env('APP_TIMEZONE', 'UTC')`; set `APP_TIMEZONE=Africa/Cairo` (the
clinic's real IANA zone, confirmed with the user — Decision D14) in `backend/.env.example`, mirrored into
the real local `backend/.env`. This is the project's actual convention for all runtime config — confirmed
by reading both compose files, neither of which sets `APP_*` variables directly; `app`/`queue`/`scheduler`
all load `backend/.env` from the mounted volume, and CI's own jobs `cp .env.example .env`, so this one line
also fixes CI without a workflow change. `tzdata` added to both `docker/php/Dockerfile` and
`Dockerfile.prod`'s `apk add` list as a defensive measure, though testing the *unmodified* image directly
confirmed PHP's bundled timezone database already resolves `Africa/Cairo` correctly without it.

**Consequence for future deployments**: a single global `APP_TIMEZONE` is correct for V1 (one clinic); per
[[policy_saas_multitenant_readiness]], the future multi-clinic model will need this to become a per-clinic
column (on `ClinicSetting`, following the same additive-column precedent as `audit_logs`/`notifications`)
rather than a container-wide env var — flagged in the design doc rather than left to be rediscovered.

**A second, previously-invisible bug this fix exposed, not introduced**: `AuditLog.created_at` is the one
column in the schema set by the *database's* own `useCurrent()` default rather than PHP's `now()` (necessary
because `$timestamps = false`, itself necessary because there is no `updated_at` column) — so it stayed on
the database's real-UTC clock while everything else moved onto clinic wall-clock time, silently breaking any
`now()`-vs-`created_at` comparison. Fixed separately (see `TECH_DEBT.md`'s Resolved section and this
session's `AuditLog::booted()` change) — recorded here because it is a direct, if non-obvious, consequence
of this decision, not an unrelated finding.

**Status**: `Africa/Cairo` confirmed by the user 2026-08-12; implemented same day on Phase 5C
(`docs/modules/notifications-phase-c-d-design.md` §16a/§19). `schedule:list` confirmed the three new
Phase 5C Commands now report correct clinic-local next-run times.

## 2026-08-13 — Clinic logo / user avatar upload: reverses `settings-design.md`'s V1 deferral; stands up the app's first public file storage

The user asked directly for three things: (1) English as the default UI language instead of Arabic, (2) the
app's existing brand mark (`frontend/public/favicon.svg` — already the browser-tab icon, never before shown
inside the app itself) surfaced as a real login-screen/sidebar/header logo alongside the "DentalSuite" name,
and (3) a clinic-logo upload in Practice Settings plus a profile-photo upload on the Users (add/edit user)
screen. Per this repo's standing rule, `docs/PROJECT_STATUS.md` and recent PR/CI state were re-verified
current before starting (nothing stale found — see `docs/PROJECT_STATUS.md`'s 2026-08-13 entry).

**(1) and (2) were self-contained frontend changes** — `detectInitialLocale()`'s fallback flipped from `'ar'`
to `'en'` (`frontend/src/locales/index.ts`), `AVAILABLE_LOCALES` reordered English-first, `index.html`'s
static `lang`/`dir`/font-preload order updated to match (avoids an RTL flash on first paint for the new
default), and a new `AppLogo.vue` (`<img src="/favicon.svg">`, not a re-inlined SVG — the file carries several
blur filters cheaper left as one cached static asset than duplicated per mount) wired into `LoginView.vue`,
the expanded sidebar header, and the mobile header. No new logo asset was designed; the existing one had
simply never been surfaced past the browser tab.

**(3) directly reopens `docs/modules/settings-design.md`'s §2/§8-decision-4/§9 "no clinic logo upload in
V1"** — that deferral was conditional ("revisit once a real document... would actually display it"), not
permanent, and the condition is now met on both sides:
- **Clinic logo**: the Practice Settings page itself is the real consumer (a live upload/preview/remove
  loop), independent of the still-outstanding Lab Case printable-slip consumer §8-decision-5 named — this
  decision does not retroactively resolve that one.
- **User avatar**: never discussed in the original design doc at all (only self-service name/email/password
  existed under My Account) — new territory, not a reversal, gated by the same `users.manage` policy as
  every other user-management action, added to the Users (add/edit) dialog only, not self-service `/profile`.

**New infrastructure, not a small extension of the existing upload pattern**: Imaging/Documents
(`PatientImageService`/`PatientDocumentService`) store to the `local` disk and serve through
authenticated, policy-checked streaming routes (`docs/modules/imaging-design.md`: "never serve images from a
public/static URL") — deliberately wrong for a logo/avatar, which must render as a plain `<img>` pre-login
and in the header with no auth round trip. This is the first feature to use the `public` disk that
`config/filesystems.php` already defined but nothing had ever activated: `storage:link` added to
`docker/php/entrypoint.sh`/`entrypoint.prod.sh`, guarded into the same single-owner (`RUN_MIGRATIONS`)
branch that already exists to stop the `app`/`queue`/`scheduler` containers racing `migrate --force` — the
identical hazard applies to `storage:link` (it errors if the target already exists). Production's
`docker/nginx/default.prod.conf` needed a new `location /storage` block: its SPA-fallback `location /` would
otherwise swallow every `/storage/...` request and return `index.html` instead of the file — a real gap this
change surfaced, not a pre-existing one (nothing served from `/storage` before).

**Data model**: `clinic_settings` gained `logo_disk`/`logo_path` (2026_08_13_000001), `users` gained
`avatar_disk`/`avatar_path` (2026_08_13_000002) — additive nullable columns, the same precedent
`ai_assistant_*`/`audit_logs`/`notifications` already established. Always `'public'`, never
`config('filesystems.default')` (unlike `PatientImageService`, which is disk-agnostic on purpose) — a
logo/avatar can never legitimately end up on the auth-gated `local` disk. Uploads reject `svg`
(`mimes:jpg,jpeg,png,webp`, `image` rule) even though the app's own favicon is an SVG — a user-uploaded SVG
can embed a `<script>` and this file is later rendered directly with no sanitization step, unlike the
hand-authored `public/favicon.svg`.

**Frontend**: one shared presentation-only `ImagePickerField.vue` (`components/common/`) backs both Practice
Settings and the Users edit dialog — same hidden-`<input type="file">` + triggering Button pattern
`AttachmentUpload.vue`/`UploadImagesDialog.vue` already established (no PrimeVue `FileUpload` anywhere in
this codebase), reduced to a single persistent-preview image instead of a one-shot dialog. Avatar upload is
edit-only, not available on the "New User" create dialog (no `id` exists yet to attach a file to) — the
dialog shows an explanatory hint instead; this was a scope call, not a limitation worth extra plumbing to
avoid, matching common precedent (GitHub/Slack also require account creation before avatar upload).

**A ripple effect deliberately not chased further**: `AuthUser.avatar_url` was added `?`-optional rather than
required, even though the API always sends it — 9 existing test files construct `AuthUser` literals that
predate avatars and have nothing to do with them (role/permission tests); every real consumer already treats
a missing value the same as `null` (`v-if="user?.avatar_url"`), so making it required would only have forced
unrelated edits across those 9 files for no behavioral gain.

**Status**: implemented and verified locally the same day; not yet committed/pushed or opened as a PR.
Backend: 2 new migrations, `ClinicSettingService::updateLogo()`/`removeLogo()`,
`UserService::updateAvatar()`/`removeAvatar()`, 2 new `FormRequest`s, 4 new routes (`POST`/`DELETE
/clinic-settings/logo`, `POST`/`DELETE /users/{user}/avatar`), `ClinicSettingResource`/`UserResource` gained
`logo_url`/`avatar_url`, 10 new Feature tests (`ClinicSettingTest`/`UserTest`) covering
upload/replace-deletes-old-file/remove/non-admin-403/non-image-422 — full local suite re-run **1224/1224
green** (1214 baseline + 10 new, zero regressions), Pint clean (571 files). Frontend: `vue-tsc`/ESLint/Prettier clean
on every touched file (the repo-wide 300+-file `prettier --check` "failure" outside `src/` scope and on
untouched files is a pre-existing Windows CRLF/`git core.autocrlf` artifact — confirmed via a byte-identical
`prettier --write` diff on an untouched file, matching the class of local-only false positive
`TECH_DEBT.md`/[[environment_local_phpstan_false_positives]] already documents; CI runs on Linux and is
unaffected), 11 new/updated Vitest tests (`UsersView.test.ts`, `locales/index.test.ts`,
`PracticeSettingsView.test.ts`'s fixture completed with `logo_url`), i18n parity re-verified at 1510/1510/1510
across en/ar/tr.
