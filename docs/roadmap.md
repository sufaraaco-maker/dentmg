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
| **Appointments** | **In Progress** — Backend API done (188/188 tests, see `modules/appointments-design-draft.md`); frontend data-layer infrastructure (types/stores/services/routes/i18n) and app shell done; UI screens (Calendar, Dialog, Detail, Working Hours, Types CRUD, Dashboard widgets) in progress per `modules/appointments-ui-design.md` §20 Implementation Sequence |
| Dental Chart | Not started |
| Treatment Plans | Not started |
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
