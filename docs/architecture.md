# Architecture

## Style

- Modular Monolith — one Laravel app, one Vue SPA, modules organized by domain (not microservices).
- Clean Architecture layering, enforced per module:
  - **Migration** — schema.
  - **Model** — Eloquent, relationships, casts. No business logic beyond simple accessors.
  - **Form Request** — validation + per-action authorization gate (`authorize()` delegates to the Policy).
  - **Service** — business logic. Controllers never contain logic beyond orchestration.
  - **Policy** — the single point of authorization truth for a model.
  - **Controller** — thin. Resolves the request, calls the service, returns a Resource.
  - **API Resource** — response shape.
  - **Vue Pages/Components** — consume the API, no direct business rules.
- API First: backend is a JSON API consumed by the Vue SPA over the same contract any future client would use.

## Repository Layout

```
DentalSuite/
├── backend/     Laravel 12 API (PHP 8.4, via Docker)
├── frontend/    Vue 3 + TypeScript + PrimeVue + Tailwind
├── docker/      Dockerfiles + nginx config
└── docs/        This documentation set
```

### Backend (`backend/app/`)

```
Enums/            Backed enums (e.g. UserRole) — used instead of lookup tables where the set of values is small and fixed
Http/Controllers/Api/   Thin controllers
Http/Requests/{Module}/ Store/Update form requests
Http/Resources/         API response transformers
Models/                 Eloquent models
Policies/                Authorization
Services/                Business logic
```

### Frontend (`frontend/src/`)

```
lib/          axios instance, shared API helpers
stores/       Pinia stores (one per module as needed, e.g. auth, ui, appointments)
services/     API Services layer, per module as needed (e.g. services/appointments/*Api.ts) —
              typed request/response shaping + error normalization between a module's stores
              and lib/api.ts; introduced by Appointments (see modules/appointments-ui-design.md
              §11.1), not used by Patients/Users, which call lib/api.ts directly instead
router/       route table + auth guard (meta.roles + router.beforeEach → ForbiddenView.vue)
layouts/      shared page chrome (DefaultLayout.vue — sidebar + header shell)
components/   layout/ (AppSidebar, AppHeader, AppSidebarItem) + per-feature components
views/        route-level pages
types/        shared TS types
locales/      ar/en/tr translation files
```

Design tokens (typography, color, spacing, radius, elevation) are centralized in `frontend/src/style.css`
(Tailwind v4 `@theme`) and the PrimeVue preset in `frontend/src/main.ts` (`definePreset(Aura, ...)`) — see
[design-system.md](design-system.md) for the full reference. Views/components consume tokens via Tailwind
utilities and PrimeVue's semantic classes (`surface-*`, component defaults); they never hardcode a color, font,
radius, or shadow value directly.

## Backend/Frontend Contract

- REST-ish JSON over `/api/*`.
- `JsonResource::withoutWrapping()` is enabled globally — single-resource responses are unwrapped (`{ "id": ... }`, not `{ "data": { ... } }`). Paginated collections keep Laravel's standard `{data, links, meta}` envelope since that shape is structural, not stylistic.
- Auth: Laravel Sanctum SPA (cookie/session), not API tokens — frontend and backend are first-party, same-site (different ports only) in dev.
- Errors follow Laravel conventions: `422` + `errors: {field: [...]}`, `403` + `message`, `401` + `message: "Unauthenticated."`.
- **Conflict responses** (introduced by Appointments — see [api-guidelines.md](api-guidelines.md)): `409` (or `422`, for non-conflict business-rule failures) + `{ message, code, overridable?, override_field? }`. `code` is the stable machine-readable identifier the frontend switches on (never parses `message`); `overridable`/`override_field` are present only when the conflict can be resubmitted with an explicit override flag (e.g. `patient_conflict`), absent for hard blocks (e.g. `dentist_conflict`).

## Cross-Cutting Concerns — Status

| Concern | Status |
|---|---|
| UUID primary keys | Implemented (`HasUuids` on all models) |
| Soft deletes | Implemented (`users`; standard for every future module unless a model has a specific reason not to) |
| Audit logs | Implemented (Patients module, 2026-07-14) — generic `Auditable` trait + `AuditObserver` + `AuditLog` model, opt-in per model, costs nothing extra for a future module to adopt. `Appointment` already uses the trait too, but no API route exposes Appointment audit logs yet (Patients' `GET /api/patients/{patient}/audit-logs` has no Appointments equivalent — see [TECH_DEBT.md](../TECH_DEBT.md)) |
| Multi-branch | Not implemented — deferred until a real need appears (see [decisions.md](decisions.md)) |

See [modules/](modules/) for per-module detail and [decisions.md](decisions.md) for the reasoning behind each choice above.
