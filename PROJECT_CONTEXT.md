# DentalSuite AI Context

Version: 1.0

---

# Project Overview

Project Name: DentalSuite

Goal: Build a modern, professional Dental Clinic Management System using Laravel 12 and Vue 3.

The project focuses ONLY on Dental Clinics.
It is NOT intended to become a Hospital Management System.

The philosophy is:

Simple
Fast
Modern
Scalable
Maintainable

The system should be enterprise-quality but without unnecessary complexity.

---

# Technology Stack

Backend

- Laravel 12
- PHP 8.4+

Frontend

- Vue 3
- TypeScript
- PrimeVue
- TailwindCSS

Database

- PostgreSQL

Cache

- Redis

Storage

- Local
- S3 Compatible

Deployment

- Docker

IDE

- Claude Code

---

# Supported Languages

Arabic (RTL)
English (LTR)
Turkish (LTR)

---

# Main Modules

Dashboard
Authentication
Users
Roles & Permissions
Patients
Appointments
Dental Chart
Treatment Plans
Clinical Notes
Billing
Payments
Inventory
Laboratory
Imaging
Reports
Settings
AI Assistant

---

# AI

Claude API integration is optional.
AI is an assistant only.

AI may help with:

Clinical Notes
Treatment Suggestions
Smart Search
Dashboard Insights
Writing Reports

Never allow AI to make medical decisions.

---

# Architecture

Modular Monolith
Clean Architecture
API First
Service Layer
Thin Controllers
Business Logic inside Services
Reusable Components

---

# Database

PostgreSQL
UUID
Soft Deletes
Audit Logs
Multi Branch
Single Organization (No Multi Tenant in Version 1)

---

# UI Principles

Simple
Modern
Responsive
Dark Mode
RTL Ready
Minimal Clicks
Excellent UX

---

# Coding Standards

PSR-12
Laravel Pint
PHPStan
Feature Tests
Unit Tests
No duplicated code
Readable code

---

# Project Philosophy

Keep it simple.
Do not over engineer.
Build only what is needed.
Always prefer readability.
Performance before complexity.
UX before fancy features.
AI is optional.

---

# Current Status

Implementation Phase.

The architecture has been approved.
The technology stack has been approved.
The initial blueprint has been approved.
Repository structure: Monorepo (backend/, frontend/, docker/).

Completed modules: Dashboard, Authentication (Sanctum SPA cookie auth; users/sessions tables use UUID primary keys), Users (CRUD + search, soft deletes, self-delete blocked), Roles & Permissions (simple backed enum: admin/dentist/receptionist; user management restricted to admin), Patients (standard clinical intake, patient_code, admin/receptionist write access, dentist read-only, generic audit log infrastructure — see docs/modules/patients.md), **Appointments — Production Ready ✅ (tagged `v1.0.0-appointments`, 2026-07-20)**: Calendar Board with Day/Week/Month/List views, full appointment CRUD + status-transition lifecycle, slot availability logic, Appointment Types, Dentist Working Hours/Time Off, Dashboard widgets, keyboard shortcuts + full a11y/RTL/responsive pass, 13/13 E2E confirmed green on GitHub Actions — see docs/modules/appointments.md (final module doc) and TECH_DEBT.md for open (non-blocking) items, **Dental Chart — Production Ready ✅ (merged to `main` 2026-07-22 following the SaaS architecture checkpoint)**: per-patient odontogram (52-tooth FDI schematic, whole-tooth and surface-specific findings/procedures), status lifecycle, admin-managed `dental_conditions` catalog, Accessible List view, keyboard tooth navigation, full a11y/RTL/i18n pass (98/98 en/ar/tr parity), 347/347 backend + 428/428 frontend tests, 16/16 E2E confirmed green via `workflow_dispatch` on GitHub Actions (run `29937143710`) — see docs/modules/dental-chart.md (final module doc) and TECH_DEBT.md for open (non-blocking) items, **Treatment Plans — Implementation Complete ✅ (`feature/treatment-plans`, commit `0677128`, 2026-07-23, not yet merged to `main`/tagged)**: multi-plan-per-patient treatment recommendation workflow with plan-level and item-level status lifecycles, cost snapshot/freeze at `presented`, `dental_conditions` reused as the V1 pricing catalog, one-way read-only links to Dental Chart/Appointments, 505/505 backend + 541/541 frontend tests — see docs/modules/treatment-plans.md (final module doc) and TECH_DEBT.md for open (non-blocking) items, including the one gap relative to this project's usual bar: no permanent E2E suite yet.

