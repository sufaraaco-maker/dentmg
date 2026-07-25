# Treatment Plans Module

**Status: Production Ready ✅ — implementation complete on `feature/treatment-plans` (commit `0677128`,
2026-07-23). Not yet merged to `main`/tagged — see Completion section.**

This is the final module doc, produced at Final Review per the two-phase workflow, superseding
[`treatment-plans-design.md`](treatment-plans-design.md) (the approved design document) as the canonical
reference for this module. The design doc is kept for historical/decision-record purposes — its competitive
research (§0), full business-rule reasoning, and open-decision resolutions are not repeated here in full;
this doc reflects what actually shipped and points back to the design doc's section numbers where useful.

## Scope (V1)

A structured way to propose, cost, present, and track multi-procedure treatment recommendations for a
patient — bridging *diagnosis* (Dental Chart) and *action* (Appointments, and eventually Billing). Each
patient can have multiple treatment plans (concurrent alternatives and/or plans over time); each plan is a
container of costed **Treatment Plan Items** (procedure, optional tooth/surfaces, optional diagnosis
traceability link); plan-level status lifecycle (`draft` → `presented` → `accepted`/`rejected` →
`in_progress` → `completed`, plus `cancelled` from any non-terminal status) models the case-acceptance
business workflow; item-level lifecycle (`planned` → `completed`/`cancelled`) tracks execution. Costs are
snapshotted (procedure name + description + unit cost) at `presented`, frozen from then on. Reuses the
existing `dental_conditions` catalog (`category = procedure`) as the procedure/pricing source, extended with
`default_cost`/`description` columns.

**Explicitly out of scope for V1** (see Known Limitations below and `TECH_DEBT.md`): Billing/Payments
(invoicing, payment collection, insurance claims — this module only produces cost *estimates*), patient
e-signature/portal presentation, a treatment-coordinator role, financing/payment-plan options, automatic
Dental-Chart synchronization (no auto-create/complete/cancel of chart entries from plan items — a deliberate,
reviewed decision, not an oversight — see Key Architectural Decisions), phase renaming/authoring UI (only a
plain integer `phase` grouping ships), a patient-agnostic clinic-wide Treatment Plans list/reporting page.

## Architecture

**Backend** (Laravel 12, PHP 8.4): same Modular Monolith / Clean Architecture conventions as every other
module — thin Controllers, business logic in **one** `TreatmentPlanService` handling both plan- and
item-level operations (a deliberate consolidation, not a Dental-Chart-style two-service split, since item
mutations frequently need to check parent-plan state — e.g. the cost-freeze rule), Policies for
authorization, Form Requests for validation. Status transitions are fixed lookup tables inside
`TreatmentPlanStatus`/`TreatmentPlanItemStatus` (`transitionsFrom()`/`canTransitionTo()`), mirroring
`AppointmentStatus`/`DentalChartEntryStatus`'s exact pattern rather than introducing a state-machine
dependency.

