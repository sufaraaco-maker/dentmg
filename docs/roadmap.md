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
| **Treatment Plans** | Merged to `main` via PR #1 (2026-07-25, merge commit `f41cda5`) — implementation complete; clinic-wide list added via PR #12 (2026-08-02, `GET /treatment-plans` + `TreatmentPlansView.vue`, fixes the sidebar's stale `comingSoon` flag); still missing a permanent Playwright E2E suite (see `TECH_DEBT.md`), so not yet at the "Production Ready" bar Appointments/Dental Chart/Clinical Notes/Inventory/Laboratory meet; see `modules/treatment-plans.md` |
| **Billing** | Merged to `main` via PR #1 (2026-07-25, merge commit `f41cda5`) — backend + frontend (Invoice UI & workflow) complete; still missing a permanent Playwright E2E suite (see `TECH_DEBT.md`); see `modules/billing-design.md` |
| **Clinical Notes** | **Done — Production Ready ✅**, merged to `main` via PR #3 (2026-07-26) — SOAP documentation, Draft→Signed lifecycle, append-only addendums, receptionist excluded by design; 703/703 backend + 595/595 frontend tests, permanent E2E suite confirmed 19/19 green on GitHub Actions (`workflow_dispatch` run `30189070147`); see `modules/clinical-notes-design.md` |
| **Payments** | Merged to `main` via PR #1 (2026-07-25, merge commit `f41cda5`), plus a concurrency-race fix via PR #2 (`58ebfa9`) — backend + frontend (Payments tab, Invoice Payments panel) complete; still missing a permanent Playwright E2E suite (same open item as Billing/Treatment Plans, see `TECH_DEBT.md`); see `modules/payments-design.md` |
| **Inventory** | **Done — Production Ready ✅**, merged to `main` via PR #4 (2026-07-27, merge commit `bf2592f`) — Supplier/Supply Category/Supply catalogs, immutable `stock_movements` ledger (computed on-hand, never stored), Purchase Order draft→placed→partially_received→received lifecycle, Low Stock Dashboard widget, top-level nav; 771/771 backend tests (68 Inventory-specific) + 19 new frontend Vitest tests green, `vue-tsc`/ESLint/Pint/Prettier clean, permanent E2E suite confirmed 20/20 green on GitHub Actions (`workflow_dispatch` run `30282195677`, after fixing 5 real bugs CI's own native runner surfaced — see `TECH_DEBT.md`); see `modules/inventory-design.md` |
| **Laboratory** | **Done — Production Ready ✅**, merged to `main` via PR #5 (2026-07-27, merge commit `bac6ae1`) — Lab vendor catalog, Lab Case draft→sent→received→quality_checked lifecycle (+ cancelled), auto-suggested due date from lab turnaround, top-level nav, Dashboard "Lab Cases Due" widget, browser-printable slip; 815/815 backend tests (58 Laboratory-specific) + 627/627 frontend Vitest tests green, `vue-tsc`/ESLint/Pint/Prettier clean, permanent E2E suite confirmed green on GitHub Actions; see `modules/laboratory-design.md` |
| **Imaging** | **Done — Production Ready ✅**, merged to `main` via PR #6 (2026-07-28, merge commit `2b1fb45`) — per-patient diagnostic image gallery (photos + X-rays), authenticated/policy-checked storage streaming, non-destructive lightbox viewer (brightness/contrast/invert/zoom/compare), mobile camera capture; 834/834 backend tests (19 Imaging-specific) + 637/637 frontend Vitest tests green, `vue-tsc`/ESLint/Pint/Prettier clean, permanent E2E suite confirmed 27/27 green on GitHub Actions (`workflow_dispatch` run `30310705267`, after fixing 3 real PHPStan errors and 3 real E2E selector bugs CI's own runs surfaced — see `TECH_DEBT.md`); see `modules/imaging-design.md` |
| **Reports** | **Done — Production Ready ✅**, merged to `main` via PR #7 (2026-07-28) — six reports (Production, Collections, A/R Aging, Appointment Analytics, Treatment Plan Acceptance, New Patients), no new tables (`ReportService` reads existing data live), financial reports admin-only via Gate abilities, CSV export (no PDF/scheduling), `DashboardService.monthly_revenue` wired to real data; 855/855 backend tests (21 Reports-specific) + 652/652 frontend Vitest tests green, `vue-tsc`/ESLint/Pint/Prettier clean, permanent E2E suite confirmed 29/29 green on GitHub Actions (`workflow_dispatch` run `30326106755`, after fixing 4 real PHPStan errors and a real shared `AppSidebarItem.vue` nav-gating bug CI's own runs surfaced — see `TECH_DEBT.md`); see `modules/reports-design.md` |
| **Settings** | **Done — Production Ready ✅**, merged to `main` via PR #8 (2026-07-30) — Practice Settings (`ClinicSetting`, wired into the Lab Case printable slip), Billing Settings (first-ever API/UI for the existing `BillingSetting` table), My Account self-service (profile + current-password-gated password change, reachable from the header avatar menu); 877/877 backend tests (22 Settings-specific) + 672/672 frontend Vitest tests green (one pre-existing `AppSidebar.test.ts` gap found and fixed — its mock router had no `settings` route), `vue-tsc`/ESLint/Pint/Prettier clean, permanent E2E suite confirmed 32/32 green on GitHub Actions (`workflow_dispatch` run `30562178951`, after fixing a Prettier formatting issue and a wrong E2E assertion string CI's own run surfaced — see `TECH_DEBT.md`); see `modules/settings-design.md` |

