# Roadmap

Modules are implemented one at a time, each fully complete (migration → model → validation → service → policy → API → Vue pages → tests → docs) before the next begins.

## Status

| Module | Status |
|---|---|
| Dashboard | Done |
| Authentication | Done |
| Users | Done |
| Roles & Permissions | Done |
| Patients | Done |
| **Appointments** | **Done — Production Ready ✅** (tagged `v1.0.0-appointments`, 2026-07-20) — full backend + frontend (Calendar Board, Appointment CRUD/lifecycle, Types, Dentist Working Hours/Time Off, Dashboard widgets), 13/13 E2E green on GitHub Actions; see `modules/appointments.md` |
| Dental Chart | **Done — Production Ready ✅**, merged to `main` 2026-07-22; see `modules/dental-chart.md` |
| **Treatment Plans** | Merged to `main` via PR #1 (2026-07-25, merge commit `f41cda5`) — implementation complete; still missing a permanent Playwright E2E suite (see `TECH_DEBT.md`), so not yet at the "Production Ready" bar Appointments/Dental Chart/Clinical Notes/Inventory/Laboratory meet; see `modules/treatment-plans.md` |
| **Billing** | Merged to `main` via PR #1 (2026-07-25, merge commit `f41cda5`) — backend + frontend (Invoice UI & workflow) complete; still missing a permanent Playwright E2E suite (see `TECH_DEBT.md`); see `modules/billing-design.md` |
| **Clinical Notes** | **Done — Production Ready ✅**, merged to `main` via PR #3 (2026-07-26) — SOAP documentation, Draft→Signed lifecycle, append-only addendums, receptionist excluded by design; 703/703 backend + 595/595 frontend tests, permanent E2E suite confirmed 19/19 green on GitHub Actions (`workflow_dispatch` run `30189070147`); see `modules/clinical-notes-design.md` |
| **Payments** | Merged to `main` via PR #1 (2026-07-25, merge commit `f41cda5`), plus a concurrency-race fix via PR #2 (`58ebfa9`) — backend + frontend (Payments tab, Invoice Payments panel) complete; still missing a permanent Playwright E2E suite (same open item as Billing/Treatment Plans, see `TECH_DEBT.md`); see `modules/payments-design.md` |
| **Inventory** | **Done — Production Ready ✅**, merged to `main` via PR #4 (2026-07-27, merge commit `bf2592f`) — Supplier/Supply Category/Supply catalogs, immutable `stock_movements` ledger (computed on-hand, never stored), Purchase Order draft→placed→partially_received→received lifecycle, Low Stock Dashboard widget, top-level nav; 771/771 backend tests (68 Inventory-specific) + 19 new frontend Vitest tests green, `vue-tsc`/ESLint/Pint/Prettier clean, permanent E2E suite confirmed 20/20 green on GitHub Actions (`workflow_dispatch` run `30282195677`, after fixing 5 real bugs CI's own native runner surfaced — see `TECH_DEBT.md`); see `modules/inventory-design.md` |
| **Laboratory** | **Done — Production Ready ✅**, CI-confirmed 2026-07-27 on `feature/laboratory` (not yet merged to `main`) — Lab vendor catalog, Lab Case draft→sent→received→quality_checked lifecycle (+ cancelled), auto-suggested due date from lab turnaround, top-level nav, Dashboard "Lab Cases Due" widget, browser-printable slip; 815/815 backend tests (58 Laboratory-specific) + 626/627 frontend Vitest tests green (13 Laboratory-specific; 1 unrelated confirmed-flaky failure), `vue-tsc`/ESLint/Pint/Prettier clean, permanent E2E suite confirmed green on GitHub Actions (`workflow_dispatch` run `30294033562`, after fixing 1 real duplicate-toast bug — see `TECH_DEBT.md`); see `modules/laboratory-design.md` |
| Imaging | Not started |
| Reports | Not started |
| Settings | Not started |
| AI Assistant | Not started (optional, assistive only — see `PROJECT_CONTEXT.md`) |

Full module list and scope boundaries are defined in `PROJECT_CONTEXT.md`.