**Frontend** (Vue 3 + TypeScript + PrimeVue + Tailwind): a dedicated `TreatmentPlanDetailView.vue` route
(`/patients/:id/treatment-plans/:planId`, matching `AppointmentDetailView.vue`'s precedent of "detail is too
much content for a tab panel"), with the Plan **List** hosted inline on `PatientDetailView.vue`'s own
"Treatment Plans" tab (`PatientTreatmentPlansPanel.vue`) alongside the existing Overview/Appointments/Dental
Chart tabs — no new page-layout convention introduced.

## Key Architectural Decisions

- **One-way, read-only reference from `TreatmentPlanItem` to `DentalChartEntry`/`Appointment`, no
  bidirectional sync** — adding/completing/cancelling a plan item never writes to `dental_chart_entries`,
  and Dental Chart has zero knowledge of Treatment Plans (no column added to that table). When work is
  performed, staff marks it complete in both places independently — mirrors the exact reasoning Dental Chart
  itself already used to reject automatic finding-to-procedure supersession (design doc §7).
- **Cost freeze at `presented`, not `accepted`, and expanded to a full snapshot** — `unit_cost`, `quantity`,
  `procedure_name`, and `procedure_description` are all editable only while the parent plan is `draft`;
  the moment a plan is shown to the patient as a concrete offer, none of that offer's substance may silently
  change underneath the conversation, even from a later catalog rename (design doc §5/§6/§8, Decision 2).
  Enforced via `TreatmentPlanItemLockedException`, mirroring Dental Chart's `EntryLockedException` pattern.
- **Multiple concurrent `presented` plans are intentional** (Dentrix-style "Option A vs. Option B"), with
  accepting one plan auto-rejecting every other `presented` plan for the same patient in the same
  transaction — the mechanism that keeps "at most one accepted/in_progress plan per patient" true without a
  separate DB constraint (design doc §5/§15 Q3).
- **Revisions are new rows, not a versioning subsystem** — a superseded plan's `superseded_by_plan_id` points
  to its replacement; combined with the existing generic `Auditable` trail, this gives full plan-lineage
  history without a new versioning table (design doc §15 Q4).
- **`estimated_cost` is never a stored column** — computed per item (`unit_cost * quantity`, a model
  accessor) and per plan (a service-layer `SUM` over non-cancelled items) — the same "don't store derivable
  state" principle already applied to `DentalChartEntry::dentition_type`, extended here to a financial total.
- **`dental_conditions` reused as the pricing catalog, V1 only** — extended with `default_cost`/
  `description` rather than building a dedicated procedure-pricing table now. Explicitly logged as technical
  debt (design doc §14, Decision 5, `TECH_DEBT.md`), not a permanent decision — see that entry for what a
  future dedicated catalog would need to support (clinic-specific/regional/insurance/dentist-override/
  historical pricing).
- **No dentist-ownership/IDOR restriction** — any dentist can create/edit/transition any patient's plan,
  inherited directly from Dental Chart's identical, already-approved precedent (no "assigned/primary dentist
  per patient" concept exists in the system).
- **Delete gated tighter than Cancel** — Delete (data-correction) is admin-only on both `TreatmentPlan` and
  `TreatmentPlanItem`; Cancel (a clinical/business action) is available to admin/dentist — the same
  Delete-vs-Cancel privilege split already established in Dental Chart.
- **Receptionist stays strictly read-only** — no accept/reject recording, even though in many real clinics
  front-desk staff records a patient's verbal acceptance at checkout. Followed literally per the kickoff
  brief rather than assumed away; flagged as a candidate for a future treatment-coordinator role if this
  proves too restrictive in practice (design doc §10/§17).

Full reasoning for every decision above is in `treatment-plans-design.md`'s "Approval & Decision Log" and the
section numbers cited.

## Backend

| Layer | Files |
|---|---|
| Migrations | `2026_07_22_000001_add_default_cost_and_description_to_dental_conditions_table.php`, `..._000002_create_treatment_plans_table.php`, `..._000003_create_treatment_plan_items_table.php` |
| Enums | `app/Enums/TreatmentPlanStatus.php` (`draft`, `presented`, `accepted`, `in_progress`, `completed`, `rejected`, `cancelled`), `app/Enums/TreatmentPlanItemStatus.php` (`planned`, `completed`, `cancelled`) |
| Models | `TreatmentPlan.php` (`Auditable`), `TreatmentPlanItem.php` (`Auditable`, `estimated_cost` accessor) — plus small additive extensions to `Patient.php` (relation) and `DentalCondition.php` (catalog fields/scope) |
| Form Requests | `TreatmentPlan/{Store,Update}TreatmentPlanRequest.php`, `CreateTreatmentPlanRevisionRequest.php`, `TreatmentPlanItem/{Store,Update}TreatmentPlanItemRequest.php` |
| Validation Rules | `app/Rules/BelongsToPatient.php`, `app/Rules/RequiresCostWhenNoDefaultPrice.php` |
| Services | `TreatmentPlanService.php` (plan- and item-level operations in one service, by design) |
| Policies | `TreatmentPlanPolicy.php`, `TreatmentPlanItemPolicy.php` |
| Exceptions | `app/Exceptions/TreatmentPlan/{InvalidTreatmentPlanStatusTransitionException,InvalidTreatmentPlanItemStatusTransitionException,TreatmentPlanHasOpenItemsException,TreatmentPlanItemLockedException}.php` |
| Controllers | `TreatmentPlanController.php`, `TreatmentPlanItemController.php` |
| Resources | `TreatmentPlanResource.php` (eager-loads items + relations), `TreatmentPlanItemResource.php`, extended `DentalConditionResource.php` |
| Factories | `TreatmentPlanFactory.php`, `TreatmentPlanItemFactory.php`, extended `DentalConditionFactory.php` |
| Seeders | `TreatmentPlanSeeder.php` (demo data), extended `DentalConditionSeeder.php` (seeded `default_cost` values), wired into `DatabaseSeeder.php` |
| Tests | Feature: `TreatmentPlanTest.php`, `TreatmentPlanItemTest.php`, extended `DentalConditionTest.php`. Unit: `Enums/{TreatmentPlanStatusTest,TreatmentPlanItemStatusTest}.php`, `Models/{TreatmentPlanTest,TreatmentPlanItemTest}.php`, `Policies/{TreatmentPlanPolicyTest,TreatmentPlanItemPolicyTest}.php`, `Requests/{StoreTreatmentPlanRequestTest,UpdateTreatmentPlanRequestTest,StoreTreatmentPlanItemRequestTest,UpdateTreatmentPlanItemRequestTest}.php`, `Services/TreatmentPlanServiceTest.php` |

## Database

**`dental_conditions`** (extended, additive-only — no reshape of the Dental Chart module): `+default_cost`
(decimal(10,2), nullable — no default means "dentist must enter a price manually," mirroring Open Dental's
"no fee" precedent), `+description` (text, nullable — patient-facing explanation, also the snapshot source
below).

**`treatment_plans`**: `id` (uuid), `patient_id` (FK), `dentist_id` (FK → `users`, the responsible/treating
dentist — app-level role check, not a DB constraint), `created_by_id` (FK → `users`, may differ from
`dentist_id`), `title` (nullable), `status` (`TreatmentPlanStatus` enum), `notes`, `presented_at`/
`accepted_at`/`rejected_at`/`started_at`/`completed_at`/`cancelled_at` (nullable, set only on their
respective transitions), `superseded_by_plan_id` (nullable, self-referencing FK — added in a separate
`Schema::table()` call since Postgres rejects a self-referencing FK declared inline in the same
`Schema::create()`), soft-deleted. Indexes: `(patient_id)`, `(patient_id, status)`, `(dentist_id)`.

**`treatment_plan_items`**: `id` (uuid), `treatment_plan_id` (FK), `dental_condition_id` (FK — kept live
purely for catalog/reporting joins, **never** used to resolve display name/price once the parent plan leaves
`draft`), `procedure_name` (snapshot, not nullable), `procedure_description` (snapshot, nullable),
`diagnosis_entry_id` (nullable FK → `dental_chart_entries`, read-only traceability), `tooth_number`
(nullable, unlike `dental_chart_entries` — not every procedure is tooth-specific), `surfaces` (json,
nullable), `quantity` (unsigned smallint, default 1), `unit_cost` (decimal(10,2), snapshotted from
`default_cost` or manually entered), `phase` (unsigned smallint, nullable, default 1 — lightweight grouping,
no phase-name table), `sequence` (unsigned smallint, nullable), `status` (`TreatmentPlanItemStatus` enum),
`appointment_id` (nullable FK → `appointments`), `notes`, `completed_at`/`cancelled_at` (nullable),
`created_by_id`/`updated_by_id` (FK → `users`), soft-deleted. Indexes: `(treatment_plan_id)`,
`(treatment_plan_id, status)`, `(dental_condition_id)`, `(appointment_id)`, `(diagnosis_entry_id)`.

`estimated_cost` is **not** a column on either table (see Key Architectural Decisions). Both tables get
`Auditable`, `HasUuids`, `SoftDeletes` — no exceptions.

## API

```
GET    /api/patients/{patient}/treatment-plans          (list, all statuses, not paginated — patient-scoped, same exception class as dental-chart-entries/audit-logs)
POST   /api/patients/{patient}/treatment-plans           (create, status=draft)
GET    /api/treatment-plans/{treatment_plan}              (single-resource detail — eager-loads items + relations)
PUT    /api/treatment-plans/{treatment_plan}
POST   /api/treatment-plans/{treatment_plan}/present
POST   /api/treatment-plans/{treatment_plan}/accept
POST   /api/treatment-plans/{treatment_plan}/reject
POST   /api/treatment-plans/{treatment_plan}/start
POST   /api/treatment-plans/{treatment_plan}/complete
POST   /api/treatment-plans/{treatment_plan}/cancel
POST   /api/treatment-plans/{treatment_plan}/revisions    (createSupersedingPlan)
DELETE /api/treatment-plans/{treatment_plan}               (soft delete — admin-only)

POST   /api/treatment-plans/{treatment_plan}/items         (add item)
PUT    /api/treatment-plan-items/{treatment_plan_item}
POST   /api/treatment-plan-items/{treatment_plan_item}/complete
POST   /api/treatment-plan-items/{treatment_plan_item}/cancel
DELETE /api/treatment-plan-items/{treatment_plan_item}      (soft delete — admin-only)
```

Unlike Dental Chart, `GET /api/treatment-plans/{plan}` **is** a real single-resource endpoint (design doc
§9) — a patient can accumulate several plans over time, so re-fetching the whole patient-level list after
every item mutation would be wasteful. All mutation endpoints (plan- and item-level alike) return the full
updated `TreatmentPlan` with items eager-loaded, so the frontend re-hydrates from one response with no extra
round-trip — the same gap already logged as `TECH_DEBT.md` debt for Appointments/Dental Chart is not
repeated here.

## Permissions

| Action | admin | dentist | receptionist |
|---|---|---|---|
| View plans / items | ✅ | ✅ | ✅ (read-only) |
| Create plan / add items | ✅ | ✅ | ❌ |
| Edit plan / items (draft only) | ✅ | ✅ | ❌ |
| Present / Accept / Reject | ✅ | ✅ | ❌ |
| Start / Complete (plan or item) | ✅ | ✅ | ❌ |
| Cancel (plan or item) | ✅ | ✅ | ❌ |
| Create revision | ✅ | ✅ | ❌ |
| Delete (soft, data correction) | ✅ | ❌ | ❌ |

No clinic-membership check exists in either policy — deliberate, matching every other module, since V1 has
no tenant/clinic concept to scope against (see SaaS Readiness below).

## Frontend

| Layer | Files |
|---|---|
| Types | `src/types/treatmentPlan.ts` |
| Services | `src/services/treatmentPlans/{treatmentPlansApi,treatmentPlanItemsApi}.ts` |
| Stores | `src/stores/treatmentPlans.ts` |
| Views | `TreatmentPlanDetailView.vue` (dedicated route) |
| Components | `src/components/treatmentPlans/` — `PatientTreatmentPlansPanel.vue` (patient-tab list host), `TreatmentPlanListTable.vue`, `CreateTreatmentPlanDialog.vue`, `TreatmentPlanStatusChip.vue`, `TreatmentPlanActionsBar.vue` (status-action buttons, reuses `StatusActionButton.vue`), `TreatmentPlanItemsTable.vue`, `TreatmentPlanItemDialog.vue` (add/edit item) |
| Router | `treatment-plan-detail` route: `patients/:id/treatment-plans/:planId` |
| PatientDetailView.vue | New `treatmentPlans` tab (4th tab, alongside Overview/Appointments/Dental Chart), hosted by `PatientTreatmentPlansPanel.vue` |
| i18n | `treatmentPlans.*` namespace — **84/84 keys, en/ar/tr parity confirmed** |
| Datetime handling | Every timestamp field routes through `frontend/src/lib/date.ts` exclusively, per the project's datetime policy — no new date-handling code |

## Testing & Verification

- Backend: **505/505 tests passing** (full suite — includes 2 extended/2 new Feature test files and 9 Unit
  test files for this module: Enums, Models, Policies, Requests, Services), matching the design doc's
  Testing Strategy (§19) file-for-file.
- Frontend: **541/541 Vitest tests passing** (full suite), covering stores, services, and every new
  component (render states, role-based UI gating — receptionist sees no action buttons, i18n key presence).
- Manual real-browser verification performed per the two-phase workflow's mandatory UI-verification step at
  each implementation checkpoint.
- **No permanent Playwright E2E suite exists yet for this module** — confirmed directly (no
  `frontend/e2e/treatment-plans.spec.ts` or equivalent, unlike `appointments.spec.ts`/`dental-chart.spec.ts`).
  The design doc's §19 specified one (golden path create→present→accept→link-appointment→complete, reject
  path, multi-plan sibling-auto-reject scenario, cancel-cascade scenario, receptionist read-only check,
  RTL/dark-mode smoke check) — not yet implemented. See Known Limitations.

