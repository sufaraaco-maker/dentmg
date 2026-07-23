# Treatment Plans Module — Design Document (APPROVED 2026-07-22)

**Status: Design approved 2026-07-22, with the decisions recorded in "Approval & Decision Log" below.
Implementation proceeds one checkpointed step at a time per §21 — Step 1 (Database migrations + enums +
models only) is authorized; every later step still requires its own explicit approval before starting.**

Grounded in the current, shipped state of the codebase (`Patient`, `Appointment`, `DentalCondition`,
`DentalChartEntry`, `Auditable` trait, `docs/database-design.md`, `docs/api-guidelines.md`,
`docs/decisions.md`, `docs/modules/dental-chart-design-draft.md`, `docs/modules/dental-chart.md` — verified
directly, not assumed) — this design reuses those exact conventions rather than inventing new ones.

---

## Approval & Decision Log (2026-07-22)

Approved with the following six decisions, each elaborated in-place below (this section is a quick-reference
index, not a duplicate):

1. **DentalChartEntry / Appointment relationship** — one-way, read-only references confirmed exactly as
   proposed (§7, §16 item 1). No sync in either direction, in either direction of time: Dental Chart changes
   never rewrite historical Treatment Plans, and Treatment Plan changes never mutate Dental Chart.
2. **Cost freezing** — confirmed at `presented`, not `accepted` (§5, §8, §15 Q2, §16 item 4) — **expanded**:
   the freeze now covers a full **procedure name + description + cost snapshot**, not cost alone (§6, §8).
3. **Multiple treatment plans** — confirmed unlimited Draft and unlimited concurrent Presented plans, with
   **only one plan ever Accepted/In Progress at a time**, enforced by the accept-auto-rejects-siblings rule
   (§5, §15 Q3) — wording reconciled explicitly in §5 below.
4. **Revisions** — confirmed `superseded_by_plan_id`, no version table (§15 Q4, §16 item 4... see note: this
   is item 4 of §15's questions, distinct from §16's numbered list — cross-referenced, not duplicated).
5. **Pricing catalog** — confirmed: reuse `dental_conditions` for V1 **only**, explicitly logged as
   technical debt (§14, plus a `TECH_DEBT.md` entry) rather than expanded in scope now.
6. **Cost totals** — confirmed: never stored/cached, always computed from items (§6, unchanged from the
   original recommendation).

Plus three additional readiness sections appended at the end of this document (§22–§24, added after §21 so
every existing `§N` cross-reference above this line stays valid): **Audit Requirements (Expanded)**,
**Patient Communication Readiness**, and **Reporting Readiness** (concrete report formulas, not just a
paragraph).

---

## 0. Competitive Research (required before any design, per standing product philosophy)

Reviewed how leading dental PMS platforms structure treatment planning, since this module sits at the
highest-stakes intersection of clinical workflow and revenue (case acceptance) in the whole system:

| Product | What it does | Taken / rejected |
|---|---|---|
| **Open Dental** | One `treatplan` per "active" set of procedures per patient, plus unlimited "inactive" (saved/alternative) plans. Every procedure carries its own fine-grained status (`TP`/`TPi` = treatment-planned on active/inactive plan, `C` = complete, `EC`/`EO` = existing-current-provider/existing-other-provider — prior work with no fee, `Ref` = referred out). The active plan drives what shows on the Chart Module's tooth chart and what's available to attach to an Appointment. | **Taken**: the core idea that a plan is a *container* of procedure-shaped line items, each individually completable, and that "what's planned" is directly visible on the chart. **Taken**: `ExistCurProv`-style "no fee" entries as the reasoning behind allowing `unit_cost` to be `0`/optional rather than force a price on every conceivable line item. **Rejected**: OD's procedures *are* the treatment-plan line item and the chart entry (one merged object) — DentalSuite already has a separate, shipped `DentalChartEntry` concept from the Dental Chart module; merging the two now would mean reshaping a production table. Kept as two related-but-distinct entities instead (§7's open decision explains the trade-off explicitly). |
| **Dentrix (Ascend)** | Multiple treatment plan **cases** per patient (e.g., "Case A: Implant," "Case B: Bridge" — real alternative treatment paths, not revisions). Case-level statuses: New → Presented/Proposed → Accepted/Rejected → Completed. **Accepting one case automatically rejects its linked alternative cases.** | **Taken directly**: multiple concurrent plans per patient as first-class support for presenting real alternatives (not a workaround), and the auto-reject-siblings-on-accept rule — a genuine, well-tested UX pattern for case acceptance, not something to reinvent. This directly answers Question 3 in §15. |
| **CareStack** | Plans are organized into **phases** (rename-able, each with its own target timeframe), assignable to a treatment coordinator, with drag-and-drop sequencing. Plans push to a Patient Portal/Kiosk for e-signature and integrate with financing options. | **Taken (scoped down)**: a lightweight integer `phase` grouping on each item (§6) — cheap, additive, avoids a reshape later — without the full drag-and-drop authoring UI, patient e-signature, or financing integration, none of which exist elsewhere in DentalSuite yet and would be premature to build only for this module. **Rejected for V1**: treatment-coordinator role, patient portal/kiosk presentation, financing — all real, valuable ideas, flagged in §17 Future Improvements rather than built now. |
| **Curve Dental / Eaglesoft / Oryx** | General 2026 trend confirmed across all of them: treatment plans as a distinct, costed, multi-visit entity tightly linked to charting, scheduling, and billing — never a free-text quote. | Confirms the direction below (a real relational entity, not a PDF/text quote) rather than contradicting it. |

**What DentalSuite does differently / better, not just clones**: every competitor above either merges the
chart and the plan into one object (Open Dental) or treats the plan as a closed, desktop-bound authoring
tool. This design keeps `DentalChartEntry` (the clinical fact) and `TreatmentPlanItem` (the costed,
patient-facing commitment to act on that fact) as two distinct, API-first entities connected by an explicit,
optional link (§7) — so a future AI Analytics Assistant or case-acceptance dashboard can query "outstanding
treatment value" or "acceptance rate by dentist" directly through the Service/Resource layers without ever
touching a chart-rendering concern, and so Billing (when built) attaches to `TreatmentPlanItem` without
needing to understand tooth charting at all.