System-Wide Production Gate (started 2026-07-18, per explicit user request, before starting the next module; closed 2026-07-20): DatabaseSeeder demo-account environment gate + `app:create-admin` command, general API rate limiting, production Docker/nginx/SSL topology (`docker-compose.prod.yml`), backup/restore scripts (rehearsed end-to-end, not just written — see TECH_DEBT.md), S3 offsite backup made config-only-activation-ready, CI/CD quality gate (`.github/workflows/ci.yml`) — Backend, Frontend, and E2E (permanent `frontend/e2e/` Playwright suite) jobs all **confirmed green on `main`** via the GitHub Actions API (run `29763458360`, commit `3faf2d7`: 13/13 E2E tests passed in 36.2s, alongside Backend/Frontend). See TECH_DEBT.md's CI entry for the full debugging trail — the last failure took four rounds of real root-causing (a DatePicker popover z-order issue, a non-retrying Playwright assertion, a guessed/non-deterministic time slot that self-collided across Playwright's automatic retry, a missing tab click on Edit, and a strict-mode-ambiguous post-cancel selector) before landing clean.

**Gate status: closed (2026-07-20)** — confirmed via the GitHub Actions API, not just local runs (a local Windows Docker Desktop networking pathology made this suite's last few percent unverifiable locally in isolation; CI's native runner has no such issue).

**SaaS Architecture Checkpoint (2026-07-22, before merging `feature/dental-chart` to `main`)**: reviewed
Dental Chart against future multi-tenant SaaS requirements (tenant isolation, data scalability, permissions
model, API/frontend isolation) — no blockers found, no implementation changes needed. DentalSuite V1 remains
single-organization by explicit design (no table is tenant/branch-scoped, confirmed system-wide, not just
for this module); full findings and the future multi-tenancy migration path are documented in
`docs/modules/dental-chart.md`'s "SaaS Readiness" section. `feature/dental-chart` merged to `main` following
this checkpoint.

**Billing — Implementation in progress (2026-07-23–2026-07-25)**: design approved; backend (migrations,
`Invoice`/`InvoiceItem`/`BillingSetting` models, `InvoiceService`, `InvoicePolicy`/`InvoiceItemPolicy`, Form
Requests, `InvoiceController`/`InvoiceItemController`, routes) and frontend (Patient Invoices tab, Invoice
Detail view, status-transition actions, manual/from-treatment-plan item entry, full en/ar/tr i18n) complete;
`vue-tsc`/ESLint clean, 541/541 frontend Vitest tests green. Backend/frontend automated tests, a permanent
E2E suite, and the final `modules/billing.md` doc are still pending before this module is marked Done — see
`docs/modules/billing-design.md` and `TECH_DEBT.md`.

**Payments — Implementation complete (2026-07-25, same day as design approval, `feature/treatment-plans`)**:
design approved with all six open decisions resolved (no paysplit fan-out, capped partial refunds, no
time-based void window, `PaymentMethod` = `cash`/`card`/`bank_transfer`/`other`, a dedicated Payments tab
on Patient Detail, no V1 outstanding-balance widget). Backend: `payments` table (nullable `invoice_id` for
unapplied/advance credits, self-referencing `refunded_payment_id` for refund rows, signed `amount`),
`PaymentService` (record/apply/refund/updateMetadata/delete), `PaymentPolicy` (admin+receptionist write,
dentist read-only, admin-only delete — mirrors `InvoicePolicy`), `InvoiceResource` gains additive
`amount_paid`/`balance_due`/`payment_status`. Frontend: `stores/payments.ts`, Patient Detail's new Payments
tab, Invoice Detail's new Payments panel + balance readout, Record/Refund/Edit/Apply dialogs, full en/ar/tr
i18n. Verification: backend `pint`/`phpstan analyse` clean, 619/619 backend Unit tests green plus 22/22 new
`PaymentTest` Feature tests (closing the Feature-test gap Billing itself still has); frontend `vue-tsc`/
`eslint` clean; the new `stores/payments.test.ts`/`services/payments/errors.test.ts` (20 tests) pass
cleanly in every run. A full-suite run (561 tests total) showed 2 failures confined to the pre-existing
`router/index.test.ts` — confirmed unrelated to Payments (that file imports nothing from `payments`) and
confirmed passing 11/11 in isolation; consistent with resource-contention flakiness under heavy parallel
load, not a code defect — logged as its own `TECH_DEBT.md` entry rather than silently ignored. A permanent
Playwright E2E spec for Payments (and for Billing) is still open — see `TECH_DEBT.md`. See
`docs/modules/payments-design.md` for the full design + Decision Log.