## Known Limitations / Deferred (non-blocking)

Full detail and revisit conditions live in `TECH_DEBT.md`; summarized here for this module:

- **No permanent E2E suite yet** (new item, logged in `TECH_DEBT.md` as part of this documentation pass) —
  every other production-ready module (Appointments, Dental Chart) has a CI-verified Playwright spec; this
  one currently relies on backend/frontend unit+feature tests and ad hoc manual verification only. Does not
  block V1 use, but is a real gap relative to this project's own established bar for "Production Ready."
- **`dental_conditions` reused as the pricing catalog (V1 only)** — see Key Architectural Decisions; a
  dedicated procedure-pricing catalog is needed once multi-tenant/insurance/regional pricing is a real
  requirement.
- **Sidebar "Treatment Plans" nav item stays `comingSoon`** — the module itself is implemented and reachable
  (via the Patient tab), but there's no patient-agnostic clinic-wide list/reporting page for the top-level
  nav entry to point to yet.
- **"Add to Treatment Plan" quick action from `ChartEntryDialog.vue`/`ChartEntryListTable.vue`** — a
  cheap, real UX improvement named in the design doc (§17) but not built for V1; today a plan item's
  optional `diagnosis_entry_id` link must be set from the Treatment Plan Item dialog itself.
- **Two-place completion** — a dentist must remember to mark both the `TreatmentPlanItem` and the
  corresponding `DentalChartEntry` complete independently (design doc §7/§17, an explicit, reviewed
  trade-off, not an oversight).
