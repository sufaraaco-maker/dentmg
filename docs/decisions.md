# Architectural Decisions

Chronological log. Entries before this file existed are backfilled from `ARCHITECTURE_REVIEW.md` (2026-07-11) to keep a single source of truth going forward.

## 2026-07-11 — Sanctum SPA (cookie) auth over API tokens

Frontend (`:5173`) and backend (`:8000`) are first-party, same-site in dev — the standard Laravel-recommended setup for this case. Avoids storing tokens in `localStorage`.
**Status**: Agreed with user.

## 2026-07-11 — UUID primary keys on every table

Required explicitly by `PROJECT_CONTEXT.md`. Converted `users`/`sessions` before any other table depended on `bigint` IDs, since retrofitting later is more expensive.
**Status**: Agreed with user.

## 2026-07-11 — Roles as a PHP backed enum, not `roles`/`permissions` tables

Single organization, no multi-tenancy in V1, three roles (`admin`, `dentist`, `receptionist`) cover the real need. Avoids pulling in `spatie/laravel-permission` per the "no unnecessary packages / prefer Laravel native" rule. Revisit only if granular, per-module custom permissions become a real requirement.
**Status**: Agreed with user.

## 2026-07-11 — Soft deletes on `users`, and as the default for future record-bearing tables

`PROJECT_CONTEXT.md` states "Soft Deletes" as a database principle. Implemented without a separate confirmation round at the time — justified directly by the document's explicit text rather than treated as a new architectural choice.
**Status**: Implemented, consistent with documented architecture (not treated as a deviation).

## 2026-07-11 — `JsonResource::withoutWrapping()` enabled globally

Makes single-resource Auth/Users responses match the already-unwrapped Dashboard response shape. Affects every future module's API shape.
**Status**: Implemented by the assistant; flagged for review in `ARCHITECTURE_REVIEW.md`, not yet explicitly re-confirmed by user. Carried forward as current convention in [api-guidelines.md](api-guidelines.md) unless overridden.

## 2026-07-11 — Rate limiting on `/api/login`

Baseline brute-force protection, implemented with Laravel's native `RateLimiter` facade (5 attempts/60s per email+IP). Not explicitly requested but treated as a non-negotiable security baseline rather than an optional feature.
**Status**: Implemented by the assistant as a security default.

## 2026-07-11 — Audit logs and Multi-branch deferred

Both are named in `PROJECT_CONTEXT.md`'s Database section but not implemented yet. Deferred deliberately until a module has a concrete need (Patients/Billing for audit logs; a real second-location requirement for multi-branch) rather than built speculatively.
**Status**: Open — revisit at the start of the Patients module (see entry below).

## 2026-07-14 — Patients module: field scope, audit logging, patient code, no photo upload

Discussed with user via explicit options before implementation:

- **Field scope**: standard clinical intake (demographics, national ID, emergency contact, blood type, allergies, medical history, insurance) — not minimal, not full/extended.
- **Audit logging**: implemented now (not deferred further) since Patients is the first module handling sensitive PII/clinical data. Built as a generic, reusable mechanism (`Auditable` trait + `AuditObserver` + `AuditLog` model) rather than Patient-specific, so it costs nothing extra for future modules to opt in.
- **`patient_code`**: added (`P-00001` style), generated from an internal `sequence_number` column, not the UUID primary key.
- **Photo/avatar upload**: deferred to the future Imaging module, which will need file storage (Local/S3) wired up properly anyway — avoids building storage plumbing twice.

**Status**: Agreed with user.

## 2026-07-14 — Patient policy: front-desk (admin/receptionist) write access, dentist read-only

Registering/editing patient demographic and administrative data is front-desk work. Dentists can view but not edit patient records through this module; clinical write access (updating allergies/history during a visit) is left for the future Clinical Notes module rather than opened up here. Delete restricted to admin only, mirroring the Users module.
**Status**: Assistant judgment call, flagged explicitly to user during planning; not explicitly re-confirmed. Revisit if it doesn't match real clinic workflow.

## 2026-07-14 — Two infrastructure bugs found and fixed during Patients verification

Manual end-to-end testing against the real Docker/Postgres stack (beyond the SQLite-backed automated test suite) surfaced two pre-existing bugs, unrelated to Patients specifically:

1. Guest requests to any protected `/api/*` endpoint without an `Accept: application/json` header crashed with a 500 (Laravel's default guest-redirect targets a `route('login')` that doesn't exist in this API-only app). Invisible to the test suite because `postJson()`/`getJson()` and axios both set that header automatically. Fixed in `bootstrap/app.php`.
2. `storage/logs/laravel.log` was unwritable by the `www-data` php-fpm worker (root-owned bind-mounted volume), so no exception had ever actually been logged since the project's inception. Fixed in `docker/php/entrypoint.sh`.

