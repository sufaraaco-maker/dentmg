# Changelog

All notable changes to DentalSuite are documented here. Format is chronological, grouped by module.

## Unreleased

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