Sources: [Open Dental Treatment Plan Module](https://opendental.com/manual/treatmentplan.html),
[Open Dental Enter Treatment](https://opendental.com/manual/entertreatment.html),
[Open Dental Treatment Plan in Chart](https://www.opendental.com/manual/charttp.html),
[Dentrix Ascend — Presenting treatment plans](https://support.dentrixascend.com/hc/en-us/articles/229955427),
[Dentrix Magazine — Tracking Treatment Plan Cases using Treatment Statuses](https://magazine.dentrix.com/tracking-treatment-plan-cases-using-treatment-statuses/),
[Dentrix Magazine — Simplify Treatment Planning with Alternate Cases](https://magazine.dentrix.com/simplify-treatment-planning-with-alternate-cases/),
[CareStack — Dental Treatment Planning Software](https://carestack.com/en-GB/dental-software/features/treatment-planning).

---

## 1. Module Goal / Purpose

Give dentists and admins a structured way to propose, cost, present, and track multi-procedure treatment
recommendations for a patient — bridging the gap between *diagnosis* (Dental Chart) and *action*
(Appointments, and eventually Billing). A Treatment Plan is the clinical-and-financial record of "what we
recommend, what it costs, whether the patient agreed, and how far along it is" — it deliberately does not
own the underlying clinical fact of a tooth's condition (Dental Chart already does), the scheduling
mechanics of a visit (Appointments already does), or invoicing/payment collection (future Billing will).

## 2. Scope (V1)

**In scope:**
- A patient can have multiple treatment plans (concurrent alternatives and/or plans over time).
- Each plan is a container of **Treatment Plan Items** — individual procedure recommendations, each
  optionally tied to a tooth/surfaces and optionally traceable back to the Dental Chart finding that
  justifies it.
- A plan-level status lifecycle (Draft → Presented → Accepted/Rejected → In Progress → Completed, plus
  Cancelled) representing the patient-facing business workflow (case acceptance).
- An item-level status lifecycle (Planned → Completed/Cancelled) representing execution tracking, since a
  single accepted plan's items are completed individually, often across multiple visits.
- Cost estimation per item (snapshotted/frozen once presented — see §15 Q2) and a plan-level cost roll-up
  computed from its items — no stored, cacheable total (§6 explains why).
- Reuse of the existing `dental_conditions` catalog (`category = procedure`) as the procedures catalog,
  extended with a `default_cost` column, rather than a new catalog table (§6).
- Optional linkage from a Treatment Plan Item to an `Appointment` (once the patient books the work) and to a
  `DentalChartEntry` (the diagnosis/finding it addresses) — both nullable, both read-only references, no
  bidirectional sync (§7's resolved open decision).
- A "supersession" trail for replaced plans (`superseded_by_plan_id`) instead of a separate
  revision/versioning subsystem (§15 Q4).
- Full audit trail via the existing `Auditable` trait — no new mechanism.
- A dedicated Patient Treatment Plans tab (list) + a dedicated Plan Detail route (§11).

**Explicitly out of scope for V1** (future modules already named in `PROJECT_CONTEXT.md`'s roadmap — this
module must not duplicate or pre-empt their design):
- **Billing/Payments** — no invoicing, no payment collection, no insurance claims/pre-authorization. A
  plan/item's cost is an *estimate* for case-acceptance purposes only. The seam Billing attaches to is
  `treatment_plan_items` (§7), not built now.
- **Patient e-signature / Patient Portal presentation** (CareStack §0) — presenting a plan in V1 is an
  in-person/phone workflow recorded by staff (`present()`/`accept()`/`reject()` actions), not a
  patient-facing digital signature flow.
- **Treatment coordinator role** (CareStack §0) — no new role; existing admin/dentist/receptionist roles
  only (§10).
- **Financing/payment-plan options** (CareStack §0) — belongs with future Billing.
- **Automatic Dental-Chart synchronization** (auto-creating/mutating chart entries when a plan item is
  completed) — deliberately not built for V1; see §7's resolved decision and rationale.
- **Phase renaming/authoring UX** (CareStack's full drag-and-drop phase builder) — V1 ships a simple integer
  `phase` grouping (§6), not a dedicated phase-management screen.

## 3. Full Workflow

Confirms and extends the chain given in the kickoff brief:

```
Patient
  → Dental Chart / Diagnosis   (existing module — an `active` finding, e.g. "Caries" on tooth 16)
  → Treatment Plan              (this module — a costed recommendation addressing that finding)
  → Treatment Plan Items        (this module — one line per procedure/tooth, e.g. "Composite Filling, #16")
  → Appointments                (existing module — the item is scheduled by linking an Appointment)
  → Billing (future)            (out of scope — will read completed items' costs to generate an invoice)
```

**Primary flow — proposing and presenting a plan (dentist, admin):**
1. From a patient's Dental Chart (or directly from their Treatment Plans tab), start a new plan — optionally
   pre-seeded from one or more `active` chart findings selected on the chart ("Add to Treatment Plan"
   action — a small, additive UI affordance on `ChartEntryDialog.vue`/`ChartEntryListTable.vue`, not a
   backend change).
2. Add items: pick a procedure from the catalog (`dental_conditions` where `category = procedure`),
   optionally a tooth + surfaces, optionally the diagnosis entry it addresses, quantity, and a cost
   (defaults from `dental_conditions.default_cost`, editable per item). Plan is `draft` — freely editable.
3. Group items into phases if useful (e.g., Phase 1: urgent/pain-relief work; Phase 2: elective/cosmetic) —
   a plain integer, not a required field.
4. When ready, transition the plan to `presented` — this **freezes every item's cost** (§15 Q2) and records
   `presented_at`.
5. Record the patient's decision: `accept()` or `reject()`. **Accepting this plan automatically rejects any
   other `presented` plan for the same patient** (§15 Q3, direct from Dentrix's confirmed behavior) — a
   Draft plan is left alone (it was never a live alternative).
6. Once `accepted`, items become schedulable: attach an existing/new `Appointment` to an item (nullable
   `appointment_id`) — the item stays `planned` until the work is actually done.
7. Staff explicitly transitions the plan to `in_progress` once work begins (not automatic — §15 Q2-adjacent
   reasoning, consistent with "explicit action, not guessed" throughout this module).
8. As each visit happens, staff marks the corresponding item `completed` (independent of whether the
   patient's own Dental Chart entry for that tooth is separately updated via the Dental Chart module — §7).
9. When every item is `completed` or `cancelled`, staff explicitly marks the plan `completed`
   (server-validated: rejects the transition if any item is still `planned`).

**Secondary flow — patient declines:** `reject()` from `presented` is terminal for that plan. If the
practice wants to offer a revised plan, a **new** plan is created (optionally cloned from the rejected one —
a cheap, additive "Duplicate Plan" action), and the old plan's `superseded_by_plan_id` is set to the new
plan's id (§15 Q4) — giving a readable lineage without a versioning subsystem.

**Tertiary flow — reviewing history:** the Patient Treatment Plans tab lists every plan (all statuses, most
recent first); each plan's detail view shows its full item list/timeline and links to its
predecessor/successor via `superseded_by_plan_id` if present.

## 4. Core Concepts (definitions)

- **Treatment Plan**: a costed, patient-facing proposal — the unit of case presentation and acceptance.
  Belongs to one `Patient`; has one responsible `dentist_id` and one `created_by_id` (may differ — an admin
  may draft a plan on a dentist's behalf, mirroring `DentalChartEntry`'s `dentist_id`/`created_by_id` split
  exactly).
- **Treatment Plan Item**: one line within a plan — one procedure, optionally scoped to a tooth/surfaces,
  with its own cost, quantity, and execution status. The unit of completion and (later) billing.
- **Procedure**: reuses the existing `dental_conditions` catalog filtered to `category = procedure` — not a
  new catalog table (§6). "Procedures" in the kickoff brief and `dental_conditions` (procedure half) are the
  same concept; extending, not duplicating.
- **Estimated cost**: `unit_cost × quantity` per item (a model accessor, never stored — §6), summed across a
  plan's non-cancelled items for the plan-level total (a service-layer query aggregate, never a stored/cached
  column — avoids the classic cached-total-drifts-from-line-items bug class).
- **Status lifecycle**: two independent state machines — plan-level (business/acceptance workflow) and
  item-level (execution tracking) — detailed in §5.

## 5. Status Lifecycle

### Plan-level: `TreatmentPlanStatus`

```
draft ──────► presented ──────► accepted ──────► in_progress ──────► completed
  │               │                  │
  │               ▼                  │
  │            rejected              │
  │                                  │
  └──────────────────────────────────┴──────────► cancelled
```

| From | Allowed to | Trigger | Side effects |
|---|---|---|---|
| `draft` | `presented`, `cancelled` | staff action | `presented`: freezes all item costs, sets `presented_at` |
| `presented` | `accepted`, `rejected`, `cancelled` | staff records patient decision | `accepted`: sets `accepted_at`; **auto-rejects sibling `presented` plans for the same patient** (§15 Q3); `rejected`: sets `rejected_at` |
| `accepted` | `in_progress`, `cancelled` | staff action (work begins) | `in_progress`: sets `started_at` |
| `in_progress` | `completed`, `cancelled` | staff action, **validated**: rejects with `422` if any item is still `planned` | `completed`: sets `completed_at` |
| `completed`, `rejected`, `cancelled` | — (terminal) | — | — |

A **plan-level `cancel`** is available from every non-terminal status (a practice may abandon a plan at any
point — patient moved away, duplicate entry, etc.) and **cascades to cancel every non-terminal item** in it
(the only cross-entity cascade this module performs — see §7 for why this one is safe/appropriate while
completion-sync is not).

**Reconciling "only one active plan at a time" (Decision 3) with concurrent Presented plans**: a patient may
have several plans `presented` at once — that's the deliberate Dentrix-style "Option A vs. Option B"
pattern (§0, §15 Q3), not a bug. What's actually constrained to *one at a time* is which plan is
`accepted`/`in_progress` — the single path the practice and patient are actually acting on. The
accept-auto-rejects-siblings rule (row 2 above) is exactly the mechanism that collapses "several presented
alternatives" down to "one accepted plan" the instant a decision is made, so the invariant "at most one
`accepted`/`in_progress` plan per patient" always holds without a separate DB constraint enforcing it — it
falls out of the transition rule itself.

### Item-level: `TreatmentPlanItemStatus`

```
planned ──────► completed
   │
   └───────────► cancelled
```

Deliberately just three states — **no stored `scheduled` state** (considered and rejected: whether an item
is "scheduled" is fully derived from `appointment_id` being set to a non-cancelled `Appointment`, so storing
it separately would be exactly the kind of derivable, driftable state `DentalChartEntry.dentition_type`
already avoids by using a computed accessor instead of a column — §6 applies the same principle here).

| From | Allowed to | Trigger |
|---|---|---|
| `planned` | `completed`, `cancelled` | staff action |
| `completed`, `cancelled` | — (terminal) | — |

Cost fields (`unit_cost`, `quantity`, and therefore the derived `estimated_cost`) become **read-only** the
moment the *parent plan* leaves `draft` (§15 Q2) — enforced in the service layer exactly like
`DentalChartService`'s existing `EntryLockedException` pattern (terminal-state entries reject all fields but
`notes`) — reused directly, not reinvented: a new `TreatmentPlanItemLockedException` mirrors it.

## 6. Database Design

### Extend `dental_conditions` (additive migration — no reshape of the Dental Chart module)

| Column | Type | Notes |
|---|---|---|
| `default_cost` | decimal(10,2), nullable | Default per-unit price for this procedure, used to prefill a new `treatment_plan_items.unit_cost` (editable per item). Nullable because not every clinic prices every procedure identically, and `null` cleanly means "no default — dentist must enter one," mirroring Open Dental's `ExistCurProv`/`ExistOther` "no fee" precedent (§0). |
| `description` | text, nullable | A short patient-facing explanation of the procedure (e.g., "A tooth-colored resin filling used to repair a cavity"). Added specifically so `treatment_plan_items` has something meaningful to snapshot (below) beyond a bare name — also the natural source text for a future printable treatment proposal (§23). Nullable: not every condition needs one, and existing seeded rows have none until backfilled. |

No currency column (§14 SaaS Readiness explains why — single implicit currency for V1, additive later).

### `treatment_plans` (new table)

| Column | Type | Notes |
|---|---|---|
| `id` | uuid | PK |
| `patient_id` | uuid, FK → `patients`, not nullable | |
| `dentist_id` | uuid, FK → `users`, not nullable | The responsible/treating dentist for this plan — same "app-level role check, not a DB constraint" approach as `dental_chart_entries.dentist_id` |
| `created_by_id` | uuid, FK → `users`, not nullable | Who authored the plan (may differ from `dentist_id` — an admin may draft on a dentist's behalf) |
| `title` | string, nullable | e.g. "Option A — Implant," useful once multiple concurrent plans exist (Dentrix §0); optional, not required for a single straightforward plan |
| `status` | string, cast to `TreatmentPlanStatus` enum | §5 |
| `notes` | text, nullable | Plan-level notes (separate from any item's own `notes`) |
| `presented_at` / `accepted_at` / `rejected_at` / `started_at` / `completed_at` / `cancelled_at` | timestamp, nullable | Set only on their respective transition, mirroring `Appointment`'s/`DentalChartEntry`'s exact pattern |
| `superseded_by_plan_id` | uuid, nullable, FK → `treatment_plans` (self-referencing) | §15 Q4 — set when a replacement plan is created for this one |
| `deleted_at` | timestamp, nullable | Soft delete |
| `created_at` / `updated_at` | timestamp | |

Indexes: `(patient_id)`, `(patient_id, status)`, `(dentist_id)`.

### `treatment_plan_items` (new table)

| Column | Type | Notes |
|---|---|---|
| `id` | uuid | PK |
| `treatment_plan_id` | uuid, FK → `treatment_plans`, not nullable | |
| `dental_condition_id` | uuid, FK → `dental_conditions`, not nullable | The procedure — kept as a live FK purely for catalog/category linkage and future reporting joins (e.g., "value of all Crown items across clinics"), **never** used to resolve display name/description/price (those always read from the snapshot columns below, per Decision 2) |
| `procedure_name` | string, not nullable | Snapshot of `dental_conditions.name` at the moment the item is created (or last edited while the plan is still `draft`) — what the patient was actually shown, immune to a later catalog rename |
| `procedure_description` | text, nullable | Snapshot of `dental_conditions.description` at the same moment — feeds a future printable proposal (§23) with the exact wording presented, not whatever the catalog says today |
| `diagnosis_entry_id` | uuid, nullable, FK → `dental_chart_entries` | Traceability link to the finding this item addresses — **read-only reference, never mutated by this module** (§7) |
| `tooth_number` | string(2), nullable | Validated against `App\Support\ToothChart` when present. **Nullable**, unlike `dental_chart_entries.tooth_number` (always required) — because not every planned procedure is tooth-specific (e.g., a full-mouth debridement, an orthodontic consult, whitening) |
| `surfaces` | json, nullable | Same shape/validation as `dental_chart_entries.surfaces`; only meaningful when `tooth_number` is present |
| `quantity` | unsigned smallint, default 1 | e.g., multiple anesthesia units; rarely >1 for a single-tooth procedure |
| `unit_cost` | decimal(10,2), not nullable | Snapshotted from `dental_conditions.default_cost` at creation (or manually entered/overridden); **frozen (read-only), together with `procedure_name`/`procedure_description`, once the parent plan leaves `draft`** (§5, §15 Q2 — Decision 2 expands the freeze to the full name+description+cost snapshot, not cost alone) |
| `phase` | unsigned smallint, nullable, default 1 | Lightweight CareStack-style grouping (§0) — no phase-name table in V1 (§16 item 3) |
| `sequence` | unsigned smallint, nullable | Manual ordering within a phase |
| `status` | string, cast to `TreatmentPlanItemStatus` enum | §5 |
| `appointment_id` | uuid, nullable, FK → `appointments` | Set once the patient books this specific item (§3 step 6) |
| `notes` | text, nullable | |
| `completed_at` / `cancelled_at` | timestamp, nullable | |
| `created_by_id` | uuid, FK → `users`, not nullable | Mirrors `dental_chart_entries`' create/update actor split |
| `updated_by_id` | uuid, nullable, FK → `users` | |
| `deleted_at` | timestamp, nullable | Soft delete |
| `created_at` / `updated_at` | timestamp | |

Indexes: `(treatment_plan_id)`, `(treatment_plan_id, status)`, `(dental_condition_id)`, `(appointment_id)`,
`(diagnosis_entry_id)`.

**`estimated_cost` is deliberately not a column** on either table — computed as `unit_cost * quantity` (item
accessor) and `SUM` over non-cancelled items (plan-level, service-layer query) — the same "don't store
derivable state" principle already applied to `DentalChartEntry.dentition_type` (§6 of the Dental Chart
design doc), extended here to a financial total instead of a clinical classification.

Both tables get `Auditable`, `HasUuids`, `SoftDeletes` — no exceptions, per `database-design.md`'s
conventions and this module's cost-plus-clinical sensitivity (§12, §22).

## 7. Table Relationships — and the Dental Chart / Appointments Integration Decision

```
Patient (existing)
  └─┬─ hasMany ─→ TreatmentPlan
    │               ├─ belongsTo → User (dentist_id)
    │               ├─ belongsTo → User (created_by_id)
    │               ├─ belongsTo → TreatmentPlan (superseded_by_plan_id, self)
    │               └─┬─ hasMany ─→ TreatmentPlanItem
    │                   ├─ belongsTo → DentalCondition (dental_condition_id — the procedure)
    │                   ├─ belongsTo → DentalChartEntry (diagnosis_entry_id, nullable)
    │                   ├─ belongsTo → Appointment (appointment_id, nullable)
    │                   ├─ belongsTo → User (created_by_id / updated_by_id)
```

**The one genuinely hard architectural question in this whole design: how tightly should
`TreatmentPlanItem` couple to `DentalChartEntry`?** Two options were weighed:

- **Option A (rejected): bidirectional sync.** Adding an item auto-creates a `planned`-status
  `dental_chart_entry` (so the odontogram immediately reflects it); completing the item auto-completes that
  chart entry; cancelling the item auto-cancels it. Pro: the odontogram is always perfectly up to date
  without a human remembering to update it in two places. Con: real, non-trivial edge cases — what happens
  if a dentist independently edits or cancels that same chart entry directly through the Dental Chart
  module while a `TreatmentPlanItem` still references it? Two modules would now be racing to own one
  record's lifecycle, and reconciling that safely needs either a lock, an event system neither module has
  yet, or defensive re-checking on every write. This is exactly the kind of cross-entity automatic mutation
  the Dental Chart module's own design already considered and rejected once (its §20 open decision #6,
  "automatic supersession of an active finding," deferred to manual review for the same reason).
- **Option B (recommended): a one-way, read-only reference.** `diagnosis_entry_id` is a plain nullable FK a
  dentist may optionally set when adding an item ("this procedure addresses that finding"), purely for
  traceability/context in the UI — Treatment Plans never writes to `dental_chart_entries`, and Dental Chart
  has zero knowledge of Treatment Plans (no new column added to `dental_chart_entries`, so a previously
  shipped, production module stays untouched). When work is actually performed, the dentist marks it
  `completed` in **both** places independently — the same two clicks a Dentrix/Open Dental user makes today
  in their own workflow (§0), not a regression.

**Decision 1 — APPROVED: Option B**, for the same reason Dental Chart deferred automatic supersession:
consistency over convenience, explicit human confirmation over guessed automatic mutation, and zero risk to
an already-shipped module. Confirmed 2026-07-22 exactly as recommended, with the reciprocal rule stated
explicitly: **changes in Dental Chart never rewrite a historical Treatment Plan, and changes in Treatment
Plans never mutate Dental Chart** — Treatment Plans is a snapshot of a clinical-and-financial moment, not a
live mirror of the chart.

**Appointments integration** follows the identical one-way pattern: `treatment_plan_items.appointment_id` is
a plain nullable reference the UI sets when a staff member books/links a visit to a specific item — no new
column on `appointments`, no cascade in either direction beyond the one explicit "cancel the plan → cancel
its items" case in §5 (a plan-level, not appointment-level, cascade).

## 8. Business Rules (consolidated)

- A plan's `patient_id` is immutable after creation (mirrors Appointments'/Dental Chart's identical rule).
- An item's `treatment_plan_id` is immutable after creation — moving an item between plans isn't supported;
  delete and re-add instead, preserving an honest audit trail (same reasoning as Dental Chart's "no editing
  `patient_id`/`tooth_number`" rule).
- `surfaces` may only be set when `tooth_number` is present, and only when the referenced
  `dental_condition.applies_to_surface` is true — identical validation shape to `dental_chart_entries`,
  reusing the same `ValidDentalChartSurfaces`-style rule rather than inventing a new one.
- `procedure_name`/`procedure_description` are populated from `dental_conditions.name`/`.description` every
  time `dental_condition_id` changes while the item is still editable (`draft`) — they are a snapshot, not a
  live join, so display code must always read these columns, never `$item->dentalCondition->name` (Decision
  2). This is what makes the plan "the clinic's actual commercial offer to the patient" rather than a
  live-priced quote that could silently change.
- `unit_cost`/`quantity`/`procedure_name`/`procedure_description` (and therefore the derived
  `estimated_cost`) are editable only while the **parent plan** is `draft`; any write attempt after that
  throws `TreatmentPlanItemLockedException` (`422`), mirroring `EntryLockedException` exactly (§5). This is
  the full "commercial offer" freeze Decision 2 requires — cost alone is not sufficient, since a renamed or
  re-described catalog procedure must not retroactively change what an already-presented plan says it is.
- Transitioning a plan to `accepted` automatically transitions every other `presented` plan for the same
  `patient_id` to `rejected` (§5, §15 Q3) — a single service-layer transaction, not a queued/async job (the
  set of sibling plans is always small — realistically 1-3 alternatives per patient at a time).
- Transitioning a plan to `completed` is rejected (`422`) if any item is still `planned` — staff must
  complete or cancel every item first; there is no "force complete" override for V1.
- Transitioning a plan to `cancelled` cascades to cancel every non-terminal (`planned`) item in it, in the
  same transaction.
- A `dental_condition_id` referenced by an item must have `category = procedure` (enforced in the Form
  Request, not a DB constraint — consistent with how the rest of the codebase enforces enum-subset rules).
- `dental_conditions.default_cost` being `null` is valid — the item's `unit_cost` simply has no prefill and
  must be entered manually (mirrors Open Dental's `ExistCurProv`/`ExistOther` "no fee" case, §0).
- Soft delete (hard-remove-behind-soft-delete) of a plan or item is a data-correction action, gated
  admin-only, distinct from `cancel` (a clinical/business action available to admin and dentist) — mirrors
  the exact Delete-vs-Cancel privilege split already established in Dental Chart.

## 9. API Design

```
GET    /api/patients/{patient}/treatment-plans              (list, all statuses, not paginated — see note)
POST   /api/patients/{patient}/treatment-plans               (create, status=draft)
GET    /api/treatment-plans/{plan}                            (detail, eager-loads items + their relations)
PUT    /api/treatment-plans/{plan}                             (edit title/notes/dentist — draft only for structural fields)
POST   /api/treatment-plans/{plan}/present
POST   /api/treatment-plans/{plan}/accept
POST   /api/treatment-plans/{plan}/reject
POST   /api/treatment-plans/{plan}/start                      (→ in_progress)
POST   /api/treatment-plans/{plan}/complete
POST   /api/treatment-plans/{plan}/cancel
POST   /api/treatment-plans/{plan}/revisions                  (createSupersedingPlan, §15 Q4 — added at Step 3, see note)
DELETE /api/treatment-plans/{plan}                             (soft delete — admin-only)

POST   /api/treatment-plans/{plan}/items                      (add item)
PUT    /api/treatment-plan-items/{item}                        (edit — cost fields locked outside draft, §8)
POST   /api/treatment-plan-items/{item}/complete
POST   /api/treatment-plan-items/{item}/cancel
DELETE /api/treatment-plan-items/{item}                        (soft delete — admin-only)
```

**Deliberately unpaginated list endpoint** (`GET .../treatment-plans`), same documented exception class as
`GET /api/patients/{patient}/dental-chart-entries` and `GET /api/patients/{id}/audit-logs`: always
patient-scoped, and a patient's lifetime plan count is naturally small (a handful, not thousands).

**Step 3 implementation discovery — `POST /api/treatment-plans/{plan}/revisions`**: Step 2 already shipped
`TreatmentPlanService::createSupersedingPlan()` and `TreatmentPlanPolicy::createRevision()` per §15 Q4's
approved revision behavior, but this original endpoint table never listed a route to reach them. Added here
as a plain resource-style route returning `201` with the new (superseding) plan — not a scope expansion,
since the behavior itself was already designed and approved; only the route was missing.

**All mutation endpoints — plan-level and item-level alike — return the full updated `TreatmentPlan`**
(items eager-loaded via `TreatmentPlanResource`), including the item endpoints (`POST .../items`,
`PUT .../treatment-plan-items/{item}`, `.../complete`, `.../cancel`): an item change moves the plan's derived
cost roll-up, so returning the parent plan lets the frontend re-hydrate from one response instead of a second
`GET .../treatment-plans/{plan}` round-trip, consistent with the "no extra round-trip" reasoning already
stated above. Only `DELETE .../treatment-plan-items/{item}` returns `204 No Content` (soft delete, matching
every other module's delete convention).

**`GET /api/treatment-plans/{plan}` is a real single-resource endpoint** (unlike Dental Chart's entries,
which deliberately have none) — a plan detail view needs its own eager-loaded item list independent of the
patient-level list, and re-fetching the *whole patient's plan history* after every item mutation (Dental
Chart's accepted trade-off, `TECH_DEBT.md`) would be wasteful once a patient has several plans. Mutation
endpoints (`present`/`accept`/item `complete`, etc.) return the full updated plan (items eager-loaded), so
the frontend re-hydrates from one response — no extra round-trip needed, avoiding the exact gap already
logged as `TECH_DEBT.md` debt for both Appointments and Dental Chart.

Error shapes: standard `422`/`403`/`401`/`404` per `api-guidelines.md`. Invalid status transitions and
`TreatmentPlanItemLockedException` are plain `422`s (no new conflict shape needed — there's no
resource-vs-resource conflict here the way double-booking is for Appointments).

## 10. Permissions

| Action | admin | dentist | receptionist |
|---|---|---|---|
| View plans / items | ✅ | ✅ | ✅ (read-only) |
| Create plan / add items | ✅ | ✅ | ❌ |
| Edit plan / items (draft only) | ✅ | ✅ | ❌ |
| Present / Accept / Reject | ✅ | ✅ | ❌ |
| Start / Complete (plan or item) | ✅ | ✅ | ❌ |
| Cancel (plan or item) | ✅ | ✅ | ❌ |
| Delete (soft, data correction) | ✅ | ❌ | ❌ |

Matches the kickoff brief exactly: admin has full access; dentist can create/edit/transition ("approve
clinical changes" = every status transition, since each one represents a clinical-and-business decision
point); receptionist is read-only with zero write/transition access.

**No dentist-ownership/IDOR restriction** — any dentist can create/edit/transition any patient's plan,
inherited directly from Dental Chart's identical, already-approved precedent (no "assigned/primary dentist
per patient" concept exists in the system). Not re-litigated here; flagged only for awareness.

**Worth flagging, not deviating from**: in many real clinics, front-desk/treatment-coordinator staff record
a patient's verbal acceptance at checkout — the kickoff brief's explicit "receptionist: view only, no
clinical modification" is followed literally here rather than assumed away. If this proves too restrictive
in practice, the clean fix is a future dedicated permission tier (e.g., a treatment-coordinator role) rather
than loosening receptionist's access generally — flagged in §17, not decided now.

## 11. Frontend UX Design

**Patient Treatment Plans tab** (`PatientDetailView.vue`, alongside the existing Overview/Appointments/
Dental Chart tabs — same `Tabs`/`TabPanel` pattern, no new page-layout convention): hosts the **Plan List**
only — a compact card/table per plan (title or "Plan #N," status badge, dentist, item count, total estimated
cost, created date) with a "View" action.

**Plan Detail — recommended as a dedicated route** (`/patients/{id}/treatment-plans/{planId}`), **not**
crammed into the tab panel — an open decision flagged for confirmation (§16), following the exact precedent
already set for `AppointmentDetailView.vue` being its own route rather than an inline card, justified the
same way: a plan's detail (item list, phases, cost summary, status actions, history) is too much content for
a tab panel without feeling cramped, and a dedicated route gives it breathing room plus a shareable/
bookmarkable URL (useful for a treatment coordinator's own workflow later).

Plan Detail view contents:
- **Header**: title, status badge, dentist, created/presented/accepted dates, `superseded_by_plan_id` link
  ("Replaced by Plan #N") when present.
- **Item Timeline**: a chronological/phase-grouped list, visually modeled on the existing
  `AppointmentTimeline.vue` step pattern (Planned → Scheduled → Completed, with a Cancelled branch) rather
  than inventing a new visual language — each item shows procedure name, tooth (if any), cost, status, and
  (if linked) its appointment date or its diagnosis-entry reference.
- **Status Actions**: buttons reusing the existing `StatusActionButton.vue` component/pattern
  (Present/Accept/Reject/Start/Complete/Cancel), with `ConfirmDialog` for destructive/terminal transitions
  (Reject, Cancel) — matching every existing status-action screen's UX exactly.
- **Cost Summary panel**: a persistent panel (visually modeled on `ChartLegend.vue`'s "always visible, not a
  buried dialog" placement) showing total estimated cost and a per-phase subtotal breakdown. No
  payment/invoice tracking (Billing's future job) — an explicit "Estimate only, not an invoice" label to set
  correct patient expectations.
- **Add/Edit Item dialog**: procedure picker (from `dental_conditions` where `category = procedure`,
  reusing the existing catalog-fetch pattern from `dentalConditions.ts`), optional tooth/surface picker
  (reusing `lib/teeth.ts` from Dental Chart — no new tooth-picker component), quantity, cost (prefilled from
  `default_cost`), optional diagnosis-entry picker (a searchable list of the patient's `active`/`existing`
  chart findings), optional phase number.

**i18n**: new `treatmentPlans.*` namespace — full `en`/`ar`/`tr` parity required before sign-off, matching
the bar every prior module met (Dental Chart: 98/98 keys, confirmed).

**RTL**: no anatomical-mirroring risk here (unlike the odontogram) — normal bidi text/layout applies. One
specific, previously-encountered bug class to guard against explicitly: **currency/decimal figures must be
wrapped in a `dir="ltr"` isolation span**, the same fix already applied to Appointment Types' hex color
codes (`AppointmentTypesView.vue`) — a cost like "1,250.00" must never bidi-reorder under Arabic.

**Dark mode**: standard design-token compliance, no new tokens or one-off colors.

**Datetime handling**: every timestamp field (`presented_at`, `accepted_at`, etc.) goes exclusively through
`frontend/src/lib/date.ts` (`parseServerDateTime`, etc.) — no new date-handling code invented, per the
project's permanent datetime policy.

## 12. Security Considerations

- Every write endpoint has a dedicated `FormRequest` whose `authorize()` delegates to the Policy
  (`$this->user()->can(...)`), per `api-guidelines.md` — no exceptions.
- `TreatmentPlan`/`TreatmentPlanItem` both `use Auditable` from their first migration — this data is at
  least as sensitive as `DentalChartEntry` (arguably more so: it's a clinical recommendation *and* a
  financial figure, a genuinely more sensitive combination than either Patients or Dental Chart alone
  carries individually).
- No new authentication/authorization mechanism — reuses Sanctum SPA/cookie auth and the existing Policy
  layer verbatim.
- Cost fields are validated server-side as non-negative decimals (`unit_cost >= 0`, `quantity >= 1`) —
  never trust a client-computed total.
- No direct DB access from any consumer — Controller → Service → Resource only, satisfying the AI-layer
  vision's "never direct DB access" principle at zero extra cost, same as every prior module.

## 13. Performance & Scalability Considerations

- All queries are `patient_id`-scoped (plan list) or `treatment_plan_id`-scoped (item list within a plan
  detail fetch) — cost stays flat as total patient/plan count grows, the same reasoning class already
  established for Dental Chart's per-patient queries.
- Composite indexes `(patient_id, status)` and `(treatment_plan_id, status)` support the two most common
  filtered queries (e.g., "this patient's non-terminal plans," "this plan's still-open items") without a
  full table scan.
- The plan-level cost total is computed via a single `SUM(unit_cost * quantity) WHERE status != cancelled`
  query per plan-detail fetch — cheap at realistic scale (a plan realistically has single-digit-to-low-tens
  of items, not thousands).
- `GET /api/treatment-plans/{plan}` eager-loads `items.dentalCondition`, `items.diagnosisEntry`,
  `items.appointment`, `dentist`, `createdBy` in one request — avoiding the N+1-per-mutation gap already
  logged as `TECH_DEBT.md` debt for both Appointments and Dental Chart (§9 explains why this module doesn't
  repeat that trade-off).
- At true SaaS scale (thousands of clinics, each with thousands of patients), the only structural change
  needed is leading every existing index with a future `clinic_id` (§14) — no index or query shape needs to
  change beyond that prefix, the same conclusion Dental Chart's own SaaS-readiness checkpoint reached.

## 14. SaaS Readiness

**Current V1 assumptions** (matches every existing module — not a new gap this module introduces):
- No `tenant_id`/`clinic_id` anywhere in the new schema — single-organization, per `PROJECT_CONTEXT.md`.
- No `currency` column — one implicit currency for the whole (single) deployment. Reasonable for V1 since
  there is no Settings module yet to configure one, and no multi-clinic concept to need more than one.
- `dental_conditions.default_cost` is global to the one deployment's catalog — there is no per-clinic
  pricing concept yet (a real gap once multi-tenant, since different clinics charge different prices for
  the same procedure — see migration requirements below).

**Future multi-tenant migration impact:**
1. Add `clinic_id` to `treatment_plans`, `treatment_plan_items`, and `dental_conditions` (if not already
   added by an earlier module's migration), backfilled from a `clinics` table — same additive pattern
   already documented for Dental Chart.
2. Extend the composite indexes above to lead with `clinic_id`.
3. `dental_conditions.default_cost` becomes genuinely clinic-specific pricing once tenant-scoped — no schema
   change needed beyond the `clinic_id` column itself, since the column already lives on the right table.
4. Apply the same global Eloquent scope (`BelongsToTenant`) and policy-level clinic-membership check
   recommended in Dental Chart's SaaS Readiness section — this module needs no different mechanism.

**Subscription implications** (product/business consideration, not a technical requirement now): Treatment
Plans' richer features — multi-plan case-acceptance comparison, phase-based scheduling, case-acceptance-rate
analytics — are natural candidates for a paid tier boundary (per the AI-layer vision's planned Starter/
Professional/Business/Enterprise tiers) once subscription billing exists. Not built or gated now; flagged
for awareness during future pricing-tier design.

**Pricing catalog — approved with caution, logged as technical debt (Decision 5).** Reusing
`dental_conditions` (extended with `default_cost`/`description`) as the procedure/pricing catalog is a
**V1-only** decision, not a permanent one. A dedicated procedure catalog will eventually be needed to
support: clinic-specific pricing (the same procedure priced differently per clinic once multi-tenant),
regional pricing (currency/market variation), insurance pricing (contracted-rate schedules per payer),
dentist-level price overrides, and historical pricing (what a procedure cost as of a given date, independent
of any one treatment plan's own snapshot). None of that is built now — deliberately, per the review's "do
not expand scope now" instruction — and this paragraph plus the accompanying `TECH_DEBT.md` entry exist so
this reuse is never mistaken for a settled, permanent architectural decision by a future contributor.

**Reporting considerations**: expanded into concrete formulas in the dedicated §24 "Reporting Readiness"
section — summary: this module is the natural source for case-acceptance-rate, outstanding-treatment-value,
completed-treatment-value, and dentist-performance reporting, all efficient once `clinic_id`-prefixed
indexes exist (point 2 above); no additional schema is needed beyond the tenant column.

**Elevated sensitivity note**: unlike Dental Chart (clinically sensitive) or Patients (PII-sensitive), this
module additionally carries **pricing data**, which is commercially sensitive to the clinic itself (a
competitor or another tenant seeing one clinic's actual procedure pricing would be a real business harm, not
just a privacy one) — worth calling out explicitly as a reason tenant isolation for this module deserves
extra verification attention whenever multi-tenancy is actually built, beyond the standard patient-data
isolation bar.

## 15. Questions to Solve (explicit answers, per the kickoff brief)

**Q1 — Should a treatment plan snapshot diagnosis at creation time? — APPROVED as recommended.**
**Yes.** `treatment_plan_items` stores `tooth_number`/`surfaces`/`dental_condition_id` directly on the item
itself (not solely derived by joining through `diagnosis_entry_id`), and `diagnosis_entry_id` is kept purely
as a *traceability* reference. Reasoning: a treatment plan is a record of "what was recommended and why" at
a point in time — it must stay accurate and readable even if the referenced chart entry is later edited,
cancelled, or (in theory) its condition deactivated. This mirrors the same reasoning already used for why
`tooth_number` isn't a live FK to a `teeth` table in Dental Chart (§6 of that design) — validated data,
captured once, not a live join depended on for correctness.

**Q2 — Should prices be frozen when accepted? — APPROVED with an expanded scope (Decision 2).**
**Frozen earlier — at `presented`, not `accepted`.** Item cost fields (`unit_cost`, `quantity`) **plus a
full `procedure_name`/`procedure_description` snapshot** are editable only while the parent plan is `draft`;
the moment it becomes `presented` (shown to the patient as a real, concrete commercial offer — e.g., Implant
$1000, Crown $600), none of that offer's substance may silently change underneath the conversation, whether
the patient accepts immediately, takes two weeks to decide, or the plan sits presented indefinitely. The
clinic must honor the presented price (and the presented procedure name/description) unless a genuinely new
plan is created (§15 Q4) — freezing cost alone would still let a catalog rename retroactively alter what an
already-presented plan appears to say. Enforced via `TreatmentPlanItemLockedException`, mirroring Dental
Chart's exact `EntryLockedException` pattern (§5, §6, §8).

**Q3 — Can one patient have multiple plans?**
**Yes, unlimited, no artificial cap** — directly following Dentrix's confirmed multi-case pattern (§0),
which is genuine, expected case-acceptance UX (presenting "Option A vs. Option B"), not a workaround.
**Constraint**: accepting one `presented` plan automatically rejects every other `presented` plan for the
same patient (§5, §8) — so only one plan is ever the "live, being-acted-on" path at a time, while `draft`
plans (not yet real alternatives) are left untouched.

**Q4 — Can a plan have revisions/history?**
**Yes, but modeled as separate plans, not a versioning subsystem.** No `treatment_plan_revisions` table, no
version numbers. A materially changed plan is a **new** `TreatmentPlan` row (optionally created via a cheap
"Duplicate Plan" convenience action), with the old plan's `superseded_by_plan_id` pointing to it — giving a
readable lineage (Plan A → superseded by → Plan B) without a separate versioning entity. Combined with the
`Auditable` trait (field-level change history within one plan) and this lineage pointer (plan-level "why
does this new plan exist" history), the two together give full history without inventing a third mechanism.
Rejected alternative: a full plan-versioning table — real added complexity with no confirmed V1 need, the
same "don't build speculatively" principle already applied to Multi-Branch (`TECH_DEBT.md`).

## 16. Decisions Resolved at Design Review (2026-07-22)

Was "Open Decisions Needing Explicit Approval" — all six resolved. Kept as a consolidated index (each is
elaborated in-place at the section cited) rather than deleted, so the reasoning trail stays visible:

1. **One-way, read-only link to `DentalChartEntry`/`Appointment`, no auto-sync** (§7) — **APPROVED** exactly
   as recommended (Decision 1). Confirmed as "the correct SaaS architecture": Treatment Plans is a
   clinical-and-financial snapshot, not a live mirror — Dental Chart changes never rewrite historical plans,
   Treatment Plan changes never mutate the chart.
2. **Plan Detail as a dedicated route**, not a tab-panel-hosted view (§11) — **APPROVED** as recommended,
   mirroring `AppointmentDetailView.vue`'s precedent.
3. **Lightweight integer `phase` column now, no phase-naming/authoring UI** (§0, §6) — **APPROVED** as
   recommended; full CareStack-style phase management stays deferred to §17.
4. **Cost freeze at `presented`, not `accepted`** (§15 Q2) — **APPROVED**, and **expanded** (Decision 2):
   the freeze covers a full procedure name + description + cost snapshot, not cost alone (§6, §8).
5. **Receptionist stays strictly read-only, no accept/reject recording** (§10) — **APPROVED** exactly as
   specified; the front-desk-workflow caveat is kept on record in §17 for future revisiting, not acted on
   now.
6. **No `currency` column in V1** (§14) — **APPROVED** as recommended.

Additionally, **Decision 5 (pricing catalog)** — not one of the original six open items, raised fresh at
this review — resolved as: **reuse `dental_conditions` for V1 only**, explicitly logged as technical debt
(§14, `TECH_DEBT.md`) rather than building a dedicated procedure-catalog table now. A future dedicated
catalog will need to support clinic-specific pricing, regional pricing, insurance pricing, dentist
overrides, and historical pricing — none of that is in scope for this module; noted so it isn't mistaken
for a settled, permanent design later.

## 17. Potential Risks / Deferred Features / Future Improvements

**Risks:**
- **Two-place completion (§7)** — a dentist must remember to mark both the `TreatmentPlanItem` and the
  corresponding `DentalChartEntry` complete independently; mitigated by UX affordances (e.g., a "Also mark
  chart entry complete?" prompt shown at item-completion time, a UI convenience, not a backend coupling) —
  worth designing at implementation time, not a schema concern.
- **Freeze-then-drift risk**: if `dental_conditions.default_cost` changes after a plan is presented, already-
  presented items are unaffected (by design, §15 Q2) — but staff must understand that re-presenting a
  *cloned* plan (Q4) will pick up the *new* default price unless manually overridden. Worth a clear UI label
  ("Prefilled from current catalog price") at add-item time.

**Deferred (named explicitly, not silently dropped):**
- Phase renaming/authoring UI, treatment-coordinator role, patient e-signature/portal presentation,
  financing integration (§2, all from CareStack's fuller feature set, §0).
- "Add to Treatment Plan" quick-action directly from `ChartEntryDialog.vue`/`ChartEntryListTable.vue` (§3) —
  a real UX improvement, cheap to add once this module's API exists, not required for V1's own completion.
- Case-acceptance-rate / outstanding-treatment-value reporting (§14) — depends on a future Reports module.
- A future treatment-coordinator permission tier (§10) if receptionist-read-only proves too restrictive in
  practice.

## 18. Future AI Integration Points (vision only — not built now)

Per the standing AI-layer vision (event-driven readiness, API-first, integrations in a separate layer):
- Natural future domain events (not built now): `TreatmentPlanPresented`, `TreatmentPlanAccepted`,
  `TreatmentPlanRejected`, `TreatmentPlanItemCompleted` — could feed an AI Follow-up/Recall flow ("plan
  presented 2 weeks ago, no decision yet — send a reminder") or an AI Analytics Assistant answering
  "what's our case acceptance rate this quarter?" directly.
- A future AI Booking Agent (vision doc) completing a scheduling flow could plausibly read `accepted`,
  unscheduled items (`appointment_id IS NULL`) to suggest "you have an accepted crown on #26 not yet booked"
  — the API-first, structured-data shape here makes that a pure read integration, no reshape needed.
- Billing (future) reads `completed` items' `unit_cost`/`quantity` through the normal Service/Resource layer
  to generate invoice lines — no special integration path, since the data is already structured and costed.

## 19. Testing Strategy

**Backend (Feature + Unit, mirrors Dental Chart's test file structure exactly):**
- `TreatmentPlanTest` (Feature): full CRUD, every plan-level status transition (valid and invalid, incl. the
  `complete`-rejected-if-items-still-planned rule), the accept-auto-rejects-siblings rule, the
  cancel-cascades-to-items rule, `superseded_by_plan_id` linkage.
- `TreatmentPlanItemTest` (Feature): full CRUD, every item-level transition, cost-field lock enforcement
  once parent leaves `draft`, tooth/surface conditional validation, `category = procedure` enforcement.
- Unit: `TreatmentPlanStatusTest`/`TreatmentPlanItemStatusTest` (enum transition matrices),
  `TreatmentPlanTest`/`TreatmentPlanItemTest` (Models — relationships, `estimated_cost` accessor,
  scopes), `TreatmentPlanServiceTest` (transition logic, cost snapshot/freeze, sibling-auto-reject,
  cascade-cancel), Form Request tests (validation edge cases mirroring
  `StoreDentalChartEntryRequestTest`'s structure).

**Permissions tests:** `TreatmentPlanPolicyTest`/`TreatmentPlanItemPolicyTest` — explicit per-role ×
per-action matrix (admin/dentist/receptionist/guest), mirroring `DentalChartEntryPolicyTest`'s existing
pattern exactly.

**Frontend component tests (Vitest):** stores (`treatmentPlans.ts`, fetch/cache/mutate logic incl. the
single-resource `GET /treatment-plans/{plan}` re-hydration pattern, §9); components (`PlanList.vue`,
`PlanDetailView.vue`/route, `TreatmentPlanItemTimeline.vue`, `CostSummaryPanel.vue`, status action buttons) —
render states (loading/empty/error), role-based UI gating (receptionist sees no action buttons, matching
Dental Chart's `PatientDentalChartPanel.test.ts` precedent for verifying read-only rendering), i18n key
presence/parity.

**E2E (Playwright, permanent suite, CI-verified — mirrors Dental Chart's `dental-chart.spec.ts` precedent):**
- Golden path: create plan (draft) → add 2+ items across different teeth → present → accept → link an
  appointment to one item → complete both items → complete plan.
- Reject path: present → reject → verify plan is terminal, `rejected_at` set.
- Multi-plan scenario: two `presented` plans for the same patient → accept one → verify the other
  auto-transitions to `rejected`.
- Cancel-cascade scenario: accept a plan with 2 planned items → cancel the plan → verify both items are
  cancelled.
- Receptionist read-only verification: no create/edit/transition controls visible or reachable.
- RTL (Arabic) + dark-mode smoke check, including the currency `dir="ltr"` isolation fix (§11).

## 20. Architecture Review (against DentalSuite's own conventions)

Checked directly against `database-design.md`, `api-guidelines.md`, and the shipped Dental Chart/Appointments
modules — no deviation found beyond the ones explicitly flagged and justified above:

- ✅ UUID PKs, `HasUuids`, `SoftDeletes`, `Auditable` on both new tables.
- ✅ Enum-backed `status` columns (`TreatmentPlanStatus`, `TreatmentPlanItemStatus`), not free-text — matches
  `AppointmentStatus`/`DentalChartEntryStatus`'s exact pattern, including a `transitionsFrom()`/
  `canTransitionTo()` static-lookup-table shape, not a state-machine package.
- ✅ Thin controllers, business logic in a Service layer (`TreatmentPlanService` — recommended as **one**
  service handling both plan- and item-level operations, since item mutations frequently need to check
  parent-plan state, e.g. the cost-freeze rule — a deliberate consolidation, not an oversight, unlike Dental
  Chart's two-services-for-two-loosely-coupled-resources split).
- ✅ Policies for every model, Form Requests delegating `authorize()` to Policies, standard error shapes —
  no new envelope/conflict-response shape needed (§9).
- ✅ No new third-party package — reuses existing catalog (`dental_conditions`), existing tooth support
  (`App\Support\ToothChart`), existing datetime utilities, existing color-contrast helper.
- ✅ API-first, no direct DB access from any consumer, event-readiness/integration-layer-separation
  principles checked and satisfied (§18) — consistent with every prior module's review.
- ✅ Datetime policy: every timestamp field routes through `frontend/src/lib/date.ts` exclusively (§11) —
  no new date-handling code.
- ⚠️ **One deliberate deviation, explicitly justified**: unlike every prior list endpoint's "always
  paginated" guideline, both `GET .../treatment-plans` and (implicitly, nested) items are unpaginated —
  same documented, narrow exception class already established twice before (Dental Chart entries, Patient
  audit logs), not a new pattern.

## 21. Proposed Implementation Sequence

Mirrors the step-by-step, checkpoint-per-step pattern from Dental Chart §24/Appointments §20 (each step:
implement → real browser verification → report → wait for approval before the next):

1. **Database + Models + Enums** — `add_default_cost_to_dental_conditions_table` migration,
   `create_treatment_plans_table`, `create_treatment_plan_items_table` migrations; `TreatmentPlan`/
   `TreatmentPlanItem` models (`Auditable`, `HasUuids`, `SoftDeletes`); `TreatmentPlanStatus`/
   `TreatmentPlanItemStatus` enums; factories.
2. **Validation + Service + Policy layers** — `TreatmentPlanService` (create/update/present/accept/reject/
   start/complete/cancel at plan level; add/update/complete/cancel at item level; sibling-auto-reject;
   cascade-cancel; cost-freeze enforcement via `TreatmentPlanItemLockedException`), `TreatmentPlanPolicy`/
   `TreatmentPlanItemPolicy` (§10), Form Requests (tooth/surface conditional rules, `category = procedure`
   enforcement, cost validation).
3. **API layer** — controllers, resources (`TreatmentPlanResource` eager-loading items and their
   relations), routes, Feature tests.
4. **Seeder update** — set sensible `default_cost` values on the existing seeded `dental_conditions` catalog
   entries (procedures only).
5. **Frontend infrastructure** — types, services, stores (`treatmentPlans.ts`), i18n keys, routes
   (`/patients/:id/treatment-plans/:planId`).
6. **Plan List + Plan creation flow** — `PlanList.vue` (hosted in the Patient tab), `PlanFormDialog.vue`.
7. **Plan Detail view** (dedicated route) — item list/timeline, phase grouping, cost summary panel.
8. **Status action components** — Present/Accept/Reject/Start/Complete/Cancel buttons + confirm dialogs,
   reusing `StatusActionButton.vue`.
9. **Appointment + diagnosis linkage UI** — attach/detach an appointment to an item; optional diagnosis-entry
   picker in the Add/Edit Item dialog.
10. **Accessibility + i18n (ar/tr) + dark-mode pass + final QA + E2E suite**, mirroring Dental Chart's
    Step 10/11 structure exactly.

## 22. Audit Requirements (Expanded, per Design Review)

The base requirement is unchanged from every prior module: `TreatmentPlan` and `TreatmentPlanItem` both
`use Auditable` from their first migration (§12) — the existing generic `Auditable` trait/`AuditObserver`
mechanism already records a row in `audit_logs` on every create/update/delete, with a JSON diff of exactly
which fields changed. Because `status` is a tracked, ordinary column, **every plan/item status transition
already produces an audit row today** — a transition to `presented` shows up as a diff `{"status": ["draft",
"presented"]}` with the acting user and timestamp — without any extra code.

**What the review's "future audit events" list (created, presented, accepted, rejected, cancelled,
completed, revised) adds on top**: these are **named, semantic** business events, not just field diffs. The
generic mechanism captures the *fact* of each transition (correctly, today) but not a human-readable *label*
for it — reading "status changed from draft to presented" out of a diff is correct but less directly useful
for a compliance/business audit report than a row that plainly says "Presented by Dr. Smith on 2026-08-01."
Recommendation: **treat this as a Future Improvement, not a V1 requirement** — the underlying data already
exists (nothing is lost), and a semantic audit-event layer is better built once real domain events exist
(the same `TreatmentPlanPresented`/`TreatmentPlanAccepted`/etc. events already named as vision-only in §18)
than as a one-off addition to this module's `AuditObserver` usage. "Revised" specifically maps to the
creation of a new plan with a populated `superseded_by_plan_id` back-reference (§15 Q4) — already fully
reconstructable from existing columns, needing no new event to be meaningful today.

**Confirmed for V1, no gap**: every one of the seven named events (`created`, `presented`, `accepted`,
`rejected`, `cancelled`, `completed`, and `revised`-via-supersession) is reconstructable today from
`audit_logs` diffs plus `treatment_plans`' own timestamp columns (`presented_at`, `accepted_at`, etc.) and
`superseded_by_plan_id` — nothing is silently unaudited; only the *presentation* of that history as
named events (rather than raw diffs) is deferred.

## 23. Patient Communication Readiness (Future — not built now)

None of the following are V1 scope, but the schema above was shaped so that none of them require a
reshape when eventually built:

- **Printable treatment proposal**: `treatment_plan_items.procedure_name`/`procedure_description` (§6) exist
  specifically to give a print/PDF template real, patient-facing copy to render per line — a pure read/
  rendering concern layered on top of existing data, no new columns needed.
- **PDF export**: same reasoning — a rendering step over `GET /api/treatment-plans/{plan}`'s existing
  eager-loaded response (§9, §13); no backend data-model change required.
- **Patient approval workflow**: the `presented → accepted/rejected` transitions (§5) already model exactly
  this business event; a future digital version of the same workflow reuses the same two endpoints, just
  triggered by a patient-facing surface instead of staff, once one exists.
- **Digital signature**: would need new, purely additive columns when actually built (e.g., a nullable
  `signature_url`/`signed_at` on `treatment_plans`) and a real e-signature provider integration — per the
  standing AI-layer vision (§18), that integration must live in the separate integration layer, never wired
  directly into `TreatmentPlanService`'s core transition logic.

Not designed further than this now, per the review's explicit "do not build speculatively" instruction —
listed here only to confirm no part of the current design would need to be reshaped to add them later.

## 24. Reporting Readiness (concrete formulas, future Reports module)

Expands §14's brief mention into ready-to-implement definitions, so a future Reports module has a precise
spec rather than a vague pointer:

- **Accepted treatment value** = `SUM(unit_cost * quantity)` across all non-cancelled items belonging to
  plans with `status IN (accepted, in_progress, completed)` — "how much treatment has this patient/clinic
  committed to."
- **Completed treatment value** = `SUM(unit_cost * quantity)` across items with `status = completed`
  (regardless of parent plan status) — "how much has actually been delivered."
- **Outstanding treatment value** = *Accepted treatment value* − *Completed treatment value* — equivalently,
  `SUM(unit_cost * quantity)` across items with `status = planned` whose parent plan is `accepted` or
  `in_progress` — "how much accepted work is still owed to the patient/still to be billed."
- **Dentist performance** = grouped by `treatment_plans.dentist_id`: count of plans `presented`, count
  `accepted`, count `rejected`, **case acceptance rate** = `accepted / (accepted + rejected)`, and completed
  treatment value (above) attributed to that dentist — a direct, per-dentist case-acceptance and production
  scorecard.

All four are efficient at realistic scale today (patient-scoped or dentist-scoped aggregate queries over the
indexes in §6/§13) and remain efficient at full SaaS scale once every index is `clinic_id`-prefixed (§14) —
no schema beyond the tenant column is needed to support any of them when a Reports module is eventually
built.

---

**Design approved 2026-07-22 (see "Approval & Decision Log" above). Implementation proceeds one checkpointed
step at a time per §21: Step 1 (Database migrations + enums + models only — no controllers, no Form
Requests/Services/Policies, no routes, no frontend) is authorized now. Each subsequent step still requires
its own explicit approval, per the project's standing two-phase workflow, before it begins.**
