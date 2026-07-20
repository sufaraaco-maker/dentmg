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

### Appointment types have no `price` or `is_default` column
The original task brief for the Appointment Types screen (Phase 2 Step 7) called for a "Price" field and a
way to mark a type as default, but `appointment_types` has neither column — confirmed from the
migration/model, not assumed. Not implemented as a frontend-only field with nowhere real to persist it; see
`docs/modules/appointments-ui-design.md` §7 for the full reasoning.
**Revisit**: pricing-per-type belongs in the Billing module's design where pricing concepts actually live
(not bolted onto Appointments); "default type" is a small additive migration + a "set as default" action if
ever requested, but is still a backend change outside this frontend-only step's scope.

### `PatientAppointmentsPanel.vue` shows a bounded ±3/6-month window, not full history
The Patient Detail "Appointments" panel (Phase 2 Step 8, design doc §9) fetches the shared,
unfiltered `appointments.ts` range cache (the same technique `TimeOffFormDialog` already uses)
and filters client-side to the patient — 3 months back to 6 months forward from today. A patient's
appointment older than 3 months or booked further than 6 months out won't appear here. Deliberate:
`GET /api/appointments` has no `patient_id`-scoped, unbounded query (it's a required-date-range,
clinic-wide endpoint by design — see the backend design doc §16/§19), and an unfiltered fetch of a
much wider range would pull every patient's appointments in that span just to show one patient's
history.
**Revisit**: if a genuine "full appointment history" need appears, add a dedicated
`GET /api/patients/{patient}/appointments` backend endpoint (paginated, patient-scoped) rather than
widening this frontend range fetch.

### Multi-branch
Documented in `PROJECT_CONTEXT.md`, not implemented. No table is branch-scoped.
**Revisit**: when a real second-location requirement appears. Do not build speculatively.

### `JsonResource::withoutWrapping()` — global convention, not re-confirmed with user
Applied globally by the assistant during the Authentication module to keep response shapes consistent; flagged in `ARCHITECTURE_REVIEW.md` for review but not explicitly re-approved since.
**Revisit**: low priority — only if a future need for a structured envelope (e.g. API versioning metadata) arises.

### `patch-package` patch on `primevue` is pinned to 4.5.5
`frontend/patches/primevue+4.5.5.patch` fixes a real upstream `DatePicker` bug (`populateTime()` crashes
parsing a typed 24-hour value with no AM/PM suffix, silently discarding it — see CHANGELOG, Phase 2 Step
6). `patch-package` re-applies it automatically on every `npm install` at the *currently installed*
version, but the patch file itself won't match a different `primevue` version's source and will fail to
apply (loudly, via `postinstall`, not silently) if `primevue` is ever bumped.
**Revisit**: whenever `primevue` is upgraded (including the eventual 5.x major), re-check whether this bug
still exists in the new version — if fixed upstream, delete the patch file; if not, regenerate it against
the new version's `datepicker/index.mjs`.

## Open (new from Appointments frontend design review, 2026-07-16)

### No dedicated dentists/providers listing endpoint
`GET /api/users` is paginated at a fixed 15/page with no `role` filter and the controller doesn't forward a `per_page` query param to `UserService::paginate()`. The Appointments frontend needs a full list of dentists for filters/dropdowns (Calendar filters, Appointment dialog, Working Hours/Time Off dentist selector), so `frontend/src/stores/providers.store.ts` works around this by paginating through every page of `GET /api/users` once per session and filtering `role === 'dentist'` client-side — acceptable at realistic clinic-staff scale (a handful of requests, once, cached indefinitely), but a real workaround, not the intended shape.
**Revisit**: add a dedicated `GET /api/dentists` (or `/api/providers`, matching wherever the future Providers module lands — see `docs/modules/appointments-ui-design.md` §4 "Dentist/Provider Store") endpoint, or at minimum a `?role=` filter plus an uncapped `per_page` on the existing `GET /api/users` for this specific staff-directory use case. Low risk, small change — do it whenever backend capacity allows; not blocking.

