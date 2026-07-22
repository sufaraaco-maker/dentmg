# Dental Chart Module — Design Document (DRAFT, pending approval)

**Status: Design Phase — no code written yet, per the project's two-phase workflow. This document must be
explicitly approved before any migration/model/component is created.** Once approved, it becomes the basis
for implementation the same way `appointments-design-draft.md` did, and is superseded by
`docs/modules/dental-chart.md` (final module doc) only once implementation, tests, and QA are complete.

Grounded in the current, shipped state of the codebase (`Patient`, `Appointment`, `AppointmentType`,
`Auditable` trait, `docs/database-design.md`, `docs/api-guidelines.md`, `docs/decisions.md` — verified
directly, not assumed) — this design reuses those exact conventions rather than inventing new ones.

---

## 0. Competitive Research (required before any UI design, per standing product philosophy)

Reviewed how leading dental PMS platforms structure charting, since this is the first genuinely
clinical/visual module (Patients and Appointments are administrative/scheduling, not clinical charting):

| Product | What it does | Taken / rejected |
|---|---|---|
| **Open Dental** | Graphical Tooth Chart: per-tooth conditions, missing-tooth marking, primary/permanent toggle, freehand drawing, referred-out treatment. Functionally comprehensive but visually dated (2000s-era desktop-app look). | Taken: comprehensive per-tooth condition/status model. Rejected: freehand drawing (niche, high build cost, not worth it for V1 — see Deferred). |
| **CareStack** | Modern odontogram, 2D/3D toggle, Permanent/Primary/Unerupted dentition filter, a single **Legend** covering Conditions, Treatments, Materials, and Tooth Chart Colors together. | Taken directly: the unified condition+color legend concept, and the "one entry, a status field drives color" model (see §7) instead of separate Diagnosis/Procedure/History tables. 3D view rejected as unnecessary complexity for V1 (2D schematic is what every platform's actual daily-use chart looks like). |
| **Dentrix (Ascend)** | Documented charting-symbol conventions: black/blue = completed work, red = planned/needed work; specific symbol shapes per condition (X for missing, parallel lines for extraction, filled vs outlined for amalgam vs composite). | Taken directly: the blue/black = existing-or-completed, red = planned/active-finding color convention (§7, §16) — this is a near-universal clinical convention across the whole industry, not one vendor's house style, so deviating from it would be a real usability regression for any dentist who has used *any* prior PMS. |
| **Curve Dental / Eaglesoft / Oryx** | No further vendor-specific detail found beyond the pattern already confirmed above; general 2026 trend across all of them is customizable 2D charts tightly integrated with treatment planning and imaging. | Confirms the design direction below (structured, queryable chart entries — not a canvas drawing) rather than contradicting it. |

**What DentalSuite does differently / better, not just clones**: every competitor above ships charting as a
closed desktop-era widget. This design keeps the chart as **structured, typed, queryable API data** (not a
drawing/canvas blob) from day one — every entry is a real row with a real status, condition, and audit
trail — which is what lets it plug into Dashboard Insights, search, and the future AI layer later without a
rebuild (see §21, §26). This is the same "structured over freehand" choice already made correctly in
Appointments (a real state machine, not a free-text status field).

Sources: [CareStack charting features](https://carestack.com/dental-software/features/charting),
[CareStack odontogram guide](https://carestack.zendesk.com/hc/en-us/articles/27732426396564-A-Guide-to-the-Odontogram),
[Open Dental Chart module manual](https://www.opendental.com/manual/chart.html),
[Open Dental Graphical Tooth Chart manual](https://opendental.com/manual/graphicaltoothchart.html),
[Dentrix Ascend charting symbols](https://hsps.pro/DentrixAscend/Help/Charting_symbols.htm).

---

## 1. Module Goal

Give dentists and admins a structured, auditable per-patient dental chart (odontogram): record diagnostic
findings and dental work — existing, planned, and completed — per tooth and per tooth surface, using the
FDI numbering system, with a clear visual chart and an equivalent accessible list view. This module is the
clinical record of *what's true about a patient's teeth*; it deliberately does not own scheduling
(Appointments already does), cost/insurance (future Billing), multi-visit treatment sequencing (future
Treatment Plans), or free-text clinical narratives (future Clinical Notes) — see §4 for the explicit
boundary with each.

## 2. Scope (V1)

**In scope:**
- Interactive tooth chart (odontogram) covering permanent and primary (deciduous) dentition, FDI notation.
- Per-tooth, per-surface charting of conditions/findings (e.g. caries, fracture, missing, impacted) and
  procedures (e.g. filling, crown, root canal, extraction, implant, sealant, bridge, veneer).
- A lifecycle status per entry (`existing` / `active` / `planned` / `completed` / `cancelled`) that
  simultaneously serves as: the diagnosis record, the lightweight treatment-planning signal, and the
  completed-procedures history — one entity, not three (see §7 for why).
- Admin-managed catalog of dental conditions/procedures (`dental_conditions`), mirroring `appointment_types`.
- A dedicated, patient-scoped chart view (odontogram + equivalent list view + legend) and an admin catalog
  screen.
- Full audit trail (reusing the existing `Auditable` trait — no new mechanism).

**Explicitly out of scope for V1** (named modules already exist in `PROJECT_CONTEXT.md`'s roadmap — this
module must not duplicate or pre-empt their design):
- **Treatment Plans** (multi-visit sequencing, cost estimation, patient acceptance/signature, insurance
  pre-authorization) — a `planned` chart entry is the seam this future module attaches to (§20's open
  decision on `treatment_plan_item_id`), not a partial implementation of it.
- **Billing/Payments** (procedure pricing, CDT/insurance codes, claims) — no cost fields on this module's
  catalog. See §20's open decision on an optional `external_code` column.
- **Clinical Notes** (free-text visit narratives/SOAP notes) — chart entries carry only a short per-entry
  note, not a general clinical-notes feature.
- **Imaging** (X-rays/photos attached per tooth) — a future optional attachment relationship on chart
  entries, not built now.
- **Periodontal charting** (pocket depths, bleeding points, mobility scores) — a materially different,
  much more complex clinical dataset every PMS above treats as a separate feature from the odontogram; not
  part of V1.

## 3. Clinical Workflow

**Primary flow — charting during/after an exam (dentist, admin):**
1. Open a patient's Dental Chart (from `PatientDetailView` or a dedicated route — see §17 open decision).
2. See the current odontogram: every tooth colored/marked per its most clinically relevant entries (a
   missing tooth is grayed with an X; a tooth with an active finding shows red; a tooth with completed work
   shows blue/black — per the industry-standard convention confirmed in §0).
3. Click a tooth (or a specific surface region on a tooth) → "Add Chart Entry" dialog opens, prefilled with
   that tooth/surface.
4. Pick a condition from the catalog (UI splits **Diagnosis** vs **Procedure** tabs, per the
   `dental_conditions.category` field — mirrors CareStack's Conditions/Treatments split, §0) → set status
   (a finding defaults to `active`; a procedure defaults to `planned`, with `completed` selectable directly
   for retroactively logging already-done work, e.g. during a new patient's intake) → optional note → save.
5. Chart re-renders that tooth immediately (optimistic-friendly, small payload).
6. A `planned` procedure entry can later be transitioned to `completed` (after the visit where it's actually
   done) or `cancelled` (patient declined/no longer needed) via explicit action buttons — mirroring
   Appointments' explicit transition-endpoint pattern (§14), not a generic status dropdown.

**Secondary flow — reviewing history:** toggle to List View (a sortable table of every entry, filterable
by tooth/status/date) — the same visual-chart-plus-equivalent-list-view pairing already established by
Appointments' Board/List toggle, and required here for accessibility (§18) as much as convenience.

**Intake flow — new patient baseline:** front-loading a new patient's existing dental work (fillings,
crowns, missing teeth already present) happens through the same single-entry dialog, one tooth at a time.
A faster multi-tooth bulk-entry flow is a plausible near-term want but is deliberately **not** V1 scope —
see §22 Future Improvements; adding it later doesn't require any data-model change, only a new dialog mode.

## 4. Business Rules

- A chart entry always references exactly one tooth (`tooth_number`); there is no "whole mouth" entry —
  general/whole-mouth remarks belong to the future Clinical Notes module, not here.
- `surfaces` is required (min 1) when the entry's `dental_condition.applies_to_surface` is true (e.g.
  caries, filling), and must be empty when false (e.g. missing, extraction, implant — whole-tooth
  conditions). Enforced server-side, not just in the UI.
- A surface code of `O` (Occlusal) is only valid on a posterior tooth (premolar/molar); `I` (Incisal) only
  on an anterior tooth (incisor/canine) — derived from the FDI tooth number itself (§7), enforced
  server-side. Prevents clinically nonsensical data (an "Occlusal" surface charted on a front tooth).
- Status transitions are a fixed lookup table, not a free-value field (mirrors `AppointmentService`'s
  transition-map pattern exactly):
  - `active` → `planned` (a finding is now being treated) or `resolved`-via-supersession (no explicit
    "resolved" status needed for V1 — see §20 open decision) — **kept minimal for V1**: a new procedure
    entry addressing an old finding does not automatically alter the finding's own status; a dentist
    manually reviews/cancels stale findings. Automatic supersession logic is deferred (§22) rather than
    guessed at.
  - `planned` → `completed` or `cancelled` (terminal states for a planned procedure).
  - `existing` and `completed` are effectively terminal/historical — editable (notes/surfaces) but not
    status-transitionable, mirroring how a completed Appointment can't un-complete.
  - `cancelled` is terminal.
- A dental condition can be deactivated (`is_active = false`) without breaking existing entries that
  reference it — mirrors `AppointmentType`'s "still resolves a since-deactivated type on an existing
  appointment" precedent exactly.
- Deleting a chart entry (hard-remove behind soft-delete) is a data-correction action, not a clinical
  action — gated tighter than `cancel` (see §19 Permissions).

## 5. Domain Model — Entities & Relationships

```
Patient (existing)
  └─┬─ hasMany ─→ DentalChartEntry
    │               ├─ belongsTo → DentalCondition (catalog)
    │               ├─ belongsTo → User (dentist_id — who recorded it)
    │               └─ tooth_number: string (FDI code, validated against the static tooth catalog, §7 —
    │                   NOT a foreign key to a database table; teeth are fixed reference data, not editable
    │                   records, so a `teeth` table would be a lookup table with no real CRUD need)
    │
DentalCondition (catalog, admin-managed — new)
  └─ hasMany ─→ DentalChartEntry
```

No new relationship is added to `Patient` beyond a `hasMany`, matching exactly how `appointments()` was
added to `Patient` in the Appointments module — no reshape of the existing model.

## 6. Tooth Chart Representation

**Numbering system: FDI (ISO 3950) two-digit notation**, not Universal (1-32) or Palmer. Reasoning:
- FDI is the WHO/global standard and is confirmed by research (§0) to be the better fit for structured EHR
  data entry specifically because it's a fixed two-digit code per tooth (quadrant + position) rather than a
  single sequential number that shifts meaning between permanent/primary dentition (Universal uses separate
  letter/number ranges for primary teeth, FDI's quadrant-based scheme extends uniformly).
- FDI is also the dominant convention across Europe, the Middle East, and most of the world outside
  North America — a better default fit for a product whose current locales are Arabic/English/Turkish.

**This is a real, hard-to-reverse decision — flagged explicitly for approval, not assumed silently.** If a
future clinic strongly prefers Universal notation, the *storage* stays FDI (avoids ever migrating historical
clinical records) and only the *display* layer needs a pure client-side lookup/conversion table — a cheap,
purely additive UI toggle, never a backend change. This same reasoning is why FDI is picked to store now
even if display-format flexibility is added later.

**Coverage**: 32 permanent teeth (FDI 11–18, 21–28, 31–38, 41–48) + 20 primary teeth (FDI 51–55, 61–65,
71–75, 81–85) = 52 valid tooth codes total. Dentition type (permanent vs primary) is **derived from the
code itself** (first digit 1-4 = permanent, 5-8 = primary) — never stored as a separate column, so it can
never drift out of sync with the tooth number.

**No `teeth` database table.** The 52-code catalog plus per-tooth metadata (display name, arch, quadrant,
anterior/posterior) is static reference data, not user-editable — implemented as a single backend
constant/helper (`App\Support\ToothChart`, used for validation and any server-side tooth-name rendering)
mirrored by a frontend TypeScript constant (`frontend/src/lib/teeth.ts`, used for rendering/labels) — the
same "no DB table for small/fixed value sets" principle `database-design.md` already states for enums,
just implemented as a plain constant here rather than a PHP backed enum (52 cases would be unwieldy
compared to one small generated list + a validation helper).

## 7. Tooth Surfaces, Diagnoses, Treatment-Planning Relationship, Completed-Procedures History

**One core decision drives this whole section: a single `DentalChartEntry` model with a `status` field,
not three separate tables for Diagnosis / Treatment Plan / History.**

Considered and rejected: separate `dental_diagnoses`, `dental_procedures_planned`, and
`dental_procedures_completed` tables. Rejected because (a) they'd be near-identical in shape (tooth,
surfaces, condition, dentist, notes, timestamps) — a textbook case of the "no duplicated code" rule, just
applied to schema instead of logic; (b) a real clinical fact routinely moves through this exact lifecycle
(an *active* caries finding becomes a *planned* filling becomes a *completed* filling) — modeling that as
three tables would mean copying data across tables to represent one continuous story, when a single
`status` transition (§4) represents it naturally, the same way `Appointment.status` already models a
lifecycle instead of three separate appointment-state tables; (c) CareStack's own unified legend (§0)
confirms real products present this as one coherent per-entry status, not three visually distinct systems.

**Tooth surfaces**: six possible surface codes — `M` (Mesial), `D` (Distal), `F` (Facial — covers
Buccal/Labial, unified under one term to avoid an anterior/posterior-dependent code), `L`
(Lingual/Palatal), `O` (Occlusal, posterior only), `I` (Incisal, anterior only). Stored as a JSON array on
the entry (`surfaces: ["M","O"]`), required only when the condition applies to a surface (§4).

**Diagnoses** = entries where `dental_condition.category = finding` and `status ∈ {active, existing}`.

**Treatment-planning relationship** = entries where `dental_condition.category = procedure` and
`status = planned`. This module does **not** implement multi-visit sequencing, cost, or acceptance — it
exposes the seam a future Treatment Plans module attaches to (§20 open decision on `treatment_plan_item_id`).

**Completed procedures history** = entries where `dental_condition.category = procedure` and
`status = completed`, each carrying `completed_at` — the append-only clinical history for a tooth. Combined
with `existing` (procedures present before this system was used, entered once at intake), this is the full
"what has been done to this tooth" record.

## 8. Database Design

### `dental_conditions` (catalog — mirrors `appointment_types` exactly)

| Column | Type | Notes |
|---|---|---|
| `id` | uuid | PK |
| `name` | string | e.g. "Dental Caries", "Composite Filling", "Crown — Porcelain" |
| `category` | string, cast to `DentalConditionCategory` enum (`finding`, `procedure`) | Drives the Diagnosis/Procedure tab split in the picker UI (§3) and the entry's default status |
| `applies_to_surface` | boolean | Whether this condition requires ≥1 surface (§4) |
| `default_color` | string (`^#[0-9A-Fa-f]{6}$`) | Same validation as `appointment_types.color` |
| `icon_key` | string, nullable | Maps to one of a small fixed set of frontend-rendered SVG glyphs (X, parallel lines, filled/outlined shape, etc. — §16), never a stored SVG blob |
| `is_active` | boolean | Same deactivate-without-breaking-history pattern as `appointment_types` |
| `sort_order` | integer, nullable | Stable, clinically-grouped ordering in the picker |
| `created_at` / `updated_at` | timestamp | |

No soft delete — a catalog entry, like `appointment_types`, is either active or not; genuinely removing one
that's already referenced would orphan history, so deactivation (not deletion) is the only supported
lifecycle, same as `AppointmentTypeService`'s existing behavior.

### `dental_chart_entries`

| Column | Type | Notes |
|---|---|---|
| `id` | uuid | PK |
| `patient_id` | uuid, FK → `patients`, not nullable | |
| `dental_condition_id` | uuid, FK → `dental_conditions`, not nullable | |
| `dentist_id` | uuid, FK → `users`, not nullable | Who recorded the entry (and, once `completed`, who performed it — no separate "performed by" column; see §20 if this proves insufficient) |
| `tooth_number` | string(2) | FDI code, validated against `App\Support\ToothChart` (§6) — **not** a DB foreign key (§6) |
| `surfaces` | json, nullable | Array of surface codes (§7); null/empty when not surface-specific |
| `status` | string, cast to `DentalChartEntryStatus` enum (`existing`, `active`, `planned`, `completed`, `cancelled`) | §4 transition rules |
| `notes` | text, nullable | Per-entry note, not a general clinical note (§2 scope boundary) |
| `recorded_at` | timestamp | When charted (defaults to now, editable for backdating intake data) |
| `completed_at` | timestamp, nullable | Set on the `complete` transition only |
| `cancelled_at` | timestamp, nullable | Set on the `cancel` transition only |
| `deleted_at` | timestamp, nullable | Soft delete, per `database-design.md` convention |
| `created_at` / `updated_at` | timestamp | |

Indexes: `(patient_id)`, `(patient_id, tooth_number)`, `(patient_id, status)`, `(dental_condition_id)`.

**`dentition_type` (permanent/primary) is deliberately not a column** — computed from `tooth_number` via a
model accessor (§6), avoiding a value that could drift out of sync with the tooth number it's derived from.

Both tables get `Auditable` (§10) and follow every convention in `database-design.md` §"Conventions" —
UUID PK, timestamps, `SoftDeletes` on the record-bearing table — with no exceptions.

## 9. Table Relationships

- `patients.id` ← `dental_chart_entries.patient_id` (one patient, many entries — unbounded over a
  lifetime, but naturally small per patient; see §9's performance note, actually §14).
- `dental_conditions.id` ← `dental_chart_entries.dental_condition_id` (catalog reference, many entries per
  condition).
- `users.id` ← `dental_chart_entries.dentist_id` (who recorded it; any dentist, not exclusively an
  "assigned" one — see §19 for why no ownership/IDOR restriction is proposed, unlike Appointments'
  `start`/`complete` dentist-ownership check).
- No relationship yet to a `treatment_plan_items` table (doesn't exist) or `appointments` table — both are
  plausible future links (§20, §22), not built now.

## 10. Audit Requirements

Reuses the existing generic `Auditable` trait/`AuditObserver`/`AuditLogService` infrastructure verbatim —
**no new audit mechanism**, exactly like `Patient` and `Appointment` already opt in. `DentalChartEntry` is
clinical PII at least as sensitive as `Patient` (arguably more so — it's a diagnosis/treatment record, not
just demographics), so it must `use Auditable` from the first migration, not added later. `DentalCondition`
(the catalog) does not need audit logging — it's configuration data, not a patient record, same tier as
`AppointmentType` (which also doesn't audit-log).

No dedicated `GET /api/patients/{patient}/dental-chart-entries/audit-logs` route is proposed for V1 — same
gap already open and tracked for Appointments (`TECH_DEBT.md`), not re-litigated here; the write-side
capture existing unconditionally is what matters for compliance/integrity, the read-side UI is a small
additive follow-up whenever convenient.

## 11. Multi-Tenant / Future SaaS Scale Considerations

V1 stays single-organization, no multi-tenancy (per `PROJECT_CONTEXT.md`, unchanged) — but checked against
the standing "don't foreclose this later" constraint:
- Neither new table has any column that would need to change shape to add a future `organization_id`/
  `clinic_id` — it would be a straightforward additive nullable column (then backfilled and made
  not-null), the exact same pattern already used for multi-branch elsewhere in the project. No design
  choice here assumes single-tenancy structurally.
- The catalog/instance split (`dental_conditions` vs `dental_chart_entries`) is itself multi-tenant-ready
  shape: a future tenant-scoped catalog is a straightforward `organization_id` addition to the catalog
  table alone, without touching the entry table's shape.
- API-first, no direct DB access from any future consumer (chart data is only ever reached through the
  Service/Controller/Resource layers already standard here) — satisfies the AI-layer vision's "never
  direct DB access" principle from day one, at zero extra cost.

## 12. Patient History Integrity

- Soft deletes mean a chart entry is never truly gone from the database, even if hidden from normal views —
  matches the same integrity bar already applied to `patients`/`appointments`.
- Status transitions are one-directional except for the deliberately narrow `cancelled`→(no revival)
  terminal state — a cancelled planned procedure isn't silently un-cancelled; a new entry is created if the
  work is reconsidered, preserving an honest history of "this was planned, then cancelled, then re-planned"
  rather than rewriting the original record.
- `dental_conditions` deactivation (not deletion) is what protects historical entries from ever pointing at
  a condition that no longer resolves to a name/color (§4, §8) — identical reasoning to `AppointmentType`.
- No entry's `patient_id` or `tooth_number` is editable after creation (mirrors Appointments' "patient_id
  not editable" rule) — a chart entry created on the wrong tooth or the wrong patient must be cancelled/
  deleted and recreated, never silently reassigned, so the audit trail always reflects what actually
  happened rather than a corrected-after-the-fact record.

## 13. Backend Architecture

| Layer | Files |
|---|---|
| Migrations | `create_dental_conditions_table.php`, `create_dental_chart_entries_table.php` |
| Enums | `App\Enums\DentalConditionCategory` (`finding`, `procedure`), `App\Enums\DentalChartEntryStatus` (`existing`, `active`, `planned`, `completed`, `cancelled`) |
| Support | `App\Support\ToothChart` — static tooth catalog + validation/lookup helpers (§6), not a model |
| Models | `DentalCondition`, `DentalChartEntry` (`Auditable`, `HasUuids`, `SoftDeletes`) |
| Form Requests | `DentalCondition/{Store,Update}DentalConditionRequest`, `DentalChartEntry/{Store,Update}DentalChartEntryRequest` |
| Services | `DentalConditionService` (thin CRUD, mirrors `AppointmentTypeService`), `DentalChartService` (create/update/complete/cancel/delete, the status-transition map, patient-scoped snapshot query) |
| Policies | `DentalConditionPolicy` (admin write, any-role read), `DentalChartEntryPolicy` (§19) |
| Controllers | `DentalConditionController`, `DentalChartEntryController` (nested under patient for index/store, flat for the rest — mirrors `PatientController::auditLogs`'s nested-route pattern) |
| Resources | `DentalConditionResource`, `DentalChartEntryResource` |
| Tests | `DentalConditionTest`, `DentalChartEntryTest` — full CRUD, transitions, per-role authorization, validation edge cases (surface-required conditional rule, O/I anterior/posterior rule) |

## 14. API Design

```
GET/POST/PUT/DELETE  /api/dental-conditions[/{condition}]     (admin write, any-role read — mirrors appointment-types)

GET   /api/patients/{patient}/dental-chart-entries            (flat list, not paginated — see note below)
POST  /api/patients/{patient}/dental-chart-entries             (create)
PUT   /api/dental-chart-entries/{entry}                        (edit notes/surfaces/condition — while not completed/cancelled)
POST  /api/dental-chart-entries/{entry}/complete
POST  /api/dental-chart-entries/{entry}/cancel
DELETE /api/dental-chart-entries/{entry}                       (soft delete — admin-only, data-correction action, §19)
```

**One endpoint, not two, for chart data** — `GET /api/patients/{patient}/dental-chart-entries` returns the
flat entry array; the frontend store computes *both* the grouped-by-tooth odontogram snapshot and the flat
List View from that one cached array, client-side — exactly how `appointments.ts` already derives both the
Board and List views from one shared range-cache. No separate "chart snapshot" endpoint.

**Deliberately unpaginated**, unlike the general "list endpoints are always paginated" API guideline: this
endpoint is always patient-scoped (never clinic-wide), and a single patient's lifetime chart history is
naturally bounded (at most 52 teeth × a handful of entries each — hundreds of rows at the extreme, not
thousands) — the same reasoning class as `GET /api/patients/{id}/audit-logs`. Flagged explicitly as a
documented exception, not an oversight, per `api-guidelines.md`'s own convention for stating exceptions.

Error shapes: standard `422`/`403`/`401`/`404` per `api-guidelines.md` — no new conflict-response shape
needed (unlike Appointments, there's no double-booking-style resource conflict here; an invalid status
transition is a plain `422`, mirroring `InvalidStatusTransitionException`'s existing shape).

## 15. Frontend Architecture

| Layer | Files |
|---|---|
| Types | `src/types/dentalChart.ts` |
| Services | `src/services/dentalChart/{dentalConditionsApi,dentalChartEntriesApi}.ts` |
| Stores | `src/stores/dentalConditions.ts` (catalog, mirrors `appointmentTypes.ts`), `src/stores/dentalChart.ts` (per-patient entry cache, computes both odontogram-grouped and flat-list shapes) |
| Shared lib | `src/lib/teeth.ts` — the frontend mirror of `App\Support\ToothChart` (§6): the 52-tooth catalog + display names + arch/quadrant/anterior-posterior metadata |
| Views | `DentalConditionsView.vue` (admin catalog CRUD, mirrors `AppointmentTypesView.vue`), a patient-scoped chart view (route TBD — §17 open decision) |
| Components | `ToothChart.vue` (the odontogram container), `ToothShape.vue` (one tooth's SVG + clickable surface regions), `ChartEntryDialog.vue` (add/edit, Diagnosis/Procedure tabs), `ChartLegend.vue` (color/symbol key), `ChartEntryListTable.vue` (accessible list view, mirrors `AppointmentListTable.vue`), `DentalConditionFormDialog.vue` (catalog CRUD dialog, mirrors `AppointmentTypeFormDialog.vue`) |
| i18n | `dentalChart.*` namespace, `en`/`ar`/`tr`, parity-verified (same requirement as every existing namespace) |

No new third-party package (§16 explains the odontogram-library research and why building in-house is
recommended). Datetime fields (`recorded_at`/`completed_at`/`cancelled_at`) go exclusively through
`frontend/src/lib/date.ts` per the standing project-wide policy — no new date handling invented.

## 16. UI/UX Design

**Odontogram rendering — build in-house, don't add a package.** Researched existing options (§0-adjacent
search, not a competitor-product survey): `react-odontogram` and `React-Odontogram-Modul` are React, not
usable in this Vue codebase; `og-odontogram` is a framework-agnostic Lit web component, MIT-licensed, that
could technically be wrapped for Vue interop — but given the FullCalendar-Premium lesson (`decisions.md`,
2026-07-16: a promising-looking package turned out to have a licensing trap only caught by reading the
actual license file, not the registry blurb), and that this specific package is a small, low-visibility
project of unclear long-term maintenance, pulling in a non-Vue web component just to render ~52 static
schematic tooth shapes is not a good trade — the actual rendering surface here (simple geometric shapes: a
rounded-rect tooth body split into up to 5 clickable surface regions, per §0's confirmed "schematic, not
photorealistic" convention every real product actually uses) is well within normal Vue component-authoring
effort, and building it in-house gives full, uncompromised control over the design-system tokens, dark
mode, and — most importantly — the anatomical-vs-locale mirroring rule below (§17), which no third-party
component could be trusted to get right without modification anyway. **Recommendation: custom-built
`ToothChart.vue`/`ToothShape.vue`, inline SVG, no new npm dependency.**

**Color convention** (per §0's Dentrix-sourced research, applied consistently): `existing`/`completed` →
blue/black tone; `active`/`planned` → red tone; `cancelled` → muted/gray. Each `dental_conditions.color`
customizes the *base* color per condition (matching CareStack's "Materials" color customization), with the
status driving a consistent saturation/tone modifier on top — a condition's color identifies *what*, the
status tone identifies *when/urgency*. Contrast-checked via the existing `lib/color.ts` WCAG helper
(already built for Appointments, reused verbatim — no new contrast logic).

**Views**: the odontogram (primary, visual) and an equivalent sortable table (`ChartEntryListTable.vue`,
filterable by tooth/status/date) — a toggle between them, mirroring Appointments' Board/List toggle exactly.

**Legend**: a persistent `ChartLegend.vue` panel — condition colors/icons plus the status-tone key — always
visible alongside the chart, per CareStack's unified-legend approach (§0), not a separate help dialog a user
has to go find.

## 17. RTL / LTR Considerations

**The single most important, non-obvious point in this whole design**: the odontogram must **never** mirror
with UI locale direction, even though every other part of the screen (buttons, dialogs, legend text) mirrors
normally for Arabic. A tooth's clinical left/right position is fixed by anatomy and by the FDI numbering
itself — flipping the chart layout because the UI is RTL would put teeth in a clinically wrong-looking
arrangement relative to their real numbering, which is a data-legibility/patient-safety concern, not a
cosmetic one (a dentist scanning the chart quickly must never have to mentally re-flip it based on which
language they're using). This is the same bug *class* already found and fixed once in this project
(`CalendarToolbar.vue`'s prev/next chevrons pointed the wrong way under RTL until explicitly fixed;
`AppointmentTypesView.vue`'s hex color codes needed explicit `dir="ltr"` isolation to avoid bidi
reordering) — applied here to something with real clinical stakes instead of just a visual nit.

**Implementation**: the `ToothChart.vue` container is forced `dir="ltr"` unconditionally (matching the
existing `dir="ltr"` isolation-span pattern from the Appointment Types hex-color fix), independent of the
active locale — tooth position and numbering render identically in Arabic and English. Only the *labels*
and *surrounding chrome* (tooth names, the legend, dialogs) follow normal RTL text direction. This needs an
explicit regression test and an explicit real-browser Arabic verification pass at implementation time,
given the project's established "manual browser verification is mandatory for UI-facing work, and it has
caught real RTL bugs before" precedent.

**Open decision (§20)**: whether the chart lives as a new tab/section on `PatientDetailView.vue` (today a
stacked-card layout) or a dedicated route (`/patients/{id}/dental-chart`), given the odontogram's spatial
size — flagged for explicit approval rather than assumed, since it changes an established page convention.

## 18. Accessibility Requirements

The odontogram is inherently visual/spatial — every requirement below exists because a chart with *only* a
clickable SVG grid would fail a keyboard-only or screen-reader user completely, not just inconveniently:

- Every tooth is a keyboard-focusable element (`tabindex="0"`, `role="button"`), with a full descriptive
  `aria-label` (e.g. "Tooth 16, upper right first molar. 2 entries: active caries on the occlusal surface;
  completed filling on the mesial surface.") — not just a bare tooth number.
- Arrow-key navigation between teeth (mirrors the existing `useCalendarKeyboardShortcuts()` composable
  pattern — a new, analogous composable for the chart, not a copy-paste of the calendar one).
- `ChartEntryListTable.vue` (§16) is the mandatory non-visual-equivalent path, not an optional nice-to-have
  — every fact the odontogram shows must also be reachable and fully operable from that table alone.
- Status/color is never the *only* signal — every condition also carries a short text label and (per §16)
  an icon glyph, so color-blind users aren't relying on hue alone (matches WCAG's "don't rely on color
  alone" success criterion, already a checked item in Appointments' §14 Accessibility Checklist precedent).
- Focus management on the entry dialog reuses the existing `useDialogFocusRestore()` composable verbatim —
  no new focus-handling code invented.

## 19. Permissions

Proposed (flagged for explicit confirmation, since it's a judgment call, not dictated by an existing
pattern — see reasoning below):

| Action | admin | dentist | receptionist |
|---|---|---|---|
| View chart / list view | ✅ | ✅ | ✅ (read-only — needs context for scheduling, e.g. "this patient needs a filling," mirrors receptionist's existing read-only relationship to clinical Patient data) |
| Create / edit / complete / cancel entry | ✅ | ✅ | ❌ |
| Delete entry (data correction) | ✅ | ❌ | ❌ |
| Manage `dental_conditions` catalog | ✅ | ❌ | ❌ |

**No dentist-ownership/IDOR restriction is proposed** (unlike Appointments' `start`/`complete`
dentist-ownership check) — any dentist should be able to view and continue charting for any patient, since
DentalSuite has no "assigned/primary dentist per patient" concept today, and a patient may reasonably see
multiple dentists at the same clinic over time. **This is an explicit open question, not a silent
assumption** — flagged in §20 for confirmation, since some clinics may want charting restricted to the
recording dentist or a patient's primary dentist; that concept doesn't exist in the system yet, so
enforcing it isn't currently buildable without adding it first.

Delete is gated tighter than cancel (admin-only) because it's a data-correction action, not a clinical one
— a dentist who made a charting mistake cancels or edits it; only an admin removes a record outright,
mirroring the elevated-privilege pattern already used for hard deletes elsewhere (Patients' `delete` is
admin-only too).

## 20. Open Decisions Needing Explicit Approval

Consolidated from throughout this document — these are genuine judgment calls, not settled facts, per the
project's "ask before major decisions, explain tradeoffs" rule:

1. **FDI vs. Universal tooth numbering** (§6) — recommended: FDI, storage-only decision, display-format
   flexibility possible later at no cost. Confirm before implementation; expensive to change after real
   patient data exists.
2. **Chart placement**: new tab/section on `PatientDetailView.vue` vs. a dedicated route (§17). Recommended:
   dedicated route, given the odontogram's spatial size and the precedent of `DentistScheduleView.vue`
   being its own page rather than a card. Needs explicit confirmation since it's a UX convention change.
3. **No dentist-ownership restriction on chart writes** (§19) — recommended as stated (any dentist can
   chart for any patient), but flagged since it's a real clinical-workflow assumption, not a technical
   default.
4. **`treatment_plan_item_id` seam**: recommended to **not** add any column now (no target table exists
   yet to reference) — add it as its own small migration when the Treatment Plans module is designed,
   rather than an unconstrained/unenforced column sitting in production early. Confirm this is acceptable
   versus reserving an unconstrained nullable UUID column today.
5. **Optional `external_code` column on `dental_conditions`** (nullable string, for a future CDT/ICD
   procedure-code mapping) — cheap and purely additive, but not requested and not needed by anything in V1;
   presented as an option, not assumed, the same way `AppointmentType` deliberately did *not* speculatively
   add `price`/`is_default`. Recommend: skip for now, add when Billing is actually designed.
6. **Automatic "supersession" of an `active` finding when a related procedure completes** (§4) — recommend
   deferring (manual review instead) rather than guessing at matching logic between a finding and the
   procedure that addresses it.

## 21. Technical Risks

- **Anatomical-mirroring bug class** (§17) — the highest-stakes risk in this design; mitigated by an
  explicit forced-`dir="ltr"` container plus a mandatory real-browser Arabic verification pass, not left to
  incidental testing.
- **Tooth-numbering lock-in** — once real chart data exists, changing the stored numbering system is a
  data migration touching every historical record; mitigated by deciding FDI now with real research behind
  it (§0, §6) rather than deferring the choice.
- **Odontogram build effort** — ~52 custom SVG tooth shapes is real, non-trivial frontend work; mitigated by
  the deliberately schematic (not anatomically photorealistic) visual style every competitor product above
  actually ships (§0, §16), keeping the shape geometry simple (rounded rectangles / stylized outlines).
- **DOM/reactivity scale** — up to ~260 clickable surface regions (52 teeth × ≤5 surfaces) on screen at
  once; mitigated by scoping Vue reactivity per-tooth (`ToothShape.vue` as an isolated child component, not
  one giant reactive array re-rendering the whole chart per click) — a standard Vue performance pattern,
  not a novel one.

## 22. Deferred Features / Future Improvements

- Bulk/multi-tooth entry for fast new-patient intake (§3) — no data-model impact if added later.
- Periodontal charting (§2) — a separate, materially larger clinical dataset.
- Freehand drawing / tooth-movement annotations (Open Dental has this, §0) — niche, high build cost, low
  confirmed demand.
- Chart PDF export/printing — a likely near-term ask, not built in V1.
- Imaging attachments per tooth/entry — belongs with the future Imaging module.
- Full Treatment Plan sequencing/cost/acceptance — belongs with the future Treatment Plans module; this
  module only exposes the `planned`-status seam (§20 item 4).
- Automatic finding-to-procedure supersession logic (§20 item 6).
- Universal-notation *display* toggle (§6) — cheap to add later, not built until requested.

## 23. Future AI Integration Points (vision only — not built now)

Per the standing AI-layer vision (event-driven readiness, API-first, integrations in a separate layer):
- Chart data is structured and queryable from day one, so a future Dashboard Insights feature ("patients
  with untreated active findings") or AI Analytics Assistant could query through the existing
  Service/Resource layers without any reshape.
- Natural future domain events (not built now): `DentalChartEntryCreated`, `DentalChartEntryCompleted` —
  could feed the AI Follow-up/Recall vision (e.g. "your filling completed 6 months ago — routine check-up
  due") the same way `AppointmentCompleted` is already named as a candidate event in that vision document.
- A future AI-assisted diagnosis suggestion (from imaging, once that module exists) would write *into* this
  module's existing `active`-finding shape via the normal API — not a special integration path — since
  chart entries are already condition+tooth+surface structured data, not free text.

## 24. Proposed Implementation Sequence

Mirrors the step-by-step, checkpoint-per-step pattern from Appointments §20 (each step: implement → real
browser verification → report → wait for approval before the next):

1. Database + Models + `App\Support\ToothChart` — migrations, `DentalCondition`/`DentalChartEntry`,
   enums, factories.
2. Validation + Service + Policy layers — `DentalConditionService`/`DentalChartService`, transition rules,
   surface/O-I validation rules.
3. API layer — controllers, resources, routes, Feature tests.
4. `dental_conditions` seeder (default catalog, mirrors `AppointmentTypeSeeder`) — proposed defaults:
   Caries, Fracture, Missing Tooth, Impacted Tooth (findings); Composite Filling, Amalgam Filling, Crown,
   Root Canal Treatment, Extraction, Implant, Sealant, Bridge, Veneer (procedures).
5. Frontend infrastructure — types, services, stores, `lib/teeth.ts`, i18n keys, routes.
6. `DentalConditionsView.vue` (admin catalog CRUD).
7. `ToothChart.vue`/`ToothShape.vue` (the odontogram itself) — highest-effort, highest-risk step (§21);
   real Arabic/RTL browser verification is mandatory before sign-off, not optional.
8. `ChartEntryDialog.vue`, `ChartLegend.vue`.
9. `ChartEntryListTable.vue` (accessible list view) + the chart-placement decision (§20 item 2) wired in.
10. Accessibility/keyboard-navigation pass + final QA, mirroring Appointments' Step 9/10 structure.

---

**This document is presented in full per the two-phase workflow. No code has been written. Awaiting
explicit approval — including the six open decisions in §20 — before Step 1 of §24 begins.**
