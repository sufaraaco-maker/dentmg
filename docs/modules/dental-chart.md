# Dental Chart Module

**Status: Production Ready ✅ — CI green on `feature/dental-chart` (commit `3ed692e`, 2026-07-22).**

This is the final module doc, produced at Final Review per the two-phase workflow, superseding
[`dental-chart-design-draft.md`](dental-chart-design-draft.md) (backend/product design),
[`dental-chart-implementation-plan.md`](dental-chart-implementation-plan.md) (step sequence), and
[`dental-chart-rendering-design.md`](dental-chart-rendering-design.md) (odontogram SVG rendering) as the
canonical reference for this module. All three design docs are kept for historical/decision-record
purposes, but this doc reflects what actually shipped.

## Scope (V1)

Per-patient odontogram (dental chart): a 52-tooth (permanent + primary) schematic diagram driven entirely
by real chart data, with whole-tooth findings (e.g. Missing Tooth) and surface-specific
findings/procedures (Mesial/Distal/Occlusal-or-Incisal/Buccal/Lingual) charted per tooth; a status
lifecycle (`existing` → `active`/`planned` → `completed`/`cancelled`); an admin-managed `dental_conditions`
catalog (mirroring `appointment_types`); an accessible List view as a non-visual equivalent to the
odontogram; and a new "Dental Chart" tab on `PatientDetailView.vue` alongside Overview/Appointments.

**Explicitly out of scope for V1** (see Known Limitations below and `TECH_DEBT.md`): periodontal charting,
freehand drawing/tooth-movement annotations, chart PDF export/printing, imaging attachments per
tooth/entry, full Treatment Plan sequencing/cost/acceptance (only a `planned` status seam exists),
automatic finding-to-procedure supersession logic, a universal-notation *display* toggle, bulk/multi-tooth
intake entry, dentist-ownership/IDOR restriction on chart writes (any dentist can chart for any patient —
an explicit, confirmed decision, not an oversight).

## Architecture

**Backend** (Laravel 12, PHP 8.4): same Modular Monolith / Clean Architecture conventions as every other
module — thin Controllers, business logic in `DentalChartService`/`DentalConditionService`, Policies for
authorization, Form Requests for validation. Status transitions are a fixed lookup table inside
`DentalChartService`, mirroring `AppointmentService`'s pattern exactly rather than introducing a
state-machine dependency. Tooth-number validity (which FDI codes exist, which arch/side/type they belong
to, which surfaces are anatomically valid) is centralized in `App\Support\ToothChart` — a pure PHP support
class, not a database table, since the set of valid teeth is a fixed anatomical fact, not editable data.

**Frontend** (Vue 3 + TypeScript + PrimeVue + Tailwind): the odontogram is custom-built SVG
(`ToothSvg.vue`/`ToothSurface.vue`), not a third-party charting library — no dental-charting library exists
for Vue, and the required visual style (schematic, not photorealistic, per competitor research) is
straightforward custom geometry (`lib/toothGeometry.ts`). `ToothChart.vue` is layout/orchestration only,
assembling permanent/primary arches from isolated per-tooth child components so Vue's reactivity stays
scoped to one tooth per click rather than re-rendering the whole ~260-region chart. A dedicated
`useToothChartKeyboardNav()` composable adds arrow-key navigation between teeth, listening on `window`
(not the container element) so it keeps working across `ToothChart.vue`'s chart/list `v-if` toggle.

## Key Architectural Decisions

- **FDI tooth numbering** chosen over Universal — a storage-only decision (display-format flexibility
  possible later at no cost), confirmed before implementation since it's expensive to change once real
  patient data exists.
- **Dedicated `PatientDetailView.vue` tab**, not a separate route — Overview/Appointments/Dental Chart as
  `Tabs`/`TabPanel`s, matching the existing Appointments panel's integration pattern rather than adding a
  new top-level route.
- **No dentist-ownership/IDOR restriction on chart writes** — any dentist can view and chart for any
  patient, since DentalSuite has no "assigned/primary dentist per patient" concept yet. An explicit,
  confirmed product decision (design draft §19/§20), not an oversight — unlike Appointments'
  `start`/`complete` dentist-ownership check, which exists because Appointments *does* have a
  per-appointment dentist assignment to enforce.
- **`treatment_plan_item_id` deliberately not added** — no target table exists yet (Treatment Plans is a
  future module); adding an unconstrained/unenforced UUID column now was rejected in favor of a real
  migration when that module is actually designed. Same reasoning kept `dental_conditions` free of a
  speculative `external_code` (future CDT/ICD mapping) or pricing columns, mirroring `AppointmentType`'s
  precedent of not speculatively adding `price`/`is_default`.
- **No automatic "supersession"** of an `active` finding when a related procedure completes — deferred
  (manual dentist review instead) rather than guessing at finding-to-procedure matching logic.
