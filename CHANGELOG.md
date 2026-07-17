# Changelog

All notable changes to DentalSuite are documented here. Format is chronological, grouped by module.

## Unreleased

### Added — Appointments (Dentist Schedule View, Phase 2 Step 6)
- **`DentistScheduleView.vue`** (design doc §5-§6): a `DentistSelect`-driven page (admin can pick any
  dentist; a dentist-role user only ever sees their own, no selector) with two tabs.
  - **Working Hours** — `WorkingHoursEditor.vue` + `WorkingHoursDayRow.vue`: a 7-day weekly grid, each day
    supporting multiple shift rows (split-shift/lunch-break support), a per-day active toggle (bulk-sets
    every shift row's `is_active`), and a "Copy to…" action to duplicate a day's shifts onto other days.
    Since the backend exposes no update endpoint for `dentist_working_hours`, editing a shift is
    implemented as delete-old + create-new under the hood, presented as a single in-place edit. A
    dentist-role user sees the same grid read-only, with an explanatory note.
  - **Time Off** — `TimeOffCalendar.vue` (a chronological list, not a mini calendar — the Board's own
    time-off overlay already covers that visualization) + `TimeOffFormDialog.vue`. The category picker
    (Vacation/Conference/Sick Leave/Emergency/Other) is a client-side-only convenience that writes its
    label into the backend's free-text `reason` field (e.g. `"Vacation: Family trip"`) — the backend has
    no structured category column, so this is never treated as one downstream. The dialog cross-references
    the dentist's cached appointments for the proposed range and shows a non-blocking conflict warning
    list (the backend neither blocks nor cascade-cancels on an overlapping time-off entry).
- Full Vitest coverage for every new component/store and the wired view (214/214 passing); `vue-tsc`,
  ESLint, and Prettier all clean.

### Fixed — real bugs found via this step's mandatory real-browser verification
- **Upstream PrimeVue `DatePicker` defect**: with `show-time` + `hour-format="24"` (used here and by the
  already-shipped `AppointmentDialog`, Step 4), typing a full date/time string and tabbing away silently
  cleared the field — no error, just data loss. Root cause: `primevue/datepicker`'s `populateTime()`
  unconditionally calls `ampm.toLowerCase()` even in 24-hour mode, where `ampm` is `undefined`, throwing
  and discarding the parsed value. Since this is a defect in the vendored library itself, not our code,
  fixed via a permanent `patch-package` patch (`frontend/patches/primevue+4.5.5.patch`, applied on every
  `npm install` via a new `postinstall` script) rather than working around it at each call site. Confirmed
  this also silently affected the Step 4 `AppointmentDialog` date/time fields, since that step's browser
  verification had only exercised the calendar-click path, never manual typing.
- **Working-hours "Add shift" race condition**: if an unrelated day's edit (e.g. a "Copy to…" action)
  forced the whole working-hours list to refresh while a just-saved shift's create request was still in
  flight, the row's resync logic treated the still-`id`-less draft as "never persisted" and silently
  stranded it — a later delete on that row would then discard it locally without ever calling the API,
  leaving an orphaned row on the backend with no way to remove it from the UI. Fixed in
  `WorkingHoursDayRow.vue` by tracking which drafts are genuinely uncommitted (added but not yet Saved)
  versus merely awaiting their real server-issued id, and only guarding the resync against the former.
- **Read-only working-hours display leaked the backend's raw `HH:mm:ss`** (e.g. `"08:00:00 – 18:00:00"`)
  for a dentist viewing their own schedule, instead of the `HH:mm` format the editable admin view already
  showed. Fixed by formatting both consistently in `WorkingHoursDayRow.vue`.

### Added — Appointments (Appointment Detail View, Phase 2 Step 5)
- **`AppointmentDetailView.vue`** rebuilt from its Step-1 stub into the real detail screen (design doc §4):
  header summary, timeline, patient panel, action bar, and future-module placeholders, all wired to the
  `appointments` store's `fetchOne`/mutation actions.
- **New components**, each unit-tested in isolation:
  - `AppointmentCard.vue` — presentational summary card (type, patient, dentist, date/time range, duration,
    status, reason). Deliberately store-free (props/events only) so it can be reused by a future Dashboard
    or search-results row without pulling in any store or permission logic.
  - `AppointmentTimeline.vue` — vertical status stepper driven by a data-driven step-definition array keyed
    to each status's real timestamp column, not the current status enum alone; a cancelled/no-show
    appointment's chain terminates at the point it actually stopped rather than showing a ghost "Completed
    (pending)" step. See TECH_DEBT.md for the one documented exception (`confirmed` has no dedicated
    timestamp column yet).
  - `AppointmentActionsBar.vue` + `StatusActionButton.vue` — the six status-transition buttons
    (Confirm/Check In/Start/Complete/Cancel/No Show), gated by a status/role/ownership visibility table.
    This table decides visibility and UX messaging only; the backend's own state machine and policies
    remain the sole authority — a stale assumption still gets rejected by the real API call, and the
    component re-fetches and re-syncs the displayed appointment rather than trusting its own guess. The
    early-no-show conflict reuses the existing `ConflictAlert`/override pattern from the booking dialog.
  - `FutureFeaturePlaceholder.vue` — generic "coming soon" card, used for Treatment Plan/Invoices/Clinical
    Notes/Attachments plus the admin-only Audit History slot (§4.2 — no backend route exists yet, see
    TECH_DEBT.md).
