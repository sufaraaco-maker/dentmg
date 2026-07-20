# Dental Chart Module — Implementation Plan

**Status: Design approved (2026-07-20) — see `dental-chart-design-draft.md` for the full design record and
the six open decisions, all now resolved (§0 below). This plan turns those approved decisions into a
concrete, buildable spec. No migration, model, or component exists yet — still awaiting explicit approval
of this plan before Step 1 of §9 begins.** Continues on `feature/dental-chart`; `main` untouched.

---

## 0. Resolved Decisions (carried forward from the design draft's §20)

| # | Decision | Resolution |
|---|---|---|
| 1 | Tooth numbering | **FDI**, 32 permanent + 20 primary, no `teeth` table — backend static helper + frontend constants |
| 2 | Core domain model | **One `DentalChartEntry`**, status lifecycle `existing → active → planned → completed/cancelled`, no separate Diagnosis/Treatment-Plan/History tables |
| 3 | Chart placement | **`PatientDetailView.vue` tab** (Overview / Appointments / Dental Chart / room for future tabs), not a dedicated route |
| 4 | Dentist ownership | **No hard restriction** — any dentist/admin can write for any patient — but full actor attribution preserved (`created_by_id`, `updated_by_id`, `dentist_id` — see §1.2) |
| 5 | Treatment Plan link | **Deferred entirely** — no `treatment_plan_item_id` column, not even unconstrained |
| 6 | Billing/external codes | **Deferred entirely** — no `external_code`/`billing_code` column |
| 7 | Auto-supersession | **Deferred** — an `active` finding and the `completed` procedure that addresses it both remain as independent, permanent records; no automatic linking or status rewriting |

---

## 1. Backend

### 1.1 Database Schema

**`dental_conditions`** (catalog):

```php
Schema::create('dental_conditions', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('name');
    $table->string('category');              // cast: DentalConditionCategory (finding|procedure)
    $table->boolean('applies_to_surface')->default(false);
    $table->string('default_color', 7);       // '#RRGGBB', same regex as appointment_types.color
    $table->string('icon_key')->nullable();
    $table->boolean('is_active')->default(true);
    $table->unsignedSmallInteger('sort_order')->nullable();
    $table->timestamps();
});
```

**`dental_chart_entries`**:

```php
Schema::create('dental_chart_entries', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('patient_id')->constrained()->cascadeOnDelete();
    $table->foreignUuid('dental_condition_id')->constrained();
    $table->foreignUuid('dentist_id')->constrained('users');       // clinical attribution
    $table->foreignUuid('created_by_id')->constrained('users');    // actor attribution, see §1.2
    $table->foreignUuid('updated_by_id')->nullable()->constrained('users');
    $table->string('tooth_number', 2);        // FDI code, validated against ToothChart, not an FK (design draft §6)
    $table->json('surfaces')->nullable();     // e.g. ["M","O"]; null when not surface-specific
    $table->string('status');                 // cast: DentalChartEntryStatus
    $table->text('notes')->nullable();
    $table->timestamp('recorded_at');
    $table->timestamp('completed_at')->nullable();
    $table->timestamp('cancelled_at')->nullable();
    $table->softDeletes();
    $table->timestamps();

    $table->index('patient_id');
    $table->index(['patient_id', 'tooth_number']);
    $table->index(['patient_id', 'status']);
    $table->index('dental_condition_id');
});
```

`patient_id` cascades on delete (matches how `appointments.patient_id` behaves today — confirm against the
live `appointments` migration at implementation time rather than assumed, since a patient hard-delete is
rare — soft delete is the normal path — but the FK behavior must be consistent project-wide). All other FKs
are `restrictOnDelete` (default) — a `dental_condition`/`user` should never be hard-deletable while
referenced by chart history (mirrors the existing `appointments.dentist_id`/`appointment_type_id` behavior).

### 1.2 Audit / Actor Attribution — `created_by_id` / `updated_by_id`, and how this relates to `audit_logs`

This is an intentional, explicit **addition** to this project's established pattern, not a duplication of
it — worth stating plainly since neither `Patient` nor `Appointment` carries its own actor columns; they
rely solely on `audit_logs.user_id` (set automatically by `AuditLogService::record()` via `Auth::id()`).

