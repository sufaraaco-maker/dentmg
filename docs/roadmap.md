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
| **Treatment Plans** | Design approved 2026-07-22 (`modules/treatment-plans-design.md`) — implementation starting, one checkpointed step at a time |
| Clinical Notes | Not started |
| Billing | Not started |
| Payments | Not started |
| Inventory | Not started |
| Laboratory | Not started |
| Imaging | Not started |
| Reports | Not started |
| Settings | Not started |
| AI Assistant | Not started (optional, assistive only — see `PROJECT_CONTEXT.md`) |

Full module list and scope boundaries are defined in `PROJECT_CONTEXT.md`.