- Edit now has a live call site: the Detail view's Edit button opens the existing `AppointmentDialog` in
  edit mode (built in Step 4, previously unwired).
- Every new datetime display goes through `frontend/src/lib/date.ts`'s shared helpers per the project's
  datetime policy (`docs/decisions.md`) — no new ad hoc date handling introduced.
- Full Vitest coverage for every new component and the wired view; `vue-tsc`, ESLint, and Prettier all clean.

### Fixed — real bugs found via this step's mandatory real-browser verification
- **Ambiguous "Cancel" button pair**: the Cancel-with-reason dialog showed two buttons both labeled
  "Cancel" — the dismiss button (close the dialog) and the destructive confirm button (actually cancel the
  appointment), since both reused the bare action verb. Renamed the dismiss button to "Keep Appointment"
  and the confirm button to the dialog's own full header text ("Cancel Appointment" / "Mark as No Show"),
  so the two are never textually identical (`StatusActionButton.vue`).
- **Empty "Actions" card for a terminal appointment**: a completed/cancelled/no-show appointment has no
  visible status-transition buttons by design, but the Actions card rendered as a blank box with no
  explanation. Added a "No actions available for this appointment" message in that state
  (`AppointmentActionsBar.vue`).
- Both found and fixed via the full Confirm→Check In→Start→Complete lifecycle, Cancel-with-reason, and
  No-Show early-conflict/override flows driven end to end against the real dev stack (Docker/Postgres) in
  English/Arabic × light/dark — see the design doc's §20 status table for Step 5.