- **Delete gated tighter than Cancel** — Delete (a data-correction action) is admin-only; Cancel (a
  clinical action) is available to admin/dentist — mirrors the elevated-privilege pattern already used for
  hard deletes elsewhere (Patients' `delete` is admin-only too).
- **`DentistSelect.vue` reused from `components/appointments/`** rather than duplicated — the same
  `providers.ts` dentist-listing workaround (no dedicated `GET /api/dentists` endpoint yet) already
  documented for Appointments applies here too.
- **Forced `dir="ltr"` on the odontogram container**, unconditionally — Mesial/Distal placement inside one
  tooth is anatomically fixed and must never mirror under Arabic RTL; verified with a mandatory real-browser
  Arabic pass per the design doc's highest-flagged risk (§17/§21).

Full reasoning and the real bugs found/fixed at each implementation step are in the git history of this
branch (Steps 1–11) and `TECH_DEBT.md`.

## Backend

| Layer | Files |
|---|---|
| Migrations | `2026_07_20_000001_create_dental_conditions_table.php`, `..._000002_create_dental_chart_entries_table.php` |
| Enums | `app/Enums/DentalConditionCategory.php` (`finding`, `procedure`), `app/Enums/DentalChartEntryStatus.php` (`existing`, `active`, `planned`, `completed`, `cancelled`) |
| Support | `app/Support/ToothChart.php` — FDI tooth validity, arch/side/type lookup, valid-surfaces-per-tooth |
| Models | `DentalCondition.php` (`Auditable`), `DentalChartEntry.php` (`Auditable`) |
| Form Requests | `DentalChartEntry/{Index,Store,Update}DentalChartEntryRequest.php`, `DentalCondition/{Store,Update}DentalConditionRequest.php` |
| Validation Rules | `app/Rules/ValidDentalChartSurfaces.php` |
| Services | `DentalChartService.php`, `DentalConditionService.php` |
| Policies | `DentalChartEntryPolicy.php`, `DentalConditionPolicy.php` |
| Exceptions | `app/Exceptions/DentalChart/{EntryLockedException,InvalidStatusTransitionException}.php` |
| Controllers | `DentalChartEntryController.php`, `DentalConditionController.php` |
| Resources | `DentalChartEntryResource.php`, `DentalConditionResource.php` |
| Tests | `tests/Feature/{DentalChartEntryTest,DentalConditionTest}.php`, `tests/Unit/{Enums/DentalChartEntryStatusTest,Models/DentalChartEntryTest,Models/DentalConditionTest,Policies/DentalChartEntryPolicyTest,Policies/DentalConditionPolicyTest,Requests/StoreDentalChartEntryRequestTest,Requests/UpdateDentalChartEntryRequestTest,Requests/StoreDentalConditionRequestTest,Requests/UpdateDentalConditionRequestTest,Services/DentalChartServiceTest,Services/DentalConditionServiceTest}.php` |

## Database

**`dental_conditions`** (catalog, mirrors `appointment_types`): `id` (uuid), `name`, `category`
(`DentalConditionCategory` enum — drives the Diagnosis/Procedure tab split in the picker UI),
`applies_to_surface` (bool), `default_color`, `icon_key` (nullable, maps to a small fixed set of
frontend-rendered SVG glyphs — never a stored SVG blob), `is_active`, `sort_order`. No soft delete —
deactivation only, same as `appointment_types`, since removing a referenced catalog entry would orphan
history.

**`dental_chart_entries`**: `id` (uuid), `patient_id` (FK), `dental_condition_id` (FK), `dentist_id` (FK →
`users`, who recorded/performed it), `tooth_number` (FDI code, validated against `ToothChart`, **not** a DB
foreign key), `surfaces` (json, nullable), `status` (`DentalChartEntryStatus` enum), `notes`, `recorded_at`,
`completed_at`/`cancelled_at` (nullable, set only on their respective transitions), soft-deleted. Indexes:
`(patient_id)`, `(patient_id, tooth_number)`, `(patient_id, status)`, `(dental_condition_id)`.
`dentition_type` (permanent/primary) is deliberately **not** a column — computed from `tooth_number` via a
model accessor, avoiding a value that could drift out of sync with the code it's derived from.

## API

```
GET/POST/PUT/DELETE   /api/dental-conditions                          (admin-only write, any-role read — apiResource)

GET    /api/patients/{patient}/dental-chart-entries
POST   /api/patients/{patient}/dental-chart-entries
PUT    /api/dental-chart-entries/{dental_chart_entry}
POST   /api/dental-chart-entries/{dental_chart_entry}/complete
POST   /api/dental-chart-entries/{dental_chart_entry}/cancel
DELETE /api/dental-chart-entries/{dental_chart_entry}
```

Deliberately no `GET /api/dental-chart-entries/{id}` single-resource endpoint (design draft §14) — the
frontend re-fetches the whole per-patient list after a mutation instead (see Known Limitations).

## Permissions

| Action | admin | dentist | receptionist |
|---|---|---|---|
| View chart / list view | ✅ | ✅ | ✅ (read-only) |
| Create / edit / complete / cancel entry | ✅ | ✅ | ❌ |
| Delete entry | ✅ | ❌ | ❌ |
| Manage `dental_conditions` catalog | ✅ | ❌ | ❌ |

## Frontend

| Layer | Files |
|---|---|
| Types | `src/types/dentalChart.ts` |
| Services | `src/services/dentalChart/{dentalChartEntriesApi,dentalConditionsApi}.ts` |
| Stores | `src/stores/{dentalChartEntries,dentalConditions}.ts` |
| Views | `DentalConditionsView.vue` (admin catalog CRUD) |
| Components | `src/components/dental-chart/` — `ToothSvg.vue`/`ToothSurface.vue` (core schematic rendering), `ToothChart.vue` (layout/orchestration + keyboard-nav wiring), `ToothLegend.vue`, `DentalChartToolbar.vue`, `ChartEntryDialog.vue`, `ChartEntryListTable.vue` (accessible list-view equivalent), `PatientDentalChartPanel.vue` (per-patient data fetch + dialog host), `DentalConditionFormDialog.vue` |
| Shared libs added | `lib/teeth.ts` (FDI code helpers, mirrors backend `ToothChart`), `lib/toothGeometry.ts` (SVG path geometry), `lib/dentalIcons.ts` (condition glyph resolution) |
| Composables | `useToothChartKeyboardNav()` — arrow-key navigation between teeth (Left/Right within an arch row, Up/Down between rows, no wraparound) |
| PatientDetailView.vue | Refactored from stacked cards to Overview/Appointments/Dental Chart tabs; new tab hosted by `PatientDentalChartPanel.vue` |
| i18n | `dentalChart.*` namespace — **98/98 keys, en/ar/tr parity confirmed** |

## Testing & Verification

- Backend: **347/347 tests passing** (full suite, includes 2 Feature + 9 Unit test files for this module),
  Pint clean, Larastan (PHPStan level 5) 0 errors — confirmed both locally and on CI.
- Frontend: **428/428 Vitest tests passing** (full suite), `vue-tsc` clean, ESLint clean, Prettier clean,
  production build succeeds — confirmed both locally and on CI (`Frontend (type-check, lint, tests,
  build)` job, run `29937143710`).
- E2E: permanent Playwright suite (`frontend/e2e/dental-chart.spec.ts`) — **16/16 passing on GitHub
  Actions** (run `29937143710`, commit `3ed692e`), covering: Create → Edit → Complete → Cancel → Delete
  through both the odontogram and the Accessible List view; receptionist read-only access; real-browser
  arrow-key tooth navigation.
- Manual real-browser verification at each implementation step (mandatory per the two-phase workflow),
  including a dedicated Arabic/RTL pass before `ToothSvg.vue`/`ToothSurface.vue` sign-off (design draft's
  highest-flagged risk).
- **Real root-causing, not guessed fixes**: getting the E2E suite from its first, never-before-run attempt
  to fully green took five real, evidence-based rounds (each confirmed against actual CI/local DOM state,
  not assumed) — a PrimeVue `Select` overlay-close race, a `getByPlaceholder` locator that could never
  match a non-`<input>` placeholder span, a `getByLabel` strict-mode violation (group and interactive child
  sharing one accessible name in whole-tooth mode), a confirm-dialog button-vs-header text mix-up, and a
  `guestOnly`-route redirect from re-logging-in mid-test. None were application regressions — full
  before/after evidence for each in the commit history (`96e909d`…`3ed692e`) and `TECH_DEBT.md`.

## Known Limitations / Deferred (non-blocking)

Full detail and revisit conditions for each live in `TECH_DEBT.md`; summarized here for this module:

- **`DentalChartEntry` mutation endpoints don't eager-load relations** — frontend re-fetches the whole
  per-patient list after every mutation instead of an in-place cache update (no single-record GET endpoint
  exists by design — see API section).
- **No UI path for the backend-allowed `active → planned` transition** — the backend/tests support it;
  `ChartEntryDialog.vue` doesn't yet render a button for it. Open question: real clinical workflow or drop
  from the backend's allowed matrix.
- **`ConfirmDialog`'s accept/reject buttons are never translated** (always "Yes"/"No" in English) — a
  systemic, app-wide gap found while debugging this module's E2E suite, not specific to Dental Chart
  (affects Patients, Appointment Types, Time Off, Dental Conditions, and Dental Chart entries' delete
  confirmations alike). Not a regression this module introduced.
- **No dentist-ownership/IDOR restriction on chart writes** — explicit, confirmed product decision (no
  "assigned dentist per patient" concept exists yet), not an oversight.
- Periodontal charting, freehand annotations, chart PDF export, imaging attachments, full Treatment Plan
  sequencing, automatic finding-to-procedure supersession, universal-notation display toggle, bulk
  multi-tooth intake — all deferred per the design draft's §22, none block V1 production use.

None of the above block production use of the module as scoped; each has an explicit revisit condition in
`TECH_DEBT.md`.

## Completion

Migration, Model, Validation, Service, Policy, API, Vue Pages, Tests, Documentation — all present.
347/347 backend tests, 428/428 frontend tests, `vue-tsc`/ESLint/Prettier/build all clean, 16/16 E2E on CI
(run `29937143710`, commit `3ed692e`). **Verdict: Production Ready.**
