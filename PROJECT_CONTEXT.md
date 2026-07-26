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

Next module: not yet selected — Clinical Notes closes out the clinical-documentation module; remaining
not-started modules per the list above are Inventory, Laboratory, Imaging, Reports, Settings, AI Assistant.
See `docs/roadmap.md` for current per-module status.

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