## Post-roadmap: Frontend UX & Navigation Redesign

With every module above Production Ready ✅, focus shifted to a cross-cutting, frontend-only
navigation/UX redesign — see `modules/frontend-ux-redesign.md` for the original 4-phase plan.
**`modules/frontend-visual-redesign-design.md` (Premium Visual Redesign) amends and supersedes that plan's
Phase 2 (Dashboard)** and pulls pieces of Phase 4 forward — see that doc for the current, authoritative
scope.

| Phase | Status |
|---|---|
| **1. Navigation Shell** | **Done — Production Ready ✅**, merged to `main` via PR #10 (2026-08-01, merge commit `f45110a`) — Sidebar (collapsible sections, Favorites, Recent Items, true recursive nesting), Header (breadcrumbs, global search entry point), Command Palette (`Ctrl+K`), app-wide keyboard shortcuts (`?`, `g`-then-X chords), and a real Billing sidebar destination (`GET /invoices`, one small approved backend exception to the frontend-only scope). 913/913 backend tests (8 new) + 751/751 frontend Vitest tests (30 new), `vue-tsc -b`/ESLint/Prettier clean, permanent E2E suite confirmed 39/39 green on GitHub Actions (`workflow_dispatch` run `30629204011`, after fixing a `vue-tsc -b` build-only type error and three real E2E bugs CI's own runs surfaced — see `TECH_DEBT.md`) |
| **Premium Visual Redesign, Steps 1-3** | Merged to `main` via PR #13 (2026-08-02) — design tokens, Sidebar redesign + partial PrimeIcons→Lucide icon migration, Header/Command Palette polish. 928/928 backend + 755/755 frontend tests green. |
| Remaining icon migration (~62 files) | Not started |
| Dashboard redesign (Premium doc §6) | **Superseded 2026-08-09** — implemented as part of roadmap Phase 3 (Dashboard 2.0), see `docs/modules/dashboard-2.0-design.md` and `docs/PROJECT_STATUS.md` |
| **3. Data Tables System** | Not started |
| **4. Cross-cutting Polish** | Partially absorbed into Premium Visual Redesign's per-step acceptance criteria |

## Post-roadmap: 8-Phase Product Roadmap (Stabilization → Launch)

A second, larger post-roadmap initiative — Stabilization & Integration → Patient Profile Redesign →
Dashboard 2.0 → Advanced Permissions & Audit → **Notification System** → SaaS Multi-Tenant Prep → PWA &
Mobile → AI Assistant Expansion → Launch Preparation — is tracked in **`docs/PROJECT_STATUS.md`** (the
living status book), not duplicated here. **Phase 1: Stabilization closed 2026-08-07** via PR #15 (+ PR #14)
— restored a fully green `main` (CI's E2E job had been red since 2026-08-01), fixed the
`Dashboard.today_appointments` bug, and closed a loading/empty-state/error-handling consistency pass.
**Phases 2-4 closed 2026-08-09** (PR #34/#35/#37). **Phase 5: Notification System (Phases A + B) closed
2026-08-11** via **PR #39** (merge commit `204194f`): a real in-app Notification Center (Laravel's
own notifications table + 4 additive columns, a second listener on the existing `PatientActivityOccurred`
event so no new dispatch call sites were needed, an 8-of-24 event-type allow-list, three authorization
layers, keys-not-text localization across en/ar/tr), plus the queue worker and scheduler containers that
had never existed despite `QUEUE_CONNECTION=redis` being configured since the project's first `.env`. A
pre-PR review found and fixed 5 further issues (2 marked SECURITY: a notification-store leak across
logout/login, and a dentist's password hash/patient PHI reaching the Redis queue payload), and a pre-merge
`workflow_dispatch` E2E gate found and fixed 2 more genuinely real, pre-existing bugs (an
E2E fixture assumption, and the E2E job never starting a queue worker) — full detail in `CHANGELOG.md`.
Post-merge CI on `main` fully green: Backend, Frontend, **and the real E2E run (56/57 passed, 1
flaky-then-passed)** — the first time this module's E2E suite has been genuinely observed passing.
**Milestone: "Phase 5 — Notification System Complete."** **Phase 5C (scheduled/administrative
notifications — types 9-13) implemented 2026-08-12**, not yet merged to `main` — a real in-app addition
(3 scheduled Commands, a reactive `AuditLogObserver`, no schema change), plus a real infrastructure fix
(D14: `config('app.timezone')` now `env()`-driven, set to the clinic's actual IANA zone, closing a latent
gap the schema had never needed to expose before this phase) — see
`docs/modules/notifications-phase-c-d-design.md` §19-20 for the full account, including 3 real bugs found
and fixed during implementation. **Phase D (email) remains designed but not started**, deferred to its own
future approval cycle by explicit user decision (D12) — needs a mailer/vendor choice this file
deliberately doesn't make. Web Push is deferred to the PWA phase and patient-facing SMS/WhatsApp reminders
to their own future module — see `docs/modules/notifications-design.md` §13 and `TECH_DEBT.md`. See
`docs/PROJECT_STATUS.md` §3/§11/§12 for current phase status and next steps.

Full module list and scope boundaries are defined in `PROJECT_CONTEXT.md`.
