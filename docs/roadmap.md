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
| **Treatment Plans** | **Done — Implementation Complete ✅**, commit `0677128` (2026-07-23), not yet merged to `main`/tagged; see `modules/treatment-plans.md` |
| **Billing** | Implementation in progress (2026-07-23–2026-07-25) — design approved, backend (migrations/models/services/policies/Form Requests/Controllers/routes) and frontend (Invoice UI & workflow) complete; backend/frontend automated tests, E2E suite, and final module doc still pending before this row moves to Done; see `modules/billing-design.md` |
| **Clinical Notes** | **Done — Production Ready ✅**, merged to `main` via PR #3 (2026-07-26) — SOAP documentation, Draft→Signed lifecycle, append-only addendums, receptionist excluded by design; 703/703 backend + 595/595 frontend tests, permanent E2E suite confirmed 19/19 green on GitHub Actions (`workflow_dispatch` run `30189070147`); see `modules/clinical-notes-design.md` |
| **Payments** | Implementation complete (2026-07-25, same day as design approval) — backend (migrations/model/service/policy/Form Requests/Controller/routes, additive `InvoiceResource` fields) and frontend (Payments tab, Invoice Payments panel, dialogs, i18n) complete; backend Unit+Feature tests and frontend Vitest tests green; permanent E2E suite still pending (same open item as Billing) before this row is fully "Done"; see `modules/payments-design.md` |
| **Inventory** | Implementation complete (2026-07-26, same day as design approval) — Supplier/Supply Category/Supply catalogs, immutable `stock_movements` ledger (computed on-hand, never stored), Purchase Order draft→placed→partially_received→received lifecycle, Low Stock Dashboard widget, top-level nav; 771/771 backend tests (68 Inventory-specific) + 19 new frontend Vitest tests green, `vue-tsc`/ESLint/Pint clean; permanent E2E suite written and structurally verified via direct browser inspection but not yet CI-confirmed (same local Windows Docker networking limitation as Dental Chart/Clinical Notes — see `TECH_DEBT.md`); see `modules/inventory-design.md` |
| Laboratory | Not started |
| Imaging | Not started |
| Reports | Not started |
| Settings | Not started |
| AI Assistant | Not started (optional, assistive only — see `PROJECT_CONTEXT.md`) |

Full module list and scope boundaries are defined in `PROJECT_CONTEXT.md`.