**Status**: Fixed as part of this module rather than deferred, since both are small, unambiguous bug fixes (not architecture changes) with a real security/observability impact. See `docs/modules/patients.md` for detail and the regression test (`tests/Feature/ApiExceptionHandlingTest.php`).

## 2026-07-15 — Final Patients module review: case-insensitive search fix, trigram indexes, Larastan

Requested by user before starting Appointments: UX review, DB scaling review, API consistency review, and installing PHPStan.

- **Case-sensitive search bug (Postgres) found and fixed**: `PatientService`/`UserService` built search queries with `where($col, 'like', "%$term%")`. SQLite's `LIKE` is case-insensitive by default, so the test suite (SQLite) never caught that Postgres's `LIKE` is case-sensitive by default — a receptionist searching "layla" would find nothing for a patient named "Layla" in the real database. Confirmed via direct query against the dev Postgres instance, then fixed using Laravel's cross-database `whereLike()`/`orWhereLike()` (case-insensitive by default, native since Laravel 11, no extra package). Fixed in both `PatientService` and `UserService` — the bug was in a pattern copied from Users into Patients, so leaving Users unfixed would reintroduce the same defect. Added a regression test, `test_patient_search_is_case_insensitive`.
- **Trigram search indexes added** (`pg_trgm`, Postgres-only, guarded in the migration by driver check): the search queries use leading-wildcard `LIKE '%term%'`, which a standard B-tree index cannot serve — without this, search degrades to a full table scan as the patient list grows. `pg_trgm` is a built-in Postgres contrib extension (not a third-party package), confirmed available and the DB user has privilege to enable it. SQLite (tests) doesn't need or support this, so the migration no-ops there.
- **Larastan (PHPStan for Laravel) installed**, per explicit user request and `PROJECT_CONTEXT.md`'s existing requirement. See `phpstan.neon` for the configured level and rationale.

**Status**: Reviewed and implemented per explicit user request for a final pre-Appointments review.

## 2026-07-16 — Design system: Google Fonts CDN over self-hosted `@fontsource` packages

Presented both options (self-hosted npm packages, offline-safe, vs. Google Fonts `<link>`, simpler setup but
an external network dependency on every page load). User explicitly chose the Google Fonts CDN link,
trading offline-safety for setup simplicity.
**Status**: Agreed with user. Revisit if DentalSuite ever needs a fully offline/on-prem deployment — swapping
to self-hosted fonts at that point requires only a `<link>`→`@font-face` change, no other code changes.

## 2026-07-16 — FullCalendar: MIT packages only, Dentists resource view deferred

While installing the Appointments Calendar Board's FullCalendar dependencies (per the frontend design doc's
§15a/"New Dependencies" section), discovered by downloading and reading the actual package `LICENSE.md`
files (not just trusting the npm registry's `license` field) that `@fullcalendar/resource` and
`@fullcalendar/resource-timegrid` — needed only for the "Dentists" resource-column view — are **not** MIT.
Both are FullCalendar Premium, tri-licensed as (a) a paid Commercial License, (b) Creative Commons
BY-NC-ND (non-commercial use only, not viable for a commercial SaaS product), or (c) GPLv3 (copyleft,
incompatible with a closed-source commercial codebase). The rest of the FullCalendar set actually used
(`core`/`vue3`/`daygrid`/`timegrid`/`interaction`) is confirmed MIT.

Presented three options to the user: (a) purchase a FullCalendar Premium commercial license and build the
Dentists view as designed, (b) drop the Dentists resource-column view from V1 (Day/Week/Month/List plus the
existing Dentist filter already cover "see everyone at once"), or (c) build a custom, non-premium
side-by-side day view. User chose **(b)** — continue with the MIT set only, no Premium dependency, no custom
workaround either. The Dentists view is deferred until a commercial-license purchase is separately evaluated
and approved.

**Status**: Agreed with user, confirmed as a permanent decision unless explicitly revisited. See
`docs/modules/appointments-ui-design.md` §20 ("FullCalendar decision") and its closing summary item 8.

## 2026-07-14 — Documentation restructured to project root

Moved `backend/docs/modules/*.md` → `docs/modules/`, and added `docs/architecture.md`, `docs/database-design.md`, `docs/api-guidelines.md`, `docs/coding-standards.md`, `docs/roadmap.md`, `docs/deployment.md`, plus root `CHANGELOG.md` and `TECH_DEBT.md`. Root-level docs cover both `backend/` and `frontend/`, so they don't belong nested under `backend/`. No content was lost — module docs were moved, not rewritten.
**Status**: Housekeeping, not a product/architecture change — proceeding without a separate approval round.