- Phase renaming/authoring UI, treatment-coordinator role, patient e-signature/portal presentation,
  financing integration, case-acceptance-rate/outstanding-treatment-value reporting UI (formulas already
  speced in design doc §24, no Reports module yet to render them) — all deferred per the design doc's §17,
  none block V1 production use.

None of the above block production use of the module as scoped.

## SaaS Readiness

Reviewed at design time (design doc §14), not re-litigated here — summary: DentalSuite V1 is
single-organization, and this module introduces no new tenancy gap beyond what already exists system-wide
(no `clinic_id`/`tenant_id` anywhere, policies authorize on role only). One point specific to this module
worth restating: it carries **pricing data**, which is commercially sensitive to the clinic itself (not just
a privacy concern) — worth extra verification attention whenever multi-tenancy is actually built, beyond the
standard patient-data isolation bar every other module needs. Full migration-path detail (add `clinic_id`,
extend indexes, tenant-scoping trait, policy clinic-membership check) mirrors Dental Chart's already-documented
path (`docs/modules/dental-chart.md`'s SaaS Readiness section) — no new mechanism needed for this module.

## Completion

Migration, Model, Validation, Service, Policy, API, Vue Pages, Tests, Documentation — all present.
505/505 backend tests, 541/541 frontend tests. **Verdict: Production Ready**, with one open item relative to
this project's established bar: no permanent E2E suite yet (see Known Limitations/Testing above).

**Not yet merged to `main` or tagged** — per the project's standing git workflow (`feature/<module>` →
CI-verified merge to `main` → `v<version>-<module-name>` tag once Final Review closes), this module currently
lives only on `feature/treatment-plans` (commit `0677128`). Merging and tagging are separate, explicit steps
this doc does not perform on its own — flagged for a deliberate decision alongside or after this
documentation pass, not assumed.