**Clinical Notes — Production Ready ✅ (merged to `main` via PR #3, 2026-07-26)**: SOAP-structured
per-patient clinical documentation (chief complaint + Subjective/Objective/Assessment/Plan, `note_type`,
optional `Appointment` link), Draft → Signed lifecycle (atomic sign via `DB::transaction`, blank-note
rejection, `ClinicalNoteLockedException` guarding any further write to a signed note), append-only
addendums (no update/delete route at any permission level, enforced at the schema level too). Admin/dentist
author+sign+addend, admin-only delete; **receptionist has no access at all** (a deliberate divergence from
Dental Chart/Treatment Plans, given the sensitivity of clinical narrative content), enforced at policy,
frontend tab-visibility, and router-guard layers. 703/703 backend tests (60 Clinical-Notes-specific),
595/595 frontend Vitest tests (31 Clinical-Notes-specific), a permanent Playwright E2E suite
(`frontend/e2e/clinical-notes.spec.ts`) built during this module's own implementation (closing the E2E gap
Treatment Plans/Billing/Payments each deferred), confirmed 19/19 green via the GitHub Actions API
(`workflow_dispatch` run `30189070147`) — see `docs/modules/clinical-notes-design.md` and TECH_DEBT.md for
open (non-blocking) items.

**Inventory — Production Ready ✅ (2026-07-27, merged to `main` via PR #4, merge commit `bf2592f`)**:
admin-managed Supplier/Supply Category/Supply catalogs (`is_active` soft-disable, mirroring
`AppointmentType`/`DentalCondition`'s convention), an immutable append-only `stock_movements` ledger
(`quantity_on_hand`/`is_low_stock` always computed live via `SUM(quantity_delta)`, never stored — a
deliberate improvement over Open Dental's own mutable-on-hand-field precedent, per the design doc's §0
competitive research), and a Purchase Order `draft` → `placed` → `partially_received` → `received` lifecycle
(per-item receiving hard-capped at `quantity_ordered`, cancel only while nothing has been received).
Dentists may record `used`/`wasted`/`expired` Stock Movements (a deliberate divergence from the
admin+receptionist-only precedent every prior financial module used, since dentists are the ones actually
consuming supplies chairside); Supplier/Category management and Purchase Order procurement remain
admin+receptionist, Purchase Order delete admin-only. New top-level **Inventory** sidebar group and a
Dashboard Low Stock widget. 771/771 backend tests (68 Inventory-specific) + 19 new frontend Vitest tests
green, `vue-tsc`/ESLint/Pint/Prettier clean; a permanent Playwright E2E suite
(`frontend/e2e/inventory.spec.ts`) confirmed via the GitHub Actions API across five `workflow_dispatch` runs
— each run's native CI environment (unaffected by this dev machine's own Windows Docker networking latency,
already logged against Dental Chart/Clinical Notes) surfaced one more real bug than the last: a genuine
PHPStan error, a codebase-wide `id`-vs-`inputId` PrimeVue accessibility defect, a missing confirm-dialog
label, two duplicate-toast bugs, and one real E2E selector ambiguity — all fixed and re-verified. Final run
(`30282195677`): **Backend success, Frontend success, E2E success — 20/20 E2E tests green**. See
`docs/modules/inventory-design.md` and TECH_DEBT.md for the full diagnostic trail.

**Laboratory — Production Ready ✅ (2026-07-27, merged to `main` via PR #5, merge commit `bac6ae1`)**: admin-managed
`Lab` vendor catalog (own model, not reused from `Supplier` — the two have unrelated relations) and
`LabCase` (patient/lab/dentist/tooth-numbers/shade/material/fee/tracking-number, one record per case
— no header+items split, unlike Purchase Orders). `LabCaseStatus` lifecycle: `draft` → `sent` →
`received` → `quality_checked`, plus `cancelled` (blocked once `received_at` is set). `send()`
auto-suggests `due_at` from the lab's `default_turnaround_days` unless already manually set.
Permissions: `admin`+`dentist` create/update/cancel (clinical prescription decision),
`admin`+`receptionist` send/receive/qualityCheck (front-desk logistics), admin-only delete
(draft-only). `treatment_plan_item_id`/`appointment_id` are one-way traceability FKs (case never
mutates either module), same convention as `TreatmentPlanItem.diagnosis_entry_id`. New top-level
**Laboratory** sidebar group, a Dashboard "Lab Cases Due" widget, and a browser-printable Lab Case
slip (CSS print only, no PDF dependency). 815/815 backend tests (58 Laboratory-specific) + 626/627
frontend Vitest tests green (13 Laboratory-specific; the one unrelated failure confirmed flaky,
untouched file), `vue-tsc`/ESLint/Pint/Prettier clean; a permanent Playwright E2E suite
(`frontend/e2e/laboratory.spec.ts`) confirmed via the GitHub Actions API across two
`workflow_dispatch` runs — the first surfaced one real bug (a duplicate-worded toast on rapid
back-to-back status transitions), fixed and re-verified; the second run's remaining E2E
failures/flakiness (`dental-chart.spec.ts`, `inventory.spec.ts`, `patients.spec.ts`) were proven —
via Playwright's own sequential execution order — to be pre-existing and unrelated to Laboratory.
See `docs/modules/laboratory-design.md` and TECH_DEBT.md for the full diagnostic trail. File/photo
attachments, Appointment/Calendar badge integration, and remake/redo case chaining are deliberately
deferred to V2 (design doc §2/§7).

**Correction (2026-07-27)**: Treatment Plans, Billing, and Payments — described above as "not yet merged"
/ "in progress" — were actually merged to `main` via PR #1 on 2026-07-25 (merge commit `f41cda5`), plus a
payments concurrency-race fix via PR #2 (`58ebfa9`, same day). This paragraph was never updated after
those merges; `docs/roadmap.md` now reflects the correct status. All three still lack a permanent
Playwright E2E suite (see `TECH_DEBT.md`), so none has reached the "Production Ready" bar Appointments/
Dental Chart/Clinical Notes/Inventory/Laboratory meet.

**Imaging — Production Ready ✅ (2026-07-27, merged to `main` via PR #6, 2026-07-28, merge commit `2b1fb45`)**: design approved
2026-07-27 (see `docs/modules/imaging-design.md`'s Approval & Decision Log). `PatientImage` per-patient gallery
(photos + X-rays, optional FDI tooth/surface tagging, `taken_at` distinct from upload time, one-way
traceability to `TreatmentPlanItem`/`Appointment`). Storage exclusively via the `Storage` facade with
a per-row `disk` column (local in V1, s3-ready via config only) and authenticated/policy-checked
streaming routes — never a public URL. GD-based synchronous thumbnails, no new Composer dependency.
Permissions: `admin`+`dentist`+`receptionist` view/upload/edit, `admin`-only delete. Frontend: a
patient-scoped Imaging tab (no top-level nav, no Pinia store), upload dialog with mobile camera
capture via the standard HTML5 `capture` attribute, and a non-destructive lightbox (brightness/
contrast/invert/zoom/compare, nothing ever persisted). First module built under the new standing
SaaS multi-tenant + PWA/mobile-first principles below — checked explicitly in the design doc's own
closing section. DICOM/CBCT, hardware capture, persistent annotation, and formal FMX grouping are
out of V1 scope by design (see `TECH_DEBT.md`). 834/834 backend tests (19 Imaging-specific) +
637/637 frontend Vitest tests green, `vue-tsc`/ESLint/Pint/Prettier clean; a permanent Playwright
E2E suite (`frontend/e2e/imaging.spec.ts`) confirmed via the GitHub Actions API across three
`workflow_dispatch` runs — the first two surfaced three real PHPStan errors and three real E2E
selector bugs, all fixed and re-verified. Final run (`30310705267`): **Backend success, Frontend
success, E2E success — 27/27 passed, 0 failed, 0 flaky.** Real bug found and fixed along the way:
`App\Rules\BelongsToPatient` throws a genuine SQL error against `TreatmentPlanItem` (no direct
`patient_id` column) — fixed in this module's own Form Requests; Laboratory's identical pre-existing
bug is flagged in `TECH_DEBT.md`, not touched here. See `TECH_DEBT.md`/`docs/roadmap.md` for the full
diagnostic trail.

**Reports — Production Ready ✅ (2026-07-28, `feature/reports`, CI-confirmed)**: design approved
2026-07-28 (see `docs/modules/reports-design.md`'s Approval & Decision Log). Six reports —
Production, Collections, A/R Aging, Appointment Analytics, Treatment Plan Acceptance, New Patients —
each a live query over existing data via a single `ReportService` (no new tables, no persisted
snapshots). Financial reports (Production/Collections/A-R Aging) gated `admin`-only via two plain
Gate abilities (`view-financial-reports`/`view-operational-reports`, since Reports has no natural
Eloquent model for a Policy); operational reports open to every role, enforced at both the API layer
and the frontend router (not nav-only). CSV export only (native `fputcsv`, no new dependency); no
PDF, no scheduled/emailed reports, no ad-hoc query builder — all explicitly out of scope, see the
design doc §8/§9. `DashboardService`'s previously-hardcoded `monthly_revenue => 0` now calls
`ReportService::collections()` directly for the current month, so the aggregation logic lives in
exactly one place. 855/855 backend tests (21 Reports-specific) + 652/652 frontend Vitest tests green,
`vue-tsc`/ESLint/Pint/Prettier clean, production build green. A permanent Playwright E2E suite
(`frontend/e2e/reports.spec.ts`, 2 tests) is **confirmed via the GitHub Actions API** across two
`workflow_dispatch` runs on `feature/reports` — local execution was blocked in this session's dev
container (Alpine/musl base vs. Playwright's glibc-only Chromium build, a new local-environment
limitation distinct from the Windows Docker networking issue logged against earlier modules), so CI
was the only real signal, and it surfaced genuine issues both times. First run (`30323783949`) found
four real Larastan findings in `ReportService.php` (two unnecessary nullsafe operators, two
return-type mismatches from `Collection`'s invariant generics) and, via the E2E suite, a real
pre-existing bug shared by every module with role-gated sidebar children (Inventory, Laboratory,
Dental Chart): `AppSidebarItem.vue` never actually filtered `item.children` by role, only
`AppSidebar.vue`'s top-level items — so a restricted nav link rendered for every role and only
denied access on click. Fixed both; second run (`30326106755`) is fully green: **Backend 855/855,
Frontend 652/652, E2E 29/29 — zero failures.** Also fixed along the way: a `whereBetween` date-range
comparison against `issue_date`/`received_at` silently excluded rows whose stored value carried a
time-of-day suffix past a bare `Y-m-d` upper bound — caught by `DashboardTest`'s own new fixture,
fixed by bounding both sides to the full day.

**Settings — Production Ready ✅ (2026-07-30, `feature/settings`, CI-confirmed)**: design approved
2026-07-30 (see `docs/modules/settings-design.md`'s Approval notes). Closes three concrete gaps rather
than building a speculative settings tree: **Practice Settings** (new `ClinicSetting` singleton table,
clinic name/phone/address/email, admin-only update but every-role `view` since the Lab Case printable
slip needs to read it), **Billing Settings** (the existing `BillingSetting` table's first-ever API/UI —
`currency_code`/`tax_rate`/`invoice_number_prefix` editable, `next_invoice_sequence` shown read-only
since `InvoiceService` alone owns writing it), and **My Account** (self-service profile edit +
current-password-gated password change for every role, structurally IDOR-proof since every route
resolves its target from `$request->user()`, never a route-model-bound `{user}`). Practice Settings is
wired into the Laboratory module's printable Lab Case slip as its first real consumer. Settings home
nav entry fills in the existing `nav.settings` `comingSoon` scaffold (admin-only at the top level,
since every child is admin-only); My Account is reachable from the header avatar menu instead, since
every role — not just admins — needs it. 877/877 backend tests (22 Settings-specific:
`ClinicSettingTest`/`BillingSettingTest`/`ProfileTest`) + 672/672 frontend Vitest tests green (a real
pre-existing gap found and fixed along the way: `AppSidebar.test.ts`'s mock router had no `settings`
route, so mounting the sidebar for an admin threw once Settings became a real nav link instead of a
`comingSoon` placeholder), `vue-tsc`/ESLint/Pint/Prettier clean; a permanent Playwright E2E suite
(`frontend/e2e/settings.spec.ts`) confirmed via the GitHub Actions API across two `workflow_dispatch`
runs — the first (`30561623931`) surfaced a Prettier formatting gap in three new files and a wrong
error-message string in the My Account E2E test (Laravel's `current_password` rule actually returns
"The password is incorrect.", not the assumed "The provided password is incorrect." — invisible to the
backend's own `ProfileTest` since it only asserts the error key, not the rendered message), both fixed;
the second run (`30562178951`) is fully green: **Backend 877/877, Frontend 672/672, E2E 32/32 — zero
failures.** Multi-branch/location settings, clinic logo upload, and notification/reminder settings are
deliberately out of scope for V1 (see `docs/modules/settings-design.md`'s §2/§9 and `TECH_DEBT.md`).

**AI Assistant — Production Ready ✅ (merged to `main` via PR #9, 2026-07-31, merge commit `644fed6`, CI-confirmed)**: design
approved 2026-07-31 (see `docs/modules/ai-assistant-design.md`'s Approval & Decisions section).
Final module on the roadmap (per user's explicit prioritization — it depends on data/workflow
completeness across everything else to add real value, so it comes last). Splits into two risk
tiers by PHI exposure: three zero-PHI features ship enabled-eligible (Dashboard Insights, Smart
Search, Writing Reports — aggregate report data or the user's own query text only, never
patient-identified content sent to the Claude API); Clinical Notes draft-assist and Treatment
Suggestions are built in the same pass but ship disabled-by-default and absent from the UI, gated
behind an explicit admin acknowledgment that a signed BAA with Anthropic is in place
(`ai_assistant_phi_features_acknowledged` on `ClinicSetting`) — a hard product requirement, not a
soft default. AI is decision-support only: every suggestion routes through the existing
Policy-gated service layer (`ClinicalNoteService`, `TreatmentPlanService`) and requires explicit
user acceptance before any write; nothing is auto-created, auto-signed, or auto-persisted. A new
append-only `AiInteractionLog` table records every prompt/response and acceptance decision;
AI-generated content stays visually/programmatically distinguishable from user-authored content
in the UI ("AI-suggested, unreviewed" tag) until accepted. The whole module is additionally gated
at the infrastructure level: with no `ANTHROPIC_API_KEY` configured, every endpoint fails closed
with a 503 rather than silently no-op'ing. 905/905 backend tests (28 new: 12 in `AiAssistantGatingTest`,
10 in `AiAssistantControllerTest`, 2 in `AiInteractionFeatureTest`, plus 4 regression tests added to
`ClinicSettingTest` for partial-update gating) + 694/694 frontend Vitest tests green
(22 new, zero regressions), `vue-tsc`/ESLint/Pint/Prettier clean; a permanent Playwright E2E suite
(`frontend/e2e/ai-assistant.spec.ts`) confirmed via the GitHub Actions API across five
`workflow_dispatch` runs on `feature/ai-assistant` — the first two iterations fixed a real backend
bug (`LengthAwarePaginator` contract doesn't expose `getCollection()`, only `items()`) and Prettier
formatting; the next two fixed E2E-spec-only issues (a switch-role query needed scoping to `main`
plus a self-healing settings reset for CI retries; a stray `/dashboard` goto that should have been
`/`, since the dashboard route is the bare root path) — the app itself needed zero further changes
after the first fix. Final run (`30605056813`) is fully green: **Backend 905/905, Frontend 694/694,
E2E 34/34 — zero failures.** Follows the standard module workflow: Design → Backend → Frontend →
Tests → CI → Documentation → PR.

See `docs/roadmap.md` for current per-module status.

**Frontend UX & Navigation Redesign** (see `docs/modules/frontend-ux-redesign.md`). With every module
on the original roadmap Production Ready ✅, focus shifted to a cross-cutting, frontend-only quality
pass, benchmarked against Linear/Notion/Stripe/Vercel interaction patterns rather than dental-EMR
competitors — explicitly no new backend scope beyond one small, user-approved exception (below), and
evolves the existing PrimeVue Aura theme rather than introducing a bespoke design system. Split into
four sequential phases (Navigation Shell → Dashboard → Data Tables → Cross-cutting Polish), each run
through the full standard workflow independently so `main` stays releasable between phases.

**Phase 1: Navigation Shell — Production Ready ✅ (merged to `main` via PR #10, 2026-08-01, merge
commit `f45110a`, CI-confirmed)**: Sidebar redesign (collapsible section grouping, Favorites, Recent Items —
last 5 visited record pages, upgraded from a generic fallback to the real name once a detail view loads
it, all `localStorage`-only per the confirmed frontend-only scope — plus true recursive nesting,
resolving a previously-tracked one-level limit), Header redesign (a breadcrumb trail derived from the
existing nav config, no per-route duplication, plus a global search/Command Palette entry point),
Command Palette (`Ctrl+K`/`Cmd+K`: role-filtered "Go to X" for every reachable route, a "New Patient"
quick action, arrow-key navigation), and app-wide keyboard shortcuts (`?` for a shortcuts-help overlay,
Linear-style `g`-then-X go-to chords) generalizing the existing calendar-shortcuts guard pattern
without touching it. One small, explicitly-approved backend exception to the "frontend-only" framing:
a `GET /invoices` endpoint (paginated, searchable, status-filterable) — the Billing sidebar entry had
been silently stuck on a stale "Coming Soon" flag despite the underlying Invoice CRUD working end to
end, and no clinic-wide list endpoint existed to point it at. 913/913 backend tests (8 new) + 751/751
frontend Vitest tests (30 new), `vue-tsc -b`/ESLint/Prettier clean, permanent E2E suite
(`frontend/e2e/frontend-nav-shell.spec.ts`) confirmed via the GitHub Actions API across three
`workflow_dispatch` runs — real findings along the way: a `vue-tsc -b` build-only type error invisible
to plain `--noEmit`, and three genuine E2E bugs (a locator searching inside a link instead of its
sibling row, a `getByRole('heading', ...)` assertion against a PrimeVue `Dialog` header that renders as
plain text not a semantic heading, and a keyboard-shortcut race against the app shell not yet being
mounted) — the app itself needed only the type fix. Final run (`30629204011`) is fully green: **Backend
913/913, Frontend 751/751, E2E 39/39 — zero failures.** Phases 2–4 (Dashboard, Data Tables System,
Cross-cutting Polish) are not yet started.

**Standing architectural principles (2026-07-27, apply to every future module's design phase)**:
1. **SaaS multi-tenant readiness** — every new schema/service/API decision must stay compatible with a
   future multi-clinic model; V1 stays single-organization, but no design should assume it in a way that
   would force a real rewrite later. See `TECH_DEBT.md`'s Multi-branch item and the Treatment-Plans-pricing
   item for two examples already flagged as relevant here.
2. **PWA & mobile-first UI** — every new screen must be responsive, touch-friendly, and PWA-installable
   from first implementation, not retrofitted later.

Full documentation set: see docs/ (architecture, database-design, api-guidelines, coding-standards, decisions, roadmap, deployment, modules/), plus CHANGELOG.md and TECH_DEBT.md at the repo root.

---

# Development Strategy

Implement one module at a time.
Complete every module before starting another.

Every module must include

Migration
Model
Validation
Service
Policy
API
Vue Pages
Tests
Documentation

---

# Claude Instructions

Always read this file first.
Never change architecture without asking.
Never introduce unnecessary packages.
Prefer Laravel native solutions.
Ask before making major decisions.
Explain tradeoffs when multiple solutions exist.
Always keep the project maintainable.
