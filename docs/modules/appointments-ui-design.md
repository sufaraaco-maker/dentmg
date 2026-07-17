# Appointments Frontend Module — UI/UX Design Document

Status: **Revised 2026-07-16, superseding the earlier draft — pending approval.** No new frontend *screen/
component* code is written until this revision is approved, per the project's two-phase-per-module
workflow. Once approved, this document drives implementation of the remaining UI (Calendar rendering,
Dialogs, Detail screen, Schedule/Types screens, Dashboard widgets — see the new §20 Implementation Sequence)
and is superseded by `docs/modules/appointments.md` (the final module doc) only once implementation, tests,
and QA are all complete.

Grounded in the **already-approved and implemented backend** (`docs/modules/appointments-design-draft.md`,
188/188 backend tests passing) — this document does not re-litigate backend decisions, it designs the Vue
layer that consumes that API exactly as built.

**What has already shipped since the original draft (verified directly against the current codebase, not
assumed) — this revision's main job is to bring the document in line with it:**

1. **Appointments frontend infrastructure** (`fbd1a4a feat(appointments): frontend infrastructure`,
   `CHANGELOG.md`): `frontend/src/types/appointment.ts` (matches this doc's §12 field-for-field), six Pinia
   stores (`appointments.ts`, `appointmentTypes.ts`, `workingHours.ts`, `timeOff.ts`, `calendar.ts`,
   `providers.ts` — note the filename, corrected below), the `frontend/src/services/appointments/` API
   Services layer (`appointmentsApi`, `appointmentTypesApi`, `workingHoursApi`, `timeOffApi`, `providersApi`,
   `errors.ts`), `auth.ts`'s `isDentist`/`isReceptionist`/`canManageAppointments` getters, placeholder routes
   and nav entry, `appointments.*` i18n keys, and the permanent Vitest/`@vue/test-utils`/ESLint/Prettier
   toolchain — all already built, matching this doc's data-layer design (§9-§12) almost exactly. 64 tests
   passing for this layer.
2. **Application shell / route permissions** (`ea0a264 feat: implement SaaS application layout and route
   permissions`): a real sidebar+header shell (`AppSidebar.vue`/`AppHeader.vue`/`config/navigation.ts`), a
   working `meta.roles` + `router.beforeEach` route-guard mechanism with a `ForbiddenView.vue`, dark
   mode/RTL wiring — this **replaces** several "doesn't exist yet" assumptions the original draft made (see
   §1.2/§1.10 below, revised accordingly).
3. **Design system / typography pass** (`220988f`): Inter/IBM Plex Sans Arabic fonts, a `DentalSuitePreset`
   PrimeVue theme, class-based dark mode (`.dark` on `<html>`), Tailwind v4 CSS-first config (no
   `tailwind.config.js`) — informs §14's contrast/dark-mode checklist below.

**Still not built (the actual scope of this document going forward):** every real screen/component this doc
designs in §2-§9 — `AppointmentsView`/`AppointmentDetailView`/`AppointmentTypesView`/`DentistScheduleView`
are today bare "coming soon" placeholder cards, and `frontend/src/components/appointments/` doesn't exist
yet. Nothing in §2-§9, §13-§19 below needed to change on that account — those sections were always designing
work that hadn't started; only the "current state" framing sections needed correcting.

**Decision already confirmed with the user before this document was written:** the Calendar Screen will use
**`@fullcalendar/vue3`** (Option A from the backend design doc's §15a), not a custom-built board.

---

## New Dependencies Introduced by This Module

Per `coding-standards.md`'s third-party-package callout rule (stated for `composer.json` specifically, but
applied here in the same spirit for `package.json`) and `PROJECT_CONTEXT.md`'s "never introduce unnecessary
packages" instruction, every package this design still requires is listed here for explicit approval.

**Now installed already, not a new addition — corrected from the original draft:** `vitest` (`^3.0.5`),
`@vue/test-utils` (`^2.4.6`), `jsdom` (`^25.0.1`), `@vitest/coverage-v8` (`^3.0.5`) all shipped as part of the
Appointments frontend-infrastructure step above (`frontend/package.json` devDependencies), alongside ESLint
and Prettier (not part of the original draft's ask, but added in the same step). One correction to how they
were wired in: `frontend/vitest.config.ts` is a **separate** config file, not a `test` block merged into
`vite.config.ts` as the original draft assumed — `frontend/vitest.config.ts`'s own header comment explains
why (this project's `vite` resolves to a rolldown-based build whose `Plugin` types conflict with the
non-rolldown `vite` that `vitest/config` bundles internally; `vue-tsc` would flag it as a type error if
merged). Functionally equivalent (`environment: 'jsdom'`, `globals: false`, `setupFiles`, v8 coverage) — just
two files instead of one.

**Installed 2026-07-16, confirmed MIT** — covers Day/Week/Month/List in full:

| Package | Version | License (verified via `npm pack` + reading the tarball's `LICENSE.md` directly, not just the registry's `license` field) | Why |
|---|---|---|---|
| `@fullcalendar/core` | `6.1.21` | MIT | FullCalendar engine, required by every other FullCalendar package |
| `@fullcalendar/vue3` | `6.1.21` | MIT | Official Vue 3 wrapper component |
| `@fullcalendar/daygrid` | `6.1.21` | MIT | Month view (`dayGridMonth`) |
| `@fullcalendar/timegrid` | `6.1.21` | MIT | Day/Week views with an hourly time grid (`timeGridDay`/`timeGridWeek`) — needed for realistic appointment-duration visualization, which `dayGrid` alone can't show |
| `@fullcalendar/interaction` | `6.1.21` | MIT | `dateClick`/`select`/`eventClick` — required for "click empty slot → open New Appointment" and "click appointment → open details" |

**Correction — the original draft's licensing claim was wrong, caught by this document's own "verify at
implementation time" instruction:** `@fullcalendar/resource` and `@fullcalendar/resource-timegrid` are
**not** MIT. Both ship a `LICENSE.md` stating they're part of "FullCalendar Premium," tri-licensed as (a) a
**paid Commercial License**, (b) **Creative Commons BY-NC-ND** (non-commercial use only — not usable by a
commercial SaaS product), or (c) **GPLv3** (strong copyleft — not compatible with a closed-source commercial
codebase without releasing the conjoined work under GPLv3 too). None of the three is "use it for free in a
commercial product," unlike the rest of the FullCalendar set above. This directly affects the **Dentist
Schedule (resource) view** (§2.1's `resourceTimeGridDay`), which is the only feature in this design that
needs these two packages — nothing else does. **Not installed; a decision on how to proceed is needed before
this specific view is built** — see the open item added to §20/closing summary below. Day/Week/Month/List
views are unaffected and already fully buildable with the confirmed-MIT set above.

No other packages are introduced. Specifically **not** added: a validation library (the existing manual
`reactive()` + server-422-driven-errors pattern from `PatientFormDialog.vue` is reused, not replaced — see
§1.5), a state-machine library (the tiny status graph is a plain lookup table, not worth a dependency), a
drag-and-drop library (out of V1 scope per the backend design doc §25 — FullCalendar's own interaction
plugin is enough to wire this up later without a new dependency).

---

## 1. Overall User Experience

### 1.1 User flows

**Primary flow — booking a walk-in/phone appointment (receptionist/admin):**
1. Land on `/appointments` (Board view, today, all dentists).
2. Click an empty slot on the desired dentist's calendar (or click "New Appointment").
3. Dialog opens, prefilled with the clicked dentist + time if applicable.
4. Search for the patient (or create one inline without leaving the dialog).
5. Pick appointment type (duration auto-fills), confirm/adjust date-time and duration.
6. Optionally check "Available Slots" if unsure of a free time.
7. Submit. If a soft conflict (patient double-booked / outside working hours) is returned, review the
   warning and either adjust or explicitly "Book Anyway." A dentist double-booking is a hard stop — must
   change dentist or time.
8. Toast confirms creation; dialog closes; the new appointment appears on the board immediately.

**Day-of-visit flow (front desk, repeated dozens of times a day):**
1. Patient arrives → click their appointment on the board → "Check In" (one click, no dialog reopen).
2. Dentist ready → "Start" (available to front desk or the treating dentist).
3. Visit ends → "Complete."
This is why one-click status actions exist on the details panel/dialog instead of requiring the full edit
form (`PROJECT_CONTEXT.md`'s "Minimal Clicks" principle, and the backend design doc §15 explicitly calls
this out).

**Dentist's flow (read-mostly):**
1. Land on `/appointments`, switch to Dentist Schedule View or filter the board to themself.
2. See the whole clinic's day for situational awareness (clinic-wide read, §14 of the backend design).
3. Progress their own patients through `checked_in → in_progress → completed` from the details panel.

**Schedule setup flow (admin, infrequent):**
1. `/appointments/schedule` → pick a dentist → edit their weekly working-hours template and/or add time off.

**Type configuration flow (admin, rare):**
1. `/appointments/types` → CRUD appointment types (name, duration, color, active).

### 1.2 Navigation flow — **already shipped, differently from the original draft**

The original draft proposed in-page tabs for Types/Schedule; the frontend-infrastructure step instead
already wired them as **sidebar nav children** (`frontend/src/config/navigation.ts`), rendered one level deep
by `AppSidebarItem.vue`. This is the real, current structure — not a proposal:

```
AppSidebar (config/navigation.ts)
 ├─ Dashboard    (routeName: 'dashboard')
 ├─ Patients     (routeName: 'patients')
 ├─ Appointments (routeName: 'appointments' — clicking the parent itself goes to the Board)
 │   ├─ Calendar   → routeName: 'appointments'        (appointments.nav.board)
 │   ├─ Types      → routeName: 'appointment-types'   (appointments.nav.types)
 │   └─ Schedule   → routeName: 'dentist-schedule'    (appointments.nav.schedule)
 ├─ Dental Chart / Treatment Plans / Billing / Reports  (comingSoon: true, disabled)
 ├─ Users        (routeName: 'users', roles: ['admin'])
 └─ Settings     (comingSoon: true, disabled)
```

`nav.appointments` and the three `appointments.nav.*` keys are already live i18n keys, already wired. No nav
work remains for this module — the only thing this document adds here is deciding whether the *route*
should also be role-gated (see the revised §1.3/§1.10 below), since today the sidebar visually hides nothing
extra for Types/Schedule beyond the parent's normal visibility to every role.

**Consequence for `PatientDetailView`'s new Appointments panel and Dashboard widgets**: unaffected by any of
the above — they're existing pages gaining new content, not new nav entries, exactly as originally designed.

### 1.3 Screen hierarchy — route guard revised (new capability didn't exist in the original draft)

| Route (`name`) | Component | Access | Route-level guard |
|---|---|---|---|
| `appointments` → `/appointments` | `AppointmentsView.vue` | all roles (Board read-only for dentists beyond own-appointment actions; write actions gated per §14) | none needed — every role has a legitimate reason to open the Board |
| `appointment-detail` → `/appointments/:id` | `AppointmentDetailView.vue` | all roles | none |
| `appointment-types` → `/appointments/types` | `AppointmentTypesView.vue` | admin only | **recommend adding `meta: { roles: ['admin'] }`**, mirroring the `users` route's precedent (`router/index.ts:38`) |
| `dentist-schedule` → `/appointments/schedule` | `DentistScheduleView.vue` | admin (any dentist); dentist (own time-off only) | **recommend adding `meta: { roles: ['admin', 'dentist'] }`** — a receptionist has no task on this screen per §14's permission matrix (working-hours edits are admin-only; time-off is admin-any/dentist-own) |
| `/patients/:id` (existing) | `PatientDetailView.vue` gains an Appointments panel | all roles (read); write follows §14 | none (unchanged) |
| `/` (Dashboard, existing) | `DashboardView.vue` gains Appointments widgets | all roles | none (unchanged) |

**Why this changed from the original draft:** at the time the draft was written, no route in this codebase
had role-based guarding at all, so the plan was necessarily page-level-only ("the page component checks the
role and renders a `403`-style empty state"). Since then, the Application Shell step built a real, tested
`meta.roles` + `router.beforeEach` + `ForbiddenView.vue` mechanism and already uses it for `users` (`meta: {
roles: ['admin'] }`, `router/index.ts:38`). Today, `appointment-types`/`dentist-schedule` have **no**
`meta.roles` set — they're reachable by any authenticated role by URL, with no guard at all yet (not even the
page-level check the original draft assumed would exist by default). Adding the two `meta.roles` entries
above is a two-line router change, consistent with the one precedent that already exists, and is recommended
as part of this module's implementation rather than left as page-level-only UX gating. The page components
still separately enforce the finer-grained rule that a `dentist` role user only ever sees **their own**
working-hours/time-off inside `DentistScheduleView` (no dentist selector shown to them) — that part of the
original design is unchanged, since `meta.roles` only gates "which roles reach this route at all," not
"which dentist's data a given dentist-role user sees."

### 1.4 Component hierarchy (high level)

```
AppointmentsView
 ├─ CalendarToolbar          (view switcher, date nav, "New Appointment" button)
 ├─ CalendarFilters          (dentist / status / type / patient / search)
 ├─ AppointmentCalendar (generic, reusable FullCalendar wrapper — Day/Week/Month/Dentists; presentational, no store access — see §2.12)
 │   └─ AppointmentEventContent (per-event custom render slot)
 ├─ AppointmentListTable     (DataTable — List toggle, reuses CalendarFilters' filtered dataset)
 └─ AppointmentDialog        (Create/Edit — Patient / Appointment / Notes tabs)
     ├─ PatientSearchSelect  (+ opens the EXISTING PatientFormDialog.vue for inline creation)
     ├─ DentistSelect
     ├─ AppointmentTypeSelect
     ├─ DurationInput
     ├─ SlotPicker
     └─ ConflictAlert

AppointmentDetailView
 ├─ AppointmentCard          (summary header)
 ├─ AppointmentActionsBar    (Confirm/CheckIn/Start/Complete/Cancel/NoShow)
 ├─ AppointmentTimeline      (status timestamps)
 ├─ FutureFeaturePlaceholder (Audit History slot — no backend route yet, see §4.2)
 └─ FutureFeaturePlaceholder × 4 (Treatment Plan / Invoices / Clinical Notes / Attachments)

DentistScheduleView
 ├─ DentistSelect
 ├─ WorkingHoursEditor
 │   └─ WorkingHoursDayRow × 7
 └─ TimeOffCalendar
     └─ TimeOffFormDialog

AppointmentTypesView
 ├─ (DataTable, inline — mirrors UsersView's inline-table convention)
 └─ AppointmentTypeFormDialog

PatientDetailView (existing, extended)
 └─ PatientAppointmentsPanel  (NEW panel/tab, reuses AppointmentListTable + opens AppointmentDialog prefilled with this patient)

DashboardView (existing, extended)
 ├─ TodayScheduleWidget       (today's appointments, waiting/late highlighting)
 ├─ UpcomingAppointmentsWidget
 └─ Quick action buttons (New Appointment / Check In next)
```

### 1.5 State management strategy

**Deliberate divergence from the Patients/Users precedent, called out explicitly:** Patients and Users have
no Pinia store — views call `lib/api.ts` directly. Appointments does add stores, because unlike Patients/
Users, the same appointment data and filter state must be shared **live** across genuinely different views
in the same session (Board ↔ List toggle without losing filters, the Dialog needs to patch the same cache
the Board renders from, the Dashboard widgets need "today's appointments" without a redundant fetch if the
user already has the board open, Patient Detail's panel needs the same store). This is sanctioned by
`coding-standards.md`'s "one store per module as needed" clause — it's additive to the convention, not a
replacement of it (Patients/Users are correctly left as-is).

Six new stores (detailed in §10): `appointments`, `appointmentTypes`, `workingHours`, `timeOff`, `calendar`
(pure UI state: view mode, current date, filters — no API calls), and `providers` (see §11's flagged gap:
there is no dedicated "list dentists/providers" endpoint, so this store paginates through the existing `GET
/api/users` once and caches the `role === 'dentist'` subset for the session — see §10's note on why this
store is deliberately **not** named `dentists.store.ts`).

**A second, thin layer sits between the stores and `lib/api.ts`: API Services** (`frontend/src/services/
appointments/`, detailed in §11.1). Components call store actions; store actions call typed service
functions; service functions are the only code touching the shared `api` axios instance for this module.
This is a deliberate, additive refinement over the Patients/Users precedent (which has neither a store nor a
service layer — views call `api.ts` directly) and is reflected in the architecture diagram in §19: `Vue Pages
→ Reusable Components → Pinia Stores → API Services → Laravel API`. The service layer exists so that (a) HTTP
request/response shaping (query params, payload typing, response unwrapping) is testable and mockable
independently of Pinia's reactivity, and (b) stores stay focused on **state** (caching, merging, eviction)
rather than also owning **HTTP mechanics** — see §10 for the exact boundary each store is documented against.

Form-local state (the dialog's `reactive()` form object, per-field validation errors) stays local to
`AppointmentDialog.vue`, exactly like `PatientFormDialog.vue` — no store involvement for transient form
state, only for the persisted domain data.

### 1.6 API integration strategy

See §11 for the full endpoint-to-action map. Summary of the approach:
- The Board/List/Dashboard all read from **one shared range-keyed cache** in `appointments.ts` —
  fetching a date range merges into the cache rather than replacing it, so switching Day→Week→Month doesn't
  discard already-loaded data outside the new range unnecessarily (see §13 for cache-eviction policy).
- Every mutation (create/update/reschedule/status-transition) is followed by a single `GET
  /api/appointments/{id}` to re-hydrate the eager-loaded `patient`/`dentist`/`appointment_type` fields,
  because those endpoints' responses omit them (a real gap found during research — see §11's flagged note).
  This keeps the fix in one place (the store) instead of every call site re-deriving nested data manually.
- 409/422 conflict responses (`code`/`overridable`/`override_field`) are surfaced by store actions as a
  typed `AppointmentConflictError` (see §12) that `ConflictAlert.vue` renders and offers to resolve via
  resubmission with the override flag — this is new error-handling surface area no prior module needed.

### 1.7 Loading states

- Board: FullCalendar's own event-source loading indicator (`loading` callback prop) plus a lightweight
  top-of-toolbar `ProgressBar` (indeterminate) during range fetches — the grid itself doesn't unmount/skeleton
  on refetch (avoids jarring flicker when just paging Day→Day).
- List: `DataTable :loading` prop, same as `PatientsView`/`UsersView`.
- Dialog: `saving` ref disables the Save button and shows a spinner icon, exactly like `PatientFormDialog`;
  `SlotPicker` has its own local `loadingSlots` ref (skeleton chips) independent of the dialog's save state.
- Detail view: `Skeleton` placeholders before the appointment loads, mirroring `PatientDetailView`.
- Dashboard widgets: `Skeleton` rows while loading, each widget independently loading (one widget's slow
  fetch never blocks another's render).

### 1.8 Empty states

- Board: an empty day/week still renders the grid (an empty calendar is informative — "nothing booked"),
  no separate empty-state graphic needed (matches how a physical appointment book looks empty).
- List: PrimeVue DataTable's default "No records found," localized.
- Filters yielding zero results: a small inline message above the grid/table, "No appointments match your
  filters" + a "Clear filters" link, distinct from "no appointments exist at all" (avoids the ambiguity of
  a plain empty grid when the user might think the feature is broken vs. their filter is just narrow).
- Dashboard widgets: "No appointments today" / "No upcoming appointments" with a "New Appointment" quick
  action, so the empty state is actionable, not a dead end.
- Working Hours: a dentist with zero configured shifts shows all 7 days as "Not set" with an inline "Add
  shift" affordance per day, not a blocking error.
- Time Off: empty list + "Add time off" call to action.

### 1.9 Error states

- Field-level validation errors (422 `errors: {field: [...]}`) render inline under each field via
  PrimeVue `Message`, identical to `PatientFormDialog`'s pattern.
- Conflict errors (409/422 `code`/`overridable`) render via `ConflictAlert.vue` inside the dialog — a
  distinct visual treatment from plain validation errors (a warning-colored banner with an explicit
  secondary action), never just another red field message, since these represent a business decision the
  user must make, not a typo to fix.
- Network/unexpected errors: generic toast (`t('appointments.saveError')` etc.), same pattern as existing
  modules — no raw error text ever shown to the user.
- 403 responses (attempting an action the UI should have already hidden — e.g., a stale tab where a role
  changed) fall back to a toast + the UI re-syncing (`auth.fetchUser()` + re-render) rather than a crash.
- If the Board's range fetch itself fails (not a mutation, the initial load), show an inline retry state in
  place of the calendar grid — a broken calendar with no data and no way to retry is a dead end.

### 1.10 Permission handling — **already implemented**, plus a new route-guard layer that didn't exist before

**Already shipped, not still to-do:** `auth.ts` already has the three getters this section originally
proposed adding, verbatim (`frontend/src/stores/auth.ts:12-15`), including a doc-comment on
`canManageAppointments` explicitly citing this design doc's §1.10:

```ts
const isDentist = computed(() => user.value?.role === 'dentist')
const isReceptionist = computed(() => user.value?.role === 'receptionist')
const canManageAppointments = computed(() => isAdmin.value || isReceptionist.value)
```

Ownership checks (`start`/`complete` open to the treating dentist for their own appointment) remain plain
inline comparisons at the point of use (`auth.user?.id === appointment.dentist_id`), exactly like the
existing `canManage`/`canDelete` local-boolean convention — not centralized, matching precedent. Nothing to
build here.

**What genuinely changed since the original draft:** the claim "no route meta, no permission
directive/composable exists anywhere in this codebase" is no longer true. `router/index.ts` now has a typed
`RouteMeta.roles?: UserRole[]` plus a `router.beforeEach` guard that redirects to a new `ForbiddenView.vue`
when the current user's role isn't in `to.meta.roles` — already used for `/users`. §1.3 above updates the
route table to recommend using this same mechanism for `/appointments/types` and `/appointments/schedule`,
instead of relying on page-level-only gating as the sole line of defense. The last paragraph below (frontend
checks are UX only, API Policies are the real boundary) is unchanged and still the governing principle —
adding `meta.roles` doesn't change that; it just means an unauthorized user gets redirected before the page
even mounts, rather than seeing the page briefly render a "not authorized" state.

The frontend's role checks remain pure UX (hiding buttons/routes a call would 403 on) — the API's Policies
are the real enforcement boundary, exactly as `docs/api-guidelines.md` states. No new security surface is
created by getting a frontend check wrong; worst case is a hidden button or reachable route becomes visible
and the API still rejects.

### 1.11 Mobile behavior

- `CalendarToolbar`/`CalendarFilters` collapse into a single overflow `Menu` (PrimeVue) below a `sm`
  breakpoint — showing 5+ toolbar controls plus 5 filters inline doesn't fit a phone width.
- FullCalendar's Month/Week views become horizontally scrollable within their container below `md` (rather
  than shrinking text illegibly) — Day view is the practical default on mobile and is auto-selected when
  the viewport is narrow on first load (still user-overridable).
- The Dentist Schedule View (resource columns) is explicitly desktop-oriented — many dentist columns don't
  fit a phone; on narrow viewports it's hidden from the view switcher in favor of Day/Week/Month/List, with
  a note that it's a tablet/desktop feature (consistent with it being a front-desk power-user tool, not
  something staff need one-handed).
- `AppointmentDialog` becomes a full-screen PrimeVue `Dialog` (`:breakpoints` / Tailwind responsive classes)
  below `sm`, matching how `PatientFormDialog` already behaves.
- List view's `DataTable` uses PrimeVue's responsive stacking (`responsiveLayout="stack"` or the v4
  equivalent) below `sm`, same as `PatientsView`.
- Dashboard widgets stack vertically (already Tailwind's default flex/grid behavior at narrow widths, no new
  pattern needed).

### 1.12 Accessibility considerations

Summarized here; full detail in §14.

### 1.13 Performance considerations

Summarized here; full detail in §13.

---

## 2. Calendar Screen

`AppointmentsView.vue` hosts a `CalendarToolbar` + `CalendarFilters` + a body that toggles between
`AppointmentCalendar` (FullCalendar) and `AppointmentListTable` (DataTable), sharing the same
`calendar.ts` filter state so switching views never loses context.

### 2.1 View modes (FullCalendar `initialView` mapping)

| UI label | FullCalendar view | Plugin |
|---|---|---|
| Day | `timeGridDay` | `@fullcalendar/timegrid` |
| Week | `timeGridWeek` | `@fullcalendar/timegrid` |
| Month | `dayGridMonth` | `@fullcalendar/daygrid` |
| Dentists | `resourceTimeGridDay` | **On hold** — `@fullcalendar/resource-timegrid` turned out to require a paid FullCalendar Premium commercial license (or GPLv3/non-commercial terms, neither viable here), not MIT as originally assumed (see the corrected "New Dependencies" section above). Not built until that's resolved. |
| List | *(not a FullCalendar view)* `AppointmentListTable.vue`, a `DataTable` | — |

**Interim behavior while the Dentists (resource) view is on hold**: the front desk gets the same
"see everyone at once" capability today via the Board's Dentist multiselect filter (§2.7) left empty
(shows all dentists' appointments together on Day/Week) — not a one-column-per-dentist layout, but not a
capability gap either, since every appointment still shows which dentist it belongs to via
`AppointmentEventContent`. The `resourceTimeGridDay` toggle itself is simply not offered in
`CalendarToolbar.vue` until the licensing decision is made.

### 2.2 Time Grid

Day/Week/Dentist views use `slotDuration: '00:15:00'` (matches the backend's default
`appointments.slot_interval_minutes` config, so the visual grid lines up with real bookable slots),
`slotMinTime`/`slotMaxTime` computed from the union of all visible dentists' working hours (padded by an
hour on each side) rather than hardcoded `08:00`–`18:00`, so a clinic with early/late hours isn't clipped.

### 2.3 Color-coded appointments by status

- Base color = `appointment_type.color` (`backgroundColor`/`borderColor` on the FullCalendar event object).
- `cancelled`/`no_show` get an additional CSS class (`fc-event-cancelled`/`fc-event-no-show` via the
  `classNames` callback) applying reduced opacity + a strikethrough on the event title — visible but clearly
  "not live," per the backend design doc §15's explicit requirement that history stay legible, not hidden.
- `AppointmentStatusChip.vue` (small colored chip + i18n label) is used consistently everywhere a status is
  shown outside the calendar grid itself (List view column, Detail header, Dashboard widgets) — one
  component, one source of truth for status→color mapping (see §12's `APPOINTMENT_STATUS_COLORS` map).

### 2.4 Working Hours overlay

FullCalendar's native `businessHours` option, built from the currently-filtered dentist's
`dentist_working_hours` rows (`workingHours.ts`), converting `day_of_week`/`start_time`/`end_time`
into FullCalendar's `{ daysOfWeek, startTime, endTime }` shape. Only rendered when exactly one dentist is
selected in the filters (business hours differ per dentist — shading a union of multiple dentists' hours
would be meaningless/misleading). In the Dentist Schedule (resource) view, each resource column gets its
own `businessHours` scoped to that dentist, since FullCalendar supports per-resource business hours
natively — this is the one view where "all dentists" and "working hours shading" aren't mutually exclusive.

### 2.5 Time Off overlay

Rendered as FullCalendar **background events** (`display: 'background'`), fetched from `timeOff.ts`
for whichever dentist(s) are in view, with a distinct fill (a subtle diagonal-stripe pattern via a CSS
`background-image`, red-tinted) so it reads as "unavailable" without being confused with a booked
appointment.

### 2.6 Current time indicator

`nowIndicator: true` (native FullCalendar option, Day/Week/Dentist views only — Month view has no time axis
for it to make sense on).

### 2.7 Filters

`CalendarFilters.vue`: Dentist (multiselect, `providers.ts` — see §10), Status (multiselect,
`APPOINTMENT_STATUSES`), Appointment Type (multiselect, `appointmentTypes.ts`), Patient (the same
`PatientSearchSelect.vue` typeahead used in the dialog, single-select here). All filters live in
`calendar.ts` and apply identically to both Board and List (the Board applies them client-side against
the already-fetched range — see §13 — the List view's underlying data is the same cached range).

### 2.8 Search

The Patient filter above *is* the search entry point (search-as-you-type against `GET
/patients?search=`, reusing the existing Patients search endpoint/UX per the backend design doc §15) —
there's no separate free-text "search appointments" box, since every meaningful search dimension (patient,
dentist, status, type) already has its own filter. This avoids building a second, redundant search
mechanism.

### 2.9 Pagination

The Board never paginates (a calendar range is the natural unit, per the backend's deliberate no-pagination
design for this endpoint — §11). The **List view** does client-side pagination of the already-fetched range
(PrimeVue `DataTable` with `:paginator="true" :rows="20"`, no `lazy`/`@page` server round-trip) — this is
the one place the existing `PatientsView`/`UsersView` lazy-paginated-DataTable pattern does **not** apply
verbatim, because (unlike Patients/Users) there is no server-side pagination to hook into for this endpoint
(confirmed via the backend research — flagged explicitly as a known divergence, not an oversight).

### 2.10 Keyboard shortcuts

| Key | Action | Scope |
|---|---|---|
| `N` | Open "New Appointment" dialog | Board/List focused, no dialog open |
| `←` / `→` | Previous/next period (day/week/month depending on active view) | Board focused |
| `T` | Jump to today | Board focused |
| `1` / `2` / `3` / `4` / `5` | Switch to Day / Week / Month / Dentists / List | Board/List focused |
| `Esc` | Close open dialog | Dialog open |
| `/` | Focus the Patient filter search box | Board/List focused |

Implemented via a single `useCalendarKeyboardShortcuts()` composable-like `onMounted`/`onUnmounted` key
listener in `AppointmentsView.vue`, ignoring keystrokes while any input/textarea/dialog has focus (never
hijacks typing). Documented in a small `?`-triggered `Dialog` shortcut-help overlay (`KeyboardShortcutsHelp`
— optional nice-to-have, not in the core Component Inventory count, can be deferred to Polish/Step 9 if time
is tight).

### 2.11 Responsive layout

Covered in §1.11.

### 2.12 `AppointmentCalendar.vue` — a genuinely reusable, decoupled wrapper

Per the required revision, FullCalendar is not wired directly into `AppointmentsView.vue` — it's isolated
behind one presentational component, `AppointmentCalendar.vue`, with no store imports and no `lib/api.ts`
usage of its own:

```ts
// Props in — everything AppointmentCalendar needs is handed to it, it fetches nothing itself
interface AppointmentCalendarProps {
  view: CalendarViewMode                 // 'timeGridDay' | 'timeGridWeek' | 'dayGridMonth' | 'resourceTimeGridDay'
  currentDate: Date
  events: AppointmentCalendarEvent[]     // pre-mapped from Appointment[] by the parent, not raw Appointment[]
  backgroundEvents: AppointmentCalendarEvent[] // time-off overlay, pre-mapped
  businessHours?: BusinessHoursInput     // undefined when no single dentist is selected (§2.4)
  resources?: CalendarResource[]         // dentist columns, only used when view === 'resourceTimeGridDay'
  slotMinTime: string
  slotMaxTime: string
  loading: boolean
}

// Emits out — AppointmentCalendar never mutates anything itself
interface AppointmentCalendarEmits {
  (e: 'event-click', appointmentId: string): void
  (e: 'date-click', payload: { start: Date; resourceId?: string }): void
  (e: 'range-change', payload: { start: Date; end: Date }): void  // parent decides whether/what to fetch
}
```

`AppointmentsView.vue` (and, later, any other page that wants a calendar — e.g. a future per-dentist "My Day"
view) owns the store wiring: it reads `appointments.ts`/`timeOff.ts`/`workingHours.ts`/`calendar.ts`, maps
domain models into the plain `AppointmentCalendarEvent[]` shape the component expects, and reacts to the
component's emitted events by calling store actions or opening `AppointmentDialog`. This means
`AppointmentCalendar.vue` itself has zero knowledge of "appointments," "dentists," or any Appointments-module
concept beyond a generic events/resources/business-hours contract — it could be dropped into an unrelated
future screen (e.g. a hypothetical staff-shift calendar) unchanged. All FullCalendar-specific concerns
(plugin registration, `classNames` callbacks for status styling, the luminance-based text-color helper from
§14, `nowIndicator`, `dayMaxEvents`) live entirely inside this one component, not leaked into the page.

### 2.13 Future support (designed for, not built)

- **Multi-clinic**: `calendar.ts`'s filter shape reserves room for a future `branch_id` filter the same
  way the backend reserves a future `branch_id` column (§7 of the backend design) — no rework needed, just
  an additive filter + query param once branches exist.
- **Multiple dentists**: already fully supported today (the Dentist multiselect filter, the resource view).
- **Resource scheduling** (chairs/operatories): `@fullcalendar/resource-timegrid` is already resource-generic
  — today's "resource" is a dentist; adding a `chair` resource dimension later is a matter of changing what
  populates the `resources` array, not a UI rewrite, if/when the backend's deferred chairs table (§8 of the
  backend design) is built.

---

## 3. Appointment Dialog

`AppointmentDialog.vue` — a shared component (`v-model:visible` + optional `:appointment` prop for edit
mode + optional `:prefill` prop for `{dentist_id?, start_at?, patient_id?}` when opened from a calendar
slot-click, a patient's detail panel, or the dashboard's quick-action button), following the
`PatientFormDialog.vue` shared-component convention identified in the research (not the `UsersView` inline
pattern), since it's reused from at least four call sites (Board, List, Patient Detail, Dashboard).

### 3.1 Tabs

PrimeVue `Tabs`/`TabPanel`:

1. **Patient** — `PatientSearchSelect` (typeahead against `GET /patients?search=`), selected-patient summary
   card once chosen, "Create New Patient" button. Locked (read-only, shows the existing patient) once an
   appointment already has a patient assigned in edit mode — `patient_id` is **not editable** via `PUT
   /appointments/{id}` per the backend design (booking the wrong patient is a soft-delete-and-rebook case,
   not a correction), so the UI must not offer to change it on an existing appointment. A visible note
   explains why ("To change the patient, cancel this appointment and create a new one").
2. **Appointment** — `DentistSelect`, `AppointmentTypeSelect` (auto-fills `DurationInput` on change, but only
   if the user hasn't manually edited duration yet in this session — tracked via a local `durationTouched`
   flag, so picking a type never silently overwrites a duration the user just typed), date picker + time
   input (combined into `start_at`), `DurationInput`, live-computed end-time display (`start + duration`,
   client-side only, purely informational — the server remains the source of truth per the backend's
   "`end_at` always server-computed" rule), a "Show Available Slots" toggle revealing `SlotPicker`,
   `ConflictAlert` (renders when a submit attempt returns a 409/422 conflict).
3. **Notes** — `reason` (Textarea, chief complaint), `notes` (Textarea, internal), and a **read-only** status
   chip (`AppointmentStatusChip`) with a note that status changes happen via the action buttons on the
   Detail view/panel, not this dialog — status is not part of `StoreAppointmentRequest`/
   `UpdateAppointmentRequest` at all.

### 3.2 Patient Search

`PatientSearchSelect.vue`: debounced (300ms, matching the existing `PatientsView` debounce convention)
typeahead against `GET /patients?search=`, rendering `patient_code` + `full_name` + `phone` per result
(enough to disambiguate common names). Selecting sets the form's `patient_id` and shows a compact summary
card (name, code, phone, DOB-derived age) below the input, dismissible/re-searchable via an "×" to change
selection (only in create mode — locked in edit mode, per §3.1).

### 3.3 Create Patient Inline

A "Create New Patient" button next to the search box opens the **existing** `PatientFormDialog.vue`
component directly (imported from `components/patients/`, not duplicated) in create mode, stacked as a
nested `Dialog` on top of `AppointmentDialog`. On its `@saved` emit, the newly-created patient is
auto-selected in `AppointmentDialog`'s Patient tab and focus moves to the Appointment tab — avoids
maintaining two separate patient-creation forms (violates "no duplicated code"), and reuses
`PatientFormDialog`'s already-correct validation/localization/timezone-safe date handling as-is.

### 3.4 Appointment Type

`AppointmentTypeSelect.vue`: `Select` sourced from `appointmentTypes.ts` (cached after first fetch —
this is small, rarely-changing clinic configuration data, not worth refetching per dialog open), showing a
small color swatch + name per option (`is_active === false` types excluded from the list, since
`StoreAppointmentRequest` requires `is_active`, but still resolvable/displayed if an *existing* appointment
references a since-deactivated type — see §3.1's edit-mode handling).

### 3.5 Dentist

`DentistSelect.vue`: sourced from `providers.ts` (see §10/§11's gap note — paginated client-side from
`GET /api/users`, cached for the session), rendered as name only (no avatars in V1 — Users module has no
photo/avatar field to source one from).

### 3.6 Available Slots

`SlotPicker.vue`: appears once dentist + type/duration are chosen. Calls `GET /api/available-slots` with
the current `dentist_id`/`date`/`duration_minutes`. Computes the *full* range of candidate slot times for
that day from `workingHours.ts` (same 15-minute granularity as the backend's
`slot_interval_minutes` default) and cross-references against the endpoint's returned `slots` array —
returned slots render as clickable chips, all other times in the working-hours range render disabled/muted
("outside hours or busy," not distinguished further — the backend doesn't tell us *why* a slot is
unavailable, only that it isn't in the list, so the UI doesn't fabricate a reason it doesn't have). Clicking
a chip sets `start_at` and switches focus back to the date/time fields so the user sees exactly what was
picked, not a silent background change.

### 3.7 Conflict Preview

Before submitting, if both dentist and start/duration are set, `SlotPicker`'s already-fetched slot list
doubles as a quiet, non-blocking hint (the chosen time not being in the available list is a visual cue —
muted/unchecked styling — but does **not** block submission client-side); the authoritative conflict check
always happens server-side on submit (§3.8) — this is a UX hint, not a duplicate of business logic on the
frontend, consistent with `docs/architecture.md`'s "Vue Pages consume the API, no direct business rules"
principle.

### 3.8 Soft Patient Conflict Warning / Hard Dentist Conflict Block

`ConflictAlert.vue`, driven by the store action's thrown `AppointmentConflictError` (§12):

- `code: 'dentist_conflict'` → red/danger banner, **no** override action, submit button stays disabled until
  the dentist or time is changed (this exception has no `overridable` key at all per the backend, so the UI
  must not offer one).
- `code: 'patient_conflict'` / `code: 'outside_working_hours'` → amber/warning banner with the exception
  `message` plus a "Book Anyway" button that resubmits the exact same payload with
  `{ [error.override_field]: true }` merged in (the field name comes from the API response, not
  hardcoded per-code, so the frontend doesn't need its own copy of which override flag maps to which code).
- `code: 'early_no_show'` is not reachable from this dialog (only from the No-Show action on the Detail
  view, §4) but shares the same `ConflictAlert` component for consistency there.

### 3.9 Duration

`DurationInput.vue`: PrimeVue `InputNumber`, bounded `5`–`480` (mirrors backend validation exactly, fails
fast client-side before ever hitting the server for an out-of-range value), step `5`, suffix "min."

### 3.10 Reason / Notes

Plain `Textarea` (`autoResize`, matching `PatientFormDialog`'s convention), `reason` capped 1000 chars,
`notes` capped 2000 chars (mirroring backend limits, with a live character counter past 80% of the cap).

### 3.11 Status

Read-only display only, per §3.1 — never an editable field in this dialog.

### 3.12 Validation

Client-side mirrors of the backend's shape rules (required patient/dentist/type/start_at, duration 5-480)
fire on blur/submit for fast feedback; the true validation source is still the server's 422 response, mapped
field-by-field into `errors: Record<string, string[]>` exactly like `PatientFormDialog`. No validation
library — same manual pattern as the rest of the codebase (§ "New Dependencies" explicitly does not add
one).

### 3.13 Loading

`saving` ref (Save button spinner + disabled state), `SlotPicker`'s own `loadingSlots` ref, `PatientSearchSelect`'s own debounced-search loading state — three independent loading indicators, none blocking the others (searching for a patient shouldn't freeze the duration field, etc.).

### 3.14 Confirmation

On success: toast (`t('appointments.saved')`), dialog closes, the store's post-mutation `GET
/appointments/{id}` re-hydration (§1.6) runs, and the Board/List re-render from the updated cache — no full
page reload, no full-range refetch needed for a single created/edited row.

---

## 4. Appointment Details Screen

`AppointmentDetailView.vue`, reached from clicking a calendar event, a List row, or a Dashboard widget row.

### 4.1 Timeline

`AppointmentTimeline.vue`: a vertical PrimeVue-styled stepper (not a heavy dependency — a small custom
component using `Card`/flex + Tailwind, consistent with how the rest of the app avoids extra UI-kit
dependencies for simple layouts) showing, in order: Scheduled (`created_at`) → Confirmed (if reached) →
Checked In (`checked_in_at`) → In Progress (`started_at`) → Completed (`completed_at`), each step showing
its timestamp once reached or greyed out if not yet reached. If the appointment is `cancelled` or `no_show`,
the chain visually terminates at that point with a distinct red/amber marker instead of continuing the happy
path — never shows a "Completed (pending)" ghost step for a cancelled appointment, which would be
misleading.

### 4.2 Audit History — **resolved by direct verification, no longer conditional**

The original draft treated this as a "verify-first" open item because the backend research hadn't confirmed
whether an audit-log route existed for Appointments. This revision checked directly against the current
backend code, so the decision below is now definitive, not conditional:

- **`Appointment` does use the `Auditable` trait** (`backend/app/Models/Appointment.php:6,19` — `use
  Auditable, HasFactory, HasUuids, SoftDeletes;`), so every create/update/status-transition is already being
  recorded in `audit_logs`, exactly like `Patient`. The data exists.
- **No route exposes it.** `backend/routes/api.php` has no `appointments/{appointment}/audit-logs` route (the
  Patients equivalent is `GET /api/patients/{patient}/audit-logs`, `routes/api.php:25`, admin-only via
  `PatientPolicy::viewAuditLogs`). `AppointmentController` has no `auditLogs()` method, and
  `AppointmentPolicy` has no `viewAuditLogs` ability. Confirmed absent, not just unconfirmed.

**Decision: build `FutureFeaturePlaceholder.vue` in this slot now — do not build `AppointmentAuditHistory.vue`
in this phase.** The conditional "verify then maybe build" branch from the original draft collapses to its
placeholder branch, definitively:

- Still admin-gated (`v-if="auth.isAdmin"`, mirroring `PatientDetailView.vue:188`'s exact existing pattern —
  note there's no separate `PatientAuditHistory.vue` component to literally copy either; `PatientDetailView`
  inlines its audit `Card`+`DataTable` directly, so this would be a new extraction either way, not a copy of
  an existing file).
- Labeled clearly ("Audit history — coming soon"), not silently hidden, so admins know the capability is
  planned, not omitted by mistake.
- **A fresh `TECH_DEBT.md` entry is needed** (none exists yet for this — confirmed by reading the current
  file) along these lines: *"Appointment audit-log route not yet exposed — the `Auditable` trait already
  records every change on `Appointment`; only the `GET /api/appointments/{appointment}/audit-logs` route +
  `AppointmentController::auditLogs()` + `AppointmentPolicy::viewAuditLogs` are missing, mirroring the
  Patients pattern exactly. Small, low-risk backend addition — revisit when backend capacity allows; not
  blocking."* This is a materially smaller lift than the original draft implied (no new audit *infrastructure*
  needed, just the read-side route/controller/policy — the write-side capture is already happening today).

### 4.3 Appointment Information

`AppointmentCard.vue` used as the page header: type (color swatch + name), dentist, date/time range,
duration, `AppointmentStatusChip`, reason. Edit button (opens `AppointmentDialog` in edit mode) shown only
if `canManageAppointments` (§1.10) and status isn't terminal (`completed`/`cancelled`/`no_show` — editing a
finished visit's core details doesn't make sense; only status-independent fields like `notes` remain
editable post-completion, handled by simply allowing the edit dialog to open but the backend's own state
rules and this component's button visibility both agree editing terminal appointments is unusual enough to
require explicit unlocking — **decision**: keep Edit available but show a confirming note ("This appointment
is completed — most fields are historical") rather than hard-disabling it, since the backend doesn't
actually block editing `notes`/`reason` on a terminal appointment and the frontend shouldn't invent a
restriction the API doesn't have).

### 4.4 Patient Summary

A compact card (reusing the patient summary card sub-component from `PatientSearchSelect.vue`, extracted as
a small shared `PatientSummaryCard.vue` used by both) — name, code, phone, DOB/age, with a "View full
patient record" link to `/patients/:id`.

### 4.5 Status History

Covered by §4.1 (Timeline) for the operational timestamps and §4.2 (Audit History) for the full field-level
diff trail — deliberately not a third separate "status history" list, since that would just be a filtered
view of the audit log (avoids building the same data three ways).

### 4.6 Actions

`AppointmentActionsBar.vue`, each button a `StatusActionButton.vue` instance, visible/enabled per the
state-machine (§3 of the backend design doc, mirrored client-side purely for **hiding buttons that would
`422 invalid_status_transition`** — never trusted as the real gate, the API still enforces it) crossed with
the role/ownership matrix (§14 of the backend design doc):

| Button | Visible when status is | Additional gate |
|---|---|---|
| Confirm | `scheduled` | `canManageAppointments` |
| Check In | `scheduled`/`confirmed` | `canManageAppointments` |
| Start | `checked_in` | `canManageAppointments` OR own dentist |
| Complete | `in_progress` | `canManageAppointments` OR own dentist |
| Cancel | any non-terminal | `canManageAppointments` |
| No Show | `scheduled`/`confirmed` | `canManageAppointments` |

Cancel and No-Show open a small confirmation dialog with an optional reason textarea (`cancellation_reason`
/ the no-show action's `override_early_no_show` flow, surfaced via the same `ConflictAlert` pattern as §3.8
if attempted before `start_at`); the other four are a plain `useConfirm()` confirmation (matching the
existing delete-confirmation convention) with no extra input, since they carry no optional payload.

### 4.7 Future placeholders

`FutureFeaturePlaceholder.vue` (one small reusable component: icon + title + "Coming soon" caption + the
future module's name), used four times: Treatment Plan, Invoices, Clinical Notes, Attachments — rendered as
disabled-looking `Card`s in a row/grid below the main details, not real tabs with fake content, so it's
honest about what doesn't exist yet rather than simulating functionality (matches the project's "no
placeholders, no TODOs, no temporary implementations" rule for *real* features, while still giving future
modules an obvious, low-effort integration point as the backend design doc's §22 calls for).

---

## 5. Working Hours Management

`DentistScheduleView.vue`'s first tab. `DentistSelect` at the top picks which dentist's schedule is shown
(admin can pick any dentist; a dentist-role user viewing their own account sees only themself, no selector).

- **Weekly editor**: `WorkingHoursEditor.vue` renders 7 `WorkingHoursDayRow.vue` rows (Sunday–Saturday,
  matching the backend's `0=Sunday..6=Saturday` convention exactly — day labels localized via `vue-i18n`,
  not hardcoded English day names).
- **Multiple shifts**: each day row supports N shift rows (start/end `time` pickers), "+ Add shift" appends
  another row within that day — directly maps to `dentist_working_hours` allowing multiple rows per
  `day_of_week` (the backend's lunch-break/split-shift support).
- **Enable/disable day**: a toggle per day row; disabling doesn't delete the shift rows (soft `is_active`
  concept in the UI, though the backend's own `is_active` column is per-row not per-day — disabling a "day"
  in the UI is a bulk-toggle convenience that sets every shift row for that day to `is_active: false`, not a
  new backend concept).
- **Copy schedule**: "Copy to..." action on a day row opens a small multiselect of other days to copy that
  day's full shift list onto — pure client-side convenience (creates N new rows via N `POST` calls), no
  backend support needed or assumed.
- **Validation**: end time after start time (mirrors `StoreDentistWorkingHourRequest`'s `after:start_time`
  rule), overlapping shifts within the same day flagged client-side as a warning before submit (the backend
  doesn't reject overlapping working-hour rows itself — it's a data-quality nicety, not a hard rule, so this
  is a non-blocking inline warning, not a submit-blocking validation error).
- **No Update endpoint** (confirmed from the backend research — only create/delete exist for
  `dentist_working_hours`): editing a shift's time is implemented as delete-old + create-new under the hood,
  wrapped so the UI still feels like an in-place edit (the row's own edit button triggers both calls
  sequentially, shows one combined loading state, and only removes the old row from the local list once the
  new one's create call succeeds — never leaves the UI in a state showing neither).
- **Responsive**: each day becomes a collapsible accordion section below `md` instead of a 7-row grid, since
  7 columns/rows of shift editors don't fit a phone width legibly.

---

## 6. Dentist Time Off

`DentistScheduleView.vue`'s second tab (or a `TabPanel` alongside Working Hours — same page, same dentist
selector, avoids a third route).

- **CRUD**: `TimeOffCalendar.vue` (a simple chronological list, not a mini calendar widget — a list is more
  scannable for what's typically a handful of entries per dentist per year, and avoids building a second,
  smaller calendar-rendering component when the main Board's Time Off overlay, §2.5, already provides the
  calendar visualization). "Add Time Off" opens `TimeOffFormDialog.vue` (start/end datetime pickers, reason
  text).
- **Types (Vacation / Conference / Sick Leave / Emergency)**: the backend's `dentist_time_off.reason` is a
  free-text nullable string, **not** an enum/category column — there is no backend-side "type" concept to
  select from. The frontend adds a **client-side-only** convenience: `reason` is prefixed via a small
  `Select` of common categories (Vacation/Conference/Sick Leave/Emergency/Other) whose chosen label is
  simply written into the free-text `reason` field on submit (e.g., selecting "Conference" + typing "Dental
  Association Summit" produces `reason: "Conference: Dental Association Summit"`). This is a UI-only
  convenience layered on top of an unstructured backend field — explicitly **not** proposing a backend
  schema change for this module; if categorized reporting on time-off type becomes a real need later, that's
  a future backend migration (a real `category` column), not something this frontend design should
  simulate by inventing a coded prefix format the backend doesn't understand structurally. Flagging this
  clearly as a UX-layer-only feature so it isn't mistaken for a backend capability during implementation.
- **Recurring support**: **not built** — the backend schema has no recurrence concept (each `dentist_time_off`
  row is a single start/end range). The UI does not fake recurrence (e.g., no "repeats weekly" checkbox that
  silently creates N individual rows) since that would be surprising to manage/cancel later (cancelling
  "the recurring vacation" would actually require finding and deleting N separate rows with no link between
  them). Genuinely future work per the backend design doc §25 — not attempted here.
- **Conflict visualization**: any appointment(s) that fall inside a proposed time-off range are shown as a
  warning list inside `TimeOffFormDialog` before submit (a client-side cross-reference against the
  `appointments.ts` cache for that dentist/date-range — informational only, since the backend does
  **not** block creating time-off that overlaps existing appointments, nor does it cascade-cancel them; the
  human decides what to do about those appointments).

---

## 7. Appointment Types

`AppointmentTypesView.vue`, admin-only route. Follows the `UsersView.vue` inline-DataTable convention (no
separate list-fetching store beyond the shared `appointmentTypes.ts` used elsewhere) rather than
`PatientsView`'s server-paginated pattern, because the backend's `GET /api/appointment-types` is — like
appointment types generally — a small, unpaginated lookup list (confirmed via the backend research), so
client-side sorting/searching over the full (small) list is both correct and simpler than fake pagination
controls over a dataset that's realistically 5-20 rows for any clinic.

- **CRUD**: `AppointmentTypeFormDialog.vue` (name, default duration, color, active toggle), DataTable with
  inline Edit/Delete actions — mirrors `PatientFormDialog`'s dialog shape and `UsersView`'s inline-actions
  column.
- **Color**: PrimeVue `ColorPicker` bound to a hex string, validated against the backend's
  `^#[0-9A-Fa-f]{6}$` regex client-side before submit.
- **Duration**: same `DurationInput.vue` component reused from the booking dialog (5–480 bound).
- **Price**: **not a backend field** — `appointment_types` has no `price` column (confirmed from the
  migration/model research). The task brief's "Price" bullet is **not implemented** in this design, since
  adding it would require a backend schema change outside this frontend-only module's scope. Flagged
  explicitly rather than silently dropped: if pricing-per-type is a real near-term need, it belongs in the
  Billing module's design (where pricing concepts actually live) or as an explicit, separately-approved
  backend addition to Appointments — not fabricated as a frontend-only field with nowhere real to persist
  it.
- **Default**: also **not a backend field** — no `is_default` column exists. Same treatment as Price: not
  implemented, flagged rather than faked. (If wanted, this is a one-column additive migration + a "set as
  default" action — small, but still a backend change outside this document's scope.)
- **Active**: maps directly to `is_active` (toggle in the form + a column badge in the table + a "Show
  inactive" filter toggle, since inactive types are hidden from booking dropdowns but must remain visible
  here for management/reactivation).
- **Sorting**: client-side column sort (DataTable's built-in `sortable` columns) — name, duration, active
  status.
- **Search**: client-side filter-as-you-type over the already-loaded list (no server round-trip needed for
  a small unpaginated dataset) — a plain `computed()` filter over `appointmentTypes.ts`'s cached list,
  not a new API call pattern.

---

## 8. Dashboard Widgets

Added to the existing `DashboardView.vue` as new sections, reusing `appointments.ts` (today's range is
just another range fetch, cached and shared with the Board if the user later opens `/appointments`).

- **`TodayScheduleWidget.vue`** — today's appointments in a compact list (time, patient, dentist, status
  chip), with "Waiting" (`checked_in` and `started_at` still null) and "Late" (`status` still
  `scheduled`/`confirmed` and `start_at` already passed by more than a small grace threshold, e.g. 10
  minutes — computed client-side from `start_at` vs. "now," not a backend flag) rows visually highlighted
  (amber/red left-border accent) so front desk sees at a glance who needs attention.
- **`UpcomingAppointmentsWidget.vue`** — next N appointments across the next few days (a short additional
  range fetch beyond today), for at-a-glance "what's coming."
- **Quick actions** — "New Appointment" button (opens `AppointmentDialog` with no prefill) and, inline per
  row in `TodayScheduleWidget`, one-click "Check In" for the next `scheduled`/`confirmed` appointment,
  reusing `StatusActionButton.vue` — the same action button used on the Detail view, not a separate
  implementation.

Both widgets are gated behind `canManageAppointments` for the "Waiting/Late" operational framing (a
dentist's dashboard instead shows a simpler "My schedule today" variant of `TodayScheduleWidget` filtered to
their own appointments — same component, a `:scope="'own' | 'all'"` prop switches the query/framing rather
than building a second component for what's fundamentally the same list).

---

## 9. Component Inventory

All new, under `frontend/src/components/appointments/` unless noted. Every component is `<script setup
lang="ts">`, typed props/emits (no `any`), and gets at least one Vitest + `@vue/test-utils` test file (see
§18). Props/Events are the component's full public contract — anything not listed is intentionally internal
state, not exposed.

| Component | Responsibility | Props | Events | Slots | Dependencies |
|---|---|---|---|---|---|
| `AppointmentCalendar.vue` | Presentational FullCalendar wrapper — Day/Week/Month/Dentists rendering only, no store access (§2.12) | `view`, `currentDate`, `events`, `backgroundEvents`, `businessHours?`, `resources?`, `slotMinTime`, `slotMaxTime`, `loading` | `event-click`, `date-click`, `range-change` | `event-content` (defaults to `AppointmentEventContent`) | `@fullcalendar/{core,vue3,daygrid,timegrid,interaction,resource,resource-timegrid}` |
| `AppointmentEventContent.vue` | Per-event render inside the calendar grid — patient name, type color, status treatment | `appointment: Appointment` (or the mapped `AppointmentCalendarEvent`) | `activate` (keyboard Enter/Space, §14) | — | `AppointmentStatusChip` |
| `AppointmentListTable.vue` | Client-paginated DataTable list view | `appointments: Appointment[]`, `loading` | `row-click` (appointmentId) | — | PrimeVue `DataTable`/`Column`, `AppointmentStatusChip` |
| `CalendarToolbar.vue` | View switcher, date navigation, current range label, New Appointment trigger | `viewMode`, `currentDate`, `rangeLabel` | `update:viewMode`, `navigate` (`'prev'\|'next'\|'today'`), `new-appointment` | — | PrimeVue `SelectButton`/`Button` |
| `CalendarFilters.vue` | Dentist / Status / Type / Patient filter controls | `modelValue: CalendarFilters` | `update:modelValue` | — | `DentistSelect`, `AppointmentTypeSelect`, `PatientSearchSelect` |
| `AppointmentStatusChip.vue` | Colored status chip + i18n label — single source of truth for status→color | `status: AppointmentStatus`, `size?` | — | — | `APPOINTMENT_STATUS_COLORS` (§12) |
| `AppointmentBadge.vue` | Compact dot/badge variant for tight spaces | `status: AppointmentStatus` | — | — | `APPOINTMENT_STATUS_COLORS` |
| `AppointmentCard.vue` | Summary card (Detail header, Dashboard rows, Patient panel rows) | `appointment: Appointment`, `variant?: 'default'\|'compact'` | `click` | `actions` (optional trailing action buttons) | `AppointmentStatusChip` |
| `AppointmentDialog.vue` | Create/Edit dialog — Patient / Appointment / Notes tabs | `visible`, `appointment?: Appointment`, `prefill?: AppointmentPrefill` | `update:visible`, `saved` (Appointment) | — | `PatientSearchSelect`, `DentistSelect`, `AppointmentTypeSelect`, `DurationInput`, `SlotPicker`, `ConflictAlert`, `PatientFormDialog` (reused, §3.3) |
| `PatientSearchSelect.vue` | Typeahead patient search + inline "Create New Patient" | `modelValue: string \| null` (patient id), `disabled?` | `update:modelValue`, `patient-selected` (Patient) | — | `PatientSummaryCard`, `PatientFormDialog`, `GET /patients?search=` |
| `PatientSummaryCard.vue` | Small reusable patient summary (name/code/phone/age) | `patient: AppointmentPatientSummary \| Patient` | — | — | — |
| `DentistSelect.vue` | Dentist dropdown | `modelValue: string \| null`, `disabled?` | `update:modelValue` | — | `providers.ts` (§10) |
| `AppointmentTypeSelect.vue` | Appointment type dropdown with color swatch, duration auto-fill signal | `modelValue: string \| null` | `update:modelValue`, `type-selected` (AppointmentType, so the dialog can conditionally prefill duration per §3.1) | — | `appointmentTypes.ts` |
| `DurationInput.vue` | Bounded (5–480 min) numeric stepper | `modelValue: number` | `update:modelValue` | — | PrimeVue `InputNumber` |
| `SlotPicker.vue` | Available-slot chip grid | `dentistId`, `date`, `durationMinutes` | `slot-selected` (Date) | — | `GET /available-slots` (direct call, §11 documented exception), `workingHours.ts` |
| `ConflictAlert.vue` | Renders 409/422 conflict responses, hard-block vs. soft-warning-with-override | `error: AppointmentConflictError \| null` | `override` (emits the `override_field` name to set) | — | — |
| `AppointmentTimeline.vue` | Vertical status-timestamp stepper | `appointment: Appointment` | — | — | — |
| ~~`AppointmentAuditHistory.vue`~~ | **Not built in this phase** (§4.2 — no backend route exists, confirmed); `FutureFeaturePlaceholder.vue` renders in its slot instead | — | — | — | — |
| `AppointmentActionsBar.vue` | Container for the six status-transition buttons, state-machine + role aware | `appointment: Appointment` | `action-completed` (Appointment) | — | `StatusActionButton` |
| `StatusActionButton.vue` | Single action button, optional reason-dialog variant (Cancel/No-Show) | `action: AppointmentActionKind`, `appointment: Appointment`, `requiresReason?` | `confirmed` (reason?: string) | — | PrimeVue `ConfirmPopup`/`Dialog` |
| `WorkingHoursEditor.vue` | Weekly working-hours grid editor | `dentistId: string` | `saved` | — | `WorkingHoursDayRow`, `workingHours.ts` |
| `WorkingHoursDayRow.vue` | One day's shift rows, add/remove/copy | `dayOfWeek: number`, `shifts: DentistWorkingHour[]` | `update:shifts`, `copy-to` (targetDays: number[]) | — | — |
| `TimeOffCalendar.vue` | Chronological time-off list for a dentist | `dentistId: string` | `add-clicked` | — | `timeOff.ts` |
| `TimeOffFormDialog.vue` | Create time-off entry, with appointment-conflict warning list | `visible`, `dentistId: string` | `update:visible`, `saved` | — | `appointments.ts` (read-only, for the conflict warning list) |
| `AppointmentTypeFormDialog.vue` | Create/Edit an appointment type | `visible`, `appointmentType?: AppointmentType` | `update:visible`, `saved` | — | `DurationInput`, PrimeVue `ColorPicker` |
| `PatientAppointmentsPanel.vue` | Patient Detail's "Appointments" tab | `patientId: string` | — | — | `AppointmentListTable`, `AppointmentDialog` |
| `TodayScheduleWidget.vue` | Dashboard: today's appointments, waiting/late highlighting | `scope: 'own' \| 'all'` (§8) | — | — | `AppointmentCard`, `StatusActionButton`, `appointments.ts` |
| `UpcomingAppointmentsWidget.vue` | Dashboard: next few days' appointments | `scope: 'own' \| 'all'` | — | — | `AppointmentCard`, `appointments.ts` |
| `FutureFeaturePlaceholder.vue` | Generic "coming soon" card, reused across the Detail view's future-module slots and the Audit History slot (§4.2) | `icon: string`, `titleKey: string`, `moduleName?: string` | — | — | — |

Reused as-is, **not duplicated**: `PatientFormDialog.vue` (inline patient creation, §3.3).

---

## 10. Pinia Stores

All under `frontend/src/stores/`, Composition-API setup-store syntax (matching `auth.ts`/`ui.ts`). Quick
reference first, full per-store documentation (Responsibility / Owner / Read-Write boundaries / Lifecycle /
Cached-data-vs-application-state) follows in §10.1, as required before implementation begins.

| Store | State | Key actions | Notes |
|---|---|---|---|
| `appointments.ts` | `cache: Map<id, Appointment>`, `rangeLoaded: Interval[]`, `loading`, `error` | `fetchRange(from, to)`, `create(payload)`, `update(id, payload)`, `confirm(id)`, `checkIn(id)`, `start(id)`, `complete(id)`, `cancel(id, reason?)`, `noShow(id, override?)`, `remove(id)`, `fetchOne(id)` | Every mutation upserts the cache via a follow-up `fetchOne` (§1.6, §11.3). Conflict errors are thrown as `AppointmentConflictError`, not swallowed, so callers (the dialog) can render `ConflictAlert` |
| `appointmentTypes.ts` | `items: AppointmentType[]`, `loaded: boolean` | `fetchAll(force?)`, `create`, `update`, `remove` | Cached indefinitely after first load (small, rarely-changing data); `force` param for the admin CRUD screen to bust the cache after a write |
| `workingHours.ts` | `byDentist: Map<userId, DentistWorkingHour[]>` | `fetchForDentist(userId)`, `create`, `remove` | Per-dentist cache, invalidated on write for that dentist only |
| `timeOff.ts` | `byDentist: Map<userId, DentistTimeOff[]>` | `fetchForDentist(userId, range?)`, `create`, `remove` | Same per-dentist cache shape as `workingHours.ts` |
| `calendar.ts` | `viewMode: CalendarViewMode`, `currentDate: Date`, `filters: { dentistIds, statuses, typeIds, patientId }` | `setViewMode`, `goNext`, `goPrev`, `goToday`, `setFilter`, `resetFilters` | Pure UI/view state, **no API calls** — computed `currentRange` (start/end of the visible period) is what `appointments.ts`'s `fetchRange` is driven by |
| `providers.ts` | `items: AuthUser[]`, `loaded: boolean` | `fetchAll(force?)` | **Temporary, explicitly not a permanent domain model — see §10.2** |

### 10.1 Per-store documentation

**`appointments.ts`**
- **Responsibility**: hold the range-keyed cache of `Appointment` records the Board/List/Dashboard/Detail
  views render from, and own every mutation (create/update/reschedule/status-transitions/delete).
- **Owner**: this module (Appointments) exclusively — no other module reads or writes it.
- **Read/Write boundaries**: written to only via its own actions, which are the only code path allowed to
  call the `appointments` API services (§11.1). Components and other stores may **read** `cache`/`loading`/
  `error` via getters but never mutate `cache` directly (Pinia doesn't hard-enforce this, but no component in
  this design touches `store.cache` outside a defined action — verified at implementation/review time).
- **Lifecycle**: created lazily (first access, e.g. when `AppointmentsView` mounts); **not** reset on route
  leave — the cache intentionally survives navigating away and back (§13's `KeepAlive` strategy relies on
  this) so returning to the Board after viewing a Detail page doesn't refetch. Reset only on `auth.logout()`
  (a global store-reset hook, matching how a future multi-user-session concern would need every store
  cleared, not just this one).
- **Cached data or application state?**: **cached server data** (a local mirror of `GET /api/appointments`
  responses), not client-only application state — every field in `cache` traces back to an API response, none
  of it is derived/synthesized client-side beyond the range-interval bookkeeping.

**`appointmentTypes.ts`**
- **Responsibility**: the clinic's configured appointment types (dropdown source + admin CRUD backing).
- **Owner**: this module, but **read** by any component needing a type dropdown (Dialog, Filters, Type CRUD
  screen) — the widest-read store in this set, since type data is referenced everywhere.
- **Read/Write boundaries**: only `AppointmentTypesView.vue`'s CRUD actions write; every other consumer reads
  only.
- **Lifecycle**: fetched once per session on first use, cached indefinitely (`loaded` flag prevents refetch)
  until an explicit `force: true` refetch after a write on the admin screen. Never reset on route change.
- **Cached data or application state?**: **cached server data**, near-static (clinic configuration changes
  rarely — admin-only writes).

**`workingHours.ts` / `timeOff.ts`**
- **Responsibility**: per-dentist schedule data backing the Board's business-hours/time-off overlays (§2.4,
  §2.5) and the `DentistScheduleView` CRUD screens.
- **Owner**: this module; read by the Board (via `AppointmentsView`, which maps the relevant dentist's rows
  into `AppointmentCalendar`'s `businessHours`/`backgroundEvents` props, §2.12) and by `SlotPicker.vue`.
- **Read/Write boundaries**: writes only from `DentistScheduleView`'s editor components
  (`WorkingHoursEditor`/`TimeOffFormDialog`); every other consumer reads only.
- **Lifecycle**: fetched per-dentist on demand (`fetchForDentist(userId)`), cached per `userId` key,
  invalidated only for that one dentist's entry on a write for that dentist — never a blanket cache-bust.
  Not reset on route change (a receptionist switching the Board's dentist filter back and forth shouldn't
  re-fetch a dentist already seen this session).
- **Cached data or application state?**: **cached server data**.

**`calendar.ts`**
- **Responsibility**: the Board/List's current view mode, current date, and active filters — purely what the
  user is currently looking at, never persisted server-side.
- **Owner**: this module; read by `AppointmentsView`, `CalendarToolbar`, `CalendarFilters`,
  `AppointmentListTable`.
- **Read/Write boundaries**: written only via its own actions (`setViewMode`/`goNext`/`goPrev`/`goToday`/
  `setFilter`/`resetFilters`), called from `CalendarToolbar`/`CalendarFilters`' emitted events — those
  components never mutate this store's state directly either, keeping the same action-only-write discipline
  as the data stores even though this one holds no server data.
- **Lifecycle**: created on first access, persists for the whole session (not reset on route leave, so
  filters survive a trip to the Detail view and back) — **is** reset to defaults on `auth.logout()`.
- **Cached data or application state?**: **pure application/UI state** — the one store in this module with
  zero API calls of its own and nothing "cached" about it.

### 10.2 The `providers.ts` store is deliberately temporary, not a permanent domain model

**Already implemented exactly as designed** (`frontend/src/stores/providers.ts`), with one filename note:
this section originally specified `providers.store.ts`; the actual file is `providers.ts`, for consistency
with every other store in the directory (none use a `.store.ts` suffix — `auth.ts`, `calendar.ts`,
`appointments.ts`, etc.). Tracked in `TECH_DEBT.md` as "not a real gap, just a naming note." Named
`providers.ts`, not `dentists.ts`, because this store exists **only** because `GET /api/users` has no role
filter and no dedicated dentist-listing shape (§10.1's sibling stores all wrap a real, purpose-built
endpoint; this one wraps a workaround). Concretely:

- **Responsibility**: provide a de-duplicated, cached list of clinic staff with `role === 'dentist'` for
  dropdowns/filters, by paginating through the generic `GET /api/users` endpoint client-side.
- **Owner**: this module, for now — but it is explicitly **not** meant to become the permanent home for
  "who can treat patients" once a dedicated Providers module exists (dental hygienists, orthodontists, or
  any non-`dentist`-role clinical staff are not representable today, since the backend's `role` enum only has
  `admin`/`dentist`/`receptionist` — a real Providers concept would likely be its own table, not a filtered
  view of `users`).
- **Read/Write boundaries**: read-only (`fetchAll` is its only action beyond the implicit cache) — this store
  never writes anything; provider/dentist account management stays entirely inside the existing Users module.
- **Lifecycle**: fetched once per session, cached indefinitely for the reasons in §11's gap note (small,
  rarely-changing staff list).
- **Cached data or application state?**: cached server data, but sourced from the *wrong* endpoint shape by
  necessity — this is the tell that it's a workaround, not a modeled domain store.
- **Removal condition**: once `TECH_DEBT.md`'s existing "No dedicated dentists/providers listing endpoint"
  entry (already tracked, added 2026-07-16 alongside the infrastructure step) is resolved with a real `GET
  /api/dentists` or `GET /api/providers` endpoint, `providers.ts` should be rewritten to call it directly
  (dropping the client-side pagination/filtering entirely) — a contained, single-file change, not a
  consumer-facing rewrite, since `DentistSelect`/`CalendarFilters`/etc. only ever see the store's
  already-filtered `items` list, never its fetch mechanics.

---

## 11. API Integration

### 11.1 API Services layer

Per the required architecture diagram (§19), a thin **API Services** layer sits between the Pinia stores and
`lib/api.ts`, under `frontend/src/services/appointments/`. Each file wraps one backend resource with typed
functions that take/return the domain types from §12 — no store logic (caching, merging), no component
logic, just typed HTTP calls:

```
frontend/src/services/appointments/
 ├─ appointmentsApi.ts       list(params), get(id), create(payload), update(id, payload), remove(id),
 │                           confirm(id), checkIn(id), start(id), complete(id),
 │                           cancel(id, reason?), noShow(id, override?), availableSlots(params)
 ├─ appointmentTypesApi.ts   list(), create(payload), update(id, payload), remove(id)
 ├─ workingHoursApi.ts       listForDentist(userId), create(userId, payload), remove(userId, id)
 ├─ timeOffApi.ts            listForDentist(userId), create(userId, payload), remove(userId, id)
 └─ providersApi.ts          listAll() — internally paginates GET /api/users and filters role==='dentist'
                              (§10.2's workaround lives here, not in the store, so replacing it later touches
                              exactly one function's implementation, not the store's public shape)
```

Stores call these functions instead of `api.get/post/put/delete` directly; this is the one place per
resource where the axios call, its query-param shaping, and its response typing are defined. Conflict
responses (409/`code`/`overridable`) are normalized here too: `appointmentsApi.ts`'s mutation functions catch
a 409/422 with a `code` field and re-throw a typed `AppointmentConflictError` (§12), so every store action
downstream deals with one consistent error type regardless of which of the five conflict-producing endpoints
raised it.

This is an intentional, documented divergence from the Patients/Users precedent (no service layer, views
call `lib/api.ts` directly) — justified the same way the store layer is (§1.5): Appointments has enough
distinct endpoints (18+) and shared conflict-handling logic that centralizing the HTTP-shaping pays for
itself here in a way it didn't for Patients/Users' handful of plain CRUD calls each.

### 11.2 Endpoint → Service → Store map

Grounded in the backend research (cross-checked against `docs/modules/appointments-design-draft.md`).

| Endpoint | Service function | Store action | Notes |
|---|---|---|---|
| `GET /api/appointments?date_from&date_to&dentist_id&patient_id&status` | `appointmentsApi.list()` | `appointments.fetchRange()` | Not paginated server-side today — see §11.3 for the forward-compatibility contract |
| `POST /api/appointments` | `appointmentsApi.create()` | `appointments.create()` | Followed by `fetchOne(id)` to hydrate nested relations — see §11.4 |
| `GET /api/appointments/{id}` | `appointmentsApi.get()` | `appointments.fetchOne()` | Used for the Detail view AND as the post-mutation re-hydration call everywhere else |
| `PUT /api/appointments/{id}` | `appointmentsApi.update()` | `appointments.update()` (also used for reschedule — same endpoint, no separate action) | |
| `DELETE /api/appointments/{id}` | `appointmentsApi.remove()` | `appointments.remove()` | Admin-only UI gate; soft delete |
| `POST /api/appointments/{id}/confirm` | `appointmentsApi.confirm()` | `appointments.confirm()` | |
| `POST /api/appointments/{id}/check-in` | `appointmentsApi.checkIn()` | `appointments.checkIn()` | |
| `POST /api/appointments/{id}/start` | `appointmentsApi.start()` | `appointments.start()` | |
| `POST /api/appointments/{id}/complete` | `appointmentsApi.complete()` | `appointments.complete()` | |
| `POST /api/appointments/{id}/cancel` | `appointmentsApi.cancel()` | `appointments.cancel()` | |
| `POST /api/appointments/{id}/no-show` | `appointmentsApi.noShow()` | `appointments.noShow()` | |
| `GET /api/available-slots?dentist_id&date&duration_minutes` | `appointmentsApi.availableSlots()` | `SlotPicker.vue`'s own local call (not store-cached — highly parameter-specific, low reuse value) | The one place a component calls a service function directly rather than through a store action, since the result isn't meaningfully shareable state — still goes through `appointmentsApi.ts`, never raw `lib/api.ts`, so §1.5's "components never touch `lib/api.ts` directly" boundary holds even here |
| `GET /api/appointment-types` | `appointmentTypesApi.list()` | `appointmentTypes.fetchAll()` | |
| `POST/PUT/DELETE /api/appointment-types[/{id}]` | `appointmentTypesApi.create/update/remove()` | `appointmentTypes.create/update/remove()` | |
| `GET /api/dentists/{user}/working-hours` | `workingHoursApi.listForDentist()` | `workingHours.fetchForDentist()` | |
| `POST/DELETE /api/dentists/{user}/working-hours[/{id}]` | `workingHoursApi.create/remove()` | `workingHours.create/remove()` | |
| `GET /api/dentists/{user}/time-off` | `timeOffApi.listForDentist()` | `timeOff.fetchForDentist()` | |
| `POST/DELETE /api/dentists/{user}/time-off[/{id}]` | `timeOffApi.create/remove()` | `timeOff.create/remove()` | |
| `GET /api/users` (paginated, all pages) | `providersApi.listAll()` | `providers.fetchAll()` | Workaround for the missing dedicated dentist/provider-list endpoint — see §10.2 and the `TECH_DEBT.md` entry added alongside this revision |
| `GET /api/patients?search=` (existing) | *(none — direct `lib/api.ts` call, matching how Patients itself has no service layer)* | `PatientSearchSelect.vue`'s own debounced fetch | Reuses the Patients module's existing endpoint/pattern as-is, no new store or service needed |

**Duplicated-request avoidance**: `appointmentTypes`/`workingHours`/`timeOff`/`providers` stores all cache
after first load and are shared across every consuming component (Board filters, Dialog dropdowns, Schedule
management) — none of them refetch on every dialog open. `appointments.ts`'s range cache means switching
Board↔List↔Dashboard-widget never re-fetches a range that's already loaded (§13 details the cache-merge and
eviction policy).

**Optimistic updates**: deliberately **not** used for the six status-transition actions or create/update —
each of these can fail with a real business-rule conflict (409/422) that the UI must surface accurately
before committing to a new state, so "assume success, roll back on failure" would mean visibly flickering
the calendar back on every declined double-booking. The one place an optimistic update *is* safe and used:
toggling a filter or switching calendar view (`calendar.ts`) is always instant/local, no server round-trip
to wait on in the first place. §13.8 covers this in full as part of the dedicated Performance Strategy
section.

### 11.3 Pagination forward-compatibility

`GET /api/appointments` has no server-side pagination today (§2.9), so `appointmentsApi.list(params)`
returns `Appointment[]` directly. To keep the door open for server-side pagination later **without a
consumer-facing rewrite**, the service function's parameter and return shape are designed now as if
pagination could be added transparently:

```ts
// Today: params has no page/per_page; the backend returns everything in range.
// If the backend later adds pagination, only this function's internals change —
// its callers (appointments.fetchRange) already treat the result as "the page(s) for this range."
async function list(params: AppointmentListParams): Promise<Appointment[]> {
  // future: could become `(await api.get<PaginatedResponse<Appointment>>(...)).data`
  // with an internal loop to collect all pages for the requested range —
  // fetchRange()'s call site and return type do not need to change either way.
}
```

`appointments.fetchRange()` in the store is written against `list()`'s current `Appointment[]` return type,
not against "however many raw HTTP responses it took to get there" — so if the backend later paginates this
endpoint, only `appointmentsApi.list()`'s internals change (loop through pages, concatenate), not
`appointments.ts`, not any component. This is the concrete mechanism by which §11's "must remain compatible
with future server-side pagination without major refactoring" requirement is satisfied: the service-layer
boundary (§11.1) is exactly what absorbs that future change.

### 11.4 Post-mutation rehydration — a documented, removable workaround

As noted in §11.2, every mutation (`create`/`update`/`confirm`/`checkIn`/`start`/`complete`/`cancel`/
`noShow`) is followed by one `appointmentsApi.get(id)` call to re-hydrate `patient`/`dentist`/
`appointment_type`, because those endpoints' own `AppointmentResource` responses don't eager-load them
(confirmed via the backend research — only `index`/`show` do). This is implemented in exactly one place —
`appointments.ts`'s internal `upsertFromMutation(response)` helper, called by every mutation action — so it
is trivial to remove later.

**This is explicitly a temporary workaround, not a permanent architectural feature.** A `TECH_DEBT.md` entry
("Appointment mutation endpoints don't eager-load relations," added alongside this design revision) records
the backend-side fix: once `store`/`confirm`/`checkIn`/`start`/`complete`/`cancel`/`noShow`/`update` eager-load
the same three relations `index`/`show` already do, `upsertFromMutation()` should be simplified to use the
mutation response directly, deleting the extra `GET` call. Implementers should **not** build additional
logic on top of this workaround (e.g. don't special-case "what if the rehydration call itself fails" beyond
a plain error surface) — it's meant to be deleted, not hardened.

### 11.5 Flagged gaps (unchanged findings, now also tracked in `TECH_DEBT.md`)

- **No dedicated dentist/provider-list endpoint** — `GET /api/users` is paginated at a fixed 15/page with no
  `role` filter and no way to request a larger page size (confirmed by reading `UserController::index()`/
  `UserService::paginate()` — the controller doesn't forward a `per_page` query param to the service).
  `providers.ts`/`providersApi.listAll()` work around this (§10.2). Tracked in `TECH_DEBT.md` under "No
  dedicated dentists/providers listing endpoint" — **not** implemented as a backend change in this
  frontend-only module without separate approval.
- **Audit-log route for Appointments confirmed absent** — `Appointment` does use the `Auditable` trait
  (`backend/app/Models/Appointment.php:6,19`), so the data is already being captured, but no route/controller
  method/Policy ability exposes it (verified directly against `routes/api.php`, `AppointmentController`,
  `AppointmentPolicy` — none exist). Per §4.2 (revised, no longer conditional), `AppointmentAuditHistory.vue`
  is **not built** in this phase; `FutureFeaturePlaceholder.vue` renders in its slot, and a fresh
  `TECH_DEBT.md` entry is added for the missing route.

---

## 12. TypeScript Types

`frontend/src/types/appointment.ts`, following the `patient.ts`/`user.ts` convention exactly: union
string-literal types + a paired `const` array for `Select`/multiselect options, flat interfaces matching
backend Resource shapes field-for-field (snake_case preserved), no `any`.

```ts
export type AppointmentStatus =
  | 'scheduled' | 'confirmed' | 'checked_in' | 'in_progress'
  | 'completed' | 'cancelled' | 'no_show'

export const APPOINTMENT_STATUSES: AppointmentStatus[] = [
  'scheduled', 'confirmed', 'checked_in', 'in_progress', 'completed', 'cancelled', 'no_show',
]

export const APPOINTMENT_STATUS_COLORS: Record<AppointmentStatus, string> = {
  scheduled: '#64748b', confirmed: '#0ea5e9', checked_in: '#eab308',
  in_progress: '#8b5cf6', completed: '#22c55e', cancelled: '#94a3b8', no_show: '#ef4444',
}

export interface AppointmentPatientSummary {
  id: string
  patient_code: string
  full_name: string
}

export interface AppointmentDentistSummary {
  id: string
  name: string
}

export interface AppointmentType {
  id: string
  name: string
  default_duration_minutes: number
  color: string
  is_active: boolean
  created_at: string
  updated_at: string
}

export interface Appointment {
  id: string
  patient_id: string
  dentist_id: string
  appointment_type_id: string
  start_at: string
  end_at: string
  duration_minutes: number
  status: AppointmentStatus
  reason: string | null
  notes: string | null
  cancellation_reason: string | null
  cancelled_at: string | null
  cancelled_by: string | null
  checked_in_at: string | null
  started_at: string | null
  completed_at: string | null
  no_show_at: string | null
  reschedule_count: number
  created_at: string
  updated_at: string
  patient?: AppointmentPatientSummary
  dentist?: AppointmentDentistSummary
  appointment_type?: AppointmentType
}

export interface DentistWorkingHour {
  id: string
  user_id: string
  day_of_week: number // 0=Sunday .. 6=Saturday
  start_time: string  // "HH:mm"
  end_time: string
  is_active: boolean
}

export interface DentistTimeOff {
  id: string
  user_id: string
  start_at: string
  end_at: string
  reason: string | null
}

export type AppointmentConflictCode =
  | 'dentist_conflict' | 'patient_conflict' | 'outside_working_hours'
  | 'early_no_show' | 'invalid_status_transition'

export interface AppointmentConflictError {
  message: string
  code: AppointmentConflictCode
  overridable?: boolean
  override_field?: string
}

export interface CreateAppointmentPayload {
  patient_id: string
  dentist_id: string
  appointment_type_id: string
  start_at: string
  duration_minutes: number
  reason?: string | null
  notes?: string | null
  override_patient_conflict?: boolean
  override_outside_working_hours?: boolean
}

export interface UpdateAppointmentPayload {
  dentist_id?: string
  appointment_type_id?: string
  start_at?: string
  duration_minutes?: number
  reason?: string | null
  notes?: string | null
  override_patient_conflict?: boolean
  override_outside_working_hours?: boolean
}

export type CalendarViewMode = 'timeGridDay' | 'timeGridWeek' | 'dayGridMonth' | 'resourceTimeGridDay' | 'list'
```

`workingHours.ts`/`timeOff.ts` payload types (`CreateWorkingHourPayload`, `CreateTimeOffPayload`) follow the
same pattern, omitted here for brevity but designed identically (mirroring `Store{X}Request` shapes exactly).

---

## 13. Performance Strategy

### 13.1 Lazy loading

Every new route component is a dynamic `import()`, exactly like every existing route (`() =>
import('@/views/AppointmentsView.vue')`, etc.) — no change to the existing convention.

### 13.2 Route / code splitting

FullCalendar's plugins are only imported inside `AppointmentCalendar.vue`, which is itself only reachable
via the lazily-loaded `AppointmentsView.vue` route — the ~150-200KB FullCalendar bundle never loads for a
session that only visits Dashboard/Patients/Users. `resource-timegrid` is imported alongside the base
plugins (not further split) since the Dentists view toggle is part of the same screen, not worth a second
async chunk boundary for one more view mode. `AppointmentTypesView.vue` and `DentistScheduleView.vue` are
each their own route chunk too (admin-only screens most sessions never visit).

### 13.3 Dynamic imports beyond routes

`PatientFormDialog.vue` (reused for inline patient creation, §3.3) is already lazily reachable only through
`AppointmentDialog.vue`'s "Create New Patient" path — no eager top-level import of it in `AppointmentsView`.
Similarly, `FutureFeaturePlaceholder.vue` (§4.2, used for the Audit History slot and the four other
future-module slots) is only pulled in from `AppointmentDetailView.vue`, not from the Board/List bundle.

### 13.4 Range cache + eviction (`appointments.ts`)

`fetchRange(from, to)` merges newly-fetched appointments into the `Map<id, Appointment>` cache and extends a
`rangeLoaded` tracking structure (an array of non-overlapping loaded intervals, not just one `{from,to}`
pair, so jumping Month→Day→a-different-Month doesn't think everything in between is loaded); a request for a
range fully covered by already-loaded intervals skips the network call entirely. Simple cap: the cache
evicts entries whose `start_at` is more than ~60 days outside the currently-viewed range on each
`fetchRange` call, so a long session (staff leaving the tab open for days, paging around a lot) doesn't grow
the cache unbounded — clinic appointment volume is modest (backend design doc §20: "low thousands/year"), so
this is a generous, rarely-hit bound, not aggressive.

### 13.5 Virtual scrolling

Not needed for the List view at realistic per-range row counts (a week/month of one clinic, per the
backend's own performance rationale, §19 of the backend design) — PrimeVue `DataTable`'s plain client
pagination (§2.9) is sufficient. FullCalendar's Month view has its own built-in "+N more" event-limiting
(`dayMaxEvents: true`) which serves the same purpose as virtual scrolling for a dense month. **Revisit
condition**: if a future clinic's realistic appointment volume per range grows well beyond "low
thousands/year" (e.g. a large multi-branch deployment), swap `DataTable`'s plain pagination for PrimeVue's
virtual-scroll mode — the component's prop surface (`AppointmentListTable`'s `appointments`/`loading` props,
§9) doesn't need to change for that swap, only its internal `DataTable` config.

### 13.6 Debounced search

`PatientSearchSelect`/`CalendarFilters`' patient filter both reuse the existing 300ms debounce convention
from `PatientsView`.

### 13.7 Memoized computed values

`calendar.ts`'s `currentRange` and `appointments.ts`'s filtered/derived views (e.g. "today's appointments"
for the Dashboard) are Pinia `computed()` getters, not re-derived imperatively on every render — standard
Vue reactivity handles memoization here without a manual `useMemo`-equivalent. `AppointmentEventContent.vue`
and `AppointmentCard.vue` receive a single `appointment: Appointment` prop object (not spread into a dozen
primitive props), so a status-only mutation (e.g. Check In) only re-renders the components actually bound to
that one appointment's reactive cache entry, not the whole grid — relies on the `Map`-keyed cache (§10)
giving each appointment a stable reference identity that only that one entry's watchers react to.

### 13.8 `KeepAlive` strategy

`AppointmentsView.vue` is wrapped in `<KeepAlive include="AppointmentsView">` at the router-view level in
`DefaultLayout.vue` (a small, targeted addition — not a blanket `<KeepAlive>` around every route, which would
change unrelated modules' behavior without cause). Rationale: navigating from the Board to
`AppointmentDetailView` (clicking an event) and back is the single most common navigation loop in this
module's primary user flow (§1.1's "day-of-visit" flow) — without `KeepAlive`, every trip back to
`/appointments` would remount `AppointmentCalendar`, discarding FullCalendar's internal scroll position and
forcing `AppointmentsView` to re-run its mount-time range fetch (which §13.4's cache would short-circuit
into a no-op network-wise, but the component tree itself — DOM, FullCalendar's internal render state — would
still be rebuilt from scratch, a visible flicker/jump). `calendar.ts`'s filter/view-mode state already
persists across navigation regardless of `KeepAlive` (§10.1 — it's a store, not component-local state), so
`KeepAlive` here is specifically about preserving `AppointmentCalendar`'s own internal FullCalendar instance
and scroll position, not about preserving data (already handled by the stores). No other view in this module
is `KeepAlive`-wrapped — `AppointmentDetailView`/`AppointmentTypesView`/`DentistScheduleView` are visited
less frequently in a tight loop and don't carry equivalent expensive-to-rebuild internal state.

### 13.9 Optimistic updates (where appropriate)

Deliberately **not** used for the six status-transition actions or create/update — each of these can fail
with a real business-rule conflict (409/422) that the UI must surface accurately before committing to a new
state, so "assume success, roll back on failure" would mean visibly flickering the calendar back on every
declined double-booking (§11.2 documents this same decision from the API-integration angle). The one place
an optimistic update *is* safe and used: toggling a filter or switching calendar view (`calendar.ts`) is
always instant/local, no server round-trip to wait on in the first place, so there's nothing to "optimistically"
do — it simply *is* the state, immediately.

---

## 14. Accessibility Checklist

Concrete, verifiable-at-QA-time checklist (replaces the earlier prose version per the required revision).
Each item names the mechanism and which component(s) own it, so it can be checked off against real markup
during Step 9 (Polish) and Step 10 (QA) of implementation, not just asserted here.

**Keyboard navigation**
- [ ] Every interactive element (calendar events, filter controls, dialog fields, action buttons, toolbar
      buttons) is reachable and operable via keyboard alone, no mouse-only affordance anywhere.
- [ ] FullCalendar's own header/nav buttons (inside `AppointmentCalendar.vue`) are natively keyboard-focusable
      (library default) — verified, not assumed, during implementation.
- [ ] Individual **events** are not natively `Tab`-focusable in FullCalendar by default — `AppointmentEventContent.vue`
      explicitly sets `tabindex="0"` plus `keydown.enter`/`keydown.space` handlers mirroring `@click`.
- [ ] `SlotPicker`'s slot chips are real `<button>` elements (not `<div>`s), natively keyboard-operable.
- [ ] §2.10's keyboard shortcuts (`N`/`←`/`→`/`T`/`1-5`/`Esc`/`/`) never fire while any input/textarea/dialog
      has focus — verified via an explicit guard in the shortcut handler, not just "shouldn't happen."

**Focus management**
- [ ] Opening `AppointmentDialog` moves focus to its first field (Patient search input).
- [ ] Closing any dialog (Cancel, successful save, or backdrop click where enabled) returns focus to the
      element that opened it (calendar slot, "New Appointment" button, or the row that was clicked) — a
      ref-captured-before-open / `.focus()`-on-close pattern, no new dependency.
- [ ] Opening `TimeOffFormDialog`/`AppointmentTypeFormDialog`/`WorkingHoursEditor`'s inline edit affordances
      follow the same capture/restore pattern.

**Focus trap**
- [ ] Every `Dialog` (`AppointmentDialog`, nested `PatientFormDialog`, `TimeOffFormDialog`,
      `AppointmentTypeFormDialog`) traps focus within itself while open, via PrimeVue `Dialog`'s native
      focus-trap behavior — no custom trap implementation needed, but verified against each dialog instance
      at QA time since nested dialogs (`PatientFormDialog` stacked on `AppointmentDialog`, §3.3) are a less
      common PrimeVue usage pattern worth explicitly checking doesn't break the trap chain.

**Escape handling**
- [ ] `Esc` closes the topmost open dialog only (PrimeVue `Dialog`'s native behavior) — with
      `AppointmentDialog` + nested `PatientFormDialog` both open, `Esc` closes `PatientFormDialog` first, not
      both at once.
- [ ] `Esc` does **not** fire calendar keyboard shortcuts (§2.10) while a dialog is open — same input-focus
      guard as the keyboard-navigation item above.

**Tab order**
- [ ] `AppointmentDialog`'s three tabs (Patient/Appointment/Notes) follow a logical DOM/tab order matching
      visual order — PrimeVue `Tabs`/`TabPanel`'s native roving-tabindex behavior for the tab list itself,
      verified for the fields within each panel.
- [ ] `CalendarToolbar` → `CalendarFilters` → calendar body → (dialog, if open) is the top-to-bottom tab
      order on `AppointmentsView`, matching visual layout.

**Screen readers**
- [ ] Status transitions triggered via `StatusActionButton` announce their result through the existing toast
      mechanism (`useToast()`), which PrimeVue's `Toast` renders with appropriate ARIA roles out of the box —
      no custom live-region code, but consistent `useToast()` usage is verified across every action, not just
      the common ones.
- [ ] `AppointmentCalendar`'s FullCalendar grid is supplemented by `AppointmentListTable` as the
      screen-reader-friendly alternative view for any user who finds the grid's spatial layout hard to
      navigate with a screen reader — the List toggle (§2.1) is explicitly called out in onboarding/help copy
      as the accessible alternative, not just a power-user convenience.

**ARIA labels**
- [ ] `AppointmentStatusChip`/`AppointmentBadge` include an `aria-label` combining status + (where relevant)
      time, since color alone conveys status visually.
- [ ] `CalendarToolbar`'s icon-only nav buttons (prev/next/today, view switcher icons) get explicit
      `aria-label`s — matching the established pattern (`DefaultLayout.vue`'s theme-toggle button already
      does this).
- [ ] `SlotPicker`'s chips carry `aria-pressed` for the selected slot and `aria-disabled` (not just visual
      muting) for unavailable ones.
- [ ] `ConflictAlert`'s banner has `role="alert"` (hard block) or `role="status"` (soft warning) so screen
      readers announce it immediately when it appears, without requiring the user to discover it visually.

**Color contrast**
- [ ] `appointment_type.color` values are clinic-entered (admin picks any hex via `ColorPicker`, §7) and
      therefore **not guaranteed** to meet contrast requirements against event text — every FullCalendar
      event's text color is computed at render time (a small luminance-based black-or-white-text-on-background
      helper, e.g. relative luminance > 0.5 → dark text, else light text) rather than assuming white text
      always works.
- [ ] `AppointmentStatusChip`'s fixed status-color palette (`APPOINTMENT_STATUS_COLORS`, §12) independently
      meets WCAG AA contrast against both light and dark theme backgrounds — verified at implementation time
      against the actual rendered surface colors, not just eyeballed.
- [ ] Status is never conveyed by color alone anywhere in the module — every color-coded element (calendar
      events, status chips, working-hours/time-off overlays) also carries a text label or distinct
      shape/pattern (§2.3's strikethrough-for-cancelled, §2.5's diagonal-stripe-for-time-off).

**Reduced motion**
- [ ] Any transition/animation this module introduces (dialog open/close, toast entry, the calendar's own
      view-switch animation) respects `prefers-reduced-motion: reduce` — FullCalendar's built-in view
      transitions and PrimeVue's `Dialog`/`Toast` animations are checked against this media query at
      implementation time; if either library doesn't respect it out of the box, a global CSS override (`@media
      (prefers-reduced-motion: reduce) { *, *::before, *::after { animation-duration: 0.01ms !important;
      transition-duration: 0.01ms !important; } }`) is added once, in `style.css`, benefiting every module,
      not just Appointments.

---

## 15. Future Integration & Expansion

Two distinct kinds of "future" are documented separately, per the required revision: (a) other future
**clinical/business modules** that will need to integrate *with* Appointments (this module becomes a
dependency of theirs), and (b) future **enhancements to Appointments itself** (scheduling mechanics this
module will grow to support). Both are designed for now without being built now.

### 15.1 Extension points for future modules

Every row below follows the same pattern established by §4.7's `FutureFeaturePlaceholder.vue`: the
Appointment Detail screen already reserves a visible, honestly-labeled slot for each of these, and
`appointments.id` (per the backend design doc §22) is the stable FK target every one of them will hang off
of — no schema or component contract changes are anticipated on the Appointments side when any of these
modules actually gets built.

| Future module | Integration point already reserved here | Nothing further needed from Appointments because |
|---|---|---|
| **Dental Chart** | `FutureFeaturePlaceholder` slot on `AppointmentDetailView` (§4.7) | A future `dental_chart_entries.appointment_id` FK reads `appointments.id`; charting-during-a-visit is additive, not a reshape |
| **Treatment Plans** | `FutureFeaturePlaceholder` slot on `AppointmentDetailView` (§4.7) | A future treatment-plan step can reference the appointment it was scheduled against/carried out in via the same stable FK |
| **Billing** | `FutureFeaturePlaceholder` slot on `AppointmentDetailView` (§4.7); `completed_at`/`status` already surfaced via `AppointmentTimeline` (§4.1) | A `completed` appointment is the natural billable-item trigger (backend design §22) — the frontend already displays the exact status transition Billing would key off of |
| **Medical Images** | `FutureFeaturePlaceholder` slot on `AppointmentDetailView` (§4.7) | Same FK pattern as Dental Chart — an imaging record attached to a specific visit |
| **Documents** | `FutureFeaturePlaceholder` slot on `AppointmentDetailView` (§4.7) | Same FK pattern — consent forms, referral letters, etc. attached to a specific visit |
| **Notifications** (WhatsApp/SMS/email reminders) | None needed on the Detail view (this isn't a per-appointment attachment, it's a background process) — `appointment_reminders` table already exists DB-side (backend design, forward-compatible-only) | A future reminder job queries `appointments` where `start_at` is within N hours and `status IN (scheduled, confirmed)` — no frontend change required for the sending mechanism itself; only a possible future "reminder sent" indicator on `AppointmentCard`, which is a small additive prop, not a rework |
| **Multi-Clinic** | `calendar.ts`'s filter shape reserves room for a future `branch_id` filter (§2.13); no component assumes a single implicit clinic | Adding branch scoping is an additive filter + query param, mirroring the backend's own "additive, not a redesign" framing (backend design §7) |

### 15.2 Future enhancements to Appointments itself

| Future capability | How this design stays ready for it |
|---|---|
| Multiple dentists | Already fully live today — Dentist filter, Dentist Schedule (resource) view (§2.1, §2.13) |
| Online booking / patient portal | A public-facing booking flow would be a wholly separate future app surface (different auth model entirely) — nothing here blocks it since all business rules already live in the backend Service layer, reusable by any future client |
| Google Calendar / Outlook sync | Would consume the same `GET /api/appointments` data this module already fetches; no schema/API shape changes implied by this design |
| Recurring appointments | Deliberately not faked anywhere in this design (§6 explicitly declines to fake recurring time-off) — a real future feature needs real backend recurrence modeling, not a frontend approximation |
| Chair / resource scheduling | `@fullcalendar/resource-timegrid`'s `resources` array is already resource-generic (§2.13) — swapping "dentist" for "chair" as the resource dimension is a data-source change, not a UI rewrite |
| Drag-and-drop rescheduling | Out of V1 scope (backend design §25) — `@fullcalendar/interaction` (already a dependency, §"New Dependencies") is the same plugin that would power this later; no additional package needed when it's built |

No component in this design hardcodes an assumption ("exactly one clinic," "exactly one resource type," "no
other module ever needs this appointment's id") that would need undoing for any of the above — consistent
with the backend design doc's own "additive, not a redesign" framing for its deferred items (§7/§8/§20).

---

## 16. Testing Strategy — **tooling already installed and proven out** (six stores/services already have tests)

Corrected from the original draft: the toolchain below is **already installed and wired in**
(`frontend/vitest.config.ts`, `frontend/src/test/setup.ts`), not a still-to-add capability — see the "New
Dependencies" section at the top of this document. It's already been exercised for real: every one of the
six Pinia stores and five API-service files built in the infrastructure step has a passing `*.test.ts`
alongside it (64 tests total per `CHANGELOG.md`). What follows is the plan for extending that same,
already-proven approach to the components/screens this document still designs.

- **Unit tests**: pure logic extracted where it's worth isolating — the slot-availability cross-reference
  computation in `SlotPicker.vue`, the status-transition visibility matrix in `AppointmentActionsBar.vue`,
  the range-interval-merge/eviction logic in `appointments.ts` (§13.4), the luminance-based text-color helper
  (§14, Color Contrast) — each gets a focused Vitest unit test independent of component mounting.
- **Component tests** (`@vue/test-utils` + `jsdom`): every component in §9's inventory gets at least one
  mount-and-assert test covering its primary rendered output and, where interactive, its primary emitted
  event/store-action call — mocking the API Services layer (§11.1), not `lib/api.ts` directly and not a real
  backend (frontend tests stay fast and independent of the Laravel test database, matching how the backend's
  own test suite is the authority on business-rule correctness, not re-verified here). Mocking at the service
  boundary rather than the axios instance is deliberate: it means a store's test doesn't care whether
  `appointmentsApi.list()` internally makes one request or, per §11.3's forward-compatibility note, several
  paginated ones later — the mock's contract is the typed function signature, not the HTTP shape behind it.
- **Store tests**: each of the six new stores (§10) gets tests for its actions against a mocked service
  module, including the conflict-error path (`AppointmentConflictError` thrown, not swallowed) and the
  cache-merge/eviction behavior.
- **Not in scope for this module**: end-to-end/browser automation (no Cypress/Playwright exists in this repo
  either, and introducing a second new test paradigm alongside Vitest in one module is more than this task's
  scope warrants) — per `CLAUDE.md`'s "For UI or frontend changes... use the feature in a browser before
  reporting complete," manual click-through against the real dev stack (Docker/Postgres, matching how
  Patients' Final Review was verified per `docs/modules/patients.md`) remains the actual acceptance gate for
  "does this really work," with Vitest covering regression-safety and component-contract correctness rather
  than full user-journey verification.
- Vitest is wired via its own `frontend/vitest.config.ts`, deliberately **separate** from `vite.config.ts`
  (not a shared `test` block as the original draft assumed) — this project's `vite` resolves to a
  rolldown-based build whose `Plugin` types conflict with the non-rolldown `vite` that `vitest/config` bundles
  internally; merging them makes `vue-tsc` report a type error. `npm run test`/`test:watch`/`test:coverage`
  already exist as scripts. `vue-tsc -b` (existing `build` script) remains the source of truth for
  type-correctness, not duplicated by the test suite.

---

## 17. Error Handling Strategy

Every HTTP status this module's endpoints can realistically return, and exactly how it's presented. This is
new surface area beyond what Patients/Users needed (they never produced a 409, and their 401/403/404/422/500
handling was ad hoc per-view) — Appointments centralizes it in the API Services layer (§11.1) so the
presentation logic below is implemented once, not once per store action.

| Status | When it happens here | How it's presented |
|---|---|---|
| **401** Unauthorized | Session cookie expired/missing mid-session (Sanctum) | Not handled per-request by this module specifically — falls through to the existing global behavior: the next `auth.fetchUser()` (router guard, §1.10) resolves `user.value = null`, and the router's existing `requiresAuth` check redirects to `/login`. No new toast/dialog invented here; this module doesn't special-case 401 beyond letting the existing app-wide mechanism handle it. |
| **403** Forbidden | A stale UI shows an action that the backend Policy rejects (e.g. a role changed in another tab, or a genuine bug in this module's own visibility gating, §1.10/§4.6) | Toast: `t('appointments.forbidden')` ("You don't have permission to do that"), then `auth.fetchUser()` is re-run to re-sync the local role state and the offending button/action re-evaluates its visibility (so a genuinely stale permission self-corrects without a manual page reload) |
| **404** Not Found | Direct navigation to `/appointments/:id` for a deleted/nonexistent/soft-deleted appointment; `PatientAppointmentsPanel` referencing a since-removed appointment | `AppointmentDetailView` renders an inline "Appointment not found" state (not a redirect, not the app's generic `NotFoundView` — this is a valid route, just missing data) with a "Back to Appointments" link |
| **409** Conflict | `dentist_conflict` / `patient_conflict` (§3.8) | `ConflictAlert.vue` inside `AppointmentDialog` — hard red banner (no override) for `dentist_conflict`, amber warning-with-"Book Anyway" for `patient_conflict`. Never a toast — this is a decision the user must see and act on inline, not a fire-and-forget notification |
| **422** Unprocessable | Two distinct shapes: (a) plain Laravel validation (`errors: {field: [...]}`) and (b) business-rule conflicts that happen to be 422 (`outside_working_hours`, `early_no_show`, `invalid_status_transition`, §1.9 of the backend research) | (a) inline field-level `Message` components under each form field, exactly like `PatientFormDialog` (§3.12). (b) routed through the same `ConflictAlert.vue` as 409s (§3.8) — the component switches on `code`, not on HTTP status, so 409-vs-422 is an implementation detail the UI doesn't need to branch on twice |
| **500** / network error | Backend exception, connection failure, timeout | Generic toast: `t('appointments.unexpectedError')` ("Something went wrong — please try again"), never raw error text/stack traces surfaced to the user. The triggering action's `saving`/`loading` state is always reset in a `finally` block so the UI never gets stuck mid-spinner on an unhandled failure |

**Implementation mechanism**: `appointmentsApi.ts`'s (and its sibling service files') functions catch
Axios errors and normalize them into one of two shapes before rethrowing: `ValidationError` (422 shape a)
or `AppointmentConflictError` (409/422 shape b, §12) — everything else (401/403/404/500/network) is left as
a plain Axios error for the calling store action to catch with a single generic `catch` block that shows the
toast and resets loading state. This two-tier catch (typed business errors vs. everything-else) is what lets
§3.8/§17's table above stay this short — there's no per-endpoint bespoke error handling to enumerate beyond
what's in this table.

---

## 18. Loading Strategy

Every screen's loading behavior, broken into the four kinds called for: initial load, refresh/re-fetch,
skeleton usage, and inline/button-level spinners. (§1.7 already summarized this at a high level; this section
is the concrete, screen-by-screen version.)

| Screen | Initial loading | Refresh loading | Skeleton | Inline spinner | Button loading state |
|---|---|---|---|---|---|
| `AppointmentsView` (Board) | `AppointmentCalendar`'s `loading` prop drives FullCalendar's own loading indicator over the (empty) grid | Same indicator, non-blocking — the grid stays interactive/visible while a background range fetch completes (no full unmount) | Not used — an empty grid is itself informative (§1.8) | A thin indeterminate `ProgressBar` at the top of `CalendarToolbar` during any range fetch | "New Appointment" button has no loading state of its own (opens instantly; the Dialog's own save flow is where loading appears) |
| `AppointmentsView` (List) | `DataTable`'s `:loading` prop (PrimeVue's built-in overlay) | Same `:loading` prop, re-triggered on filter change | Not used — matches existing `PatientsView`/`UsersView` convention (they don't use skeletons for the table either) | — | — |
| `AppointmentDialog` | N/A (opens instantly with either blank or prefilled form — no fetch blocks opening) | — | — | `SlotPicker`'s own `loadingSlots` ref shows skeleton chips while `GET /available-slots` is in flight; `PatientSearchSelect` shows a small inline spinner in the input's suffix during its debounced search | Save button: spinner icon + disabled state (`saving` ref), exact `PatientFormDialog` pattern |
| `AppointmentDetailView` | `Skeleton` placeholders (header card, timeline, actions bar) before `fetchOne(id)` resolves — mirrors `PatientDetailView`'s existing pattern exactly | Not applicable in V1 (no auto-refresh/polling of an open Detail view — §"Live Refresh" is a Step 2 implementation concern for the Board, not the Detail page) | Yes, as above | — | Each `StatusActionButton` independently shows its own spinner + disables itself (not the whole action bar) while its specific action call is in flight |
| `AppointmentTypesView` | `DataTable :loading`, matching `UsersView`'s inline-table convention | Same, re-triggered after create/update/delete | Not used | — | Save button in `AppointmentTypeFormDialog`: same `saving` pattern as above |
| `DentistScheduleView` | `Skeleton` rows for `WorkingHoursEditor`'s 7-day grid and `TimeOffCalendar`'s list, independently, while their respective `fetchForDentist()` calls are in flight — switching the dentist selector re-triggers both, each showing its own skeleton without blocking the other | Same skeleton, re-shown on dentist switch | Yes, as above | Per-shift-row spinner during the delete-then-recreate sequence (§5's "no Update endpoint" workaround) so a mid-flight edit visibly shows *which* row is updating | `TimeOffFormDialog` save button: same pattern |
| Dashboard widgets | Each of `TodayScheduleWidget`/`UpcomingAppointmentsWidget` shows its own `Skeleton` rows independently — one widget's slow fetch never blocks the other's render (§1.7) | Not applicable in V1 (no dashboard auto-refresh) | Yes, as above | Inline "Check In" quick-action button (in `TodayScheduleWidget`) has its own button-loading state, same as `StatusActionButton` | — |

**General rule enforced everywhere above**: no screen ever shows two competing loading indicators for the
same in-flight request (e.g. a skeleton *and* a spinner for the same fetch) — each request's loading state
maps to exactly one visual treatment, chosen per the table above.

---

## 19. Architecture Diagram

```
 ┌─────────────────────────────────────────────────────────────────┐
 │  Vue Pages                                                       │
 │  AppointmentsView · AppointmentDetailView · AppointmentTypesView │
 │  DentistScheduleView · (+ DashboardView / PatientDetailView,     │
 │  extended)                                                       │
 └───────────────────────────────┬───────────────────────────────────┘
                                  │ compose / wire props+events
                                  ▼
 ┌─────────────────────────────────────────────────────────────────┐
 │  Reusable Components                                             │
 │  AppointmentCalendar · AppointmentDialog · AppointmentActionsBar │
 │  CalendarToolbar/Filters · SlotPicker · ConflictAlert · … (§9)   │
 └───────────────────────────────┬───────────────────────────────────┘
                                  │ call store actions (never the API directly,
                                  │ except SlotPicker → service directly, §11.2)
                                  ▼
 ┌─────────────────────────────────────────────────────────────────┐
 │  Pinia Stores                                                    │
 │  appointments · appointmentTypes · workingHours · timeOff ·      │
 │  calendar (UI state only) · providers (temporary, §10.2)         │
 │  — own caching, merging, eviction, conflict-error surfacing      │
 └───────────────────────────────┬───────────────────────────────────┘
                                  │ call typed functions (never axios directly)
                                  ▼
 ┌─────────────────────────────────────────────────────────────────┐
 │  API Services                                                    │
 │  frontend/src/services/appointments/{appointmentsApi,            │
 │  appointmentTypesApi, workingHoursApi, timeOffApi,                │
 │  providersApi}.ts — typed request/response shaping,               │
 │  409/422 → typed conflict-error normalization (§11.1, §17)        │
 └───────────────────────────────┬───────────────────────────────────┘
                                  │ frontend/src/lib/api.ts (shared axios
                                  │ instance, Sanctum cookie auth)
                                  ▼
 ┌─────────────────────────────────────────────────────────────────┐
 │  Laravel API                                                     │
 │  AppointmentController · AppointmentTypeController ·             │
 │  DentistWorkingHourController · DentistTimeOffController ·       │
 │  UserController (§10.2's temporary providers workaround)         │
 └─────────────────────────────────────────────────────────────────┘
```

Each layer only ever calls the layer directly below it — Pages never call Services or `lib/api.ts` directly,
Components never touch a store's raw state (only its actions/getters), Stores never construct raw HTTP
requests themselves. This is what makes §11.3's "pagination-compatible without major refactoring" and
§11.4's "rehydration workaround removable in one place" claims true in practice, not just in principle: each
boundary is a real seam a future change can land inside without rippling upward.

---

## 20. Implementation Sequence

This document's scattered references to "Step 9 (Polish)"/"Step 10 (QA)" (§2.10, §16) previously had no
defined step list. Given what's already shipped (see the status block at the top), the remaining work
breaks down as:

| Step | Scope | Depends on | Status |
|---|---|---|---|
| 1 | Data-layer infrastructure (types, stores, services, routes, i18n, toolchain) | — | ✅ Completed (2026-07-16, prior session) |
| 2 | Install `@fullcalendar/*` set; build `AppointmentCalendar.vue`/`CalendarToolbar`/`CalendarFilters`; wire the Board to real data | Step 1 | ✅ **Completed (2026-07-16).** Day/Week/Month (MIT only — `@fullcalendar/resource`/`resource-timegrid` deliberately **not** installed, see the FullCalendar decision below); List toggle is Step 3; Patient filter deferred to Step 4 (needs `PatientSearchSelect.vue`). Verified against the real dev stack (Docker/Postgres) in Arabic/English × light/dark — see CHANGELOG for the three real bugs this caught and fixed, all treated as production-quality fixes, not temporary patches. User-approved 2026-07-16. |
| 3 | `AppointmentListTable.vue` + List toggle on `AppointmentsView.vue` | Step 1 | ✅ **Completed (2026-07-16).** Client-paginated (`:rows="20"`, no server round-trip, per §2.9), sortable by Date & Time, shares `appointments.filteredAppointments` with the Board. Added a view-agnostic range-fetch watcher so prev/next/today navigation still refetches while List is active (the Board's own `range-change` emit doesn't fire when `AppointmentCalendar` is unmounted). Verified against the real dev stack in Arabic/English × light/dark — no bugs found this pass. User-approved 2026-07-16. |
| 4 | `AppointmentDialog.vue` + its sub-components (`PatientSearchSelect`, `DentistSelect`, `AppointmentTypeSelect`, `DurationInput`, `SlotPicker`, `ConflictAlert`) — the Create/Edit flow end to end, including the inline `PatientFormDialog` reuse | Steps 2-3 | ✅ **Completed (2026-07-16), pending review.** Full Create flow wired into the Board (New Appointment button + empty-slot click); Edit mode implemented and unit-tested but not yet wired to a live call site (that's `AppointmentDetailView`, Step 5). Verified against the real dev stack in Arabic/English × light/dark — see CHANGELOG for the real bugs this pass found and fixed (a date-*time* timezone bug affecting both the dialog and, more severely, the already-shipped Board's event rendering; a PrimeVue MultiSelect rendering quirk; a `providers.ts`/`appointmentTypes.ts` fetch race; native-form-submit button bugs; a non-clickable toggle label; a local PHP-FPM pool-sizing gap). ✅ **User-approved 2026-07-17**, including the project-wide datetime policy this step's audit produced (see `docs/decisions.md`). |
| 5 | `AppointmentDetailView.vue` + `AppointmentCard`/`AppointmentTimeline`/`AppointmentActionsBar`/`StatusActionButton` + the `FutureFeaturePlaceholder` slots (§4.2, §4.7) | Step 4 | ✅ **Completed (2026-07-17)**, full Vitest coverage (186/186), `vue-tsc`/ESLint/Prettier clean. Edit now has a live call site (`AppointmentDialog`, built Step 4). Verified against the real dev stack (Docker/Postgres) — full Confirm→Check In→Start→Complete lifecycle, Cancel-with-reason, and No-Show early-conflict/override all driven end to end in English/Arabic × light/dark; two real UX bugs found and fixed (see CHANGELOG). Awaiting user approval before Step 6. |
| 6 | `DentistScheduleView.vue` (Working Hours + Time Off, §5-§6) — `meta.roles: ['admin', 'dentist']` route guard already applied 2026-07-16 (§1.3) | Step 1 | ✅ **Completed (2026-07-17)**, full Vitest coverage (214/214), `vue-tsc`/ESLint/Prettier clean (2 pre-existing `no-explicit-any` errors in the unrelated Patients/Users modules aside). Verified against the real dev stack (Docker/Postgres) — full Working Hours (add/edit-as-delete-then-create/toggle-day-active/copy-to/delete) and Time Off (create/conflict-warning/delete) flows driven end to end as both admin (any dentist) and a dentist role (self-service only, read-only working hours) in English/Arabic × light/dark. Three real bugs found and fixed this pass, none of them workarounds (see CHANGELOG): an upstream PrimeVue `DatePicker` defect that silently discarded any typed (not clicked) 24-hour date/time value, patched via `patch-package` since it also affects the already-shipped `AppointmentDialog` (Step 4); a race condition where an in-progress "Add shift" could be silently stranded as unsaved if an unrelated day's edit forced the shifts list to refresh before the create resolved; and a raw `HH:mm:ss` seconds suffix leaking into the dentist's read-only time display. Awaiting user approval before Step 7. |
| 7 | `AppointmentTypesView.vue` CRUD (§7) — `meta.roles: ['admin']` route guard already applied 2026-07-16 (§1.3) | Step 1 | Not started (route guard portion already done) |
| 8 | Dashboard widgets (§8) + `PatientAppointmentsPanel.vue` on `PatientDetailView` | Steps 2-5 | Not started |
| 9 | Polish: keyboard shortcuts (§2.10), accessibility checklist (§14) verified against real markup, reduced-motion, luminance-based text-color helper | Steps 2-8 | Not started |
| 10 | QA: manual click-through per `CLAUDE.md`'s browser-testing rule, full Vitest suite, `vue-tsc -b`, ESLint/Prettier clean, then the Final Review Report (per the project's two-phase workflow) and `docs/modules/appointments.md`. **Must also confirm no demo/seed data leaks into any production deployment workflow** before this module is considered production-ready. | Step 9 | Not started |

**FullCalendar decision (confirmed 2026-07-16, permanent unless explicitly revisited):** continue with the MIT package set only (`core`/`vue3`/`daygrid`/`timegrid`/`interaction`). Do not add `@fullcalendar/resource`/`resource-timegrid` or any other FullCalendar Premium package. The Dentists (resource-column) view stays deferred until a commercial-license purchase is separately evaluated and approved, or a custom non-premium alternative is explicitly chosen — see item 8 in the closing summary below and `docs/decisions.md`.

Each step should still get its own sign-off checkpoint consistent with how the backend was built layer-by-layer (per `appointments-design-draft.md`'s Implementation Progress table) — this isn't mandating one giant PR.

---

## Summary of Open Items Carried Into Implementation

1. **§4.2 — resolved and actioned.** No Appointments audit-log route exists (verified directly, not
   assumed); `FutureFeaturePlaceholder` renders in that slot, and the `TECH_DEBT.md` entry ("Appointment
   audit-log route not yet exposed") has been added.
2. **§7** — Price and Default fields from the task brief have no backing database column; **not implemented**
   in this design, by requirement. If genuinely wanted, needs a separate, explicitly-approved backend change
   (out of this module's frontend-only scope).
3. **§10.2/§11.5 — the `providers.ts` workaround already exists and is already tracked.** Not a new item to
   add: `TECH_DEBT.md`'s "No dedicated dentists/providers listing endpoint" entry already exists (added
   2026-07-16), cross-referencing this doc. Not blocking; revisit when backend capacity allows.
4. **§11.4 — the post-mutation rehydration workaround is already implemented and already tracked**, in both
   `frontend/src/stores/appointments.ts` (the `fetchOne` call after every mutation) and `TECH_DEBT.md`
   ("Appointment mutation endpoints don't eager-load relations"). Remove it once the backend eager-loads
   those relations on mutation responses — no other frontend code needs to change when that happens.
5. **New dependencies — installed 2026-07-16, license-verified by downloading and reading the actual
   tarball, not just the registry field.** `@fullcalendar/{core,vue3,daygrid,timegrid,interaction}` are MIT,
   installed, and cover Day/Week/Month/List in full. **New finding, needs your decision (see item 8 below)**:
   `@fullcalendar/resource`/`resource-timegrid` — needed only for the Dentists (resource-column) view — turned
   out to be FullCalendar Premium (paid Commercial / CC-BY-NC-ND non-commercial / GPLv3), not MIT as the
   original draft assumed. Not installed.
6. **§1.3/§1.10 — applied 2026-07-16.** The `meta.roles` route guard is now live for `/appointments/types`
   (`['admin']`) and `/appointments/schedule` (`['admin', 'dentist']`), matching the precedent already set for
   `/users`, with router tests covering all three role combinations for both routes.
7. **Documentation sync — done 2026-07-16, before any component code was written**, per the required
   ordering: `docs/architecture.md`'s Cross-Cutting Concerns table now correctly says audit logs are
   implemented (and notes the Appointments-specific gap from item 1); its Backend/Frontend Contract section
   now documents the 409/`code`/`overridable` error shape; its frontend directory tree now lists the
   `services/` layer. `docs/roadmap.md` now reflects Appointments as "In Progress" with a status summary
   instead of "Up next."
8. **New, needs your decision — the Dentists (resource-column) calendar view's licensing.** Confirmed by
   downloading and reading `@fullcalendar/resource`/`@fullcalendar/resource-timegrid`'s actual `LICENSE.md`:
   both are FullCalendar Premium, tri-licensed as (a) a paid Commercial License, (b) CC-BY-NC-ND
   (non-commercial only — not usable here), or (c) GPLv3 (copyleft — not compatible with a closed-source
   commercial codebase). None of the three is "free to use in a commercial product." Options:
   - **(a) Purchase a FullCalendar Premium commercial license** and build the Dentists view as originally
     designed (§2.1, §2.13's "resources array is already resource-generic" framing).
   - **(b) Drop the Dentists (resource-column) view from V1**, keep Day/Week/Month/List (already fully
     buildable, MIT, no cost) — the "see everyone at once" need is still met via the Board's Dentist filter
     left empty (§2.1's interim note above); revisit the resource view later if/when a commercial license is
     purchased, additive, no rework of anything already built.
   - **(c) Build a lightweight custom side-by-side day view** (N single-dentist columns via CSS grid/flex,
     no drag-and-drop between columns) instead of the premium plugin — free, but real engineering effort on a
     narrower version of what the premium plugin already solves.
   **Decision confirmed by the user, 2026-07-16: (b).** The Dentists (resource-column) view is postponed
   entirely for now — proceed with Day/Week/Month/List (MIT) + Dentist filtering only. No custom CSS-grid
   replacement is built, and no FullCalendar Premium dependency is added. Keep `AppointmentCalendar.vue`'s
   props/emits contract (§2.12) resource-generic as designed, so the Dentists view is a clean additive change
   later (swap in the premium plugin, or build the custom version, per whichever real SaaS-customer need
   justifies the cost) — not a rework of anything built now.

---

This document is ready for your review. Please confirm, adjust, or reject before any implementation
(Phase 2, remaining steps per §20) begins — per the project's two-phase workflow, no component/screen code
will be written until this revision is approved.