- `audit_logs` remains the single authoritative, complete change history (every create/update/delete, full
  diff) — `DentalChartEntry` still `use Auditable;` and gets this for free, unchanged.
- `created_by_id`/`updated_by_id`/`dentist_id` on the entry itself are a **display-only denormalization**:
  they let the UI show "Recorded by Dr. X" directly on a chart entry card without a second query into audit
  history — genuinely useful here (unlike Patients/Appointments) because a chart entry is small, numerous
  (many per patient), and routinely rendered in a dense grid/list where per-row audit lookups would be
  wasteful. Never a substitute for `audit_logs` — both exist, doing different jobs.
- Set in the Service layer, not the controller: `created_by_id = Auth::id()` once at creation (immutable
  after — no edit ever changes who created a record); `updated_by_id = Auth::id()` on every subsequent
  write (edit, complete, cancel).
- `dentist_id` is a distinct concept from `created_by_id`: it's *clinical* attribution (which dentist this
  finding/procedure is attributed to) and is an explicit field on the create form; `created_by_id` is
  *system* attribution (which logged-in account performed the data entry) and is never a form field — it's
  always the authenticated user. In the common case they're the same person; they diverge when an admin
  enters/corrects a chart entry on a dentist's behalf.

### 1.3 Enums

```php
enum DentalConditionCategory: string
{
    case Finding = 'finding';
    case Procedure = 'procedure';
}

enum DentalChartEntryStatus: string
{
    case Existing = 'existing';
    case Active = 'active';
    case Planned = 'planned';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    /** @return list<self> */
    public static function transitionsFrom(self $status): array
    {
        return match ($status) {
            self::Active => [self::Planned],
            self::Planned => [self::Completed, self::Cancelled],
            self::Existing, self::Completed, self::Cancelled => [],
        };
    }
}
```

### 1.4 `App\Support\ToothChart`

Static helper, not a model — no database table (design draft §6):

```php
final class ToothChart
{
    /** @return list<string> all 52 valid FDI codes */
    public static function allCodes(): array { /* generated from quadrant ranges 1-4 (permanent), 5-8 (primary) */ }

    public static function isValidCode(string $code): bool { /* ... */ }

    public static function isPermanent(string $code): bool { /* first digit 1-4 */ }

    /** True for incisors/canines (positions 1-3 in each quadrant) — drives the O-vs-I surface rule */
    public static function isAnterior(string $code): bool { /* ... */ }

    /** e.g. "Upper Right First Molar" — used for audit-log rendering and API resource enrichment */
    public static function displayName(string $code): string { /* ... */ }
}
```