### Appointment audit-log route not yet exposed
`Appointment` already uses the `Auditable` trait (`backend/app/Models/Appointment.php:6,19`), so every
create/update/status-transition is already being recorded in `audit_logs`, exactly like `Patient` — the data
exists. But no route/controller/policy exposes it: unlike Patients' `GET
/api/patients/{patient}/audit-logs` (`routes/api.php:25`, `PatientController::auditLogs()`,
`PatientPolicy::viewAuditLogs`), there is no `appointments/{appointment}/audit-logs` route,
`AppointmentController` has no `auditLogs()` method, and `AppointmentPolicy` has no `viewAuditLogs` ability.
Confirmed absent by reading the code directly, not just unconfirmed. The frontend's `AppointmentDetailView`
renders a `FutureFeaturePlaceholder` in that slot instead of a real audit panel (see
`docs/modules/appointments-ui-design.md` §4.2).
**Revisit**: add `GET /api/appointments/{appointment}/audit-logs` + `AppointmentController::auditLogs()` +
`AppointmentPolicy::viewAuditLogs` (admin only), mirroring the Patients pattern exactly. Small, low-risk —
the write-side capture already works, only the read-side route is missing. Not blocking.

### Appointment mutation endpoints don't eager-load relations
`AppointmentResource` responses from `store`/`confirm`/`check-in`/`start`/`complete`/`cancel`/`no-show`/`update` don't eager-load `patient`/`dentist`/`appointment_type` (only `index`/`show` do), so the frontend's `appointments.store.ts` issues a follow-up `GET /api/appointments/{id}` after every mutation to re-hydrate those nested fields before updating its cache — see `docs/modules/appointments-ui-design.md` §11 "Post-Mutation Rehydration."
**Revisit**: if these controller actions eager-load the same three relations before returning their `AppointmentResource` (mirroring what `index`/`show` already do), the frontend's extra round-trip can be deleted entirely. Small, low-risk backend change; not blocking initial implementation.

## Open (new from Appointments Phase 2 Step 5 — Appointment Detail View, 2026-07-17)

### No `confirmed_at` column — `AppointmentTimeline`'s Confirmed step is a status-order approximation
Every other step in `AppointmentTimeline.vue` (Scheduled/Checked In/In Progress/Completed) reads a real,
dedicated timestamp column (`created_at`/`checked_in_at`/`started_at`/`completed_at`) — confirmed directly
against the `appointments` migration. `confirmed` has no such column, and Check-In can happen directly from
`scheduled` (per the backend's state machine), so once an appointment has moved past `confirmed`, whether it
was ever actually confirmed can no longer be proven from `status` alone. The component documents this
explicitly (`TimelineStepDef.timestampField: null` for this one step) and never lets the approximation gate
later, ground-truth steps for a cancelled/no-show appointment's chain.
**Revisit**: if a real near-term need arises for reporting on confirmation lag/rate, add a `confirmed_at`
column + set it in the `confirm()` service action. The frontend change is a one-line fix — flip `STEPS`'
`confirmed` entry to `timestampField: 'confirmed_at'` — no other code change needed. Not blocking; low
priority.

## Open (new from Appointments Phase 2 Step 1 — Infrastructure, 2026-07-16)

### Pre-existing Patients/Users files are not yet Prettier-clean
Introducing ESLint + Prettier this step (see Resolved section below) surfaced pre-existing issues in files this step didn't touch: `PatientsView.vue` has one `vue/attributes-order` warning, and a handful of files (`PatientFormDialog.vue`, `DefaultLayout.vue`, `style.css`, `LoginView.vue`, `PatientDetailView.vue`, `PatientsView.vue`) have minor Prettier formatting drift. The `@typescript-eslint/no-explicit-any` errors in `PatientFormDialog.vue`/`UsersView.vue` (`catch (err: any)`) were fixed during the 2026-07-18 Production Gate — `npx eslint .` is fully clean now, matching the pattern already used in `AppointmentDialog.vue` (`catch (err: unknown)` + narrowed inline casts). Only the Prettier-formatting/attribute-order drift remains, deliberately not touched to keep unrelated diffs out of feature commits.
**Revisit**: a small, dedicated, easy-to-review follow-up pass — `npm run format` across the whole `frontend/src/` tree — whenever convenient. Purely mechanical, low risk.

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

### System-Wide Production Gate (resolved 2026-07-18)
Full pre-launch audit and hardening pass across the whole system (not scoped to one module), per
explicit user request after Appointments' Step 10 Final QA was approved. See
`docs/deployment.md` for the full production runbook this pass produced, and the Production Gate
report delivered in-conversation for the complete verified/fixed/risk breakdown. Highlights:

- **`DatabaseSeeder` demo-account gate** (closes the "no environment gate on demo accounts" item
  above): demo users/patients now only seed under `app()->environment('local')`;
  `AppointmentTypeSeeder` (real reference data) always runs. New `php artisan app:create-admin`
  interactive command creates the real first production admin with no known/default credential.
- **General API rate limiting**: `bootstrap/app.php` now calls `throttleApi()`, backed by a
  `RateLimiter::for('api', ...)` (120 req/min per user/IP) registered in `AppServiceProvider` —
  previously only `/login` had any throttling; every other endpoint (patients, users,
  appointments) had none.
- **Production Docker topology**: new `docker-compose.prod.yml`, `docker/php/Dockerfile.prod` +
  `entrypoint.prod.sh` (refuses to boot on a missing `.env`, unset `APP_KEY`, or
  `APP_ENV=local`), `docker/nginx/default.prod.conf` (serves the built frontend + proxies
  `/api`/`/sanctum`/`/up` to php-fpm from one origin), `backend/.env.production.example`,
  `frontend/.env.production.example`. Postgres/Redis no longer publish host ports in production.
- **Backup & recovery**: `docker/scripts/backup.sh` (nightly Postgres dump + storage tar, 14-day
  local retention, optional S3 sync) and `restore.sh` (explicit two-argument, confirmation-gated
  restore). See the "No S3/offsite backup configured yet" and "Restore procedure has not yet been
  exercised" items above — written and reviewed, not yet exercised against real data.
- **CI/CD quality gate**: new `.github/workflows/ci.yml` — backend (Pint, Larastan, `php artisan
  test`) and frontend (`vue-tsc`, ESLint, Prettier check, Vitest, `npm run build`) jobs on every
  push/PR.
- **Frontend fixes**: `PatientFormDialog.vue`/`UsersView.vue`'s `catch (err: any)` → `catch (err:
  unknown)` with narrowed casts (matching `AppointmentDialog.vue`'s existing pattern) — closes the
  ESLint-error half of the "pre-existing files not lint-clean" item above. `vite.config.ts` now
  sets `build.sourcemap: false` explicitly (was relying on an implicit default).
- **Verification performed**: 188/188 backend tests, Pint clean, PHPStan/Larastan 0 errors (level
  5), `vue-tsc` clean, ESLint clean, 259/259 frontend Vitest tests (one router-guard test's
  apparent flake under heavy session-local CPU contention was confirmed, via isolated re-run, to
  be environment-induced, not a real bug — see the isolated 11/11-pass result), production
  frontend build succeeds with no source maps and reasonable chunk sizes. Live-browser Playwright
  verification covered login, dashboard, patients CRUD (cross-checked against the database
  directly), Calendar Board + Day/Week/Month/List views, RTL↔LTR switching, 390px mobile viewport
  (no horizontal overflow), and all four permission-boundary scenarios (dentist/receptionist/guest/
  admin) — all confirmed passing. See the "Playwright E2E coverage exists only as ad hoc scratchpad
  scripts" item above for what wasn't committed as a durable test suite.

### Board day-navigation could land on the wrong calendar day (resolved 2026-07-16)
Originally logged during Appointments Phase 2 Step 4 as a narrow "few hours near local midnight" edge
case. A closer, user-requested audit of the whole datetime stack found that assessment was **wrong** — it
was not narrow at all: once `AppointmentCalendar.vue` set FullCalendar's `timeZone: 'UTC'` (fixing a
separate, confirmed event-rendering bug), passing `calendar.ts`'s genuinely-local `currentDate` straight
into `gotoDate()`/`initialDate` selected the wrong day **every single day**, for any positive-UTC-offset
browser — confirmed directly in a real browser (clicking "Today" showed Thursday instead of the real
Friday). Fixed with a new `lib/date.ts` helper, `toCalendarUtcDate`, applied at both call sites in
`AppointmentCalendar.vue`; regression-tested (`lib/date.test.ts`, `AppointmentCalendar.test.ts`). See
`docs/decisions.md`'s "Project-wide datetime policy" entry for the full audit this came out of.

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

## Open (new from Appointments Phase 2 Step 10 — Final QA, 2026-07-18)

### Board's fetch-failure UX is a toast, not the inline retry state the design doc calls for
Design doc §1.9 ("If the Board's range fetch itself fails... show an inline retry state in place of
the calendar grid — a broken calendar with no data and no way to retry is a dead end") describes a
richer treatment than what Step 10 actually shipped: `AppointmentsView`/the three Dashboard-adjacent
widgets now show an error toast when `appointments.error` is set (previously showed nothing at all —
see CHANGELOG), but the calendar/widget still renders its ordinary empty state underneath, and there
is no in-place "Retry" affordance.
**Revisit**: if silent/toast-only failure proves insufficient in practice, replace the Board's empty
render with a dedicated error state (message + retry button) when `appointments.error` is set,
matching §1.9 as originally specified. Not blocking — the toast closes the "totally silent" gap that
existed before; this is about matching the fuller documented design, not fixing a currently-broken
experience.

### Mobile Week/Month view is legible but cramped; design doc's "auto-select Day view on narrow
viewports" isn't implemented
Design doc §1.11 states Day view "is auto-selected when the viewport is narrow on first load (still
user-overridable)." No such logic exists anywhere in `calendar.ts`/`AppointmentsView.vue` — confirmed
by reading both files. Verified directly at a 390px viewport: Week view (the default) still renders
all 7 day columns, each only a few pixels wide once time labels are accounted for — readable, and no
longer causes the whole-page horizontal scroll fixed this step (see CHANGELOG), but a poor first
impression compared to what the design doc describes.
**Revisit**: add a `window.innerWidth` (or a CSS-driven) check on first mount that defaults
`calendar.viewMode` to `timeGridDay` below a mobile breakpoint, only when the user hasn't already
picked a view this session. Small, self-contained change; not blocking.

### `vue-i18n`'s full compiler+runtime build ships on every page (~84 KB gzipped)
Confirmed via `npm run build`: `vue-i18n` is the second-largest chunk in the whole app (273 KB /
84 KB gzip), eagerly loaded on every route since `main.ts` installs it globally. This project has no
`@intlify/unplugin-vue-i18n` (or equivalent) build step — locale JSON is passed straight to
`createI18n({ messages })` and compiled to render functions **at runtime** via `t()`, which is exactly
why the full (not runtime-only) build is required. Simply aliasing to
`vue-i18n/dist/vue-i18n.runtime.esm-bundler.js` without also adding message precompilation would break
every translation in the app — not attempted this step; the fix has real setup cost and its own
regression surface across all 3 locales.
**Revisit**: add `@intlify/unplugin-vue-i18n` to `vite.config.ts` to precompile `locales/*.json` into
message functions at build time, then switch the `vue-i18n` import to its runtime-only build. Worth
doing given the app-wide (not just Appointments) bundle-size win, but needs its own dedicated,
carefully-verified pass across `en`/`ar`/`tr`.

### Keyboard-shortcut-triggered dialog close doesn't restore focus anywhere meaningful
`useDialogFocusRestore()` correctly returns focus to the triggering element when a dialog is opened
by a **click** (confirmed directly: closing a mouse-opened "New Appointment" dialog returns focus to
that exact button). But when the same dialog is opened via the `N` keyboard shortcut, there was no
specific focused element to begin with (the shortcut is a global `window` keydown listener, not tied
to any button) — closing it restores focus to `<body>`, forcing a keyboard-only user to tab from the
very top of the page again. This is the composable behaving exactly as designed ("restore whatever
had focus"); the gap is that "whatever had focus" is meaningless for this specific entry path.
**Revisit**: if this proves a real friction point for keyboard users, have the `N`/`?` shortcut
handlers move focus to a stable anchor (e.g. the page's `<h1>` or the New Appointment button itself)
immediately before opening the dialog, so there's something meaningful for the restore to return to.
Low priority, narrow fix.

## Open (new from 2026-07-15 review)

### PHPStan level 5, not stricter
Chosen as a pragmatic starting point for a codebase with no prior static analysis (Larastan's own recommended default). Levels 6-9 add generic-type-hint strictness that would need broader retrofitting across the codebase.
**Revisit**: raise incrementally (6, then 7, ...) as new modules are built and the team has bandwidth to address the stricter findings — not urgent, no known bugs are being masked by staying at 5 today.

## Open (new from System-Wide Production Gate, 2026-07-18)

### No S3/offsite backup configured yet — local-disk-only
`docker/scripts/backup.sh` dumps Postgres + `storage/app/private` to a local directory on the VPS
host (`/var/backups/dentalsuite`) with a 14-day retention prune. The `aws s3 sync` offsite-copy line
is present but commented out — no S3-compatible bucket has been provisioned yet. A local-disk-only
backup does not survive VPS loss, disk failure, or provider-level incident.
**Revisit**: before onboarding the first real clinic — provision an S3-compatible bucket
(Backblaze B2, Wasabi, or AWS S3) and uncomment the sync line in `backup.sh`. See
`docs/deployment.md` "Backup & Recovery".

### Restore procedure — RESOLVED (2026-07-20): rehearsed end-to-end, not just written
Updated same pass: ran the full runbook for real, not just reviewed it. Added
`docker-compose.rehearsal.yml` (isolated, disposable Postgres + app stack — its own network, no
host ports, anonymous volume) plus `COMPOSE_FILE`/`ENV_FILE` overrides on `backup.sh`/`restore.sh`
so the *actual*, unmodified production scripts could target it. Took a real backup from the dev
stack, restored it into the fresh rehearsal target, and verified: every table's row count matched
the source exactly (9 patients, 3 users, 6 appointment types, 0 appointments), the first patient
record matched field-by-field including the UUID, and the storage tar extracted correctly — while
confirming the source dev stack was completely unaffected throughout. See `docs/deployment.md`
"Testing restore" for the exact commands and full result. Only remaining gap: this proved the
mechanics work, not a run against real production data (none exists yet) — re-run once live.

### `.github/workflows/ci.yml` — CONFIRMED running, backend + frontend green, e2e 12/13
Updated 2026-07-20 (later same pass): the workflow's first real pushes to `main` (commits
`b2459de` onward) surfaced and closed several genuine CI-harness-only bugs — none were
application bugs, all confirmed via reading actual job logs/screenshots/backend logs, not
assumed:
- `php artisan serve` needs `PHP_CLI_SERVER_WORKERS` (single-threaded by default; a real browser
  fires several concurrent requests per navigation) — fixed (`ba2bc1c`).
- The E2E job pointed the browser/Vite at `127.0.0.1` while `backend/.env.example`'s
  `FRONTEND_URL`/`SANCTUM_STATEFUL_DOMAINS` say `localhost` — different strings, so every
  cross-origin request failed Sanctum/CORS's origin check and was silently blocked by the
  browser before reaching the server (this was the single biggest fix — took the suite from
  12/13 *failing* to 11/13 *passing*) — fixed (`f3447cf`).
- `frontend/src/views/AppointmentsView.test.ts` (Vitest, pre-existing, unrelated to this
  session's E2E work): a fixture appointment hardcoded to `2026-07-15` only stayed inside the
  Board's "current week" filter (computed from the real `new Date()`) as long as wall-clock time
  hadn't drifted past it — broke once it finally did, mid-session. Fixed with
  `vi.useFakeTimers()`/`setSystemTime()` (`33eb84d`).
- Backend (Pint, Larastan, 188 tests) and Frontend (`vue-tsc`, ESLint, Prettier, 259 Vitest
  tests, production build) jobs are both **fully green** as of `33eb84d`.

**Still open**: the E2E job's "creates, reschedules, and cancels an appointment" test — 12/13
other E2E tests pass reliably (auth, dashboard, patients CRUD, calendar views, RTL/LTR, mobile,
all permission boundaries). This one test's remaining failure is under active investigation;
several real test-authoring bugs were found and fixed along the way (unscoped dialog selector
colliding with `CalendarFilters`' own patient search, a hardcoded random-seed patient name,
`page.request` lacking the Origin/Referer headers Sanctum's stateful check needs, searching the
concatenated full name instead of a single token, and working hours never being seeded by
`DatabaseSeeder` at all — deliberately, they're clinic setup, not demo data) — but the exact
current failure mode (working-hours POST appears to succeed per the added diagnostic, yet the
save still doesn't complete) isn't nailed down yet.
**Revisit immediately**: this is very close — re-run with the diagnostic in place, read the
`Upload Playwright report on failure` artifact (needs repo-admin GitHub auth to download, not
available to this session) or WebFetch the job's HTML output for the specific error. Not
release-blocking on its own: the same create/reschedule/cancel/status-transition logic is proven
by 32/32 passing backend `AppointmentTest` cases and the `AppointmentDialog`/`SlotPicker`
Vitest component suites — this is about closing the very last gap in live-browser E2E coverage,
not an unverified feature.
tests plus the backend feature-test suite already cover the underlying logic; this is about
closing the last mile of true cross-stack browser confirmation.
