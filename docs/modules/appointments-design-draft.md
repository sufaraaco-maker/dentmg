# Appointments Module — Design Document (DRAFT, pending approval)

**Status: SUPERSEDED (2026-07-20) — see [`appointments.md`](appointments.md), the final module doc,
for the current architecture, decisions, and status. Kept here as the original design/decision record.**

Status: **Design approved (2026-07-15).** Implementation in progress, layer by layer, each with its own
sign-off. This file becomes the basis for `docs/modules/appointments.md` (implementation doc) once the
module is fully complete, the same way `patients.md` was produced — not before.

## Implementation Progress

| Layer | Status |
|---|---|
| Database (migrations) | ✅ Approved 2026-07-15 |
| Models & Relationships | ✅ Approved 2026-07-15 |
| Validation (Form Requests, Policies backing them, §17) | ✅ Implemented 2026-07-15 — see below |
| Service (`AppointmentService`) | ✅ Implemented 2026-07-15 — see below |
| Policy Layer completeness (dentist-ownership IDOR checks for the `start`/`complete` actions, §18) | ✅ Implemented 2026-07-15 — folded into the API Layer step below |
| API (Controllers, routes) | ✅ Implemented 2026-07-15 — see below |
| Vue Pages | Pending |
| Tests | Incremental per layer so far (141 tests added across Validation + Service + API Layers); full suite at module completion |
| Documentation (`docs/modules/appointments.md`) | Pending — written at Final Review |

**Service Layer, implemented 2026-07-15:**
- `App\Services\AppointmentService`: `availableSlots()`, `dentistHasConflict()`/`patientHasConflict()`/`isOutsideWorkingHours()`, `create()`, `reschedule()`, `cancel()`, `markNoShow()`, `confirm()`/`checkIn()`/`start()`/`complete()`. All business logic lives here — Models/FormRequests stay pure per §17/§18.
- Status transitions enforced via a single allowed-transitions map (design doc §3): `confirmed` is optional (`scheduled` can jump straight to `checked_in`), `checked_in → completed` cannot skip `in_progress`, `cancelled` is reachable from every non-terminal status, `no_show` only from `scheduled`/`confirmed`. Never trusts a status value from the caller.
- Dentist conflict is a hard block, never overridable; patient conflict and outside-working-hours are soft warnings, each independently overridable via `override_patient_conflict`/`override_outside_working_hours` (the flags already validated as booleans in the Validation Layer). `no_show` before `start_at` has passed needs `override_early_no_show`.
- Belt-and-suspenders (§10): an app-level pre-check runs before every write, and `runGuardedAgainstDentistRace()` also catches a real Postgres `appointments_no_overlapping_dentist_slots` EXCLUDE-constraint violation and translates it into the same `DentistConflictException` — verified directly against the real Postgres container (not just SQLite), including forcing a raw insert past the app-level check to confirm the DB constraint itself rejects the overlap.
- Reschedule is in-place (§11): re-runs conflict/availability checks only when the slot actually moves; `reschedule_count` increments only when `dentist_id`/`start_at` change (not on a notes-only edit); old→new values captured automatically by the existing `Auditable` trait — no manual audit-log code needed.
- New exceptions: `App\Exceptions\Appointments\{DentistConflictException, PatientConflictException, OutsideWorkingHoursException, EarlyNoShowException, InvalidStatusTransitionException}`. No HTTP-status mapping yet — that's the Controller layer's job, not built here.
- New config: `config/appointments.php` (`slot_interval_minutes`, default 15, per §6/§9) — no new package.
- Verification: Pint clean, PHPStan (Larastan level 5) 0 errors, full suite 127/127 passing (34 new: 12 Unit + 22 Feature), plus a manual smoke test against the real Postgres container (not just SQLite) confirming the EXCLUDE constraint, reschedule audit trail, and status transitions all behave correctly there too.

**Validation Layer, implemented 2026-07-15:**
- `App\Policies`: `AppointmentPolicy`, `AppointmentTypePolicy`, `DentistWorkingHourPolicy`, `DentistTimeOffPolicy` — scoped to the abilities the Form Requests below call into (`create`/`update`/`cancel`/`markNoShow`/`delete`/`viewAny`/`view`). Confirm/check-in/start/complete abilities (and their dentist-ownership IDOR check, §18) are deferred to the Service/Controller step, since nothing calls them yet.
- `App\Http\Requests\Appointment`: `StoreAppointmentRequest`, `UpdateAppointmentRequest`, `CancelAppointmentRequest`, `MarkNoShowAppointmentRequest`.
- `App\Http\Requests\AppointmentType`: `StoreAppointmentTypeRequest`, `UpdateAppointmentTypeRequest`.
- `App\Http\Requests\DentistWorkingHour`: `StoreDentistWorkingHourRequest` (no Update — API design §16 only exposes create/delete).
- `App\Http\Requests\DentistTimeOff`: `StoreDentistTimeOffRequest` (same reasoning).
- Scope strictly held to shape/reference validation per §17 — no availability calculation, slot generation, or conflict detection here; those stay in `AppointmentService`.
- Verification: Pint clean, PHPStan (Larastan level 5) 0 errors, full suite 93/93 passing (46 new).

