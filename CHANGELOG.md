# Changelog

All notable changes to DentalSuite are documented here. Format is chronological, grouped by module.

## Unreleased

### Added — Design System / Typography & Visual Polish
- Formalized the application shell's visual language into a documented design system
  (`docs/design-system.md`) — the shell is now considered **frozen** for all future modules.
- **Typography**: Inter (Latin) + IBM Plex Sans Arabic (Arabic, the app's default locale), loaded via Google
  Fonts (`index.html`), swapped by the existing `[dir]`-driven locale mechanism — no new JS. Replaces the
  previous generic system-font stack (`'Segoe UI'`/unsourced `'Cairo'` reference that had no actual font
  file behind it). `index.html`'s initial `lang`/`dir` now matches the default `ar` locale (no
  flash-of-wrong-direction before Vue mounts); `<title>` fixed from the Vite scaffold default `"frontend"`
  to `"DentalSuite"`.
- **Design tokens**: border-radius nudged via a PrimeVue Aura preset extension (`main.ts`'s
  `definePreset(Aura, ...)`: `md` 6px→8px, `xl` 12px→16px), `tabular-nums` utility for numeric/tabular data.
- **Visual polish**: sidebar active-item accent bar (`AppSidebarItem.vue`), dashboard stat cards upgraded
  from bare icons to tinted circular icon badges with a hover-lift shadow, sticky header gains `shadow-sm`,
  sidebar widened 256px→288px (`w-64`→`w-72`) to stop "Treatment Plans" truncating to "Treatment …".
- **Base CSS reset fix**: `style.css` imported Tailwind's `theme.css`/`utilities.css` but never
  `preflight.css`, so raw (non-PrimeVue) elements had no `box-sizing: border-box` and native `<button>`
  chrome (`border: 2px outset`) showed through on hand-rolled buttons like the sidebar's parent-toggle row.
  Fixed with a minimal, explicitly-scoped reset inside the `tailwind-base` cascade layer (not a full
  preflight import, so PrimeVue's and Tailwind utilities' own styling still wins where intended).
- **Bug fix**: a stray focus ring could stick to the sidebar's "Appointments" toggle immediately after
  login, because Vue's DOM patching reused the login submit `<button>`'s node across the Login→Dashboard
  route swap, carrying the browser's focus with it. Fixed with an explicit `blur()` in `LoginView.vue`
  after a successful login.
- **Bug fix**: `NotFoundView.vue` (404) was missing dark-mode text-color classes and hardcoded English text
  with no i18n, unlike the equivalent `ForbiddenView.vue` (403) it should mirror. Brought to parity; new
  `errors.pageNotFound.*` i18n keys added to `en`/`ar`/`tr`.
- Verified manually (headless-Chromium screenshots against the real `docker compose` stack) across
  Arabic/English × light/dark × desktop/tablet/mobile. `npm run build`, `vue-tsc`, `eslint`, and `vitest`
  (88/88) all pass.

### Added — Application Shell / Layout Architecture
- Replaced the informal top-nav `DefaultLayout.vue` with a permanent sidebar+header SaaS shell
  (`docs/modules/layout-architecture.md`, design approved 2026-07-16). New components under
  `frontend/src/components/layout/`: `AppSidebar.vue` (desktop-docked, collapsible icon rail, and — via a
  `variant: 'desktop' | 'drawer'` prop — reused unmodified inside the mobile PrimeVue `Drawer`, so nav markup
  and role-filtering logic are never duplicated), `AppHeader.vue` (hamburger on mobile, notifications
  popover, locale/theme toggles moved from the old header, user menu with logout), `AppSidebarItem.vue`
  (single nav row: active/disabled/coming-soon states, one level of expandable children).
- `frontend/src/config/navigation.ts` — configuration-driven single source of truth for the sidebar. Each
  entry declares `labelKey`/`icon`/optional `routeName`/optional `roles`/optional `comingSoon`. Unbuilt
  modules (Dental Chart, Treatment Plans, Billing, Reports, Settings) render visible but disabled with a
  "Soon" badge — no placeholder routes or fake pages. Adding a real module later is a two-line change.
- `stores/ui.ts` gained `sidebarCollapsed` (persisted to `localStorage`, same pattern as theme/locale) and
  `mobileSidebarOpen` + open/close actions for the mobile drawer.
- **Route-level authorization**, closing a pre-existing gap where hiding a nav item was the only thing
  stopping a non-admin from reaching `/users` by URL: `router/index.ts` adds a `RouteMeta.roles?: UserRole[]`
  module augmentation, `users` now carries `meta: { roles: ['admin'] }`, and `router.beforeEach` redirects to
  a new `forbidden` route when the authenticated user's role isn't allowed — centralized in the router, not
  duplicated per-view.
