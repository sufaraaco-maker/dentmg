# Patient Profile — Phase 2.4: Laboratory Integration — Design Document

| | |
|---|---|
| **Status** | **Approved and implemented 2026-08-08.** All 5 decisions in §16 were approved by the user as recommended (Option B read/write tab, required `BelongsToPatient` fix, drop the dead `?patient_id=` filter, tab position after Imaging, i18n namespace as proposed). Implemented on `feature/patient-profile-phase2-4-laboratory`, opened as a PR, CI pending — see `docs/PROJECT_STATUS.md` §12 for current merge status. |
| **Roadmap position** | Phase 2 (Patient Profile Redesign), sub-phase 2.4 of 7 — see `docs/PROJECT_STATUS.md` §12 and `docs/modules/patient-profile-redesign-design.md` §17. Follows 2.1 (Foundation, PR #18), 2.2 (Billing, PR #20), 2.3 (Medical History, PR #22) — all merged to `main`. |
| **Governing decisions inherited** | §0 and §9A of `docs/modules/patient-profile-redesign-design.md` are binding here unchanged — nothing in this doc revisits them. This doc is a **drill-down**, not a replacement: the umbrella doc's §6.2/§6.3/§8/§10/§17 already named the intended shape of this sub-phase in outline; this doc verifies that outline against the actual code on `main` today and turns it into an implementable spec. |
| **Analysis basis** | Direct inspection of every Laboratory backend/frontend file on `main` (post-PR #23), the original `docs/modules/laboratory-design.md`, the umbrella `patient-profile-redesign-design.md`, `TECH_DEBT.md`, and the concrete Route→Scope→Store→Panel implementation already shipped for Treatment Plans (2.1) and Imaging (2.1 retrofit) — **not** an assumption that the umbrella doc's 2.4 outline still matches current code. See §1 for what changed vs. what that outline assumed. |
| **Author** | Claude Code, for user review. Every recommendation below is a proposal, not a decision — see §12 for the explicit approval list. |

---

## Implementation Summary (added post-implementation, 2026-08-08)

All 5 §16 decisions approved as recommended — no deviations. Implemented exactly as specced in §3-§11:

- **§5 fix**: `StoreLabCaseRequest`/`UpdateLabCaseRequest` now use the `whereHas('treatmentPlan', ...)`
  closure in place of `BelongsToPatient(TreatmentPlanItem::class, ...)`, mirroring Imaging's pattern
  verbatim. 4 new regression tests added to `LabCaseTest.php`. `TECH_DEBT.md`'s entry moved to Resolved.
- **§4.2**: `LabCase::scopeForPatient()` and `Patient::labCases()` added exactly as specced.
- **§4.1/§4.3**: `GET /patients/{patient}/lab-cases` added via `LabCaseController::forPatient()`; the flat
  `GET /lab-cases` endpoint's dead `?patient_id=` filter removed in the same PR (decision 3, approved).
- **§4.4**: `patientLabCases.ts` implemented with the full Map-cache/pagination contract, plus thin
  `send`/`receive`/`qualityCheck`/`cancel` wrappers and a public `upsert()` for `LabCaseActionsBar.vue`'s
  emitted results to flow into.
- **§4.5/§7 decision 1 (Option B)**: `PatientLabCasesPanel.vue` built read/write — inline create (via
  `CreateLabCaseDialog.vue`'s new optional `patientId` prop, which now routes the mutation through
  `patientLabCases.ts` when set) and inline status actions (`LabCaseActionsBar.vue`, reused unchanged).
  Built as a card-row list, not a `DataTable`, specifically to avoid the row-click/inline-button
  event-bubbling conflict named in §4.5 — this is a small, deliberate deviation from
  `TreatmentPlanListTable.vue`/`InvoiceListTable.vue`'s `DataTable` convention, not an oversight.
- **§8.1/§8.2**: tab inserted immediately after `imaging`, before `treatmentPlans`, exactly as specced.
- **§9**: no policy changes — confirmed `LabCasePolicy`/`LabPolicy` reused unchanged.
- **§10**: 15/page pagination on the new endpoint, matching every other patient-scoped list.
- **§11**: `patients.tabs.laboratory`, `patients.laboratoryPanel.*` added in `ar`/`en`/`tr`; one key this
  doc's own §11 didn't anticipate (`laboratory.labCases.loadError`, needed by the store's error state) was
  caught by the frontend test suite before the PR, not by CI — added in the same pass.
- **§13 Testing**: backend +13 tests (1007/1007 total, Pint clean); frontend +27 tests (store, panel,
  `CreateLabCaseDialog.vue`'s patientId behavior, +1 `PatientDetailView.test.ts` tab-rendering assertion) —
  full suite 894/894 green, type-check/ESLint/Prettier clean.
- **§14**: landed as one PR, as proposed (not split).
- **§15**: no new deferrals — Documents/Timeline/tab-reorder items remain exactly as this doc named them.

## 0. Why this doc exists separately from the umbrella design doc

`docs/modules/patient-profile-redesign-design.md` already sketches Phase 2.4 in three places (§6.2 `Patient.php` additions, §6.3 `LabCaseService` extension, §8 API changes, §17 phase table) and rates it **Low risk — repeats an existing, well-understood pattern exactly**. Per the user's explicit instruction, this doc does not take that outline on faith — it re-derives the design from the actual current state of `main`, and it turns out the outline is **directionally correct but incomplete**: it correctly identifies the missing relation/scope/route/store/panel, but it does not account for (a) a real, confirmed-reproducible SQL bug in the exact code path this phase makes newly reachable (§5), (b) the fact that the standalone Laboratory module already has a full create/status-transition UI whose relationship to a new Patient Profile tab is an undecided product question, not just a technical one (§7), and (c) the current *actual* tab order in `PatientDetailView.vue` has already diverged from the umbrella doc's originally envisioned final order (§8.1). This doc resolves all three before proposing a spec.

## 1. Current State Audit (read directly from `main` @ `f417f05`, not assumed)

### 1.1 Backend — fully implemented, Production Ready, unchanged since PR #5 (2026-07-27)

| Piece | File | State |
|---|---|---|
| `Lab` model | `backend/app/Models/Lab.php` | Vendor catalog, `HasFactory, HasUuids` only, `scopeActive()`, `hasMany(LabCase)`. No `Auditable`/`SoftDeletes` (matches `Supplier`). |
| `LabCase` model | `backend/app/Models/LabCase.php` | `Auditable, HasFactory, HasUuids, SoftDeletes`. `belongsTo(Patient)`, `belongsTo(Lab)`, `belongsTo(User, 'dentist_id')`, `belongsTo(TreatmentPlanItem)`, `belongsTo(Appointment)` (both traceability-only). `scopeWithStatus()`, `scopeDueOrOverdue()`. **No `scopeForPatient()` exists.** |
| `LabCaseStatus` enum | `backend/app/Enums/LabCaseStatus.php` | `Draft→Sent→Received→QualityChecked` (+`Cancelled`), `transitionsFrom`/`canTransitionTo`/`isTerminal` — same shape as every other status enum. Unchanged, no action needed. |
| `LabCaseService` | `backend/app/Services/LabCaseService.php` | `create/update/send/receive/qualityCheck/cancel/delete`, lock-highest-and-increment `case_number` (`LC-000001`), row-locked transitions (`lockForUpdate`). Fully correct, needs **no new methods** for this phase — see §4. |
| `LabController` / `LabCaseController` | `backend/app/Http/Controllers/Api/` | `LabCaseController::index()` is **flat**, filters via `?patient_id=&lab_id=&status=`, `paginate((int) $request->query('per_page', 20))`. **No patient-scoped route exists.** |
| `LabPolicy` / `LabCasePolicy` | `backend/app/Policies/` | Finalized, unchanged, needs no new methods — see §9. |
| `StoreLabCaseRequest` / `UpdateLabCaseRequest` | `backend/app/Http/Requests/LabCase/` | Validate `treatment_plan_item_id`/`appointment_id` via `App\Rules\BelongsToPatient` — **this is the confirmed bug, see §5.** |
| Migrations | `2026_07_27_000001/000002` | `labs`, `lab_cases` tables — both already ship `patient_id`, `treatment_plan_item_id`, `appointment_id` FKs. **No new migration needed for this phase** (see §6 — this is a pure relation/scope/route addition, not a schema change). |
| Tests | `LabCaseTest.php` (24), `LabTest.php` (7), `LabCaseServiceTest.php` (13) — 44 total | **Zero tests set `treatment_plan_item_id`** on any request (confirmed via direct grep) — this is exactly why §5's bug has never fired in CI. |

### 1.2 Frontend — fully implemented as a **top-level, patient-agnostic** module

| Piece | File | State |
|---|---|---|
| `useLabsStore` | `stores/labs.ts` | Small catalog store (`items/loaded/loading`, `fetchAll/create/update/deactivate`) — mirrors `stores/suppliers.ts`. Needed as-is for the lab-picker dropdown in any create flow; **no change needed**. |
| `LabCasesView.vue` | `views/LabCasesView.vue` | Top-level list, **no store** — direct `api.get('/lab-cases', {params: {lab_id, status, page}})`. **Never passes `patient_id`** — confirmed via grep, so the controller's `?patient_id=` filter is currently dead code, called by nothing. |
| `LabCaseDetailView.vue` | `views/LabCaseDetailView.vue` | Overview + `LabCaseActionsBar.vue` (send/receive/quality-check/cancel) + printable slip. Fully reusable as the deep-link target from a new Patient Profile tab — **no change needed**. |
| `CreateLabCaseDialog.vue` | `components/laboratory/CreateLabCaseDialog.vue` | Requires `patient_id` to be picked via `PatientSearchSelect.vue` (`form.patient_id` starts `null`) — **built only for the patient-agnostic top-level flow**, not reusable as-is inside a Patient Profile tab where the patient is already known (§7). |
| `LabCaseActionsBar.vue` | `components/laboratory/LabCaseActionsBar.vue` | Status-transition buttons, emits `updated`, no store dependency — **directly reusable** inside a new panel with no changes. |
| `LabCaseStatusChip.vue` | `components/laboratory/LabCaseStatusChip.vue` | Presentational, **directly reusable**. |
| `types/laboratory.ts`, `services/laboratory/errors.ts` | — | `LabCase`/`CreateLabCasePayload`/error-code types already complete — **no change needed**. |
| **`PatientLabCasesPanel.vue`** | — | **Does not exist.** |
| **`patientLabCases.ts` store** | — | **Does not exist.** |

### 1.3 Patient Profile integration — confirmed absent

- `Patient.php` (`backend/app/Models/Patient.php:56-99`) has `appointments()/dentalChartEntries()/treatmentPlans()/invoices()/payments()/clinicalNotes()/images()/allergies()` — **no `labCases()`**.
- `PatientDetailView.vue`'s `tabDefinitions` (lines 62-71) lists 8 tabs (`overview, medicalHistory, appointments, dentalChart, imaging, treatmentPlans, clinicalNotes, billing`) — **no `laboratory` entry, no import of any Laboratory component.**
- This matches the umbrella doc's own §2 finding ("Laboratory — Not visible from a patient's record at all") — confirmed still true, unchanged by 2.1/2.2/2.3.

### 1.4 What this confirms vs. the umbrella doc's 2.4 outline

The umbrella doc's §17 line for 2.4 (`Patient::labCases()`, `forPatient` scope, new patient-scoped route, `patientLabCases` store, `PatientLabCasesPanel.vue`) is **accurate as a checklist of missing pieces** — nothing it names turns out to already exist, and nothing it names turns out to be unnecessary. What it does **not** surface, because it was written before this phase's own close inspection, is §5 (the bug) and §7 (the create/write-access product question) below.

## 2. Relationship to Clinical Notes, Imaging, and Documents

Per the user's explicit ask to check this before designing:

- **Treatment Plans**: `LabCase.treatment_plan_item_id` is an existing one-way traceability FK (never mutated by Laboratory, exact convention as `TreatmentPlanItem.diagnosis_entry_id`). This is the field affected by §5's bug — the *only* real cross-module coupling Laboratory has today.
- **Appointments**: `LabCase.appointment_id` is the same one-way traceability shape, also validated via the same buggy `BelongsToPatient` rule (§5) — but against `Appointment`, which **does** have a real `patient_id` column (confirmed in `appointments` migration), so **that half of the validation is not broken** — only the `TreatmentPlanItem` side is.
- **Imaging**: no data relationship at all. Relevant only as the closest **structural precedent** for this phase's new panel — the umbrella doc explicitly names `PatientImagingPanel.vue` as what `PatientLabCasesPanel.vue` should mirror (list + filters + `EmptyState.vue` + patient-scoped store), and this audit confirms that precedent is still current and buildable (§1.2/§1.3).
- **Clinical Notes**: no relationship. Different permission shape (Clinical Notes excludes receptionists entirely; Laboratory's `viewAny` is open to all staff) — worth noting only so the new Laboratory tab's visibility rule is not accidentally copied from Clinical Notes' narrower one (§9).
- **Documents (Phase 2.5, not yet designed)**: the user asked specifically about "files or lab results possibly attached to a case." `docs/modules/laboratory-design.md` §7 decision 4 already deferred file/photo/STL attachments to V2 as a **Laboratory-module-level** decision (no upload infrastructure existed anywhere in the codebase at the time). That reasoning is now partially stale: Documents (2.5) is designed to add exactly that generic upload infrastructure, one phase after this one. **This phase does not build any Lab Case-specific attachment field or table** — see §11 for why, and the explicit recommendation that a future lab report/scan should be a `PatientDocument` (category `lab_report`, already named in the umbrella doc's §7 category enum) referencing the patient, not a new FK on `lab_cases`. No schema coupling is introduced now; this is named so it isn't silently forgotten, matching this project's own standing discipline (`docs/PROJECT_STATUS.md` §0).
- **Timeline (Phase 2.6, not yet designed)**: `LabCaseStatusChanged`-shaped events are already anticipated by name in the umbrella doc's §9.2 event list (`LabCaseStatusChanged`). This phase does **not** wire any event dispatch — that is explicitly 2.6's job — but §4/§9 below are written so `LabCaseService`'s transition methods stay the natural, undisturbed place to add that dispatch later (no refactor of this phase's own work should be needed when 2.6 starts).

## 3. Scope for Phase 2.4

**In scope:**
- `Patient::labCases()` inverse relation.
- `LabCase::scopeForPatient()`, mirroring `TreatmentPlan::scopeForPatient()` exactly.
- `GET /patients/{patient}/lab-cases` — new paginated, patient-scoped endpoint.
- `patientLabCases.ts` Pinia store, following the standardized `fetchForPatient` contract (umbrella doc §14.2).
- `PatientLabCasesPanel.vue` — new Patient Profile tab.
- Fixing the `BelongsToPatient`/`TreatmentPlanItem` SQL bug in `StoreLabCaseRequest`/`UpdateLabCaseRequest` (§5 — proposed as required, not optional, for this phase).
- A regression test for that fix, on both the general Laboratory suite and specifically exercised through the new patient-scoped path.
- New `laboratory` tab entry in `PatientDetailView.vue`'s `tabDefinitions`, plus `ar`/`en`/`tr` i18n keys.
- **Pending §12 decision A**: extending `CreateLabCaseDialog.vue` to accept an optional pre-set `patientId` (skipping `PatientSearchSelect`) so the new tab can create cases inline, not just view them.

**Explicitly out of scope for this phase** (named, not silently dropped):
- Any change to the standalone `LabsView.vue`/`LabCasesView.vue`/`LabCaseDetailView.vue` top-level pages — they keep working exactly as they do today, unmodified.
- Any file/photo/STL attachment on `LabCase` — deferred to Documents (2.5), see §2.
- Any `PatientActivity`/Timeline event dispatch from `LabCaseService` — deferred to 2.6, see §2.
- Any change to `LabCasePolicy`/`LabPolicy` rules — the existing roles/rules are reused unchanged (§9).
- Reordering any existing Patient Profile tab other than inserting the new one (§8.1) — a full reorder to the umbrella doc's originally envisioned final order is a separate, unscoped concern touching Appointments, which isn't retired until Phase 2.6.
- Removing the deprecated `?patient_id=` query filter from the flat `GET /lab-cases` endpoint's signature is proposed (§4.1) but is a small cleanup, not a breaking change for any current caller (confirmed unused, §1.2).

## 4. Architecture: Route → Scope → Store → Panel

This phase is a direct application of the pattern already proven twice (Treatment Plans in 2.1, Imaging's retrofit in 2.1) — no new pattern is introduced.

### 4.1 Route

```
GET /patients/{patient}/lab-cases      # new — paginated, 15/page (umbrella doc §11.2 default)
```

Added in `routes/api.php` alongside the other `patients/{patient}/{resource}` routes (next to `treatment-plans`/`clinical-notes`/`images`), inside the existing `auth:sanctum` group.

**Proposed alongside it** (§12 decision B): drop the `?patient_id=` filter from `LabCaseController::index()`'s flat `GET /lab-cases` — confirmed dead code (§1.2), and leaving two ways to fetch a patient's cases (a query-string filter on the flat endpoint vs. the new nested route) is exactly the kind of drift this codebase's own conventions avoid elsewhere (the umbrella doc's own §8 already anticipated this: *"Laboratory's existing `?patient_id=` filtering is superseded by a real patient-scoped route for consistency"*).

### 4.2 Scope

```php
// backend/app/Models/LabCase.php — new method, same shape as TreatmentPlan::scopeForPatient()
public function scopeForPatient(Builder $query, string $patientId): Builder
{
    return $query->where('patient_id', $patientId);
}
```

```php
// backend/app/Models/Patient.php — new method, inserted after allergies() (line ~99),
// alongside the other hasMany relations (appointments/treatmentPlans/clinicalNotes/images)
public function labCases(): HasMany
{
    return $this->hasMany(LabCase::class);
}
```

Both are added — the relation for consistency with every sibling clinical entity (and as the natural place a future Timeline/aggregate query would use), the scope because the controller itself follows `TreatmentPlanController`'s exact convention (`LabCase::query()->forPatient($patient->id)`), not Imaging's `$patient->images()` variant. Both codebase conventions already coexist today (§1.1/§1.2); this phase follows the Treatment Plans one specifically because `LabCaseController::index()` already has its own `WITH`/`when()`-filter-chain shape that composes more naturally with a scope than a relation-first query.

### 4.3 Controller

```php
// New method on LabCaseController, alongside the existing flat index()
public function forPatient(Request $request, Patient $patient)
{
    $this->authorize('viewAny', LabCase::class);

    $cases = LabCase::query()
        ->forPatient($patient->id)
        ->with(['lab', 'dentist']) // 'patient' omitted — already known from the route
        ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
        ->latest('created_at')
        ->paginate(15);

    return LabCaseResource::collection($cases);
}
```

`patient` is dropped from the eager-load list (unlike the flat endpoint's `self::WITH`) since the panel already has `patientId` from its own prop — a small, deliberate response-size optimization, not a behavior change (`LabCaseResource`'s `patient` field is already `whenLoaded`, so omitting the eager-load correctly omits it from the JSON with no resource change needed).

### 4.4 Store

```typescript
// frontend/src/stores/patientLabCases.ts — new, follows the standardized
// fetchForPatient(patientId, page, force) contract (umbrella doc §14.2),
// same Map-cache + patientPageIds/patientPageMeta/loadedPatientPage shape as treatmentPlans.ts
export const usePatientLabCasesStore = defineStore('patientLabCases', () => {
  const cache = reactive(new Map<string, LabCase>())
  const patientPageIds = reactive(new Map<string, string[]>())
  const patientPageMeta = reactive(new Map<string, PatientPageMeta>())
  const loadedPatientPage = reactive(new Map<string, number>())
  const loading = ref(false)
  const error = ref<string | null>(null)

  function casesForPatient(patientId: string): LabCase[] { /* same shape as plansForPatient */ }
  function pageMetaForPatient(patientId: string): PatientPageMeta { /* same shape */ }
  async function fetchForPatient(patientId: string, page = 1, force = false): Promise<void> { /* ... */ }
  function upsert(labCase: LabCase) { cache.set(labCase.id, labCase) }

  // Thin wrappers around the *existing*, unchanged laboratory API calls — this store does not
  // reimplement create/send/receive/qualityCheck/cancel logic, it just upserts their responses
  // into this patient-scoped cache so the new tab reflects a status change immediately without a
  // manual refetch, mirroring how treatmentPlansStore.accept()/complete() etc. already work.
  async function create(patientId: string, payload: CreateLabCasePayload): Promise<LabCase> { /* POST /lab-cases, upsert, refresh page 1 */ }
  async function send(id: string): Promise<LabCase> { /* POST /lab-cases/{id}/send, upsert */ }
  async function receive(id: string): Promise<LabCase> { /* ... */ }
  async function qualityCheck(id: string): Promise<LabCase> { /* ... */ }
  async function cancel(id: string): Promise<LabCase> { /* ... */ }

  return { cache, loading, error, casesForPatient, pageMetaForPatient, fetchForPatient, create, send, receive, qualityCheck, cancel, $reset }
})
```

A **new** store, not a reuse of `useLabsStore` (which stays exactly what it is — the small `Lab` vendor-catalog dropdown source) and not a raw wrap of `LabCasesView.vue`'s direct-`api`-call pattern (that pattern was chosen there specifically because the flat clinic-wide list has "no store" per the original design doc — a patient-scoped tab, by contrast, needs exactly the cache/reactivity every other Patient Profile tab has, so it follows that pattern instead, matching Treatment Plans' precedent, not the flat Lab Cases page's).

### 4.5 Panel

`PatientLabCasesPanel.vue` — new component, structurally mirrors `PatientImagingPanel.vue` (list + status filter + `EmptyState.vue` + `Paginator` + create action), reusing `LabCaseStatusChip.vue` and `LabCaseActionsBar.vue` as-is, and (pending §12 decision A) `CreateLabCaseDialog.vue` with a pre-set `patientId`. Deep-links to the existing `LabCaseDetailView.vue` (`router.push({ name: 'lab-case-detail', params: { id } })`) for the full printable-slip view — this phase does **not** rebuild that view inside the tab, exactly as Billing's tab links out to nothing further because its sub-panels are embedded directly, but Laboratory's detail view (with its print stylesheet) is a poor fit to embed inline in a tab panel.

## 5. Required fix: `BelongsToPatient` vs. `TreatmentPlanItem` — no longer just latent debt

**Confirmed directly** (not re-asserting the existing `TECH_DEBT.md` entry on faith):
- `treatment_plan_items` table has no `patient_id` column (only `treatment_plan_id` → `treatment_plans.patient_id`) — confirmed via the migration file.
- `App\Rules\BelongsToPatient::validate()` (`backend/app/Rules/BelongsToPatient.php:32-35`) runs `$modelClass::query()->where('id', $value)->where('patient_id', $patientId)->exists()` unconditionally.
- `StoreLabCaseRequest`/`UpdateLabCaseRequest` both call `new BelongsToPatient(TreatmentPlanItem::class, $patientId)` for `treatment_plan_item_id` — confirmed still present, unchanged since PR #5.
- `LabCaseTest.php`'s 24 tests: **zero** set `treatment_plan_item_id` — confirmed via grep. The bug has never fired in CI for exactly that reason.
- Imaging already carries the **correct** fix, live in production since PR #6 (`StorePatientImageRequest.php:60-73`): a closure-based rule using `whereHas('treatmentPlan', fn ($q) => $q->where('patient_id', $patientId))` instead of the generic `BelongsToPatient` class.

**Why this graduates from "flagged, unexercised" to "must fix this phase," not later**: today, the only way to set `treatment_plan_item_id` on a Lab Case is via `CreateLabCaseDialog.vue`'s standalone flow, which has no treatment-plan-item picker UI at all (confirmed — the dialog only has patient/lab/dentist/tooth/shade/material/instructions fields, `treatment_plan_item_id` is accepted by the backend but never sent by this dialog). The field is effectively unreachable from the UI today, which is exactly why the bug has stayed dormant. **A natural next UI extension of this phase** — letting a Lab Case be created directly from a Treatment Plan Item's own row (linking the two, the traceability field's whole reason for existing) — is exactly the kind of feature a Patient Profile-centric Laboratory tab makes people want to build next. Shipping this phase without the fix means shipping a landmine one obvious next step closer to being stepped on.

**Proposed fix** (mirrors Imaging's pattern exactly, in both `StoreLabCaseRequest`/`UpdateLabCaseRequest`):
```php
'treatment_plan_item_id' => [
    'nullable',
    'uuid',
    function ($attribute, $value, $fail) use ($patientId) {
        $belongsToPatient = TreatmentPlanItem::query()
            ->where('id', $value)
            ->whereHas('treatmentPlan', fn ($query) => $query->where('patient_id', $patientId))
            ->exists();

        if (! $belongsToPatient) {
            $fail('The selected treatment plan item does not belong to this patient.');
        }
    },
],
'appointment_id' => ['nullable', 'uuid', new BelongsToPatient(Appointment::class, $patientId)], // unchanged — Appointment does have patient_id
```

Plus one new Feature test in `LabCaseTest.php` asserting a case can be created/updated with a valid same-patient `treatment_plan_item_id`, and one asserting a cross-patient one is rejected with a normal 422 (not a 500) — closing the exact gap `TECH_DEBT.md` names. Once fixed, `TECH_DEBT.md`'s existing "Open" entry for this bug (§ "new from Imaging module, 2026-07-28") moves to "Resolved," updated in that same implementation PR per this project's Definition of Done.

## 6. Database Changes

**None.** No new migration. `lab_cases`/`labs` tables already carry every column this phase needs (`patient_id` FK already exists and is already indexed — confirmed in the migration, `$table->index('patient_id')`). This phase is a pure relation/scope/route/frontend addition — the umbrella doc's own risk rating ("Low — repeats an existing, well-understood pattern exactly," §17) is correct on this specific point.

## 7. Open product question: should the new tab be read-only or read/write?

This is the one place this audit found a genuine **product** decision, not just a technical gap — see §12 decision A for the two options, stated precisely:

- **Option A (read-only)**: the tab lists the patient's cases (status, lab, due date), each row deep-links to the existing `LabCaseDetailView.vue` for any creation/status-transition action. Matches the umbrella doc's literal §17 wording ("Low risk") most conservatively — no dialog changes needed at all.
- **Option B (read/write, recommended)**: the tab also supports creating a new case (pre-filled patient, no search step) and taking status actions inline via the already-fully-reusable `LabCaseActionsBar.vue`, matching how **every other** clinical Patient Profile tab already behaves (Imaging uploads inline, Treatment Plans creates/accepts/completes inline, Clinical Notes drafts/signs inline). `CreateTreatmentPlanDialog.vue` already establishes the exact precedent needed — it takes a required `patientId` prop and has no `PatientSearchSelect` at all, confirming this codebase already treats "patient-context dialog" and "patient-agnostic dialog" as two different, deliberately-shaped components at other call sites, not something to route around. The concrete change is small: make `CreateLabCaseDialog.vue`'s `patientId` an optional prop — when set, skip rendering `PatientSearchSelect` and pre-fill `form.patient_id` — rather than building a second, near-duplicate dialog component.

Recommendation is **B**, specifically because Option A would make Laboratory the *only* clinical tab in the redesigned Patient Profile that can't be acted on without leaving the page — a real inconsistency the umbrella doc's own Vision (§1: *"every patient-relevant fact is one tab away, none of it silently degrading... rarely needs to leave"*) argues against. The incremental cost is small (one optional prop, no new component) precisely because `LabCaseActionsBar.vue` was already built decoupled from any store (§1.2) — it was already reusable, it just had nowhere to be reused yet.

## 8. Patient Profile Tab Integration

### 8.1 Tab position

Current actual `tabDefinitions` (`PatientDetailView.vue:62-71`, confirmed, not the umbrella doc's aspirational final order which assumes Appointments is already retired — it isn't, that's Phase 2.6):

```
overview → medicalHistory → appointments → dentalChart → imaging → treatmentPlans → clinicalNotes → billing
```

**Proposed insertion**: immediately after `imaging`, before `treatmentPlans`:

```
overview → medicalHistory → appointments → dentalChart → imaging → laboratory → treatmentPlans → clinicalNotes → billing
```

Rationale: the umbrella doc's own IA reasoning (§3) explicitly groups Imaging and Laboratory together as "diagnostic/workflow support," immediately after the clinical-context tabs — this is the smallest change that honors that grouping without touching any other tab's position, which would be an unscoped reorder (§3 "explicitly out of scope"). A full reorder to the umbrella doc's originally-envisioned 10-tab final order is deferred to whichever phase actually retires the Appointments tab (2.6).

### 8.2 Config change

```typescript
const tabDefinitions = computed(() => [
  { key: 'overview', labelKey: 'patients.tabs.overview', visible: true },
  { key: 'medicalHistory', labelKey: 'patients.tabs.medicalHistory', visible: true },
  { key: 'appointments', labelKey: 'patients.tabs.appointments', visible: true },
  { key: 'dentalChart', labelKey: 'patients.tabs.dentalChart', visible: true },
  { key: 'imaging', labelKey: 'patients.tabs.imaging', visible: true },
  { key: 'laboratory', labelKey: 'patients.tabs.laboratory', visible: true },   // new
  { key: 'treatmentPlans', labelKey: 'patients.tabs.treatmentPlans', visible: true },
  { key: 'clinicalNotes', labelKey: 'patients.tabs.clinicalNotes', visible: canAccessClinicalNotes.value },
  { key: 'billing', labelKey: 'patients.tabs.billing', visible: true },
])
```

Plus the corresponding `<TabPanel value="laboratory">` block and `<PatientLabCasesPanel :patient-id="patientId" />`, following the exact pattern already used for `medicalHistory`/`imaging`/`treatmentPlans` in the same file.

## 9. Permission Matrix

No new policy, no new roles — `LabCasePolicy`/`LabPolicy` are reused entirely unchanged, confirmed already finalized and correct for this context:

| Action | Roles | Enforced by |
|---|---|---|
| View tab / list cases | All staff (admin, dentist, receptionist) | `LabCasePolicy::viewAny()` — unchanged, already `true` for everyone |
| Create case | admin, dentist | `LabCasePolicy::create()` — unchanged |
| Edit case (Draft only) | admin, dentist | `LabCasePolicy::update()` — unchanged |
| Send / Receive / Quality-check | admin, receptionist | `LabCasePolicy::send/receive/qualityCheck()` — unchanged |
| Cancel | admin, dentist | `LabCasePolicy::cancel()` — unchanged |
| Delete (Draft only) | admin only | `LabCasePolicy::delete()` — unchanged |

The new `forPatient()` controller method calls `$this->authorize('viewAny', LabCase::class)` exactly like the existing flat `index()` — no per-patient permission concept exists or is needed (matches every sibling patient-scoped endpoint: Treatment Plans, Clinical Notes, Imaging all gate identically). Confirmed **not** the Clinical Notes shape (receptionist-excluded) — Laboratory's tab should be visible to all three roles in `PatientDetailView.vue`'s `tabDefinitions`, i.e. `visible: true`, not gated behind a `canAccessX` computed the way `clinicalNotes` is.

## 10. Pagination Strategy

`GET /patients/{patient}/lab-cases` paginates at **15/page**, matching every other patient-scoped list added since Phase 2.1 (`PatientService::paginate()`'s established default, also used by Treatment Plans/Clinical Notes). This intentionally differs from the flat `GET /lab-cases` endpoint's own default of 20/page (`(int) $request->query('per_page', 20)`) — that default is preserved unchanged for the top-level clinic-wide view, which is a different UI context with more vertical space (full page vs. an embedded tab panel).

## 11. i18n Requirements

New keys needed in `ar`/`en`/`tr` locale files, verified for 1:1 parity the same way every prior module was (manual check per `docs/PROJECT_STATUS.md` §12/§5 — no automated i18n-parity check exists in this repo yet):
- `patients.tabs.laboratory` (tab label — `en`: "Laboratory").
- A handful of panel-local strings under a new `patients.laboratory.*` or reused `laboratory.labCases.*` namespace for empty-state text, the inline create action, and any panel-specific labels not already covered by the existing `laboratory.*` namespace (most field labels — status, due date, lab name — already have translations from the standalone module and should be reused via the same keys, not duplicated).
- `laboratory.*` namespace itself is untouched — this phase adds Patient-Profile-panel-specific strings only, it does not rename or restructure the existing namespace the standalone pages depend on.

## 12. Mobile UX

No new mobile mechanism — this phase inherits the `<768px` dropdown tab switcher already shipped in Phase 2.2 (umbrella doc §4/§12), which already drives `activeTab` regardless of how many tabs exist. `PatientLabCasesPanel.vue` itself must be verified at a 390px viewport during implementation (per the umbrella doc §12's blanket requirement for every new component in this redesign) — specifically: the status filter + list rows must not overflow horizontally, and (if §12 decision A resolves to Option B) the inline create action must remain reachable and usable at that width, matching how `PatientImagingPanel.vue`'s own upload action already handles the same constraint.

## 13. Testing Strategy

**Backend**:
- Feature test: `GET /patients/{patient}/lab-cases` returns only that patient's cases, paginated, respects `?status=` filter, 403s correctly per role — mirrors `TreatmentPlanTest`'s existing patient-scoped-index test shape.
- Feature test: the §5 fix — same-patient `treatment_plan_item_id` accepted, cross-patient one rejected with 422 (not 500), for both `Store`/`UpdateLabCaseRequest`.
- No new Policy tests needed — `LabCasePolicy` is unchanged; existing `LabCaseTest.php` assertions already cover its rules against the flat endpoint, and the same policy method is reused for the new one.

**Frontend**:
- Vitest store test for `patientLabCases.ts`: cache-hit/force-refresh behavior (same contract test shape as `treatmentPlans.test.ts`), `create`/`send`/`receive`/`qualityCheck`/`cancel` each upsert correctly.
- Component test for `PatientLabCasesPanel.vue`: empty state renders, list renders, status filter works, (if Option B) create dialog opens pre-filled with the current patient and skips the patient-search step.

**E2E (Playwright)**: extend `frontend/e2e/laboratory.spec.ts` or the Patient Profile spec with: Laboratory tab visible and navigable for all three roles, a case created from the tab appears immediately without a manual refresh, a status-transition action taken from the tab updates the row in place, deep-link from a tab row to `LabCaseDetailView.vue` and back.

## 14. Implementation Sub-phases (proposed)

Given the scope confirmed above is genuinely small (§6: no schema change; §4: one route/scope/store/panel), this doesn't need splitting into further sub-phases the way 2.6 (Timeline) will — proposed as a **single PR**, consistent with every prior sub-phase's one-PR convention (`docs/PROJECT_STATUS.md` §4), containing:
1. §5's bug fix + regression tests (small, self-contained, could technically land first/separately if the user prefers it isolated from the rest — flagged as an option, not a requirement).
2. `Patient::labCases()` + `LabCase::scopeForPatient()` + new route + controller method.
3. `patientLabCases.ts` store.
4. `PatientLabCasesPanel.vue` + (pending §12 decision A) `CreateLabCaseDialog.vue`'s optional-`patientId` change.
5. Tab wiring in `PatientDetailView.vue` + i18n keys (§8, §11).
6. Tests (§13).
7. Docs: `docs/modules/laboratory-design.md` gets an "Implementation Summary" addendum (matching how `patient-profile-redesign-design.md` itself documents each merged sub-phase) noting the Patient Profile integration now exists; `docs/PROJECT_STATUS.md`/`CHANGELOG.md`/`TECH_DEBT.md` updated per the standing Definition of Done.

## 15. Deferred Items / Tech Debt Carried Forward

- File/photo/STL/lab-report attachments on a Lab Case — deferred to Documents (2.5), see §2. Not re-flagged as new debt; the existing `docs/modules/laboratory-design.md` §7/§8 deferral already covers it, this doc just confirms the deferral still holds and names the eventual mechanism (`PatientDocument`, category `lab_report`).
- `PatientActivity`/Timeline event dispatch from `LabCaseService` transitions — deferred to 2.6, see §2.
- Full Patient Profile tab reorder to the umbrella doc's originally-envisioned final order — deferred to whichever phase retires the Appointments tab (2.6), see §8.1.
- Dropping the now-dead `?patient_id=` filter from the flat `GET /lab-cases` endpoint (§4.1) — proposed as part of this phase's own PR (small, low-risk, confirmed-unused), not deferred, but named here in case review prefers to keep the flat endpoint's surface untouched and defer even this small cleanup.

## 16. Decisions Requiring Approval Before Implementation

1. **§7 — Read-only vs. read/write Laboratory tab.** Recommend **Option B (read/write)**: extend `CreateLabCaseDialog.vue` with an optional `patientId` prop, reuse `LabCaseActionsBar.vue` inline. Alternative is Option A (list + deep-link only), smaller diff but leaves Laboratory as the one clinical tab that can't be acted on without navigating away.
2. **§5 — Fix the `BelongsToPatient`/`TreatmentPlanItem` bug as part of this phase, not separately.** Recommend **yes, required**: this phase is what makes the buggy code path newly reachable in practice; Imaging's existing fix pattern is a direct copy-paste-and-adapt, low effort. Alternative: fix it as its own tiny separate PR first (§14 already names this as optionally splittable) — either way, recommend it lands *before or with* this phase, not after.
3. **§4.1 — Drop the dead `?patient_id=` query filter from the flat `GET /lab-cases` endpoint.** Recommend **yes**: confirmed unused by any current frontend caller, and leaving two parallel ways to filter by patient (query param vs. new nested route) is avoidable drift. Alternative: leave it untouched (harmless, just redundant) if the user prefers a strictly additive diff for this PR.
4. **§8.1 — Tab insertion point** (after Imaging, before Treatment Plans). Recommend as stated — smallest change consistent with the umbrella doc's own IA grouping rationale. Alternative: any other position the user prefers; this is a low-stakes call.
5. **§11 — New i18n namespace shape** for panel-local strings (reuse `laboratory.*` for shared field labels, new keys only for panel-specific UI). Recommend as stated; flagged since it's a naming-convention judgment call, not a hard technical constraint.

No implementation begins until these are resolved.