Mirrored, hand-written (not code-generated — same accepted BE/FE duplication as every other type in this
project, e.g. `AppointmentStatus` enum vs. `appointment.ts`'s status union) in `frontend/src/lib/teeth.ts`.

### 1.5 Models

**`DentalCondition`** — `HasUuids`, `HasFactory` (no `Auditable`, no `SoftDeletes` — catalog config data,
same tier as `AppointmentType`). `casts()`: `category` → enum, `applies_to_surface`/`is_active` → boolean,
`sort_order` → integer. `hasMany(DentalChartEntry::class)`. `scopeActive()` mirrors `AppointmentType`.

**`DentalChartEntry`** — `Auditable`, `HasUuids`, `HasFactory`, `SoftDeletes`. `casts()`: `surfaces` →
array, `status` → enum, `recorded_at`/`completed_at`/`cancelled_at` → datetime. Relations: `patient()`,
`dentalCondition()`, `dentist()` (`belongsTo(User::class, 'dentist_id')`), `createdBy()`/`updatedBy()`
(`belongsTo(User::class, ...)`). Accessor `getDentitionTypeAttribute()` → `ToothChart::isPermanent(...)
? 'permanent' : 'primary'` (computed, never stored — design draft §6). `scopeForPatient()`,
`scopeForTooth()`, `scopeWithStatus()` — thin query scopes, no business logic (business logic stays in the
Service, per `coding-standards.md`).

### 1.6 Form Requests & Validation Rules

`StoreDentalConditionRequest` / `UpdateDentalConditionRequest` — mirrors
`Store/UpdateAppointmentTypeRequest` exactly: `name` required string, `category` required `Rule::enum(...)`,
`default_color` required `regex:/^#[0-9A-Fa-f]{6}$/`, `applies_to_surface` required boolean, `icon_key`
nullable string, `is_active` boolean.

`StoreDentalChartEntryRequest`:
- `dental_condition_id` — required, `exists:dental_conditions,id`.
- `dentist_id` — required, `exists:users,id` (Service/Policy additionally verifies the referenced user's
  role is `dentist`, not just any user — mirrors how `AppointmentService` verifies `dentist_id`).
- `tooth_number` — required, custom rule backed by `ToothChart::isValidCode()`.
- `surfaces` — array, conditionally required: a closure rule that loads the referenced
  `dental_condition.applies_to_surface` and (a) requires ≥1 entry when true, (b) requires empty/absent when
  false; each surface value validated against the fixed `['M','D','F','L','O','I']` set, and `O`/`I`
  cross-checked against `ToothChart::isAnterior($tooth_number)`.
- `status` — required, `Rule::enum(...)`, restricted to `{existing, active, planned, completed}` on create
  (a brand-new entry is never created pre-cancelled — `cancelled` is only reachable via the `/cancel`
  transition endpoint).
- `notes` — nullable string, max length matching the project's existing note-field convention (check
  `Appointment.notes`'s validation at implementation time for the exact figure, reuse it verbatim).
- `recorded_at` — nullable, defaults to now in the Service if absent; when present, validated as a real
  datetime through the same `date.ts`-paired backend convention already used for `appointments.start_at`.

`UpdateDentalChartEntryRequest` — same shape minus `status` (transitions are their own endpoints, §1.8);
Service additionally rejects an edit attempt on an entry whose status is `completed` or `cancelled` (mirrors
"can't edit a completed Appointment's core fields" precedent) except for `notes`, which stays editable.

### 1.7 Services

**`DentalConditionService`** — thin CRUD, mirrors `AppointmentTypeService`.

**`DentalChartService`**:
- `listForPatient(string $patientId, array $filters): Collection` — the single query backing both the
  odontogram and the list view (API design §1.8), eager-loading `dentalCondition`, `dentist`, `createdBy`.
- `create(array $data, User $actor): DentalChartEntry` — sets `created_by_id = $actor->id`.
- `update(DentalChartEntry $entry, array $data, User $actor): DentalChartEntry` — sets
  `updated_by_id = $actor->id`; rejects if `status` is terminal (§1.6).
- `complete(DentalChartEntry $entry, User $actor): DentalChartEntry` — validates current status is
  `planned` via `DentalChartEntryStatus::transitionsFrom()`, else throws `InvalidStatusTransitionException`
  (reuse the existing Appointments exception class — same shape, same meaning, no new exception type
  needed for this one case; confirm at implementation time whether it's generic enough to reuse directly
  or needs a `Dental`-namespaced sibling with identical behavior, per `coding-standards.md`'s "no
  duplicated code" — reuse if the existing class has no Appointments-specific wording baked in).
- `cancel(DentalChartEntry $entry, User $actor): DentalChartEntry` — same transition-guard pattern.
- `delete(DentalChartEntry $entry): void` — soft delete, admin-only (enforced in Policy, not here).

### 1.8 Policies

**`DentalConditionPolicy`**: `viewAny`/`view` → any authenticated role; `create`/`update`/`delete` → admin
only. Mirrors `AppointmentTypePolicy` exactly.

**`DentalChartEntryPolicy`**:
- `viewAny`/`view` → any authenticated role (admin/dentist/receptionist — design draft §19).
- `create`/`update`/`complete`/`cancel` → admin or dentist.
- `delete` → admin only.
- No per-record ownership check (resolved decision #4) — every ability checks role only, not
  `$entry->dentist_id === $user->id`.

### 1.9 Controllers & API Endpoints

```
GET    /api/dental-conditions                                  (any role)
POST   /api/dental-conditions                                  (admin)
PUT    /api/dental-conditions/{condition}                      (admin)
DELETE /api/dental-conditions/{condition}                      (admin — deactivate is the real path; see below)

GET    /api/patients/{patient}/dental-chart-entries             (any role) — ?status=&tooth_number=&category=
POST   /api/patients/{patient}/dental-chart-entries             (admin, dentist)
PUT    /api/dental-chart-entries/{entry}                        (admin, dentist)
POST   /api/dental-chart-entries/{entry}/complete                (admin, dentist)
POST   /api/dental-chart-entries/{entry}/cancel                  (admin, dentist)
DELETE /api/dental-chart-entries/{entry}                         (admin)
```

`DELETE /api/dental-conditions/{condition}` follows `AppointmentTypeController`'s existing precedent
exactly if that endpoint actually deactivates rather than hard-deletes when referenced — **verify
`AppointmentTypeController::destroy()`'s real behavior at implementation time and mirror it precisely**,
rather than assuming; do not invent a different delete semantic for a structurally identical catalog.

**`DentalChartEntryResource`** shape (example):

```json
{
  "id": "uuid",
  "patient_id": "uuid",
  "tooth_number": "16",
  "dentition_type": "permanent",
  "surfaces": ["M", "O"],
  "status": "planned",
  "notes": "Deep caries, recommend composite filling",
  "recorded_at": "2026-07-20T09:00:00+00:00",
  "completed_at": null,
  "cancelled_at": null,
  "dental_condition": { "id": "uuid", "name": "Composite Filling", "category": "procedure", "default_color": "#2563eb", "icon_key": "filling" },
  "dentist": { "id": "uuid", "name": "Dr. Layla Hassan" },
  "created_by": { "id": "uuid", "name": "Dr. Layla Hassan" },
  "updated_by": null
}
```

Datetime fields via `->toIso8601String()`, matching every existing Resource exactly (`decisions.md`'s
project-wide datetime policy — no deviation).

### 1.10 Testing Strategy — Backend

`DentalConditionTest` (mirrors `AppointmentTypeTest`): full CRUD, admin-only write, deactivation-not-delete
behavior, active-only scoping.

`DentalChartEntryTest`: create/list/update per role (admin/dentist allowed, receptionist read-only,
verified with a 403 on write), the conditional surface-required validation (both directions: required when
`applies_to_surface`, rejected when not), the O/I anterior/posterior rule (a posterior `O` on tooth 11
rejected, on tooth 16 accepted; vice versa for `I`), the full status-transition matrix (`active→planned`
allowed, `planned→completed`/`cancelled` allowed, `completed→*`/`cancelled→*` rejected with the standard
409/422 shape — confirm which status code applies once `InvalidStatusTransitionException`'s existing
render() is reused, §1.7), `created_by_id`/`updated_by_id` correctness across create then edit by a
*different* authenticated user, soft-delete admin-only enforcement, and `tooth_number`/FDI-code validation
(valid codes accepted, out-of-range codes like `"19"` or `"91"` rejected).

---

## 2. Frontend

### 2.1 Component Architecture — `components/dental-chart/`

Deliberately split, no single oversized component:

| File | Responsibility |
|---|---|
| `ToothChart.vue` | Layout/orchestration only — arranges the four quadrants (or two arches), owns the selected-tooth/surface state, renders `ToothSvg.vue` per tooth plus `ToothLegend.vue` and `DentalChartToolbar.vue`. No SVG geometry and no entry-status color logic of its own — delegates both down. |
| `ToothSvg.vue` | One tooth's schematic shape — the tooth outline plus its surface regions, each rendered via `ToothSurface.vue`. Computes its own display color/icon from the entries passed in as a prop (pure, presentational — no store access), and owns the tooth-level `aria-label`/keyboard-focus target (design draft §18). |
| `ToothSurface.vue` | A single clickable surface region (or the whole-tooth region for a non-surface condition) — emits `surface-click`/`tooth-click`, purely presentational, no business logic. Kept separate from `ToothSvg.vue` specifically so surface-selection interaction logic doesn't bloat the tooth-shape component, and so a future "3D/alternate rendering mode" (deferred, design draft §22) could swap this one piece without touching `ToothSvg.vue`. |
| `ToothLegend.vue` | The persistent condition-color/icon + status-tone key (design draft §16) — reads from the `dentalConditions` store directly, no props needed from `ToothChart.vue`. |
| `DentalChartToolbar.vue` | View toggle (Chart / List), status/tooth/category filters for the list view, "Add Entry" button, dentition filter (Permanent/Primary/All) — mirrors `CalendarToolbar.vue`'s role in the Appointments module (a dedicated toolbar component, not inlined into the view). |
| `ChartEntryDialog.vue` | Add/edit dialog — Diagnosis/Procedure tabs (`Tabs`/`TabList`/`Tab`/`TabPanels`/`TabPanel`, matching `AppointmentDialog.vue`'s exact tab-component usage, §2.3), condition picker, surface picker (reuses `ToothSurface.vue`'s click regions in a small single-tooth preview), status field, notes. |
| `ChartEntryListTable.vue` | Accessible list view — `DataTable`, mirrors `AppointmentListTable.vue`'s client-paginated, sortable convention. |
| `DentalConditionFormDialog.vue` | Catalog create/edit dialog — mirrors `AppointmentTypeFormDialog.vue` (including its `ColorPicker` + synced hex `InputText` pattern for `default_color`). |

`DentalConditionsView.vue` (route-level, admin catalog CRUD) lives in `views/`, mirrors
`AppointmentTypesView.vue` exactly (client-side search/sort/"show inactive" over the cached catalog list —
same reasoning as `AppointmentTypeSelect`'s dropdown source: a small, rarely-changing admin-managed list).

### 2.2 Stores, Services, Types

| File | Role |
|---|---|
| `types/dentalChart.ts` | `DentalCondition`, `DentalChartEntry`, `DentalConditionCategory`, `DentalChartEntryStatus`, payload/response types — matches every backend field exactly, no `any` |
| `services/dentalChart/dentalConditionsApi.ts` | mirrors `appointmentTypesApi.ts` |
| `services/dentalChart/dentalChartEntriesApi.ts` | `list(patientId, filters)`, `create`, `update`, `complete`, `cancel`, `delete` |
| `stores/dentalConditions.ts` | Catalog cache, mirrors `appointmentTypes.ts` (including its in-flight-request guard — §1's Appointments precedent for concurrent-mount duplicate-request protection) |
| `stores/dentalChart.ts` | Per-patient entry cache (keyed by `patientId`, not a range-cache like `appointments.ts` — a patient's chart is fetched whole, not by date window, per design draft §14's "deliberately unpaginated" decision); exposes both a `groupedByTooth` computed (odontogram data source) and the raw flat array (list-view data source) from one fetch — no duplicate network calls for the two views |
| `lib/teeth.ts` | Frontend mirror of `App\Support\ToothChart` (§1.4) |

### 2.3 `PatientDetailView.vue` Refactor — Stacked Cards → Tabs

Current state (verified by reading the file directly): one `grid grid-cols-1 lg:grid-cols-2` of `Card`s
(Demographics, Contact, Medical, Insurance), `PatientAppointmentsPanel` spanning both columns, and an
admin-only audit-history `Card`, all stacked under one header — no tabs today.

**Planned refactor**, using the exact `Tabs`/`TabList`/`Tab`/`TabPanels`/`TabPanel` components already
established by `AppointmentDialog.vue` and `DentistScheduleView.vue` (no new tab-component pattern
introduced):

- **Overview** tab — the existing 4-card grid + the admin-only audit-history table, unchanged content,
  just moved inside a `TabPanel`.
- **Appointments** tab — `PatientAppointmentsPanel`, unchanged, moved inside a `TabPanel`.
- **Dental Chart** tab — new: `DentalChartToolbar.vue` + `ToothChart.vue`/`ChartEntryListTable.vue`
  (toggle) + `ChartEntryDialog.vue`.
- Tab strip is structured to make adding a future tab (Treatment Plans, Billing, Clinical Notes) a
  small additive change, not a reshape — matching the "room for future modules" instruction directly.

This touches shipped, tested code (`PatientDetailView.test.ts` if it exists — confirm at implementation
time — must be updated, not just the new tab's own tests added) — called out explicitly as its own
implementation step (§9) rather than folded silently into "build the chart," since it's a real change to
an existing view, not purely additive.

### 2.4 Odontogram Requirements

- **Schematic rendering**: simplified geometric tooth shapes (rounded-rect body, up to 5 surface regions as
  sub-paths), not anatomically photorealistic — matches every competitor product's actual daily-use chart
  (design draft §0, §16).
- **Expandable architecture**: adding a new `dental_conditions` catalog entry (new finding/procedure type)
  must never require a frontend code change — `ToothSvg.vue`/`ToothSurface.vue` render color/icon purely
  from the entry's `dental_condition.default_color`/`icon_key` data, never a hardcoded per-condition
  `if`/`switch`. `icon_key` maps to a small fixed *rendering* glyph set (X, parallel-lines, filled-shape,
  outlined-shape — a bounded set of visual treatments, §16), not one icon per condition name — a new
  condition picks an existing glyph, it doesn't need a new one drawn for it.
- **Status visualization**: color (condition-specific hue) × status (tone/saturation modifier, per the
  blue/black-vs-red convention, design draft §16) × icon glyph — three independent signals, so status is
  never conveyed by color alone (design draft §18 accessibility requirement).
- **Surface selection**: click/keyboard-activate a specific surface region (when the condition being added
  is surface-specific) or the whole tooth (when not) — `ToothSurface.vue`'s emitted event carries which
  surface was hit; `ChartEntryDialog.vue` pre-fills from it exactly like `AppointmentsView.vue`'s
  slot-click-prefills-the-dialog pattern for the Calendar Board.
- **Legend system**: `ToothLegend.vue`, always visible alongside the chart (not a separate dialog),
  reading the live `dentalConditions` catalog — the legend can never drift out of sync with the actual
  condition set, since it's not a hardcoded static image.

### 2.5 RTL / LTR — Critical Requirement

Restated from the design draft (§17) as a concrete implementation rule: **`ToothChart.vue`'s root element
carries `dir="ltr"` unconditionally**, independent of `locale.value`/the page's own `dir`. Tooth position
and left/right arrangement render identically in Arabic and English — only text labels inside it (tooth
names in tooltips/`aria-label`s, the legend's own text) follow normal RTL rendering for their language.

This needs its own explicit unit test (assert the rendered root always has `dir="ltr"` regardless of
`locale.value`) **and** a mandatory real-browser Arabic verification screenshot at implementation time —
not inferred from the unit test alone, per the project's standing "real browser verification is mandatory
for UI-facing work" rule, given this is the highest-stakes correctness requirement in the whole module.

---

## 3. Additional Design Considerations

### 3.1 Multi-Tenant SaaS Readiness

No structural change needed to add a future `organization_id` to either table later (design draft §11,
unchanged) — both `dental_conditions` and `dental_chart_entries` would take the same additive-nullable-
then-backfilled column any other table in the system would, whenever a real multi-tenant requirement
appears. Not implemented now.

### 3.2 Future AI Integration Points

Unchanged from the design draft (§23): structured, queryable chart data (never a canvas/blob) means a
future Dashboard Insights feature or AI Analytics Assistant can query through the existing
Service/Resource layers without a rebuild; `DentalChartEntryCreated`/`DentalChartEntryCompleted` are
plausible future domain events (not built now) that would slot into the AI Follow-up/Recall vision the same
way `AppointmentCompleted` already does.

### 3.3 Clinical Data Integrity

- `created_by_id` is immutable after creation; `patient_id`/`tooth_number` are immutable after creation
  (§1.6, mirrors Appointments' "patient_id not editable" precedent) — a miscreated entry is cancelled/
  deleted and recreated, never silently reassigned.
- Soft deletes throughout — nothing is ever truly unrecoverable from the database.
- The `active`/`completed` findings-and-procedures-both-remain-visible behavior (resolved decision #7)
  is itself a data-integrity choice: the chart never silently rewrites what a dentist observed in the past
  just because a later procedure addressed it.
- `dental_conditions` are deactivated, never hard-deleted while referenced (§1.9) — no chart entry can ever
  end up pointing at a condition that no longer resolves to a name/color.

### 3.4 Performance Considerations

- `listForPatient()` (§1.7) is a single eager-loaded query per chart view, not N+1 per tooth.
- Deliberately unpaginated per-patient endpoint (design draft §14, restated) — bounded by realistic scale
  (at most 52 teeth × a handful of entries), not clinic-wide.
- Frontend: `ToothSvg.vue` as an isolated child component per tooth (Vue reactivity scoped per-tooth, not
  one giant reactive array driving a full-chart re-render on every click) — up to ~260 clickable surface
  regions total (52 teeth × ≤5 surfaces), well within normal budgets if scoped correctly (design draft §21).

### 3.5 Testing Strategy (full picture)

- **Backend**: §1.10 above — full Feature-test coverage, PHPStan/Larastan level 5 clean, Pint clean.
- **Frontend Vitest**: every new store/service/component in isolation — `ToothSvg.vue`'s color/icon
  derivation from entry data, `ToothSurface.vue`'s click/keyboard emit contract, the conditional
  surface-required form validation, the RTL `dir="ltr"` assertion (§2.5), the status-transition button
  visibility table (mirrors `AppointmentActionsBar`'s existing test pattern).
- **Manual real-browser verification** (mandatory per the two-phase workflow for any UI-facing step, not
  optional): full create/edit/complete/cancel flow, Arabic/English × light/dark, the `PatientDetailView`
  tab refactor confirmed non-regressive for the existing Overview/Appointments content, keyboard-only
  navigation through the odontogram, and — specifically — the anatomical-mirroring check in Arabic (§2.5).
- **E2E**: extend `frontend/e2e/` with a Dental Chart spec once the module is otherwise complete, following
  the same real-slot-driven, non-hardcoded pattern the Appointments E2E suite had to learn the hard way
  (`TECH_DEBT.md`'s CI entry) — not assumed to be a smaller/simpler suite than Appointments' was.

### 3.6 Known Risks

Unchanged from the design draft (§21): the anatomical-mirroring bug class (highest stakes, mitigated by
§2.5), tooth-numbering lock-in (mitigated by deciding FDI now with real research behind it), odontogram
build effort (mitigated by schematic-not-photorealistic scope), DOM/reactivity scale (mitigated by
per-tooth component scoping). One implementation-plan-specific addition:

- **`PatientDetailView.vue` refactor regression risk** (§2.3) — converting a shipped, tested stacked-card
  view into a tabbed layout touches working code; mitigated by treating it as its own explicit
  implementation step with its own verification pass (§9), not folded silently into the new chart work.

### 3.7 Deferred Features List

Unchanged from the design draft (§22): bulk/multi-tooth intake entry, periodontal charting, freehand
drawing/tooth-movement annotations, chart PDF export, imaging attachments, full Treatment Plan
sequencing/cost/acceptance, automatic finding-to-procedure supersession, a Universal-notation display
toggle.

---

## 4. Proposed Implementation Sequence

Same checkpoint-per-step discipline as Appointments §20 (implement → real browser verification → report →
wait for approval before the next step):

1. Database + Enums + `App\Support\ToothChart` + Models + Factories.
2. Form Requests + `DentalConditionService`/`DentalChartService` + Policies.
3. Controllers + Resources + routes + backend Feature tests (§1.10).
4. `dental_conditions` seeder (default catalog — proposed list unchanged from design draft §24: Caries,
   Fracture, Missing Tooth, Impacted Tooth; Composite Filling, Amalgam Filling, Crown, Root Canal
   Treatment, Extraction, Implant, Sealant, Bridge, Veneer).
5. Frontend infrastructure — types, services, stores, `lib/teeth.ts`, i18n keys.
6. `DentalConditionsView.vue` + `DentalConditionFormDialog.vue` (admin catalog CRUD).
7. `ToothSvg.vue` + `ToothSurface.vue` (the core schematic rendering, built and verified in isolation via
   Storybook-less manual/Vitest testing before wiring into the full chart) — highest-effort, highest-risk
   step; RTL verification (§2.5) is mandatory before this step is signed off.
8. `ToothChart.vue` + `ToothLegend.vue` + `DentalChartToolbar.vue` (assembling the pieces from Step 7).
9. `ChartEntryDialog.vue` + `ChartEntryListTable.vue`.
10. `PatientDetailView.vue` tab refactor (§2.3) — its own explicit step, with regression verification of
    the pre-existing Overview/Appointments content.
11. Accessibility/keyboard-navigation pass + final QA + E2E spec, mirroring Appointments' Step 9/10
    structure.

---

**Awaiting explicit approval of this plan before Step 1 begins. No migration, model, or component has been
created.**
