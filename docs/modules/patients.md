# Patients Module

## Scope (V1)

Registering, searching, viewing, and editing patient records (standard clinical intake), soft delete, and a human-readable patient code. Avatar/photo upload is deferred to the future Imaging module (decision recorded in [decisions.md](../decisions.md)).

## Decision Log

- **Fields**: standard clinical intake — demographics, national ID, emergency contact, blood type, allergies, medical history notes, insurance info. Agreed with user before implementation (see [decisions.md](../decisions.md)).
- **`patient_code`**: a short human-readable identifier (`P-00001`) for front-desk reference, derived from an internal `sequence_number` column (not the primary key, which stays UUID per project convention). Generated inside a DB transaction using `orderByDesc('sequence_number')->lockForUpdate()->value(...)` rather than `MAX(...)` — PostgreSQL rejects `FOR UPDATE` combined with an aggregate function, so this locks the highest-numbered row instead of aggregating. Portable across SQLite (tests) and PostgreSQL (dev/prod).
- **Audit logging**: introduced as a generic, reusable mechanism (not Patient-specific) because this is the first module handling sensitive PII/clinical data. See "Audit Log Infrastructure" below.
- **Policy**: `viewAny`/`view` open to any authenticated staff member; `create`/`update` limited to `admin`/`receptionist` (front-desk data entry); `delete` restricted to `admin` (mirrors the Users module). Dentists get read-only access here — clinical write access during a visit belongs to the future Clinical Notes module, not this one.

## Audit Log Infrastructure

Built as a cross-cutting concern any future module can opt into, not specific to Patient:

| File | Role |
|---|---|
| `database/migrations/2026_07_14_000001_create_audit_logs_table.php` | `audit_logs`: UUID PK, nullable actor (`user_id`), polymorphic `auditable_type`/`auditable_id`, `action`, `changes` (JSON) |
| `app/Models/AuditLog.php` | Model — `belongsTo(User)`, `morphTo(auditable)` |
| `app/Models/Concerns/Auditable.php` | Trait — `use Auditable;` on any model registers `AuditObserver` and exposes `auditLogs()` |
| `app/Observers/AuditObserver.php` | Fires on `created`/`updated`/`deleted`, delegates to `AuditLogService` |
| `app/Services/AuditLogService.php` | Writes the log row; strips `password`/`remember_token`/timestamps from the recorded diff regardless of model |

`Patient` is the first model to `use Auditable`.

## Backend

| File | Role |
|---|---|
| `database/migrations/2026_07_14_000002_create_patients_table.php` | `patients` table — UUID PK, `sequence_number`, demographic/medical/insurance fields, soft deletes |
| `app/Enums/PatientGender.php`, `app/Enums/BloodType.php` | Backed enums, same pattern as `UserRole` |
| `app/Models/Patient.php` | `HasUuids`, `SoftDeletes`, `Auditable`; appends `patient_code` and `full_name` |
| `app/Http/Requests/Patient/{Store,Update}PatientRequest.php` | Validation + authorization via `PatientPolicy` |
| `app/Services/PatientService.php` | Pagination + search (name/phone/national ID/email), `patient_code` generation, create/update/delete |
| `app/Policies/PatientPolicy.php` | See Policy decision above; also gates `viewAuditLogs` (admin only) |
| `app/Http/Controllers/Api/PatientController.php` | Thin controller: index/store/show/update/destroy + `auditLogs` |
| `app/Http/Resources/PatientResource.php`, `AuditLogResource.php` | Response shapes |
| `tests/Feature/PatientTest.php` | Full CRUD, search, per-role authorization, audit log creation/content, audit log access control |

## Infrastructure Fixes Found During Verification

Manual end-to-end verification against the real Docker/Postgres stack (not just the SQLite-backed test suite) surfaced two pre-existing issues unrelated to Patients specifically, fixed as part of this module:

1. **Guest requests without `Accept: application/json` crashed with 500** instead of a clean 401. Laravel's `ApplicationBuilder::withMiddleware()` registers a default guest-redirect to `route('login')`, which doesn't exist in this API-only app. Fixed in `bootstrap/app.php` (`redirectGuestsTo(fn () => null)` + `shouldRenderJsonWhen` for `/api/*`). Regression test: `tests/Feature/ApiExceptionHandlingTest.php`. This affected every protected endpoint (Users, Dashboard, Patients), not just this module — axios and PHPUnit's `postJson()`/`getJson()` both set that header automatically, which is why it was invisible until manual `curl` testing.
2. **`storage/logs/laravel.log` was unwritable by the `www-data` php-fpm worker** (root-owned from the bind-mounted volume), so exceptions were never actually logged — silently, compounding into secondary logging failures. Fixed in `docker/php/entrypoint.sh` (`chown -R www-data:www-data storage bootstrap/cache` on container start).

