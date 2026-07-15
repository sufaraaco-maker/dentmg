# Database Design

## Engine

PostgreSQL 17, run via Docker. No `doctrine/dbal` — Laravel 11+ handles `->change()` natively on Postgres.

## Conventions (apply to every table going forward)

- **Primary key**: `uuid`, via the `HasUuids` trait on the model — not `bigint` auto-increment. Required explicitly by `PROJECT_CONTEXT.md`.
- **Soft deletes**: every table that represents a real-world record a clinic would need to recover (users, patients, appointments, etc.) gets `deleted_at` + the `SoftDeletes` trait. Lookup/pivot tables are the exception.
- **Timestamps**: standard `created_at`/`updated_at` on every table.
- **Foreign keys**: `foreignUuid('x_id')->constrained()` — never nullable unless the relationship is genuinely optional.
- **Enums**: small, fixed, unlikely-to-need-metadata value sets (e.g. `role`) are a `string` column cast to a PHP backed enum — not a separate lookup table. If a set needs to grow dynamically or carry extra attributes, use a real table instead.

## Current Schema

### `users`

| Column | Type | Notes |
|---|---|---|
| `id` | uuid | PK |
| `name` | string | |
| `email` | string | unique |
| `email_verified_at` | timestamp | nullable, unused in V1 (no self-registration) |
| `password` | string | hashed |
| `role` | string | cast to `App\Enums\UserRole` (`admin`, `dentist`, `receptionist`) |
| `remember_token` | string | nullable |
| `deleted_at` | timestamp | nullable — soft delete |
| `created_at` / `updated_at` | timestamp | |

`sessions.user_id` is also `uuid` to match.

## Known Gaps

- **Audit logs**: documented in `PROJECT_CONTEXT.md`, not yet implemented. First candidate module: Patients or Billing (both touch sensitive data). See [decisions.md](decisions.md).
- **Multi-branch**: documented, not implemented. No table is branch-scoped yet.

Per-module schema detail lives in [modules/](modules/).