### Added — Appointments (Appointment Dialog, Phase 2 Step 4)
- **`AppointmentDialog.vue`** (Patient / Appointment / Notes tabs, design doc §3) — the full Create flow end
  to end: patient search-and-select or inline creation, dentist/type selection with duration auto-fill
  (only while the user hasn't manually touched duration), a calendar-driven date/time picker with a live
  "Ends at" preview, an available-slots toggle, and reason/notes fields with character counters. Edit mode
  (`:appointment` prop) locks the Patient tab per the backend's "patient_id not editable" rule and shows a
  read-only status chip; not yet wired to a live call site (that's `AppointmentDetailView`, Step 5) but
  fully implemented and unit-tested.
- **New components**: `PatientSearchSelect.vue` (debounced typeahead + "Create New Patient", reusing the
  existing `PatientFormDialog.vue` rather than a second form), `PatientSummaryCard.vue` (shared by the
  search results and edit-mode display), `DentistSelect.vue`, `AppointmentTypeSelect.vue` (color swatch,
  still resolves a since-deactivated type on an existing appointment), `DurationInput.vue`, `SlotPicker.vue`
  (candidate slots computed from working hours, cross-referenced against `GET /available-slots`),
  `ConflictAlert.vue` (hard-stop `dentist_conflict` vs. soft/overridable `patient_conflict` /
  `outside_working_hours`, per §3.8).
- **Wired into the Board**: `AppointmentsView.vue`'s "New Appointment" button and clicking an empty calendar
  slot both open the dialog now (previously a "coming soon" toast); `CalendarFilters.vue` gained the Patient
  filter (deferred from Step 2/3 pending `PatientSearchSelect.vue`).
- Full Vitest coverage for every new component plus the create/conflict/override paths (150+ new assertions
  across the module); `vue-tsc`, ESLint, and Prettier all clean.

### Fixed — real bugs found via this step's mandatory real-browser verification
- **Date-time silently shifted by the browser's OS timezone** (the most significant finding): the dialog
  built `start_at` with `.toISOString()`, and the Board (`AppointmentCalendar.vue`) rendered `start_at`/
  `end_at` under FullCalendar's default `timeZone: 'local'`. DentalSuite is a single-clinic system with no
  real per-request timezone conversion (`config/app.php`'s `timezone` is `UTC` used as a neutral baseline,
  not a real UTC boundary) — every stored digit already **is** the clinic's own wall-clock time. Confirmed
  directly: booking "10:00" from a browser whose OS timezone wasn't UTC submitted `07:00:00.000Z`, and an
  existing 10:00 appointment rendered on the Board at 1:00 PM. Fixed by extending the project's existing
  `parseLocalDate`/`toLocalDateString` convention (already used for date-only fields) to date-*time* values:
  new `frontend/src/lib/date.ts` helpers `toLocalDateTimeString`/`parseServerDateTime`, used by
  `AppointmentDialog.vue` and `AppointmentsView.vue`'s slot-click prefill; `AppointmentCalendar.vue` now
  sets FullCalendar's `timeZone: 'UTC'` explicitly. Regression-tested (`lib/date.test.ts`, new
  `AppointmentCalendar.test.ts` assertion).
- **PrimeVue `MultiSelect` rendered its placeholder twice** (e.g. "DentistDentist") for the ~1-2s a
  `:loading` prop stayed `true` before its `options` populated — a real PrimeVue 4.5.5 rendering quirk
  specific to `display="chip"` + `loading` + empty `modelValue`/`options` all being true at once. Fixed by
  no longer passing `:loading` to `CalendarFilters.vue`'s Dentist/Type filters (never required by design
  doc §1.7; the Status filter already had no `loading` prop and never showed the bug).
- **Concurrent `fetchAll()` calls raced into duplicate network requests**: `providers.ts`/
  `appointmentTypes.ts` had no in-flight-request guard, so every consumer mounting at once (the Board, the
  filters, and now `DentistSelect`/`AppointmentTypeSelect` inside the new dialog) each fired their own `GET
  /api/users`/`GET /api/appointment-types`. Fixed with a shared in-flight-promise guard in both stores;
  regression tests added.
- **Buttons without an explicit `type` inside a `<form>` default to `type="submit"`**: clicking "Cancel", a
  patient search result's "Change" button, or `ConflictAlert`'s "Book Anyway" inside `AppointmentDialog`'s
  form additionally triggered a native form submission alongside the button's own `@click` handler (caught
  by a real double-`POST` in browser verification, not by unit tests, which don't exercise native form
  submission). Fixed by adding `type="button"` to every non-submit button inside the form, including
  `PatientFormDialog.vue`'s pre-existing Cancel button (same latent bug, same fix).
- **`ToggleSwitch`'s label text wasn't clickable** — "Show available slots" only responded to clicks on the
  switch itself, not the adjacent text (the native `<label>`/control association PrimeVue's own markup
  supports was never wired up). Fixed by wrapping both in a `<label>`.
