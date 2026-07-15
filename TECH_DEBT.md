# Technical Debt

Postponed work, tracked deliberately rather than forgotten. Each item names the module it affects and the condition under which it should be revisited.

## Open

### `national_id` uniqueness survives soft delete
A soft-deleted patient's `national_id` can't be reused by a newly-registered patient, because the DB-level unique constraint doesn't exclude soft-deleted rows. Simpler than scoping the constraint, and arguably safer for data integrity, but worth knowing.
**Revisit**: only if re-registering a previously-deleted patient with the same national ID becomes a real workflow need.

### Multi-branch
Documented in `PROJECT_CONTEXT.md`, not implemented. No table is branch-scoped.
**Revisit**: when a real second-location requirement appears. Do not build speculatively.

### `JsonResource::withoutWrapping()` — global convention, not re-confirmed with user
Applied globally by the assistant during the Authentication module to keep response shapes consistent; flagged in `ARCHITECTURE_REVIEW.md` for review but not explicitly re-approved since.
**Revisit**: low priority — only if a future need for a structured envelope (e.g. API versioning metadata) arises.

## Open (new from Appointments frontend design review, 2026-07-16)

### No dedicated dentists/providers listing endpoint
`GET /api/users` is paginated at a fixed 15/page with no `role` filter and the controller doesn't forward a `per_page` query param to `UserService::paginate()`. The Appointments frontend needs a full list of dentists for filters/dropdowns (Calendar filters, Appointment dialog, Working Hours/Time Off dentist selector), so `frontend/src/stores/providers.store.ts` works around this by paginating through every page of `GET /api/users` once per session and filtering `role === 'dentist'` client-side — acceptable at realistic clinic-staff scale (a handful of requests, once, cached indefinitely), but a real workaround, not the intended shape.
**Revisit**: add a dedicated `GET /api/dentists` (or `/api/providers`, matching wherever the future Providers module lands — see `docs/modules/appointments-ui-design.md` §4 "Dentist/Provider Store") endpoint, or at minimum a `?role=` filter plus an uncapped `per_page` on the existing `GET /api/users` for this specific staff-directory use case. Low risk, small change — do it whenever backend capacity allows; not blocking.

### Appointment mutation endpoints don't eager-load relations
`AppointmentResource` responses from `store`/`confirm`/`check-in`/`start`/`complete`/`cancel`/`no-show`/`update` don't eager-load `patient`/`dentist`/`appointment_type` (only `index`/`show` do), so the frontend's `appointments.store.ts` issues a follow-up `GET /api/appointments/{id}` after every mutation to re-hydrate those nested fields before updating its cache — see `docs/modules/appointments-ui-design.md` §11 "Post-Mutation Rehydration."
**Revisit**: if these controller actions eager-load the same three relations before returning their `AppointmentResource` (mirroring what `index`/`show` already do), the frontend's extra round-trip can be deleted entirely. Small, low-risk backend change; not blocking initial implementation.

## Open (new from system validation checkpoint, 2026-07-16)

### Frontend has no linting configuration
Confirmed during this checkpoint: `frontend/package.json` has no `lint` script and no ESLint/Prettier config files exist anywhere in `frontend/`. Type-safety is covered by `vue-tsc` (part of `npm run build`), but there's no automated enforcement of code-style/best-practice rules (unused imports beyond what `noUnusedLocals` catches, Vue-specific lint rules, accessibility lint rules, etc.).
**Revisit**: add ESLint (`eslint-plugin-vue`, `@vue/eslint-config-typescript`) + Prettier, matching the rigor already applied to the backend (Pint). Reasonable to introduce alongside the Appointments module's new Vitest toolchain (`docs/modules/appointments-ui-design.md`, "New Dependencies") rather than as a separate effort, since both are "add missing frontend tooling" work. Not blocking — `vue-tsc` and manual review have been sufficient so far.

## Resolved

### Appointment types had no default-data seeder (resolved 2026-07-16)
`docs/modules/appointments-design-draft.md` §4 stated appointment types should be seeded with a sensible default set on install; verified during the 2026-07-16 checkpoint that `GET /api/appointment-types` returned `[]` on a fresh install — no seeder existed. Fixed by adding `backend/database/seeders/AppointmentTypeSeeder.php` (Consultation/Cleaning/Filling/Root Canal/Crown/Extraction, each with a duration and color, `firstOrCreate`-based so it's safe to re-run), wired into `DatabaseSeeder`. Verified live against the real Postgres container after `migrate:fresh --seed`: `GET /api/appointment-types` now returns all 6 types. See `docs/demo-guide.md` §5 for the exact seeded values.

### Root `.gitignore` was incomplete prior to the first commit (resolved 2026-07-16)
The repository had no git history until the 2026-07-16 checkpoint. The root `.gitignore` present at that point only excluded OS/editor cruft (`.DS_Store`, `.idea`, etc.) — it did not exclude `backend/vendor/`, `frontend/node_modules/`, `.env` files, or Laravel's runtime `storage/`/`bootstrap/cache/` artifacts at the root level. In practice this was harmless because `backend/.gitignore` and `frontend/.gitignore` (Laravel's and Vite's own scaffolded ignore files) already excluded those paths independently — but the root file was still incomplete on its own terms. Fixed by expanding the root `.gitignore` with explicit backend/frontend dependency, build-output, and secret-file exclusions before staging the first commit, as a belt-and-suspenders measure.
**Status**: resolved as part of that checkpoint's git initialization (commit `9a74ffb`).

### Audit logs (resolved 2026-07-14)
Implemented as generic infrastructure (`Auditable` trait + `AuditObserver` + `AuditLog` model) as part of the Patients module, since Patients was the first module to touch sensitive PII. See [docs/modules/patients.md](docs/modules/patients.md).

### Guest requests crashing without `Accept: application/json` (resolved 2026-07-14)
Fixed in `bootstrap/app.php`. See [docs/decisions.md](docs/decisions.md).

### `storage/logs/laravel.log` unwritable by php-fpm worker (resolved 2026-07-14)
Fixed in `docker/php/entrypoint.sh`. See [docs/decisions.md](docs/decisions.md).

### PHPStan not installed (resolved 2026-07-15)
Larastan (`larastan/larastan ^3.10`) installed at level 5, configured in `backend/phpstan.neon`. 0 errors after fixing the issues it found (see `docs/decisions.md`). Level 5 is a deliberate starting point, not a ceiling — see `phpstan.neon` comments.

### Patient/User search was case-sensitive on Postgres (resolved 2026-07-15)
Fixed by switching to Laravel's `whereLike()`/`orWhereLike()`. See [docs/decisions.md](docs/decisions.md).

### Patient search had no usable index for `LIKE '%term%'` queries (resolved 2026-07-15)
`pg_trgm` GIN indexes added on the searched columns (Postgres-only migration). See [docs/decisions.md](docs/decisions.md).

## Open (new from 2026-07-15 review)

### PHPStan level 5, not stricter
Chosen as a pragmatic starting point for a codebase with no prior static analysis (Larastan's own recommended default). Levels 6-9 add generic-type-hint strictness that would need broader retrofitting across the codebase.
**Revisit**: raise incrementally (6, then 7, ...) as new modules are built and the team has bandwidth to address the stricter findings — not urgent, no known bugs are being masked by staying at 5 today.
