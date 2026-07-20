# Appointments Module

**Status: Production Ready ✅ — tagged `v1.0.0-appointments` (2026-07-20).**

This is the final module doc, produced at Final Review per the two-phase workflow, superseding
[`appointments-design-draft.md`](appointments-design-draft.md) (backend design) and
[`appointments-ui-design.md`](appointments-ui-design.md) (frontend design) as the canonical reference for
this module. Both design docs are kept for historical/decision-record purposes — see their own "superseded"
notes — but this doc reflects what actually shipped.

## Scope (V1)

Calendar-based appointment scheduling for a single clinic: booking, rescheduling, and the full
status-transition lifecycle (Scheduled → Confirmed → Checked In → In Progress → Completed, with
Cancel/No-Show as terminal exits from any non-terminal state); appointment types (admin-managed, with
duration/color); per-dentist working hours and time off, driving slot availability; a Calendar Board
(Day/Week/Month/List), a dedicated Detail view, Dashboard widgets (Today's Schedule, Upcoming), and a
Patient Detail "Appointments" panel.

**Explicitly out of scope for V1** (see Known Limitations below and `TECH_DEBT.md`): a Dentists
resource-column calendar view (blocked on a paid FullCalendar Premium license), drag-and-drop
rescheduling, appointment reminders (table exists, `AppointmentReminder`, but nothing populates/sends
them yet), a dedicated audit-log UI panel (data is captured, no route exposes it), multi-branch scheduling.

## Architecture

**Backend** (Laravel 12, PHP 8.4): Modular Monolith / Clean Architecture per `PROJECT_CONTEXT.md` —
thin Controllers, all business logic in Services, Policies for authorization, Form Requests for
validation. Status transitions are enforced by a single allowed-transitions lookup table inside
`AppointmentService`, not a state-machine library. Domain conflicts (dentist double-booking, patient
double-booking, outside working hours, early no-show, invalid transition) are modeled as dedicated
exceptions under `app/Exceptions/Appointments/`, each rendering its own JSON response — a `409` with
`code`/`overridable` for the two conflict types, `422` for the rest (see `docs/api-guidelines.md`).
Dentist-conflict is a hard block; patient-conflict and outside-working-hours are soft/overridable
warnings the caller can resubmit past with an explicit override flag.

**Belt-and-suspenders concurrency control**: an app-level pre-check runs before every write, backed by a
real Postgres `EXCLUDE` constraint (`appointments_no_overlapping_dentist_slots`,
migration `2026_07_15_000007`) that rejects an overlapping insert even if the app-level check is ever
raced — verified directly against Postgres, not assumed.

**Frontend** (Vue 3 + TypeScript + PrimeVue + Tailwind): a dedicated API Services layer
(`frontend/src/services/appointments/`) between six Pinia stores and `lib/api.ts`, normalizing
409/422 conflict responses into a typed `AppointmentConflictError`. The Calendar Board is built on
`@fullcalendar/vue3` (confirmed MIT-licensed) rather than a custom-built grid. All appointment
date/times go through the project-wide `frontend/src/lib/date.ts` convention (single-clinic system,
stored wall-clock time treated as a neutral baseline, never real UTC — see
`docs/decisions.md`'s "Project-wide datetime policy").

## Key Architectural Decisions

- **FullCalendar Community (MIT) chosen over a custom calendar build** — Day/Week/Month/List fully
  covered by `@fullcalendar/{core,vue3,daygrid,timegrid,interaction}`. `@fullcalendar/resource*`
  (needed for a Dentists resource-column view) turned out to be FullCalendar Premium
  (paid/CC-BY-NC-ND/GPLv3, none compatible with a closed-source commercial product) — **not
  installed**; that one view is deferred pending a licensing decision, not built as a workaround.
- **Status transitions as a plain lookup table**, not a state-machine package — the graph is small and
  static enough that a dependency wasn't justified (`PROJECT_CONTEXT.md`'s "never introduce unnecessary
  packages").
- **No generic `/status` endpoint** — six dedicated transition endpoints
  (`confirm`/`check-in`/`start`/`complete`/`cancel`/`no-show`) instead, matching the originally-approved
  design; `PUT /api/appointments/{id}` handles both plain edits and in-place reschedules (no separate
  reschedule endpoint).
- **Reschedule is in-place**, not a cancel+recreate — conflict/availability checks only re-run when the
  slot actually moves; `reschedule_count` increments only on a real dentist/time change.
- **`providers.ts` dentist-listing workaround**: no dedicated `GET /api/dentists` endpoint exists yet, so
  the frontend paginates through `GET /api/users` once per session and filters `role === 'dentist'`
  client-side — acceptable at clinic-staff scale, documented as a workaround, not the intended shape (see
  Known Limitations).
- **Post-mutation rehydration**: mutation endpoints (`store`/`confirm`/.../`update`) don't eager-load
  `patient`/`dentist`/`appointment_type`, so the frontend issues a follow-up `GET` to re-hydrate those
  relations before updating its cache. A small backend change (eager-load on those actions) would let
  this be deleted later.
- **`patch-package` patch pinned to `primevue@4.5.5`**: fixes a real upstream `DatePicker` crash
  (`populateTime()` calls `ampm.toLowerCase()` unconditionally, throwing in 24-hour mode) — a vendored
  library defect, not application code, so patched rather than worked around at each call site.
- **No validation library, no drag-and-drop library** — the existing manual `reactive()` +
  server-422-driven-errors pattern (from Patients) is reused; drag-and-drop is out of V1 scope per the
  backend design doc.

Full reasoning and the real bugs found/fixed at each implementation step are in `CHANGELOG.md`'s
Appointments entries (Phase 2 Steps 1–10) and `docs/decisions.md`.

## Backend

| Layer | Files |
|---|---|
| Migrations | `2026_07_15_000002_create_appointment_types_table.php`, `..._000003_create_dentist_working_hours_table.php`, `..._000004_create_dentist_time_off_table.php`, `..._000005_create_appointments_table.php`, `..._000006_create_appointment_reminders_table.php`, `..._000007_add_conflict_exclusion_constraint_to_appointments_table.php` |
| Enums | `app/Enums/AppointmentStatus.php` |
| Models | `Appointment.php` (`Auditable`), `AppointmentType.php`, `DentistWorkingHour.php`, `DentistTimeOff.php`, `AppointmentReminder.php` |
| Form Requests | `Appointment/{Index,Store,Update,Cancel,MarkNoShow}AppointmentRequest.php`, `AppointmentType/{Store,Update}AppointmentTypeRequest.php`, `DentistWorkingHour/StoreDentistWorkingHourRequest.php`, `DentistTimeOff/StoreDentistTimeOffRequest.php` |
| Services | `AppointmentService.php` (`availableSlots`, conflict/working-hours checks, `create`/`reschedule`/`cancel`/`markNoShow`/`confirm`/`checkIn`/`start`/`complete`/`search`/`delete`), `AppointmentTypeService.php`, `DentistWorkingHourService.php`, `DentistTimeOffService.php` |
| Policies | `AppointmentPolicy.php` (incl. `confirm`/`checkIn`/`start`/`complete` abilities and the dentist-ownership IDOR check on `start`/`complete`), `AppointmentTypePolicy.php`, `DentistWorkingHourPolicy.php`, `DentistTimeOffPolicy.php` |
| Exceptions | `app/Exceptions/Appointments/{DentistConflictException,PatientConflictException,OutsideWorkingHoursException,EarlyNoShowException,InvalidStatusTransitionException}.php` |
| Controllers | `AppointmentController.php`, `AppointmentTypeController.php`, `DentistWorkingHourController.php`, `DentistTimeOffController.php` |
| Resources | `AppointmentResource.php`, `AppointmentTypeResource.php`, `DentistWorkingHourResource.php`, `DentistTimeOffResource.php` |
| Config | `config/appointments.php` (`slot_interval_minutes`, default 15) |
| Tests | `tests/Feature/{AppointmentTest,AppointmentTypeTest,DentistWorkingHourTest,DentistTimeOffTest}.php` — 61 tests |

## API

```
GET    /api/appointments?date_from=...&date_to=...&dentist_id=...&status=...   (date-range required, clinic-wide, not paginated)
POST   /api/appointments
GET    /api/appointments/{appointment}
PUT    /api/appointments/{appointment}                (plain edit or in-place reschedule)
DELETE /api/appointments/{appointment}
POST   /api/appointments/{appointment}/confirm
POST   /api/appointments/{appointment}/check-in
POST   /api/appointments/{appointment}/start
POST   /api/appointments/{appointment}/complete
POST   /api/appointments/{appointment}/cancel
POST   /api/appointments/{appointment}/no-show
GET    /api/available-slots?dentist_id=...&date=...&type_id=...

GET/POST                       /api/appointment-types            (admin-only write, any-role read)
GET/POST/PUT/DELETE            /api/appointment-types/{type}

GET/POST     /api/dentists/{user}/working-hours       (admin-only write; dentist reads own read-only)
DELETE       /api/dentists/{user}/working-hours/{workingHour}
GET/POST     /api/dentists/{user}/time-off            (admin-any, dentist-own self-service)
DELETE       /api/dentists/{user}/time-off/{timeOff}
```

Domain-conflict error shape (`409`): `{ "code": "dentist_conflict" | "patient_conflict", "overridable": bool, "message": "..." }`.
`422` for `outside_working_hours` / `early_no_show` / `invalid_status_transition`. Full shapes in
`docs/api-guidelines.md`.

## Frontend

| Layer | Files |
|---|---|
| Types | `src/types/appointment.ts` |
| Services | `src/services/appointments/{appointmentsApi,appointmentTypesApi,workingHoursApi,timeOffApi,providersApi,errors}.ts` |
| Stores | `src/stores/{appointments,appointmentTypes,workingHours,timeOff,calendar,providers}.ts` |
| Views | `AppointmentsView.vue` (Board/List), `AppointmentDetailView.vue`, `AppointmentTypesView.vue`, `DentistScheduleView.vue` (Working Hours + Time Off tabs) |
| Components | `src/components/appointments/` — 27 components: calendar (`AppointmentCalendar`, `CalendarToolbar`, `CalendarFilters`, `AppointmentEventContent`, `AppointmentStatusChip`), booking dialog (`AppointmentDialog`, `PatientSearchSelect`, `PatientSummaryCard`, `DentistSelect`, `AppointmentTypeSelect`, `DurationInput`, `SlotPicker`, `ConflictAlert`), detail view (`AppointmentCard`, `AppointmentTimeline`, `AppointmentActionsBar`, `StatusActionButton`, `FutureFeaturePlaceholder`), schedule management (`WorkingHoursEditor`, `WorkingHoursDayRow`, `TimeOffCalendar`, `TimeOffFormDialog`), types CRUD (`AppointmentTypeFormDialog`), Dashboard/Patient integration (`TodayScheduleWidget`, `UpcomingAppointmentsWidget`, `PatientAppointmentsPanel`), list view (`AppointmentListTable`), a11y (`KeyboardShortcutsHelp`) |
| Shared libs added/extended | `lib/date.ts` (datetime helpers, project-wide policy), `lib/color.ts` (WCAG contrast helper), `useCalendarKeyboardShortcuts()`, `useDialogFocusRestore()` composables |
| i18n | `appointments.*` namespace, `en`/`ar`/`tr`, parity-verified |

## Testing & Verification

- Backend: 188/188 tests passing, Pint clean, Larastan (PHPStan level 5) 0 errors.
- Frontend: 259/259 Vitest passing, `vue-tsc` clean, ESLint clean, Prettier clean, production build succeeds.
- E2E: permanent Playwright suite (`frontend/e2e/appointments.spec.ts`) — **13/13 passing on GitHub
  Actions** (run `29763458360`, commit `3faf2d7`), covering create/reschedule/cancel through the real
  booking dialog and available-slots flow.
- Manual real-browser verification at every implementation step (mandatory per the two-phase workflow),
  Arabic/English × light/dark × desktop/tablet/mobile — see `CHANGELOG.md` for the real bugs each pass
  found and fixed (timezone-shift bug, FullCalendar write-once-option bug, RTL/dark-mode gaps, focus
  management, WCAG contrast, and the multi-round E2E hardening documented in `TECH_DEBT.md`).

## Known Limitations / Deferred (non-blocking)

Full detail and revisit conditions for each live in `TECH_DEBT.md`; summarized here for this module:

- **No Dentists resource-column calendar view** — blocked on FullCalendar Premium licensing, not a bug.
- **No dedicated `GET /api/dentists` endpoint** — frontend works around it via `providers.ts` paginating `GET /api/users` client-side.
- **Appointment audit-log has no read route** — write-side capture works (`Auditable` trait), no `GET /api/appointments/{id}/audit-logs` exists yet; UI shows a placeholder.
- **Mutation endpoints don't eager-load relations** — frontend does a follow-up `GET` per mutation instead.
- **No `confirmed_at` column** — the Detail Timeline's "Confirmed" step is a status-order approximation, not a real timestamp.
- **`PatientAppointmentsPanel` shows a bounded ±3/6-month window**, not full history — no patient-scoped unbounded endpoint exists.
- **Appointment Types have no `price`/`is_default` column** — pricing belongs in the future Billing module's design.
- **Board's fetch-failure UX is a toast, not the richer inline retry state** the original design doc described.
- **No auto-select-Day-view on narrow viewports** — Week view is legible but cramped at ~390px; no functional bug (no more horizontal overflow).
- **`vue-i18n` ships its full compiler+runtime build** (~84 KB gzip) app-wide — a build-step fix (`@intlify/unplugin-vue-i18n`), not scoped to this module.
- **Keyboard-shortcut-opened dialogs restore focus to `<body>`**, not a meaningful anchor, since the shortcut has no originating click target.

None of the above block production use of the module as scoped; each has an explicit revisit condition in `TECH_DEBT.md`.

## Production Gate (closed 2026-07-20)

A system-wide (not Appointments-only) pre-launch hardening pass ran after this module's Final QA:
demo-account environment gating + `app:create-admin`, general API rate limiting, production
Docker/nginx/SSL topology, backup/restore rehearsed end-to-end against a disposable stack, and a CI/CD
quality gate (`.github/workflows/ci.yml`) confirmed green on `main` via the GitHub Actions API — Backend,
Frontend, and E2E (13/13) jobs all passing. Full detail: `TECH_DEBT.md`'s Production Gate / CI entries,
`docs/deployment.md`.

## Completion

Migration, Model, Validation, Service, Policy, API, Vue Pages, Tests, Documentation — all present.
188/188 backend tests, 259/259 frontend tests, 13/13 E2E on CI, Pint/Larastan/`vue-tsc`/ESLint/Prettier
all clean. **Verdict: Production Ready.**