- **PHP-FPM's pool (`pm.max_children = 5`, the base `php:8.4-fpm-alpine` image's default) saturates under
  a single page load's normal concurrency** — the Board alone fires 4-5 concurrent requests on mount, and
  opening the dialog adds more; requests past the limit queue or briefly appear to hang rather than the app
  actually being broken (confirmed via `pm.max_children` warnings in the container logs and Postgres
  showing no stuck queries). `docker/php/www.conf` now sets a larger local-dev pool
  (`max_children=20`), copied into the image by `docker/php/Dockerfile`. Local-dev tuning only, not a
  production sizing decision.
- Verified manually against the real dev stack (Docker/Postgres): full create flow (existing-patient search
  and inline patient creation, dentist/type/duration, calendar-driven date-time + available slots, notes),
  the hard-stop `dentist_conflict` banner, the overridable `outside_working_hours` banner and its "Book
  Anyway" resubmission, all across Arabic/English × light/dark. No remaining visual issues found.

### Fixed — project-wide datetime audit (requested before Step 4 sign-off, not narrower Appointments-only follow-up)
Before approving Step 4, the datetime fix above was required to be verified as an explicit, project-wide
policy rather than a local patch — see `docs/decisions.md`'s "Project-wide datetime policy" entry for the
full verified audit (database column types, Laravel serialization, every API Resource, every frontend call
site). That audit found and fixed real gaps the original Step 4 pass missed:
- **Board day-navigation landed on the wrong day, every day, for any positive-UTC-offset browser** — not
  the "few hours near midnight" edge case originally logged in `TECH_DEBT.md`. Once `AppointmentCalendar.vue`
  set `timeZone: 'UTC'` (fixing event rendering), its `gotoDate()`/`initialDate` calls still received
  `calendar.ts`'s genuinely-local `currentDate` unconverted. Confirmed directly in a real browser (clicking
  "Today" showed Thursday instead of the real Friday). Fixed with a new `toCalendarUtcDate` helper
  (`lib/date.ts`), the inverse of `parseServerDateTime`; regression-tested.
- **`SlotPicker.vue` silently showed "no available slots" for any dentist not currently selected in the
  Board's own Dentist filter** — it read `workingHours.byDentist`, a store only ever populated as a side
  effect of that unrelated filter, never by the dialog's own dentist selection. Fixed by having `SlotPicker`
  fetch working hours for its own `dentistId` itself; regression-tested.
- **List view's Date/Time column, the Board's `filteredAppointments` range filtering/cache eviction, and
  `PatientDetailView.vue`'s audit-log timestamp column** (this last one a pre-existing bug in the Patients
  module, predating Appointments entirely) all read a raw `new Date(apiValue)` instead of
  `parseServerDateTime` — same silent-shift bug as the original finding, just not yet swept into the fix.
  All four switched to `parseServerDateTime`.
- Whole-tree grep for every remaining date-construction/formatting call site as the closing check — no
  further gaps found. `lib/date.ts`'s four helpers are the sole approach in force project-wide going
  forward, not an Appointments-module convention.
- 160/160 Vitest passing, `vue-tsc` clean; re-verified manually against the real dev stack (day-alignment,
  slot-matching for a dentist not in the Board filter, full create/conflict flows all still correct).

### Added — Appointments (List View, Phase 2 Step 3)
- **`AppointmentListTable.vue`**: client-paginated (`:rows="20"`, no server round-trip — `GET
  /api/appointments` has no server-side pagination to hook into, per the backend design) DataTable, sortable
  by Date & Time, columns for Patient/Dentist/Type (color swatch)/Duration/Status
  (`AppointmentStatusChip`). Renders whatever range/filters the parent already fetched — fetches nothing
  itself, matching the documented `appointments`/`loading` props + `row-click` emit contract.
- **List toggle**: `CalendarToolbar.vue`'s view switcher gained a fourth "List" option alongside Day/Week/
  Month. `AppointmentsView.vue` now toggles between `AppointmentCalendar` and `AppointmentListTable` on the
  same shared `calendar.ts` filter/range state, so switching views never loses context.