Both are logged in [decisions.md](../decisions.md) and [CHANGELOG.md](../../CHANGELOG.md).

## Frontend

| File | Role |
|---|---|
| `src/types/patient.ts` | `Patient`, `PatientAuditLog` types, gender/blood-type constants |
| `src/components/patients/PatientFormDialog.vue` | Shared create/edit dialog, used by both the list and detail views (avoids duplicating the form) |
| `src/views/PatientsView.vue` | List — search, create, inline edit/delete, row click → detail |
| `src/views/PatientDetailView.vue` | Full record view (demographics/contact/medical/insurance cards) + admin-only audit history panel |
| `src/router/index.ts` | `/patients`, `/patients/:id` |
| `src/layouts/DefaultLayout.vue` | Nav link |
| `src/locales/{ar,en,tr}.json` | `patients.*` keys |

## API

```
GET    /api/patients?search=...          (auth:sanctum, any role)
POST   /api/patients                     (auth:sanctum, admin/receptionist)
GET    /api/patients/{patient}           (auth:sanctum, any role)
PUT    /api/patients/{patient}           (auth:sanctum, admin/receptionist)
DELETE /api/patients/{patient}           (auth:sanctum, admin only)
GET    /api/patients/{patient}/audit-logs (auth:sanctum, admin only)
```

Response shape example:

```json
{
  "id": "uuid",
  "patient_code": "P-00001",
  "first_name": "Layla",
  "last_name": "Hassan",
  "full_name": "Layla Hassan",
  "date_of_birth": "1990-05-10",
  "gender": "female",
  "phone": "0501234567",
  "blood_type": "O+",
  "...": "..."
}
```

## Known Issues / Technical Debt

- `national_id` uniqueness is enforced at the DB level even across soft-deleted patients (a soft-deleted patient's national ID can't be reused). Acceptable for V1; revisit only if re-registration after a soft delete becomes a real workflow.
- Full interactive browser testing (clicking through the Vue UI) was not performed — verified via `vue-tsc` compilation (clean) and full backend API golden-path testing against real Postgres instead. Recommend a manual click-through before considering the module fully signed off.

## Final Review (2026-07-15)

Requested by user before starting Appointments: UX review, DB scaling review, API consistency review, and installing/configuring PHPStan. Findings and fixes:

- **Case-sensitive search bug (Postgres) — fixed.** `PatientService`/`UserService` used `where($col, 'like', ...)`; SQLite's `LIKE` is case-insensitive by default so the (SQLite-backed) test suite never caught that Postgres's isn't. Switched to Laravel's cross-database `whereLike()`/`orWhereLike()`. Fixed in both services since it was the same copied pattern in each. Regression test added.
- **No usable index for the search's leading-wildcard `LIKE '%term%'` — fixed.** Added `pg_trgm` GIN indexes on `first_name`/`last_name`/`phone`/`national_id`/`email` (Postgres-only migration, guarded by driver check; SQLite tests don't need it).
- **UX**: fixed a missing error toast on non-422 save failures (inconsistent with `UsersView`'s pattern — silent failure otherwise), fixed timezone-unsafe date-of-birth parsing/serialization (`new Date("yyyy-mm-dd")`/`.toISOString()` both go through UTC and can silently shift the date by a day in negative-UTC-offset timezones — now uses local-date-safe helpers in `src/lib/date.ts`), and capped the date-of-birth picker at today (matches the existing backend `before:today` rule, avoids a wasted submit-then-error round trip).
- **API consistency**: reviewed against `docs/api-guidelines.md` — consistent. Added a `@mixin` docblock to `UserResource` to match `PatientResource`/`AuditLogResource` (comment-only, no behavior change).
- **PHPStan (Larastan) installed and configured** at level 5 (`backend/phpstan.neon`). Found 13 real static-analysis errors, all traced to one root cause: Larastan's `parseModelCastsMethod` defaults to `false`, so it doesn't read Laravel 11+'s method-based `casts(): array` — every cast attribute (enums, dates) was seen as its raw string type, which cascaded into false "always false" warnings on legitimate code (`$user->role === UserRole::Admin`, the role checks in `PatientPolicy`). Fixed via config, not by changing the models. The remaining handful of findings were legitimate (unnecessary `?->` on non-nullable columns, a relation missing its generic type hint) and fixed properly. 0 errors after.

## Completion

Migration, Model, Validation, Service, Policy, API, Vue Pages, Tests, Documentation — all present. 47/47 backend tests passing, Pint clean, PHPStan (level 5) clean, `vue-tsc` clean.