**API Layer, implemented 2026-07-15:**
- **Controllers** (`App\Http\Controllers\Api`), thin per `coding-standards.md` — parse request → call one Service method → return a Resource:
  - `AppointmentController`: `index` (`GET /api/appointments`, date-range-bounded search), `store`, `show`, `update` (full edit **and** reschedule — see decision below), `destroy` (admin-only soft delete), `cancel`, `noShow`, `confirm`, `checkIn`, `start`, `complete` (six dedicated transition endpoints), `availableSlots` (`GET /api/available-slots`, top-level route).
  - `AppointmentTypeController`: full CRUD, admin-only writes, any-role reads.
  - `DentistWorkingHourController`: `index(User $user)`/`store`/`destroy`, admin-only (design doc §6).
  - `DentistTimeOffController`: `index(User $user)`/`store`/`destroy`, admin-any + dentist-own self-service.
- **Resolved decision — transition-endpoint shape:** a later instruction proposed collapsing the six transition endpoints into a single generic `POST /api/appointments/{id}/status` endpoint. The user explicitly confirmed sticking with the originally-approved §16 design instead: six dedicated endpoints (`confirm`/`check-in`/`start`/`complete`/`cancel`/`no-show`), because per-action authorization (`start`/`complete` open to the treating dentist, the others aren't) is only expressible cleanly when each transition is its own route+Policy ability — a generic endpoint would need to re-derive "which status was requested" before it could even ask the Policy the right question. Reschedule likewise stays folded into `PUT /api/appointments/{id}` (→ `AppointmentService::reschedule()`) rather than getting its own endpoint, exactly as §11/§16 specified.
- **Exception → HTTP mapping** — new ground; nothing mapped these before. Each exception in `App\Exceptions\Appointments` implements its own `render(Request $request): JsonResponse`, which Laravel's handler auto-invokes (no `bootstrap/app.php` change, no controller `try`/`catch`):

  | Exception | HTTP Status | JSON body |
  |---|---|---|
  | `DentistConflictException` | 409 | `{message, code: "dentist_conflict"}` |
  | `PatientConflictException` | 409 | `{message, code: "patient_conflict", overridable: true, override_field: "override_patient_conflict"}` |
  | `OutsideWorkingHoursException` | 422 | `{message, code: "outside_working_hours", overridable: true, override_field: "override_outside_working_hours"}` |
  | `EarlyNoShowException` | 422 | `{message, code: "early_no_show", overridable: true, override_field: "override_early_no_show"}` |
  | `InvalidStatusTransitionException` | 422 | `{message, code: "invalid_status_transition"}` |

  Rationale: `DentistConflictException`/`PatientConflictException` are both framed as `409 Conflict` — both describe an overlapping-appointment conflict between two resources, even though one is hard-blocked and one is soft/overridable. The `overridable`/`override_field` keys are what let the frontend distinguish the two and offer a "Book Anyway" confirmation only where one is actually possible. `OutsideWorkingHoursException`/`EarlyNoShowException`/`InvalidStatusTransitionException` are `422` — business-rule validation failures on the request as submitted, not a conflict between two resources. This new `409`/`code`/`overridable` shape is documented in `docs/api-guidelines.md`'s Errors section, since no prior module needed it.
- **Policy gaps filled** (`AppointmentPolicy`): `confirm`/`checkIn` (admin/receptionist only) and `start`/`complete` (admin/receptionist, or the treating dentist for their own appointment — `$actor->is($appointment->dentist)`, the IDOR check design doc §18 called for). These were explicitly deferred by the Validation Layer entry above; nothing called them until this layer's Controller wired them up.
- **Service gaps filled:**
  - `AppointmentService::search(array $filters): Collection` — date-range-bounded list (`date_from`/`date_to` required, optional `dentist_id`/`patient_id`/`status`), eager-loads `patient`/`dentist`/`appointmentType`. Deliberately not paginated — same exemption rationale as the Dashboard summary endpoint (§16/§19): a calendar-range query is naturally bounded (a week of one clinic is at most a few hundred rows), so classic pagination would only get in the way of a board/list view that wants the whole range at once.
  - `AppointmentService::delete(Appointment $appointment): void` — soft delete, mirrors `PatientService::delete()`'s existence purely for controller-calls-one-Service-method consistency.
  - Three new thin lookup-table Services, matching `PatientService`'s shape (one method per controller action, no business logic beyond what's needed): `AppointmentTypeService` (`list()` — also deliberately not paginated, same UX rationale: it's a small clinic-configured dropdown source for the booking form, not a browsable list — `create()`/`update()`/`delete()`), `DentistWorkingHourService` (`listForDentist()`/`create()`/`delete()`), `DentistTimeOffService` (`listForDentist()`/`create()`/`delete()`).
  - Bug found and fixed along the way: `AppointmentType::create()` without an explicit `is_active` left the in-memory model attribute `null` immediately after creation (Eloquent doesn't refetch DB-applied column defaults after an insert), even though the migration's `default(true)` meant the actual row was `true` — so the very next API response (the 201 from `store`) would have shown `is_active: null`, a client-visible lie about the row's real state. Fixed by explicitly defaulting `is_active` to `true` in `AppointmentTypeService::create()` before the insert, rather than relying on the DB column default alone.
- **New FormRequests:** `IndexAppointmentRequest` (`date_from`/`date_to` required dates, `date_to` after-or-equal `date_from`; optional `dentist_id`/`patient_id`/`status`), `AvailableSlotsRequest` (`dentist_id` required uuid+exists+dentist-role, `date` required date, `duration_minutes` required 5–480). Both `authorize() => true` — any authenticated role, per §14's clinic-wide read visibility.
- **New Resources:** `AppointmentResource` (flat fields + `whenLoaded()`-guarded `patient`/`dentist`/`appointment_type` summaries — the latter nesting `AppointmentTypeResource`), `AppointmentTypeResource`, `DentistWorkingHourResource`, `DentistTimeOffResource`.
- **Model gap filled in passing:** `Appointment`'s `belongsTo` relations (`patient`, `dentist`, `appointmentType`, `cancelledBy`) and `reminders` `hasMany` were missing the generic `@return BelongsTo<X, $this>`/`@return HasMany<X, $this>` PHPDoc `coding-standards.md` requires — harmless until `AppointmentResource` became the first code in the module to actually read `$this->patient`/`$this->dentist` inside a `whenLoaded()` closure, at which point Larastan could no longer infer the related model's shape and flagged 5 "undefined property" errors. Added the missing annotations rather than working around it in the Resource.
- **Verification:** Pint clean, PHPStan (Larastan level 5) 0 errors, full suite 188/188 passing (61 new: `AppointmentTest` 31, `AppointmentTypeTest` 11, `DentistWorkingHourTest` 8, `DentistTimeOffTest` 10, plus the one-line `Appointment` model PHPDoc fix and the `AppointmentTypeService::create()` default-value fix above), up from 127/127 at the end of the Service Layer.
- **Not touched:** Vue/frontend, `PROJECT_CONTEXT.md`, `docs/modules/appointments.md` (written at Final Review once Vue Pages are also done, per the project's two-phase-per-module workflow).

---

## 1. Module Goals

- Let front-desk staff (receptionist/admin) book, view, reschedule, cancel, check in, and track patient appointments with a specific dentist.
- Prevent double-booking of a dentist (hard rule) and warn on double-booking a patient (soft rule — see §5).
- Model the full real-world visit lifecycle (booked → arrived → in the chair → done), not just "booked vs. not booked," because downstream modules (Billing, Reports, future Notifications) need those timestamps.
- Give each dentist a working-hours template and time-off calendar, so the system can compute real availability instead of relying on staff to remember schedules.
- Lay the groundwork — without building — for multi-branch scheduling, chair/operatory scheduling, notifications, and billing/treatment integration, so none of those become a breaking schema change later.
- Stay inside V1 scope: no patient self-booking portal, no SMS/WhatsApp reminders, no recall/recare automation. These are listed in §25 as deliberate future work, not oversights.

---

## 2. Full Workflow

This follows how dental front desks actually operate (Open Dental / Dentrix / Curve-style workflow), not a generic calendar-booking flow:

1. **Initiate booking.** Receptionist (or admin) opens "New Appointment," searches an existing patient (reusing the Patients search UX) or is directed to register a new patient first if not found — no duplicate "quick-patient" shortcut, since Patients already owns that responsibility.
2. **Choose dentist + type.** Select the treating dentist and an appointment type (e.g. "Consultation," "Cleaning," "Filling," "Root Canal," "Emergency"). The type carries a default duration that pre-fills the form.
3. **Pick a slot.** Staff either types a date/time directly or opens "Available Slots" for that dentist/date, which the system computes from working hours minus existing bookings and time-off (§6, §10).
4. **Conflict check.** On submit, the system hard-blocks a dentist double-booking and soft-warns (with an explicit override) on a patient double-booking. Appointment is created with status `scheduled`.
5. **(Optional) Confirmation.** Staff can mark it `confirmed` after reaching the patient by phone — this step is optional, not gating; clinics that don't do confirmation calls simply skip it.
6. **Day of visit — check-in.** Patient arrives at the front desk; receptionist marks `checked_in`. `checked_in_at` is stamped.
7. **Seated / in progress.** When the dentist/assistant brings the patient into the operatory, status moves to `in_progress`. `started_at` is stamped. (This step can be triggered by the dentist's own view of their day, or by the front desk — both roles can do it, see §14.)
8. **Completed.** When the visit ends, status becomes `completed`, `completed_at` is stamped. This is the hand-off point for future Billing/Treatment Plan integration (§22).
9. **Alternative endings:**
   - **Cancelled** — by clinic or patient request, any time before completion. Reason optional.
   - **No-show** — patient never arrived. Normally only settable after `start_at` has passed (soft rule, overridable — see §5).
   - **Rescheduled** — not a separate terminal status; it's an in-place update of `start_at`/`dentist_id` that re-runs conflict detection and is fully captured by the existing audit-log trail (§11).

---

## 3. Appointment Statuses & Transitions

```
scheduled ──► confirmed ──► checked_in ──► in_progress ──► completed
    │             │              │
    ├─────────────┴──────────────┼──────────────► cancelled
    │                            │
    └────────────────────────────┴──────────────► no_show
```

Rules:
- `scheduled` is the only entry status (set automatically on create).
- `confirmed` is optional — `scheduled → checked_in` directly is valid; a clinic that skips phone confirmations shouldn't be forced through it.
- `checked_in → in_progress → completed` is a strict forward chain — you cannot jump straight from `checked_in` to `completed` (the system should reflect that a visit actually happened in the chair), but skipping `in_progress` is allowed by directly moving to `completed` **only if explicitly confirmed by the user in this design** — my recommendation is to require passing through `in_progress` for data-quality reasons feeding future wait-time/chair-utilization reports. Flagging as a decision point.
- `cancelled` and `no_show` are terminal — a corrected mistake creates a **new** appointment rather than resurrecting one (keeps the historical record honest for reporting).
- No transition may move backwards (e.g. `completed → scheduled` is invalid) — corrections happen by cancelling and rebooking, not by mutating history.
- All transitions are enforced in `AppointmentService`, not just trusted from the frontend — the same defense-in-depth pattern already used for policy checks.

---

## 4. Database Design

### `appointments`

| Column | Type | Notes |
|---|---|---|
| `id` | uuid | PK |
| `patient_id` | uuid | FK → `patients`, required |
| `dentist_id` | uuid | FK → `users`, required (app-level check that the referenced user has role `dentist`, not a DB constraint — same approach as everywhere else roles are checked) |
| `appointment_type_id` | uuid | FK → `appointment_types`, required |
| `start_at` | timestamp | required |
| `end_at` | timestamp | required, **computed by the service** as `start_at + duration_minutes` — never set directly by the client |
| `duration_minutes` | integer | required; defaults from `appointment_type.default_duration_minutes` but overridable per booking (real visits sometimes need more/less time than the type's default) |
| `status` | string → backed enum `AppointmentStatus` | see §3 |
| `reason` | text, nullable | patient's stated reason for the visit ("chief complaint") |
| `notes` | text, nullable | internal staff notes |
| `cancellation_reason` | text, nullable | free text, optional even when cancelling (no forced friction) |
| `cancelled_at` / `cancelled_by` | timestamp / uuid FK → `users`, nullable | |
| `checked_in_at` | timestamp, nullable | |
| `started_at` | timestamp, nullable | when status → `in_progress` |
| `completed_at` | timestamp, nullable | |
| `no_show_at` | timestamp, nullable | |
| `reschedule_count` | integer, default 0 | incremented on every in-place reschedule — cheap for reporting without parsing audit logs |
| `deleted_at` | timestamp, nullable | soft delete — see below, distinct from `cancelled` |
| `created_at` / `updated_at` | timestamp | |

**Why 5 nullable timestamp columns instead of relying only on the audit log:** the audit log (`AuditLog`, generic JSON diff) already captures every field change for compliance/history purposes, but Reports (§21) need fast, indexable, non-JSON columns for KPIs like average wait time (`started_at - checked_in_at`) and dentist utilization. This is a deliberate, purpose-built duplication — not redundant, since the two serve different consumers (audit trail vs. operational reporting).

**Why `cancelled` (status) is separate from `deleted_at` (soft delete):** a cancelled appointment is a real business event that must stay visible in cancellation-rate/no-show reports and on the patient's visit history. Soft delete is reserved for pure data-entry mistakes (booked the wrong patient entirely) that should disappear from every list and report, mirroring how Patients/Users already treat soft delete vs. status.

### `appointment_types`

| Column | Type | Notes |
|---|---|---|
| `id` | uuid | PK |
| `name` | string | clinic-entered, **not translated** — see rationale below |
| `default_duration_minutes` | integer | |
| `color` | string (hex) | for calendar/board color-coding |
| `is_active` | boolean, default true | soft-disable without breaking historical appointments' FK |
| `created_at` / `updated_at` | timestamp | no soft delete — `is_active` covers the "retire this type" use case, consistent with the "lookup tables are the exception to soft delete" rule in `database-design.md` |

**Why a real table, not a backed enum like `UserRole`:** appointment types are clinic-configurable (an orthodontics-focused clinic and a general-dentistry clinic have different type lists) and carry extra attributes (duration, color) — exactly the case `database-design.md` already calls out as the trigger to use a table instead of an enum.

**Why `name` isn't localized (no per-locale JSON column):** this is clinic-entered *data*, not application UI text — the same category as `insurance_provider` on Patients, which also isn't translated. A clinic operating in Arabic will simply name its types in Arabic.

Seeded with a sensible default set on install (Consultation, Cleaning, Filling, Extraction, Root Canal, Follow-up, Emergency) editable via a minimal admin-only CRUD included in this module (not deferred to a future Settings module — appointments cannot function without at least one type, so this is core, not an extra).

### `dentist_working_hours`

| Column | Type | Notes |
|---|---|---|
| `id` | uuid | PK |
| `user_id` | uuid | FK → `users` (dentist) |
| `day_of_week` | tinyint (0=Sunday..6=Saturday) | |
| `start_time` | time | |
| `end_time` | time | |
| `is_active` | boolean, default true | lets a row be disabled without deleting it |

A dentist can have **multiple rows for the same day** (e.g. 09:00–13:00 and 15:00–19:00, to model a lunch break) — this is why it's a table of ranges, not one start/end pair per day.

### `dentist_time_off`

| Column | Type | Notes |
|---|---|---|
| `id` | uuid | PK |
| `user_id` | uuid | FK → `users` (dentist) |
| `start_at` / `end_at` | timestamp | the blocked range (vacation day, half-day, conference, etc.) |
| `reason` | string, nullable | |

### Relationships

```
patients (1) ───< appointments >─── (1) users [dentist_id]
appointment_types (1) ───< appointments
users [dentist] (1) ───< dentist_working_hours
users [dentist] (1) ───< dentist_time_off
users [cancelled_by] (1) ───< appointments (nullable)
appointments ───< audit_logs (polymorphic, via existing Auditable infra)
```

No `branch_id` anywhere yet — see §7.

---

## 5. Business Rules

1. Every appointment belongs to exactly one patient and one dentist.
2. `end_at` is always server-computed from `start_at + duration_minutes`; clients never set it directly.
3. **Dentist double-booking is hard-blocked** (physically impossible for one dentist to be in two places). Checked against all appointments in `{scheduled, confirmed, checked_in, in_progress}` — `cancelled`/`no_show`/`completed` don't count as occupying the slot.
4. **Patient double-booking is a soft warning**, not a hard block — real clinics legitimately book a patient with two providers back-to-back or concurrently (e.g., hygienist + dentist). The API returns a `409`-style warning payload the frontend must show and let staff explicitly confirm past.
5. Booking outside a dentist's working hours or during their time-off is also a **soft warning, not a hard block** — emergencies and exceptions are common in a dental practice; rigid enforcement would just get worked around by staff booking a nearby "safe" time and lying about the real one.
6. `duration_minutes` bounded to a sane range (`5`–`480`) at the validation layer — pure sanity bound, not a workflow rule.
7. No restriction on booking in the past (staff need to log same-day walk-ins and backfill data entry).
8. Status transitions must follow §3's state machine — enforced in the service layer.
9. `no_show` should normally only be settable after `start_at` has passed — soft rule (warning, not a block), since staff sometimes correct records after the fact.
10. Create/update/cancel/reschedule/check-in: `admin` + `receptionist`. Status progression `checked_in → in_progress → completed`: also open to the treating `dentist` for their **own** appointments. Soft-delete: `admin` only (mirrors Patients/Users). Full detail in §14.

---

## 6. Dentist Schedule Management

- **Weekly template** (`dentist_working_hours`): recurring hours per day-of-week, multiple rows per day supported (lunch breaks, split shifts).
- **Time-off** (`dentist_time_off`): one-off exceptions — vacation, conference, sick leave, holiday.
- **Slot suggestion**: `AppointmentService::availableSlots(dentist, date, duration)` computes working-hour ranges for that day, subtracts existing non-cancelled appointments and time-off, and slices the remainder into candidate start times at a configurable granularity (`config('appointments.slot_interval_minutes')`, default 15).
- **Who edits what** (recommended, flagging as a decision point): the recurring weekly template is **admin-only** (prevents a dentist from silently expanding their own booking window in a way the front desk doesn't expect); **time-off** can be self-service — a dentist can create/cancel their own time-off entries, admin can manage anyone's. This mirrors how real clinics operate: the practice sets the standard schedule, but a dentist requesting a day off is routine and shouldn't require an admin as a bottleneck.

---

## 7. Branch Management (deferred, non-blocking)

Multi-branch is explicitly deferred project-wide (`PROJECT_CONTEXT.md`, `decisions.md`) — no `branches` table exists yet. This design adds **no** `branch_id` column now (there'd be nothing to reference). Future path, documented here so it's not a surprise later:

- Add nullable `branch_id` to `appointments`, `dentist_working_hours`, `dentist_time_off`, and `users` when multi-branch is actually built.
- Conflict-detection and availability queries would gain a `WHERE branch_id = ?` clause.
- This is a purely additive migration — nothing in this design has to be undone or reshaped to support it later.

---

## 8. Chairs / Operatories — recommend deferring

Real PMS software (Dentrix, Open Dental) treats the chair/operatory as a schedulable resource independent of the dentist, because in larger clinics chairs outnumber dentists (hygienist chairs, assistant-staffed rooms). Two options:

**Option A — Build chair scheduling now.** A `chairs` table + `chair_id` on appointments + a second conflict-detection dimension (chair, not just dentist).
- Pros: matches "real" dental PMS feature parity from day one.
- Cons: real added complexity (a second overlap-detection axis, a second admin CRUD, a second UI concept) for a need that isn't in `PROJECT_CONTEXT.md`'s module list and that a solo-dentist-per-chair clinic (the likely V1 customer profile) doesn't need — one dentist implicitly *is* one chair, so dentist-level conflict detection already prevents the double-booking that matters.

**Option B — Defer.** No chair table. If needed later, it's an additive table + additive nullable `chair_id` FK + an additional conflict check — doesn't invalidate anything built now.

**Recommendation: Option B.** Matches the project's explicit "don't add features beyond what the task requires" / no-speculative-building philosophy (same reasoning already applied to multi-branch and to deferring photo upload in Patients). Revisit when a clinic with more chairs than dentists is a real, stated requirement.

---

## 9. Appointment Duration & Calculation

- Each `appointment_type` carries a `default_duration_minutes`, pre-filling the booking form.
- Staff can override the duration per booking (a "simple filling" sometimes needs more chair time than the type's default).
- `end_at` is always `start_at + duration_minutes`, computed server-side in `AppointmentService`, never client-supplied — prevents a client bug/tamper from creating an inconsistent range.
- Slot-suggestion granularity is a config value (default 15 minutes), not hardcoded, so it can be tuned per-clinic later without a schema change.

---

## 10. Conflict Detection

Two layers, deliberately redundant (belt-and-suspenders), because a double-booked dentist is one of the worst failure modes for a clinic:

1. **Application-level check** (`AppointmentService`, inside a DB transaction): query for any appointment on the same `dentist_id` in an active status where `start_at < new.end_at AND end_at > new.start_at`. Fast, portable (works identically on SQLite for tests and Postgres for prod), and lets the API return a friendly, specific error instead of a raw constraint violation.
2. **Database-level safety net** (Postgres only): a `EXCLUDE` constraint using the `btree_gist` extension on `(dentist_id, tsrange(start_at, end_at)) WHERE status NOT IN ('cancelled','no_show')`, guarded by a driver check in the migration exactly like the `pg_trgm` trigram-index migration already added for Patients search. This is the actual guarantee against a race condition where two receptionists submit overlapping bookings for the same dentist within milliseconds of each other — the app-level check alone has a TOCTOU gap under concurrent requests.

`btree_gist` is a built-in Postgres contrib extension (not a third-party package), consistent with how `pg_trgm` was already justified and enabled for Patients.

---

## 11. Rescheduling

**Recommended: in-place update**, not cancel-and-recreate.

- `start_at` (and optionally `dentist_id`) is updated directly on the existing row; `reschedule_count` increments; the existing `Auditable` trait/`AuditLog` infrastructure automatically captures the old → new values, exactly as it already does for Patient edits.
- Re-runs full conflict detection (§10) against the new slot.
- Status is untouched by a reschedule (a `confirmed` appointment that gets moved stays `confirmed`, doesn't reset to `scheduled`).

**Alternative considered — cancel old row + create new row, linked by a self-referential `rescheduled_from_id`.**
- Pros: makes "chain of reschedules" trivially queryable without touching `audit_logs`.
- Cons: duplicates data, adds a nullable self-referential FK, and produces two rows in every list/report for a single real-world appointment unless every query remembers to filter — more schema surface for a need (chain-of-reschedule reporting) nobody has asked for yet.

**Recommendation: in-place**, matching the project's "prefer readability... no premature abstraction" standard and reusing infrastructure that already exists, exactly the same reasoning already applied when Audit Logs were built generically during Patients.

---

## 12. Cancellation

- `status → cancelled`, stamps `cancelled_at`/`cancelled_by`, `cancellation_reason` optional (no forced friction — matches "Minimal Clicks" UI principle).
- Does **not** soft-delete — stays visible for no-show/cancellation-rate reporting and patient history.
- Terminal: a cancelled appointment can't be un-cancelled; correcting a mistaken cancellation means booking a new appointment.

---

## 13. No-Show Handling

- `status → no_show`, stamps `no_show_at`.
- Soft rule: normally only allowed after `start_at` has passed, with an explicit override allowed for backfilling records (see §5.9).
- Terminal, same as cancellation — rebooking creates a new row.
- No-show tracking directly feeds a future "flag frequently-no-show patients" report — schema already supports it (`patient_id` + `status = no_show` + `no_show_at` is enough), no future migration needed.

---

## 14. Permissions Per Role

| Action | Admin | Receptionist | Dentist |
|---|---|---|---|
| View all appointments (clinic-wide) | ✅ | ✅ | ✅ (read-only, clinic-wide — see rationale below) |
| Create / update / reschedule any appointment | ✅ | ✅ | ❌ |
| Cancel / mark no-show | ✅ | ✅ | ❌ |
| Check-in a patient | ✅ | ✅ | ❌ |
| Progress own appointment (`checked_in → in_progress → completed`) | ✅ | ❌ | ✅ (own appointments only) |
| Soft-delete an appointment | ✅ | ❌ | ❌ |
| Manage appointment types (CRUD) | ✅ | ❌ | ❌ |
| Manage own working hours (weekly template) | ✅ (any dentist) | ❌ | ❌ (view only) |
| Manage own time-off | ✅ (any dentist) | ❌ | ✅ (own only) |

**Why dentists get clinic-wide read visibility, not just their own appointments:** a dental clinic is a small trusted team working off one shared day-board (covering for a colleague, checking who's free, seeing the whole day's flow) — this isn't PII-sensitive the way Patient demographic *edits* are already restricted; it mirrors how the front desk needs full visibility to do its job. Flagging as a decision point in case the real clinics you're targeting want stricter dentist-to-dentist privacy — easy to tighten later (swap the policy's `viewAny` gate), not a schema change.

---

## 15. UI/UX Design

Primary audience: **the receptionist**, who will use this screen dozens of times a day — optimize for minimal clicks per the project's UI principles.

- **Board/Calendar view (primary)**: day view by default, week view toggle, optional filter by dentist. Appointments color-coded by `appointment_type.color`, with a distinct visual treatment (e.g. diagonal stripe or dimmed) for `cancelled`/`no_show` so the board stays legible without hiding history. Clicking an empty slot opens "New Appointment" prefilled with that dentist/time. Clicking an existing appointment opens a detail panel with one-click status actions (Check In / Start / Complete / Cancel / No-Show) — no need to open a full edit form for the common case of moving a patient through their visit.
- **List view (secondary)**: a `DataTable`, reusing the same pattern as `PatientsView`/`UsersView` — for staff who prefer searching/filtering over browsing a calendar, and for admin reviewing history.
- **New/Edit dialog**: patient search-as-you-type (reuses the existing Patients search UX/endpoint), dentist + type dropdowns, live-computed end time as duration changes, inline "Available Slots" helper that greys out unavailable times instead of letting staff guess-and-fail.
- **Patient detail page**: gets a new "Appointments" tab/panel (mirrors the existing audit-log panel pattern on `PatientDetailView`) showing that patient's appointment history.
- **Conflict warnings**: patient-double-booking and outside-working-hours warnings surface inline in the dialog with an explicit "Book Anyway" confirmation — never a silent auto-accept, never a hard dead-end for legitimate edge cases.
- **Drag-and-drop rescheduling on the board is explicitly out of V1 scope** — listed in §25 Future Improvements. V1 reschedule happens through the edit dialog's date/time fields. Rationale in §15a below.

### 15a. Calendar UI library — needs your explicit decision (new package)

PrimeVue ships a date-*picker*, not a scheduling/time-grid board. Building a real day/week scheduling board (overlapping-appointment layout, time-grid rendering) from scratch in Tailwind is substantial, easy-to-get-subtly-wrong work that a mature library already solves.

**Option A — `@fullcalendar/vue3` (recommended).** Industry-standard scheduling library, MIT-licensed core (day/week/resource views are free; only the premium resource-timeline view is paid, which this design doesn't need). One new frontend dependency.
**Option B — Build a custom simplified day-view board.** No new dependency, but real engineering time spent on a solved problem, and likely worse edge-case handling (overlapping-appointment layout, etc.) than a mature library, at least for V1.

**Recommendation: Option A**, flagged explicitly per the "every third-party package addition must be called out, not silently added" rule in `coding-standards.md`. This is the one new package this module needs — please confirm before I add it to `package.json`.

---

## 16. API Design

Follows `docs/api-guidelines.md` conventions (unwrapped single resources, paginated collections, thin controllers, `Store{X}Request`/`Update{X}Request`).

```
GET    /api/appointments?date_from=&date_to=&dentist_id=&patient_id=&status=
                                           (auth:sanctum, any role; bounded by
                                            required date range instead of classic
                                            pagination — same exemption rationale
                                            as the Dashboard summary endpoint)
POST   /api/appointments                  (admin/receptionist)
GET    /api/appointments/{appointment}    (any role)
PUT    /api/appointments/{appointment}    (admin/receptionist — full edit incl. reschedule)
DELETE /api/appointments/{appointment}    (admin only — soft delete)

POST   /api/appointments/{appointment}/confirm     (admin/receptionist)
POST   /api/appointments/{appointment}/check-in    (admin/receptionist)
POST   /api/appointments/{appointment}/start        (admin/receptionist/own dentist)
POST   /api/appointments/{appointment}/complete      (admin/receptionist/own dentist)
POST   /api/appointments/{appointment}/cancel        (admin/receptionist)
POST   /api/appointments/{appointment}/no-show       (admin/receptionist)

GET    /api/available-slots?dentist_id=&date=&duration_minutes=   (any role)

GET/POST/PUT/DELETE /api/appointment-types[/{type}]   (admin only)
GET/POST/DELETE      /api/dentists/{user}/working-hours[/{entry}]  (admin only)
GET/POST/DELETE      /api/dentists/{user}/time-off[/{entry}]       (admin any; dentist own)
```

Each status-transition endpoint is a dedicated action rather than overloading `PUT` with a `status` field — makes authorization per-action explicit (e.g. `start`/`complete` open to the treating dentist, but `cancel` isn't) and matches REST practice for state-machine-driven resources.

---

## 17. Validation Rules

- `patient_id`: required, must exist in `patients` and not be soft-deleted.
- `dentist_id`: required, must exist in `users`, app-level check that `role === dentist`.
- `appointment_type_id`: required, must exist and be `is_active`.
- `start_at`: required, valid datetime.
- `duration_minutes`: required, integer, `5`–`480`.
- `reason` / `notes` / `cancellation_reason`: nullable text, length-capped.
- Status-transition endpoints validate the *current* status allows the requested transition (service-layer state machine, not just a form rule).
- Conflict/availability checks happen in the service, not the form request, since they require querying other rows — form requests validate shape, services validate business rules (existing project convention).

---

## 18. Security Considerations

- Same defense-in-depth pattern as Users/Patients: `FormRequest::authorize()` delegates to `AppointmentPolicy`, controllers never inline role checks.
- Dentist-scoped actions (`start`/`complete`) verify `dentist_id === auth()->id()` in the policy — prevents one dentist from marking another dentist's patient as seen (IDOR).
- `appointments` gets the `Auditable` trait from day one (reuses existing generic infra) — `reason`/`notes` can contain clinically-adjacent free text, same sensitivity class as Patients' `medical_history`.
- No new attack surface beyond the existing Sanctum session-cookie model; all new endpoints sit behind `auth:sanctum` like everything else.
- `available-slots` endpoint reveals dentist schedule gaps — acceptable to any authenticated staff member (not sensitive), consistent with clinic-wide visibility already decided in §14.

---

## 19. Performance Considerations

- Composite indexes: `(dentist_id, start_at)`, `(patient_id, start_at)`, `status`. The Postgres `EXCLUDE` constraint from §10 also functions as an index for overlap queries.
- List/board queries eager-load `patient`, `dentist`, `appointmentType` (avoids N+1, same `Resource`-driven pattern as `PatientResource`).
- Calendar-range queries are naturally bounded (a week of one clinic's appointments is at most a few hundred rows) — no pagination needed there, but the plain list endpoint still respects the "never return unbounded collections" rule via its required date range.
- `available-slots` computation is O(appointments in that one day for one dentist) — trivially fast at realistic clinic scale.

---

## 20. Scalability Considerations

- UUID PKs (consistent with the rest of the schema) keep the design shard/replica-friendly if DentalSuite scales out as multi-tenant SaaS later.
- Per-clinic appointment volume is modest (low thousands/year for a typical practice) — no partitioning or archival strategy needed at V1 scale.
- Multi-branch-ready by construction (§7) — adding it later is additive, not a redesign.
- No session-affinity or in-memory state introduced — horizontal API scaling (multiple app containers behind the existing nginx/Docker setup) works unchanged.

---

## 21. Reports That Will Depend on Appointment Data

Not built in this module (Reports is a separate future module per the roadmap), but the schema is deliberately shaped to support these without a later migration:

- Daily/weekly schedule board (the board view itself doubles as this).
- No-show rate per dentist / per patient / per period.
- Cancellation rate per dentist / per period.
- Dentist utilization (booked minutes ÷ available working-hours minutes).
- Average wait time (`started_at - checked_in_at`) and average visit duration (`completed_at - started_at`).
- Per-patient visit history (surfaced early via the Patient-detail "Appointments" tab in §15, ahead of the full Reports module).

---

## 22. Future Integration Points

- **Dental Chart**: future `dental_chart_entries.appointment_id` FK — charting done during a specific visit.
- **Treatment Plans**: future FK linking a treatment-plan step to the appointment it was carried out in, or scheduled against.
- **Billing**: future `invoices`/`invoice_items.appointment_id` — a `completed` appointment is the natural trigger for billable-item generation.
- **Notifications**: future reminder job queries `appointments` where `start_at` is within the next N hours and `status IN (scheduled, confirmed)` — no schema changes needed to support this later; the Laravel queue worker is already running in the dev stack (`composer dev` script) so the infrastructure exists, only the notification-sending code doesn't yet.

`appointments.id` is the stable FK target for all four — nothing above requires touching this module's schema, only adding FKs on the other side later.

---

## 23. Architectural Decisions & Trade-offs (Summary)

| Decision | Recommended | Alternative | Why |
|---|---|---|---|
| Appointment types | Real table | Backed enum | Clinic-configurable, carries extra attributes (duration/color) — matches existing rule for when to use a table over an enum |
| Chairs/operatories | Defer (§8) | Build now | No stated need yet; dentist-level conflict detection already prevents the double-booking that matters for a solo-dentist-per-chair clinic |
| Branches | Defer, additive-only later (§7) | Add nullable `branch_id` now | No `branches` table exists; nothing to reference yet |
| Reschedule | In-place update + audit log | Cancel + new linked row | Reuses existing Auditable infra, avoids duplicate rows nobody's asked to query yet |
| Conflict prevention | App check + Postgres `EXCLUDE` constraint | App check only | Closes a real TOCTOU race under concurrent booking; mirrors the existing `pg_trgm` precedent |
| Patient double-booking | Soft warning, overridable | Hard block | Legitimate multi-provider same-slot bookings happen in real clinics |
| Outside working-hours booking | Soft warning, overridable | Hard block | Emergencies/exceptions are routine, not rare |
| Calendar UI | `@fullcalendar/vue3` (new dependency — needs your sign-off) | Custom-built board | Mature, solved problem; avoids re-solving overlapping-event layout badly |
| Dentist schedule visibility | Clinic-wide read for all staff | Dentist sees only own | Small trusted team, shared day-board is normal clinic operation |
| Weekly template ownership | Admin-only edits | Dentist self-service | Prevents inconsistent, un-reviewed booking-window changes |
| Time-off ownership | Admin (any) + dentist (own) | Admin-only | Routine self-service request, shouldn't need an admin bottleneck |

---

## 24. Risks

- **Race conditions on concurrent booking** — mitigated by the Postgres `EXCLUDE` constraint (§10), not just app-level checking.
- **Timezone bugs** — the Patients module already hit a timezone bug (`date_of_birth` shifting a day via naive UTC parsing). Appointments involve *timestamps*, not just dates, so this risk is larger here. Mitigation: store all timestamps in UTC (Laravel default), do all date-math server-side, and extend the existing `src/lib/date.ts` timezone-safe helpers rather than writing new ad-hoc date handling in the calendar UI. Single-clinic-timezone assumption for V1 (no per-branch timezone yet, consistent with branches being deferred).
- **Scope creep into Reports/Notifications/Settings** — mitigated by strictly bounding this module to booking + lifecycle tracking; the schema supports those future modules without needing to be reshaped, but none of their features (reminders, KPI dashboards, type-management-as-part-of-Settings) are built here.
- **`@fullcalendar/vue3` learning curve / bundle size** — mitigated by only pulling in the day-grid/time-grid free packages actually needed, not the full plugin suite.
- **Irregular provider schedules** (split shifts, lunch breaks) — handled by allowing multiple `dentist_working_hours` rows per day (§6), not a single start/end pair.

---

## 25. Future Improvements (explicitly out of V1 scope)

- Drag-and-drop rescheduling on the calendar board.
- Automated SMS/WhatsApp/email reminders (Notifications concern).
- Patient self-booking portal.
- Waitlist management (fill a cancellation slot automatically).
- Recurring appointments (e.g., monthly orthodontic visits).
- Chair/operatory scheduling (§8).
- Multi-branch scheduling (§7).
- Family/linked-patient block booking.
- Insurance eligibility pre-check before booking.
- Recall/recare automated scheduling (e.g., 6-month checkup reminders) — a very standard dental-PMS feature, deliberately deferred rather than omitted by oversight.

---

## Open Decision Points for Your Review

Summarized from throughout the doc — please confirm, adjust, or reject each:

1. **§3** — Should `checked_in → completed` be allowed to skip `in_progress`, or is passing through `in_progress` mandatory (my recommendation, for report data quality)?
2. **§8** — Confirm deferring chairs/operatories (recommended) vs. building chair scheduling now.
3. **§14** — Confirm clinic-wide read visibility for dentists (recommended) vs. dentist-sees-only-own.
4. **§15a** — Approve adding `@fullcalendar/vue3` as a new frontend dependency (recommended), or prefer a custom-built board.
5. **§6** — Confirm admin-only ownership of the weekly working-hours template, with dentist self-service only for time-off (recommended split).
6. Any V1 scope item in §25 you'd like pulled *into* V1 instead of deferred (e.g., is recall/recare actually wanted now)?

No code will be written until you respond. I'm ready to start Implementation as soon as this design (and the open points above) are approved, with or without adjustments.