- Added a view-agnostic range-fetch watcher in `AppointmentsView.vue`: the Board's own range-change signal
  (FullCalendar's `datesSet`) only fires while `AppointmentCalendar` is mounted, so prev/next/today
  navigation while the List view is active would otherwise never fetch the new range. `appointments.ts`'s
  `fetchRange` already no-ops on an already-cached range, so this and the Board's own trigger never cause a
  duplicate request.
- Verified manually against the real dev stack (Docker/Postgres) in Arabic/English × light/dark, including
  the List↔Board toggle and prev/next navigation while List is active — no bugs found this pass.

### Added — Appointments (Calendar Board, Phase 2 Step 2)
- **Design doc revised** (`docs/modules/appointments-ui-design.md`) against the current codebase before any
  component was built: corrected several stale assumptions (test tooling already installed, route guards now
  exist, nav already shipped as sidebar children not in-page tabs), resolved the Audit History open item
  definitively (no backend route exists — `FutureFeaturePlaceholder` used instead, `Auditable` trait already
  captures the data), and added a new §20 Implementation Sequence.
- **Docs synced before implementation, per the two-phase workflow**: `docs/architecture.md` (audit-log
  status corrected, `services/` layer documented, 409 error shape added), `docs/roadmap.md` (Appointments
  status updated from "Up next"), `TECH_DEBT.md` (new entry for the missing Appointments audit-log route).
- **Route guards applied**: `meta: { roles: ['admin'] }` on `/appointments/types`, `meta: { roles: ['admin',
  'dentist'] }` on `/appointments/schedule`, matching the `/users` precedent — router tests added for both.
- **`@fullcalendar/{core,vue3,daygrid,timegrid,interaction}` installed** (MIT, license-verified by reading
  each package's actual tarball, not just the registry field) — covers Day/Week/Month/List in full.
  `@fullcalendar/resource`/`resource-timegrid` (needed only for the Dentists resource-column view) turned
  out to be FullCalendar Premium (paid/non-commercial/GPLv3), not MIT as the original draft assumed — **not
  installed**; the Dentists view is deferred, decided with the user rather than assumed.
- **New components** (`frontend/src/components/appointments/`): `AppointmentCalendar.vue` (presentational
  FullCalendar wrapper, §2.12), `AppointmentEventContent.vue`, `AppointmentStatusChip.vue`,
  `CalendarToolbar.vue` (Day/Week/Month — List deferred to the next step), `CalendarFilters.vue`
  (Dentist/Status/Type — Patient filter deferred until `PatientSearchSelect.vue` exists). New
  `frontend/src/lib/color.ts` (WCAG luminance-based contrast helper for clinic-picked event colors).
  `AppointmentsView.vue`'s Board is now wired to real stores/data instead of the placeholder card.
- **Three real bugs found and fixed via manual browser verification against the real dev stack** (not
  caught by unit tests, which mock at the service boundary): `appointmentsApi.list()` assumed a `{data:
  [...]}` paginated envelope, but `GET /api/appointments` deliberately returns a bare array — its own unit
  test had mocked the wrong shape, masking the crash; FullCalendar's `eventSources: []` was silently
  suppressing the separately-passed `events` array (merged into one array instead); `initialView`/
  `initialDate` are FullCalendar "write-once" options — the toolbar's Day/Week/Month switch and prev/next/
  today now call the imperative `changeView()`/`gotoDate()` API instead.
- **RTL and dark-mode gaps closed**: FullCalendar's `direction` now follows the active locale (grid was
  staying LTR while the rest of the page mirrored); FullCalendar's own CSS variables now respect `.dark`
  (header/day cells were staying white).
- Fixed a pre-existing timezone-fragile test in `calendar.test.ts` (`.toISOString()` date comparison broke
  in positive-UTC-offset timezones) using the project's existing `toLocalDateString()` helper.
- Verified manually (headless-Chromium screenshots against the real `docker compose` stack, with a real
  appointment created via `AppointmentService::create()`) across Arabic/English × light/dark × Day/Week/
  Month views. `npm run build`, `vue-tsc`, `eslint`, `prettier`, and `vitest` (114/114) all pass.

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
