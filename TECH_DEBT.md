# Technical Debt

Postponed work, tracked deliberately rather than forgotten. Each item names the module it affects and the condition under which it should be revisited.

## Open

### Header notifications is inert UI (no notification backend)
`AppHeader.vue`'s bell icon opens a popover that always reads "No notifications yet" — there is no
notification system in the backend. Scaffolded deliberately as inert UI (per the layout architecture design,
`docs/modules/layout-architecture.md`) rather than wired to fake data, so the header only needs a data source
filled in later, not a redesign.
**Revisit**: when a real notification system exists on the backend.

### `AppSidebarItem.vue` supports only one level of nested children
`config/navigation.ts`'s `NavItem.children` type technically allows arbitrary nesting, but the renderer
(`AppSidebarItem.vue`) only renders one level (used today only by "Appointments" → Calendar/Types/Working
Hours). Deliberately not built recursive since no current or near-term IA item needs a second level, and
premature recursion would add complexity with no current benefit.
**Revisit**: only if a future module's navigation genuinely needs two levels of nesting; extend
`AppSidebarItem.vue` to render itself recursively at that point.

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

## Open (new from Appointments Phase 2 Step 1 — Infrastructure, 2026-07-16)

### Pre-existing Patients/Users files are not yet ESLint/Prettier-clean
Introducing ESLint + Prettier this step (see Resolved section below) surfaced pre-existing issues in files this step didn't touch: `PatientFormDialog.vue` and `UsersView.vue` each have one `@typescript-eslint/no-explicit-any` error (`catch (err: any)`), `PatientsView.vue` has one `vue/attributes-order` warning, and a handful of files (`PatientFormDialog.vue`, `DefaultLayout.vue`, `style.css`, `LoginView.vue`, `PatientDetailView.vue`, `PatientsView.vue`) have minor Prettier formatting drift. Deliberately **not** fixed as part of this infrastructure step — bundling unrelated formatting/lint fixes into a feature commit would obscure the actual diff being reviewed (every new/touched Appointments file in this step is already lint- and format-clean, verified via `npx eslint .` and `npx prettier --check src/`).
**Revisit**: a small, dedicated, easy-to-review follow-up pass — `npm run lint` + `npm run format` across the whole `frontend/src/` tree — whenever convenient. Purely mechanical, low risk.

### `providers.ts` store filename doesn't match the design doc's suggested `providers.store.ts`
`docs/modules/appointments-ui-design.md` §10.2 (written per explicit user instruction) names the file `providers.store.ts`. Implemented as `frontend/src/stores/providers.ts` instead, for consistency with every other store in the directory (`auth.ts`, `ui.ts`, `calendar.ts`, `appointments.ts`, `appointmentTypes.ts`, `workingHours.ts`, `timeOff.ts` — none use a `.store.ts` suffix). The store's actual intent (temporary, explicitly documented as not a permanent domain model, §10.2) is preserved via a doc-comment in the file itself.
**Revisit**: not a real gap — purely a naming note so the design doc and implementation aren't read as silently contradicting each other. No action needed unless the project later adopts a `.store.ts` suffix convention project-wide.

## Open (new from Design System / Visual Polish pass, 2026-07-16)

### `style.css` still doesn't import Tailwind's full `preflight.css`
This pass fixed the two gaps that actually surfaced visually (missing `box-sizing: border-box`, native
`<button>` chrome) with a minimal, explicitly-scoped reset inside the `tailwind-base` cascade layer — not a
full preflight import, to avoid it out-ranking PrimeVue's own component base styles. No other raw-HTML-element
gaps (headings, lists, forms, tables) surfaced during manual review, but a full preflight audit wasn't
performed. See `docs/design-system.md` §7.
**Revisit**: only if a future raw (non-PrimeVue) element shows unexpected native browser styling.

### Docker Desktop bind-mount + Vite dev server: edits sometimes require a container restart
While verifying this pass's changes, the frontend dev server (`dentalsuite_frontend`, Windows host →
Docker Desktop → Linux container bind mount) intermittently kept serving a stale compiled module for an
edited `.vue`/`.ts` file — confirmed by comparing the file Vite's own transform endpoint returned (fresh) against
what the running page actually rendered (stale), with a `docker compose restart node` reliably fixing it. Very
likely a known class of Docker Desktop issue (inotify file-change events not propagating reliably across the
Windows↔WSL2↔container boundary for bind mounts), not an application bug.
**Revisit**: not urgent — a workaround (`docker compose restart node`) exists. Only worth investigating
further (e.g. switching to polling-based file watching in `vite.config.ts`) if it starts noticeably slowing
down day-to-day frontend development.

## Resolved

### Frontend had no linting/formatting configuration (resolved 2026-07-16)
Added ESLint (flat config, `eslint-plugin-vue` + `@vue/eslint-config-typescript` + `@vue/eslint-config-prettier`) and Prettier (`semi: false, singleQuote: true, printWidth: 110, trailingComma: "all"`, matching the codebase's existing style exactly) as permanent frontend tooling, alongside the Vitest toolchain added in the same step. `npm run lint` / `npm run format` scripts added. See the note above re: pre-existing files not yet reformatted.

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