- `frontend/src/views/ForbiddenView.vue` (403) — new route `forbidden`.
- Full RTL support (logical Tailwind utilities throughout; the mobile `Drawer`'s slide edge follows locale
  direction) and dark-mode parity, verified in `ar`/`en` and both themes.
- `nav.*`/`common.*`/`errors.forbidden.*` i18n keys added to `en`/`ar`/`tr` (parity-verified).
- First round of **component-level tests** for the project (previously store/service-only): 24 new tests
  across `AppSidebarItem`, `AppSidebar` (role-based visibility, coming-soon rendering, mobile drawer close),
  `AppHeader` (hamburger opens the drawer), `stores/ui.ts`, and the router's role guard. `src/test/setup.ts`
  now globally registers the PrimeVue plugin, the `Tooltip` directive, and a `matchMedia` polyfill (jsdom
  doesn't implement it) — permanent test infra, not just for this module.

### Added — Appointments (Frontend Infrastructure, Phase 2 Step 1)
- `frontend/src/types/appointment.ts` — `Appointment`/`AppointmentType`/`DentistWorkingHour`/`DentistTimeOff`/conflict-error/payload types, matching every backend Resource/Request shape field-for-field (no `any`).
- New **API Services layer** (`frontend/src/services/appointments/`): `appointmentsApi`, `appointmentTypesApi`, `workingHoursApi`, `timeOffApi`, `providersApi`, plus `errors.ts` normalizing 409/422 `code` responses into a typed `AppointmentConflictError` (`docs/modules/appointments-ui-design.md` §11.1/§17) — the layer between the new Pinia stores and `lib/api.ts`.
- Six new Pinia stores: `appointments` (range cache with interval merge/eviction, post-mutation rehydration), `appointmentTypes`, `workingHours`, `timeOff` (per-dentist caches), `calendar` (pure UI state — view mode/date/filters, drives `appointments.fetchRange`), `providers` (temporary dentist-list workaround, explicitly documented as such per §10.2).
- `auth.ts` gained `isDentist`/`isReceptionist`/`canManageAppointments` getters.
- Routes + minimal placeholder views wired for `/appointments`, `/appointments/:id`, `/appointments/types`, `/appointments/schedule`; `nav.appointments` link added to `DefaultLayout.vue` (the i18n key already existed, unused until now). Static routes ordered before the `:id` wildcard.
- `appointments.*` i18n namespace added to `en`/`ar`/`tr` (parity-verified — 111 leaf keys each).
- **New permanent frontend toolchain**: Vitest + `@vue/test-utils` + jsdom + `@vitest/coverage-v8` (`vitest.config.ts`, kept separate from `vite.config.ts` — merging them hit a type conflict between this project's rolldown-based `vite` and the (different) `vite` Vitest bundles internally); ESLint (flat config, `eslint-plugin-vue` + `@vue/eslint-config-typescript` + `@vue/eslint-config-prettier`) and Prettier, matching the existing code style exactly. `npm run test`/`test:coverage`/`lint`/`format` scripts added.
- 64 new frontend tests (10 service/store files) covering the range-cache/eviction logic, post-mutation rehydration, conflict-error propagation, per-dentist caching, the provider pagination workaround, and the new auth getters.

### Added — Demo Environment (system validation checkpoint)
- `backend/database/seeders/AppointmentTypeSeeder.php` — 6 named default types (Consultation, Cleaning, Filling, Root Canal, Crown, Extraction) with realistic durations/colors, `firstOrCreate`-based so it's safe to re-run. Closes the gap where `GET /api/appointment-types` returned `[]` on a fresh install.
- `DatabaseSeeder` now seeds three clearly-named demo accounts (`admin@example.com`, `dentist@example.com`, `receptionist@example.com`, password `password`) plus 8 demo patients, replacing the single generic `test@example.com` admin account.
- `docs/demo-guide.md` — how to start the stack, login credentials, available screens, a recommended walkthrough, and the exact sample data seeded.
- Repository git history initialized (previously uncommitted since project inception); root `.gitignore` hardened for backend/frontend dependency and secret exclusions.

### Added — Appointments (API Layer)
- `AppointmentController`: `GET/POST /api/appointments`, `GET/PUT/DELETE /api/appointments/{id}`, plus six dedicated transition endpoints (`confirm`, `check-in`, `start`, `complete`, `cancel`, `no-show`) — no generic `/status` endpoint, matching the originally-approved design. `PUT /api/appointments/{id}` handles both plain edits and in-place reschedules; there's no separate reschedule endpoint. `GET /api/available-slots` is a top-level route.
- `AppointmentTypeController` (admin-only CRUD, any-role reads), `DentistWorkingHourController` (admin-only self-service), `DentistTimeOffController` (admin-any + dentist-own self-service) under `GET/POST/DELETE /api/dentists/{user}/working-hours` and `/time-off`.
- New `409`/`code`/`overridable` error shape for domain conflicts (`DentistConflictException` 409 hard block, `PatientConflictException` 409 soft/overridable, `OutsideWorkingHoursException`/`EarlyNoShowException`/`InvalidStatusTransitionException` 422) — each exception renders its own JSON response, documented in `docs/api-guidelines.md`.
- `AppointmentPolicy` gained `confirm`/`checkIn`/`start`/`complete` abilities, including the `start`/`complete` dentist-ownership IDOR check.
- `AppointmentService` gained `search()` (date-range-bounded list, not paginated) and `delete()`; new thin `AppointmentTypeService`, `DentistWorkingHourService`, `DentistTimeOffService`.
- 61 new Feature tests (`AppointmentTest`, `AppointmentTypeTest`, `DentistWorkingHourTest`, `DentistTimeOffTest`) — full suite now 188/188.

### Added — Patients
- Full CRUD for patient records (`/api/patients`): demographics, national ID, emergency contact, blood type, allergies, medical history, insurance info.
- Auto-generated human-readable `patient_code` (`P-00001`, ...).
- `GET /api/patients/{id}/audit-logs` (admin only) — who created/changed a patient record and what changed.
- `PatientsView.vue` (list, search, create/edit), `PatientDetailView.vue` (full record + audit history panel).
- Dashboard's `total_patients` stat now reflects real data (the counting logic already existed, waiting for this table).

### Added — Audit Logging (generic infrastructure)
- `audit_logs` table + `Auditable` trait + `AuditObserver` + `AuditLogService` — any model can opt in with `use Auditable;`. First adopter: `Patient`.

### Fixed
- Guest requests to protected `/api/*` endpoints without an `Accept: application/json` header (e.g. plain `curl`, misconfigured clients) crashed with a 500 instead of a 401 — Laravel's default guest-redirect targeted a non-existent `login` route. Fixed in `bootstrap/app.php`. Affected all protected endpoints (Users, Dashboard, Patients), not just this module.
- `storage/logs/laravel.log` was unwritable by the php-fpm worker due to root-owned bind-mounted volumes, so no exception had ever been logged. Fixed in `docker/php/entrypoint.sh`.
- **Patient/User search was case-sensitive on Postgres** (SQLite's `LIKE` is case-insensitive by default, masking this in the test suite) — a search for "layla" would not match "Layla" in the real database. Fixed using Laravel's cross-database `whereLike()`/`orWhereLike()` in both `PatientService` and `UserService`.
- Patient search had no usable database index for its leading-wildcard `LIKE '%term%'` queries — added `pg_trgm` GIN indexes (Postgres-only).
- Patient form: silent failure on non-422 save errors (no toast shown) — now consistent with the Users form.
- Patient form: date-of-birth could be off by a day in negative-UTC-offset timezones due to `Date`'s UTC-based ISO-string parsing — fixed with local-date-safe helpers.

### Changed
- Installed and configured Larastan (PHPStan for Laravel) at level 5, per `PROJECT_CONTEXT.md`'s coding standards requirement. Fixed all 13 issues it found (traced to one root cause: `parseModelCastsMethod` needed enabling for Laravel 11+'s method-based `casts()` to be inferred correctly — see `docs/decisions.md`).

### Documentation
- Established root-level `docs/` structure (`architecture.md`, `database-design.md`, `api-guidelines.md`, `coding-standards.md`, `decisions.md`, `roadmap.md`, `deployment.md`, `modules/`), plus `CHANGELOG.md` and `TECH_DEBT.md`. Migrated existing module docs from `backend/docs/modules/`.
- `docs/modules/patients.md`, including a Final Review section.

## 2026-07-11

### Added — Dashboard
- `GET /api/dashboard/summary` returning clinic-wide stats, gracefully defaulting to zero for tables that don't exist yet.

### Added — Authentication
- Sanctum SPA (cookie/session) authentication: `GET /sanctum/csrf-cookie`, `POST /api/login`, `POST /api/logout`, `GET /api/user`.
- Rate limiting on login (5 attempts/60s per email+IP).
- `users`/`sessions` primary keys converted to UUID.

### Added — Users
- Full CRUD for staff accounts (`/api/users`), search, soft delete, self-delete protection.
- `UsersView.vue` (PrimeVue DataTable + Dialog CRUD).

### Added — Roles & Permissions
- `UserRole` backed enum (`admin`, `dentist`, `receptionist`).
- `UserPolicy`: `create`/`update`/`delete` restricted to `admin`; `viewAny`/`view` open to any authenticated user.
