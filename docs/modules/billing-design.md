# Billing Module — Design Proposal (Design Approved with Refinements, 2026-07-23)

**Status: Design direction approved, with five required refinements incorporated below (Approval & Decision
Log). No migrations, models, services, or frontend code have been written. Per the two-phase workflow, this
refined document is the deliverable for a final review pass — Phase 2 implementation requires its own
separate, explicit go-ahead, not yet given.**

Grounded in the current, shipped state of the codebase (`Patient` — specifically its `sequence_number`/
`patient_code` generation pattern, `TreatmentPlan`/`TreatmentPlanItem` — specifically its snapshot-and-freeze
pattern, `Auditable` trait, `docs/database-design.md`, `docs/api-guidelines.md`, `docs/decisions.md`) —
verified directly, not assumed.

---

## Approval & Decision Log (2026-07-23)

Approved with five refinements, each elaborated in-place at the section cited below (this section is a
quick-reference index, not a duplicate — mirrors the pattern already used in
`treatment-plans-design.md`'s own Decision Log):

1. **Invoice lifecycle V1 — `draft` → `issued` → `void` only** (§5) — **CONFIRMED** exactly as originally
   proposed. No `paid`/`partially_paid` status until the Payments module exists. The "combined release"
   alternative originally flagged alongside this recommendation (§15) is explicitly **rejected** — Billing
   ships and closes on its own, per the project's standing "one module fully complete before the next
   begins" development strategy.
2. **Issued-invoice immutability, sharpened** (§5, §8) — **CONFIRMED**: draft invoices stay fully editable;
   the instant an invoice is `issued`, every financial value on every item becomes a frozen snapshot with
   no silent-mutation path anywhere in the API. Corrections happen through `void` (built now) or a future
   `adjustment`-kind mechanism (named and reserved, not built — §6, §16) — never by editing an issued
   invoice or its items in place.
3. **Invoice numbering** (§6) — **CONFIRMED**: a human-readable `invoice_number`, derived from a dedicated
   `sequence_number` column, fully independent of the `id` UUID primary key — reusing
   `PatientService::nextSequenceNumber()`'s exact concurrency-safe pattern verbatim. Designed
   clinic-scoped-ready from the start (§14) even though V1 has one global sequence.
4. **Module boundaries stated explicitly** (new §19) — **CONFIRMED**: Treatment Plans produce billable
   *candidates* (completed items, not yet a financial record); Invoices are the actual financial
   obligation; Payments (future) apply against invoices. Previously implied by the schema/relationships;
   now stated as its own prominent section rather than left for the reader to infer.
5. **Relationship diagram added** (new §20) — **CONFIRMED**: `TreatmentPlanItem → InvoiceItem (optional,
   traceability-only) → Payment (future)` shown as its own explicit linear chain, distinct from and
   complementing the full entity-relationship diagram already in §7.

The two remaining items originally listed as open decisions (§15) — the admin+receptionist-write/
dentist-read-only permissions split, and the bare-nullable-FK, no-reverse-column coupling mechanics — are
**both approved exactly as recommended, no changes requested.**

---

## 0. Competitive Research (required before any design, per standing product philosophy)

| Product | What it does | Taken / rejected |
|---|---|---|
| **Open Dental** | **Ledger-centric**: the Account Module's Ledger is the single source of truth — a chronological, date-grouped list of every procedure, payment, adjustment, and claim. "Invoice" is not a persistent core entity; it's a document *generated on demand* from selected production (procedures + adjustments + pay plan charges) for a date range, then saved as a PDF. Only `Complete`-status procedures ever appear in the ledger — treatment-planned (`TP`) work is invisible to billing until completed. Void/refund operate on *payments*, not invoices: voiding a card transaction reverses it same-day if undeposited; a Refund creates a new negative payment record referencing the original. | **Taken**: "only completed production is billable" confirms the exact seam already designed into Treatment Plans (`treatment_plan_items.status = completed`). **Taken**: refund-as-a-payment-reversal-record, not an invoice-mutation — reinforces keeping refunds entirely out of this module's scope (Payments' job, sequenced next). **Rejected**: making the ledger (not a distinct `Invoice` entity) the actual source of truth — a flat, date-grouped transaction log is a real architectural style, but it breaks from every other DentalSuite module's pattern of a named, status-driven entity (`Appointment`, `DentalChartEntry`, `TreatmentPlan`) with its own lifecycle. Adopting it here would be the first genuinely different data-modeling style in the codebase for no clear benefit. |
| **Dentrix (Ascend)** | Same ledger-centric philosophy as Open Dental: the Ledger shows service charges, applied payments/adjustments, and running balance; a "Billing Statement" is a generated, date-ranged document, not a locked persistent object. Voiding/refunding a payment is done directly against the ledger entry, including partial refunds without cancelling the whole original payment. | **Taken**: partial-refund-without-cancelling-the-original as the right shape for *payments* (again, out of scope here, but worth carrying forward into the Payments module's own design later). Confirms the ledger-centric pattern is an industry norm, not just an Open Dental idiosyncrasy — but the rejection reasoning above still applies. |
| **CareStack** | More modern middle ground: funds post directly into a patient ledger (auto-updating totals, "zero room for data-entry errors" per their own materials), but payments can be **held unapplied** or applied to a specific coded treatment — i.e., a payment doesn't have to resolve against a named "invoice," it can float against the patient balance generally. Statements are generated by configurable criteria, not stored as a locked entity either. | **Taken**: "a payment can be held unapplied, not forced onto one invoice" is a real, sensible flexibility — worth keeping in mind for the *future* Payments module's design (a payment should be able to apply against a specific invoice **or** sit as an unapplied credit on the patient account), even though V1 Billing itself has no payment-application logic yet. **Not directly applicable to Billing's own scope**, since Billing here precedes Payments. |
| **Curve Dental** | The clear outlier and the most directly relevant precedent: **invoice-centric**, not ledger-centric. "Curve's invoice-based ledger offers a clear, itemized view of payments, adjustments, and balances" — invoices are real, itemized, patient-facing documents generated immediately after treatment, with line-item-level adjustments and discounts, and insurance payments/EOBs matched line-by-line against specific invoice lines. Statements are fully customizable per line item (payment breakdowns by line, adjustments by line, even voided transactions shown transparently). | **Taken directly, as the primary architectural model**: a first-class `Invoice`/`InvoiceItem` entity pair, itemized, with each line individually traceable — this is the one competitor whose data model actually looks like `TreatmentPlan`/`TreatmentPlanItem`'s existing shape (a named entity with a lifecycle, containing snapshotted line items), so it's the natural fit for DentalSuite's established modeling convention. Curve's "voided transactions shown transparently, not deleted" also directly informs the void-vs-delete decision below. |
| **Industry-wide 2026 trend** (confirmed across the above plus general dental billing literature) | Two real architectural schools exist: ledger-as-source-of-truth (Open Dental, Dentrix — mature, been-around-decades pattern) vs. invoice-as-first-class-entity (Curve — newer, cloud-native pattern). Neither is "wrong"; they're different philosophies for the same underlying data. | Confirms this is a genuine, deliberate architectural choice to make explicitly (§15, Decision 1), not a default to skip past. |

**What DentalSuite does differently / better, not just clones**: every ledger-centric competitor (Open Dental,
Dentrix) treats "invoice" as a transient, regenerable report over an authoritative flat ledger — which
works well for their decades-old desktop-first architectures but doesn't fit an API-first SaaS platform
well (a "report" isn't a stable resource a future mobile app, patient portal, or AI Analytics Assistant can
reference by ID). Curve's invoice-centric model is closer to right, but this design goes further than any
of the four in one respect: it **generalizes the line-item source** so an invoice line can originate from a
Treatment Plan item, a direct/manual charge, or (later, additively) a product/service sale — without ever
requiring the invoice to "belong to" a single treatment plan the way none of the four competitors' models
cleanly separate "what was recommended" from "what was billed." This directly satisfies this module's
explicit design constraint: never permanently and tightly couple `invoice_items` to `treatment_plan_items`.

Sources: [Open Dental — Account Module](https://www.opendental.com/manual/account.html), [Open Dental —
Invoice](https://www.opendental.com/manual/invoice.html), [Open Dental — Treatment Plan
Module](https://opendental.com/manual/treatmentplan.html), [Open Dental —
Refund](https://www.opendental.com/manual/refunds.html), [Open Dental — XCharge Void
Payment](https://www.opendental.com/manual/xchargevoid.html), [Dentrix Ascend — Ledger
Overview](https://learn.dentrixascend.com/courses/financial-essentials-for-teams/lessons/ledger/topic/ledger-overview/),
[Dentrix Ascend — Generating billing
statements](https://support.dentrixascend.com/hc/en-us/articles/229957127), [CareStack — Revenue Cycle
Management](https://carestack.com/dental-software/features/revenue-cycle-management), [Curve Dental — Revised
Account Statements](https://www.curvedental.com/blog/revised-account-statements-for-everyone), [Curve Dental —
Dental Billing Software](https://www.curvedental.com/dental-billing-software).

---

## 1. Module Goal / Purpose

Turn completed clinical work (and any other billable activity) into a real, itemized financial document —
the `Invoice` — that a patient can be shown/handed/emailed, and that establishes what is owed. This module
deliberately stops at "what is owed," not "what has been collected" — recording payments against an invoice
is the next module's job (Payments), sequenced immediately after per the approved roadmap.

## 2. Scope (V1)

**In scope:**
- A patient can have multiple invoices over time (one per visit, one covering several visits, or ad hoc).
- Each invoice is a container of **Invoice Items** — individual billable lines, each either a `charge`
  (positive), a `discount` (subtracted), or a `tax` line (positive) — see §6.
- An invoice-level status lifecycle: `draft → issued → void`, plus soft-delete as a separate,
  admin-only data-correction action (§5, §8).
- A convenience "Generate from completed treatment plan items" action that pre-populates charge lines from a
  patient's not-yet-invoiced, `completed` `TreatmentPlanItem`s — but the resulting invoice is a fully
  independent, freely-editable-while-draft document, not a locked mirror of the plan.
- Fully manual/direct invoices with no treatment-plan origin at all (walk-in charges, product sales once
  Inventory exists, adjustment-only corrections).
- A snapshot-and-freeze pattern identical to Treatment Plans': every item's `description`/`unit_amount` is
  captured at creation and frozen once the invoice is `issued`.
- A minimal, additive `billing_settings` table (tax rate, currency code, invoice numbering prefix) — see §6 —
  scoped narrowly to what this module needs, not a general Settings module.
- Sequential, human-readable invoice numbers (`INV-000001`), reusing `PatientService`'s existing
  concurrency-safe numbering pattern verbatim.
- Full audit trail via the existing `Auditable` trait — no new mechanism.

**Explicitly out of scope for V1** (per this task's explicit constraints and the roadmap's own module
boundaries):
- **Payments** — no payment recording, no "paid"/"partially paid" status, no refunds. `Invoice` exposes a
  placeholder `amountPaid`/`balanceDue` accessor that always reflects "nothing paid yet" in V1 (§5, §15
  Decision 2) — a real, if incomplete-feeling, gap that closes the moment the next module ships, by
  design, not by accident.
- **Insurance claims / EOB processing / write-off adjustments** — CareStack's and Curve's "adjustment"
  concepts are fundamentally insurance-driven; nothing insurance-related exists in DentalSuite yet. A
  generic `adjustment` line kind is deferred (§15 Decision 3) rather than built without a real consumer.
- **Multi-currency / multi-tax-jurisdiction engine** — one flat, optional tax rate per deployment (§6, §14).
  No per-line tax classes, no tax-exempt-item logic, no compounding/cascading tax rules.
- **Percentage-based discount calculation** — V1 discount lines are staff-entered fixed currency amounts
  only; percentage-off is a pure frontend convenience (compute, then fill the fixed amount) if ever added,
  never a backend concept (§6, §15 Decision 4).
- **Credit notes / partial invoice correction** — the V1 correction path for an issued invoice with an error
  is `void` + create a new corrected invoice, not a dedicated credit-note entity (§8, §17).
- **Products/Inventory/Services catalog integration** — the schema reserves room for this additively (§7,
  §14) but no such catalog exists yet to integrate with.
- **Patient-facing online invoice viewing/e-payment** — belongs with a future Patient Portal, per the
  standing AI-layer vision's "integrations in a separate layer" principle.
- **PDF generation/printing** — a pure rendering concern over this module's existing data (mirrors Treatment
  Plans §23's identical reasoning); flagged as a near-term, cheap follow-up, not built in this pass.

## 3. Full Workflow

```
Patient
  → Treatment Plan Items (existing — status = completed)   [optional origin]
  → Invoice                                                  (this module — draft, editable)
  → Invoice Items                                            (this module — charge/discount/tax lines)
  → Invoice issued                                           (this module — frozen, numbered, patient-facing)
  → Payments (future)                                        (out of scope — will read completed/issued
                                                                invoices and record collections against them)
```

**Primary flow — billing completed treatment (admin, receptionist):**
1. From a patient's account (or their Treatment Plans tab), start a new invoice — optionally pre-seeded via
   "Add completed items" (a picker listing the patient's `completed` `TreatmentPlanItem`s not already on a
   non-void invoice — derived by querying `invoice_items`, not a stored flag, §7). Invoice is `draft` —
   freely editable.
2. Add/remove/edit charge lines (manually or from the picker), add an optional discount line (fixed amount,
   staff-justified via `notes`), add an optional tax line (prefilled from `billing_settings.tax_rate` against
   the charge subtotal, editable/removable per invoice).
3. Review the computed total (never stored — §6), then transition to `issued` — this **freezes every
   item's description/amount** (§8) and assigns the invoice's permanent `invoice_number`.
4. Hand/print/email the invoice to the patient (rendering only in V1 — no delivery mechanism built, §2).

**Secondary flow — direct/manual charge:** create an invoice with zero treatment-plan origin, add one or more
manual charge lines with a freeform description and amount (e.g., "Teeth whitening kit — retail," "Missed
appointment fee") — fully supported, no treatment plan involvement required anywhere (§2, §7).

**Correction flow — invoice issued in error:** `void` it (admin or receptionist, §10) — preserves the row,
its `invoice_number`, and its full item snapshot for audit; excluded from any future outstanding-balance
total. Create a new, corrected invoice separately. No credit-note entity in V1 (§2, §17).

## 4. Core Concepts (definitions)

- **Invoice**: a costed, patient-facing financial document — the unit of billing. Belongs to one `Patient`;
  has a `created_by_id` (who authored it) and a permanent, sequential `invoice_number` assigned at `issued`.
- **Invoice Item**: one line within an invoice — a `charge`, `discount`, or `tax` — with its own snapshotted
  description and amount. The unit of what a patient is actually shown as owing.
- **Total**: `SUM(charge lines) + SUM(tax lines) - SUM(discount lines)`, all non-void/non-cancelled — a
  service-layer query aggregate, **never** a stored/cached column, extending the same principle already
  applied to `TreatmentPlan`'s cost roll-up and `DentalChartEntry.dentition_type` to a top-level financial
  total here too (§6).
- **Balance due** (V1 placeholder): equals the total, always — `amountPaid` has no data source yet (no
  `payments` table exists), so it's a `0`-returning accessor today, extended with zero reshape once Payments
  ships (§15 Decision 2).

## 5. Status Lifecycle

### `InvoiceStatus`

```
draft ──────► issued ──────► void
```

| From | Allowed to | Trigger | Side effects |
|---|---|---|---|
| `draft` | `issued` | staff action | `issued`: freezes all item snapshots, assigns `sequence_number`/`invoice_number`, sets `issued_at` |
| `issued` | `void` | staff action (correction) | `void`: sets `voided_at`; excluded from outstanding-balance totals |
| `void` | — (terminal) | — | — |

**Correction made during Step 1 implementation (2026-07-23)**: this table and diagram originally allowed
`draft → void` directly, which contradicted §8's explicit business rule ("void is available only from
`issued`... a draft invoice is simply deleted instead"). §8 was always the intended behavior — a `draft`
invoice never received a real `sequence_number`, so there is nothing for `void` to preserve; abandoning one
is a plain (soft) delete, not a status transition. Fixed here to match; `InvoiceStatus::transitionsFrom()`
implements the corrected version (`Draft → [Issued]`, `Issued → [Void]`, `Void → []`).

**Confirmed: no `paid`/`partially_paid` status in V1** (Decision Log item 1, §15 Decision 2) — `draft`/
`issued`/`void` is the complete V1 status set. Once the Payments module exists, `Invoice` gains a computed
`paymentStatus` accessor (derived from a `SUM` over its `payments`, mirroring exactly how
`TreatmentPlanItem.estimated_cost` is computed, not stored) — no column changes needed on `invoices` itself
when that day comes, only a new relation and accessor.

Item-level: **no separate status enum for `InvoiceItem`** — an item's only states are "on a draft invoice
(editable)" and "on an issued/void invoice (frozen)," which is fully derived from its parent invoice's status
(§8), not a stored column — the same "don't store derivable state" principle applied once more.

## 6. Database Design

### `billing_settings` (new, minimal, additive — not a general Settings module)

A single-implicit-row table in V1 (see §14 for why it's still designed clinic-scoped-ready, not a true
global singleton).

| Column | Type | Notes |
|---|---|---|
| `id` | uuid | PK |
| `currency_code` | string(3), default `'USD'` | ISO 4217 code — display-only in V1, no conversion logic |
| `tax_rate` | decimal(5,2), nullable | Percentage (e.g. `5.00` = 5%), used only to **prefill** a new invoice's tax line — never a live-applied rule (§2) |
| `invoice_number_prefix` | string, default `'INV'` | |
| `created_at` / `updated_at` | timestamp | |

### `invoices` (new table)

| Column | Type | Notes |
|---|---|---|
| `id` | uuid | PK |
| `patient_id` | uuid, FK → `patients`, not nullable | Immutable after creation, mirrors every prior module |
| `created_by_id` | uuid, FK → `users`, not nullable | |
| `sequence_number` | unsigned integer, nullable until issued | Set only at `issued` (not at `draft` creation — a draft invoice that's later discarded should never burn a real invoice number); generated via the exact `lockForUpdate()`/`orderByDesc()` pattern already used by `PatientService::nextSequenceNumber()` |
| `status` | string, cast to `InvoiceStatus` enum | §5 |
| `notes` | text, nullable | |
| `issue_date` | date, nullable | Defaults to `issued_at`'s date but staff-editable (e.g., backdating to the actual treatment date) |
| `due_date` | date, nullable | |
| `issued_at` / `voided_at` | timestamp, nullable | Set only on their respective transitions |
| `deleted_at` | timestamp, nullable | Soft delete (data-correction, distinct from `void` — §8) |
| `created_at` / `updated_at` | timestamp | |

Indexes: `(patient_id)`, `(patient_id, status)`, unique index on `sequence_number` (nullable-safe — Postgres
allows multiple `NULL`s under a unique index).

**No `treatment_plan_id` column** — by design (§2, §7): an invoice is never structurally owned by one
treatment plan.

### `invoice_items` (new table)

| Column | Type | Notes |
|---|---|---|
| `id` | uuid | PK |
| `invoice_id` | uuid, FK → `invoices`, not nullable | |
| `treatment_plan_item_id` | uuid, **nullable**, FK → `treatment_plan_items`, `nullOnDelete` | Traceability only — **never** used to resolve description/amount once written (Decision mirrors Treatment Plans' own Decision 2 exactly); `null` for manual/direct charges, and for `discount`/`tax` lines always |
| `kind` | string, cast to `InvoiceItemKind` enum (`charge`, `discount`, `tax`) | §4 — determines sign in the total formula, not the raw stored value (amounts are always stored positive, §6 below) |
| `description` | string, not nullable | Snapshot — from `treatment_plan_item.procedure_name` when sourced, freeform when manual, or a fixed label (e.g. "Tax", "Discount") for tax/discount lines |
| `quantity` | unsigned smallint, default 1 | Mirrors `treatment_plan_items.quantity`; realistically always `1` for discount/tax lines |
| `unit_amount` | decimal(10,2), not nullable | Always stored as a **positive** number regardless of `kind` — a discount line's effect (subtraction) comes from `kind`, not a negative value, avoiding double-negative bugs in the total formula (§4) |
| `sequence` | unsigned smallint, nullable | Manual display ordering, mirrors `treatment_plan_items.sequence` |
| `notes` | text, nullable | e.g., justification for a discount |
| `created_by_id` | uuid, FK → `users`, not nullable | |
| `deleted_at` | timestamp, nullable | Soft delete — only meaningful while the parent invoice is still `draft` (§8) |
| `created_at` / `updated_at` | timestamp | |

Indexes: `(invoice_id)`, `(treatment_plan_item_id)`.

**`amount` (the line's contribution to the total) is deliberately not a column** — computed as `unit_amount *
quantity` (accessor), same principle as `TreatmentPlanItem.estimated_cost`. The **invoice total** is a
service-layer `SUM`, per §4 — never cached.

Both tables get `Auditable`, `HasUuids`, `SoftDeletes` — no exceptions, matching every existing financial/
clinical table's convention.

## 7. Table Relationships — and the Treatment-Plan-Coupling Decision

```
Patient (existing)
  └─┬─ hasMany ─→ Invoice
    │               ├─ belongsTo → User (created_by_id)
    │               └─┬─ hasMany ─→ InvoiceItem
    │                   ├─ belongsTo → TreatmentPlanItem (treatment_plan_item_id, nullable)
    │                   └─ belongsTo → User (created_by_id)
```

**The explicit design constraint this task named — do not tightly couple `invoice_items` to
`treatment_plan_items` — is satisfied structurally, not just by convention**:

- The FK is **nullable** — an `InvoiceItem` is fully self-contained (its own `description`/`unit_amount`
  snapshot) whether or not a source is set. Deleting a `TreatmentPlanItem` never breaks an existing invoice
  line (`nullOnDelete`).
- There is **no reverse column** on `treatment_plan_items` (no `invoiced_at`, no `invoice_item_id`) — Billing
  does not reach back and mutate a shipped, production Treatment Plans table, the same non-negotiable
  principle Treatment Plans itself already applied to Dental Chart (§7 of that design doc) and Dental Chart
  applied to itself (no speculative `treatment_plan_item_id` column was ever added, confirmed in
  `docs/modules/dental-chart.md`'s Key Architectural Decisions).
- "Has this treatment-plan item already been invoiced?" (needed only for the convenience picker in §3 step 1)
  is answered by **querying** `invoice_items` for a non-void, non-deleted row with that
  `treatment_plan_item_id` — a derived read, not a stored flag anywhere (§4's "don't store derivable state"
  principle, applied to a cross-module relationship this time, not just a single table).
- **Future direct charges, services, and products are a pure additive extension**: when a Services catalog
  or Inventory product exists, it gains its own nullable FK column on `invoice_items` (e.g.
  `inventory_item_id`) alongside `treatment_plan_item_id` — both optional, at most one meaningfully set per
  row, `description`/`unit_amount` always present regardless. No reshape of `invoices`/`invoice_items`
  themselves, no migration of existing rows — the same "additive, no reshape" pattern used throughout this
  codebase (Treatment Plans extending `dental_conditions`, Dental Chart's own additive-only precedent).

This is a deliberately **looser** coupling than Treatment Plans↔Dental Chart's one-way reference (§7 of that
design doc) — even that link, one-way as it is, still assumes a fixed, singular "the diagnosis this item
addresses." Billing's relationship to its sources is closer to "zero or one nullable pointer, purely for
traceability, never structurally required and never exclusive" — appropriate because, unlike a treatment
plan item (always about one clinical finding), an invoice line can legitimately originate from any number of
different, unrelated kinds of billable events over the module's lifetime.

## 8. Business Rules (consolidated)

- An invoice's `patient_id` is immutable after creation (mirrors every prior module).
- An item's `invoice_id` is immutable after creation — moving a line between invoices isn't supported;
  delete and re-add instead (same reasoning as Treatment Plans/Dental Chart's identical rule).
- `description`/`unit_amount`/`quantity` are editable only while the **parent invoice** is `draft`; any
  write attempt after `issued` throws an `InvoiceItemLockedException` (`422`), mirroring
  `TreatmentPlanItemLockedException` exactly.
- A `treatment_plan_item_id` referenced by a `charge`-kind item, if set, must belong to the same patient as
  the invoice (Form-Request-level check, mirroring the pattern already used for
  `diagnosis_entry_id`/`RequiresCostWhenNoDefaultPrice`/`BelongsToPatient` rules shipped with Treatment
  Plans — `BelongsToPatient` is directly reusable here, not reinvented).
- `discount`/`tax`-kind items never carry a `treatment_plan_item_id` (Form Request validation).
- `sequence_number`/`invoice_number` are assigned exactly once, at the `draft → issued` transition, never
  reassigned, never reused even if the invoice is later voided (§6) — preserves a gapless-enough, always-
  traceable numbering sequence for accounting purposes.
- **No silent mutation of an issued invoice, ever** (Decision Log item 2): once `issued`, an invoice and its
  items are read-only for every financial field, with no code path — API, service, or otherwise — that
  writes to them again short of the `void` transition itself. The only sanctioned corrections are `void`
  (built now, admin/receptionist) or a future `adjustment`-kind `InvoiceItem` on a *new* invoice (named,
  reserved, not built in V1 — §6, §16) — never an in-place edit of a frozen line.
- Transitioning an invoice to `void` is available only from `issued` (not from `draft` — a draft invoice
  with no `invoice_number` yet is simply deleted instead, since it was never a real financial record).
- Soft delete (hard-remove-behind-soft-delete) is gated **admin-only**, distinct from `void` (available to
  admin/receptionist, §10) — mirrors the Delete-vs-Cancel privilege split already established in Treatment
  Plans/Dental Chart, applied here as Delete-vs-Void.
- `unit_amount` is validated server-side as a non-negative decimal (`>= 0`) for every kind, `quantity >= 1`
  — never trust a client-computed total (mirrors Treatment Plans' identical rule).

## 9. API Design

```
GET    /api/patients/{patient}/invoices                    (list, all statuses, not paginated — same
                                                              documented exception class as Treatment Plans/
                                                              Dental Chart/Patient audit logs)
POST   /api/patients/{patient}/invoices                     (create, status=draft)
GET    /api/invoices/{invoice}                                (detail, eager-loads items + relations)
PUT    /api/invoices/{invoice}                                (edit notes/issue_date/due_date — draft only
                                                              for anything affecting the total)
POST   /api/invoices/{invoice}/issue
POST   /api/invoices/{invoice}/void
DELETE /api/invoices/{invoice}                                (soft delete — admin-only, draft only)

POST   /api/invoices/{invoice}/items                         (add item)
PUT    /api/invoice-items/{invoice_item}                      (edit — locked outside draft, §8)
DELETE /api/invoice-items/{invoice_item}                      (remove — locked outside draft, §8)

GET    /api/patients/{patient}/treatment-plan-items/billable  (the "not yet invoiced, completed" picker
                                                              source for §3 step 1 — a derived, read-only
                                                              query, not a new stored concept, §7)
```

**All mutation endpoints — invoice-level and item-level alike — return the full updated `Invoice`** (items
eager-loaded), for the same "no extra round-trip" reasoning already established for Treatment Plans (§9 of
that design doc) — avoiding the N+1-per-mutation gap logged as `TECH_DEBT.md` debt for both Appointments and
Dental Chart.

Error shapes: standard `422`/`403`/`401`/`404` per `api-guidelines.md`. `InvoiceItemLockedException` and
invalid status transitions are plain `422`s.

## 10. Permissions

**Proposed as a deliberate divergence from Treatment Plans' admin+dentist pattern** — flagged explicitly for
your confirmation in §15 Decision 5, because billing is front-desk/administrative work, not a clinical
action, the same reasoning already used for Patients' own permission split (docs/decisions.md, 2026-07-14:
"Registering/editing patient demographic and administrative data is front-desk work").

| Action | admin | dentist | receptionist |
|---|---|---|---|
| View invoices / items | ✅ | ✅ (read-only) | ✅ |
| Create invoice / add items | ✅ | ❌ | ✅ |
| Edit invoice / items (draft only) | ✅ | ❌ | ✅ |
| Issue | ✅ | ❌ | ✅ |
| Void | ✅ | ❌ | ✅ |
| Delete (soft, data correction) | ✅ | ❌ | ❌ |

Dentists get read-only visibility (useful context during a clinical conversation — "has this been billed
yet") but no write access, mirroring their role on the Patients module exactly. No dentist-ownership/IDOR
restriction (no "assigned dentist" concept exists system-wide, consistent with every prior module).

## 11. Frontend UX Design (high-level — a full pass follows a later checkpoint)

**Patient Invoices tab** (`PatientDetailView.vue`, a 5th tab alongside Overview/Appointments/Dental
Chart/Treatment Plans — same `Tabs`/`TabPanel` pattern): Invoice List (status badge, invoice number once
issued, total, created date), "New Invoice" action.

**Invoice Detail** — a dedicated route (`/patients/{id}/invoices/{invoiceId}`), following the same
established precedent as `AppointmentDetailView.vue`/`TreatmentPlanDetailView.vue`: header (patient, status,
invoice number, dates), itemized line table (charge/discount/tax visually distinguished — e.g., discount
rows in a muted/green tone, matching the existing design token system, not a one-off color), a persistent
Total panel (visually modeled on Treatment Plans' Cost Summary panel), status-action buttons reusing
`StatusActionButton.vue`, an "Add from Treatment Plan" picker dialog (§3) alongside a manual "Add Charge"
dialog.

**i18n**: new `billing.*` namespace, full `en`/`ar`/`tr` parity required before sign-off, matching the bar
every prior module met.

**RTL / currency formatting**: the exact same `dir="ltr"` isolation-span fix already applied to Treatment
Plans' cost figures (and Appointment Types' hex colors) applies to every amount shown here — called out
explicitly since this module surfaces currency more prominently than any prior one.

**Datetime handling**: every timestamp routes through `frontend/src/lib/date.ts` exclusively — no new
date-handling code, per the project's permanent datetime policy. `issue_date`/`due_date` are date-only
fields — use the existing `parseLocalDate`/`toLocalDateString` helpers (already used by Patients'
`date_of_birth`), not the datetime pair.

## 12. Security Considerations

- Every write endpoint has a dedicated `FormRequest` whose `authorize()` delegates to the Policy, per
  `api-guidelines.md` — no exceptions.
- `Invoice`/`InvoiceItem` both `use Auditable` from their first migration — financial data, at least as
  sensitive as Treatment Plans' pricing data (§14 elaborates the commercial-sensitivity angle).
- No new authentication/authorization mechanism — reuses Sanctum SPA/cookie auth and the existing Policy
  layer verbatim.
- Amount fields validated server-side as non-negative decimals — never trust a client-computed total (§8).
- No direct DB access from any consumer — Controller → Service → Resource only, consistent with every prior
  module and the AI-layer vision's "never direct DB access" principle.

## 13. Performance & Scalability Considerations

- All queries are `patient_id`-scoped (invoice list) or `invoice_id`-scoped (item list within a detail
  fetch) — cost stays flat as total patient/invoice count grows, the same reasoning class already
  established for Treatment Plans/Dental Chart.
- The invoice total is computed via a single aggregate query per invoice-detail fetch — cheap at realistic
  scale (an invoice realistically has single-digit-to-low-tens of lines).
- `GET /api/invoices/{invoice}` eager-loads `items.treatmentPlanItem`, `createdBy` in one request — avoiding
  the N+1-per-mutation gap already logged as debt for Appointments/Dental Chart (§9 explains why this module
  doesn't repeat that trade-off, following Treatment Plans' precedent).
- The "billable treatment plan items" picker query (§9) is `patient_id`-scoped and anti-joined against
  `invoice_items` — bounded by one patient's realistic lifetime item count (tens, not thousands), no
  different in cost profile from any other per-patient query in the system.
- At true SaaS scale, the only structural change needed is leading every index with a future `clinic_id`
  (§14) — no index or query shape needs to change beyond that prefix, the same conclusion every prior
  module's SaaS-readiness review reached.

## 14. SaaS Readiness

**Current V1 assumptions** (matches every existing module):
- No `tenant_id`/`clinic_id` anywhere in the new schema — single-organization, per `PROJECT_CONTEXT.md`.
- `billing_settings` is designed as a **clinic-scoped-ready singleton** — even though V1 has exactly one
  implicit row with no `clinic_id` column yet, the table's *purpose* (one settings row per organization) is
  already the right shape; adding `clinic_id` later is a plain additive column plus a lookup-by-tenant
  change, not a redesign. This directly follows the recommendation already logged in this project's own
  cross-module planning discussion for the future Settings module.
- `invoice_number` sequencing is currently global (one counter for the whole deployment, exactly like
  `patient_code`) — becomes genuinely `clinic_id`-scoped once multi-tenant, the same migration path already
  flagged for `patients.sequence_number` implicitly (never explicitly documented before now, since Patients
  predates this level of SaaS-readiness rigor — worth backfilling a short note in `docs/modules/patients.md`
  at some point, flagged here rather than silently left inconsistent).

**Future multi-tenant migration impact:**
1. Add `clinic_id` to `invoices`, `invoice_items`, and `billing_settings`, backfilled from a `clinics` table
   — same additive pattern as every other module.
2. Extend the composite indexes above to lead with `clinic_id`; re-scope `sequence_number`'s uniqueness to
   be per-`clinic_id`, not global (mirroring the same required change already flagged for
   `patients.national_id` in `TECH_DEBT.md`).
3. `billing_settings` becomes a real per-clinic row instead of one implicit global row — no schema change
   beyond the `clinic_id` column itself.
4. Apply the same global Eloquent scope (`BelongsToTenant`) and policy-level clinic-membership check
   recommended in every prior module's SaaS Readiness section.

**Elevated sensitivity note**: like Treatment Plans, this module carries **pricing/financial data** —
arguably the single most commercially sensitive data category in the whole system once real money and real
per-clinic pricing are involved. Worth the same extra tenant-isolation verification attention flagged in
Treatment Plans' own SaaS Readiness section, compounded here since actual issued financial documents (not
just estimates) are at stake.

**Reporting readiness** (for the future Reports module, mirroring Treatment Plans §24's format):
- **Total billed** = `SUM(invoice totals)` across `issued` (non-void) invoices in a period.
- **Outstanding balance** (V1-honest version, until Payments exists) = *Total billed*, in full — there is no
  "amount collected" concept yet to subtract (§5). Once Payments ships, this formula gains a real subtraction
  term with zero change to Billing's own schema.
- **Billing-to-treatment traceability** = for any `issued` invoice, the set of `TreatmentPlanItem`s it
  references (via `invoice_items.treatment_plan_item_id`) — supports a future "value of completed but
  unbilled work" report by finding `completed` items with no matching non-void `invoice_items` row (the
  same query already used for the billable-items picker, §7/§9, reused for reporting rather than duplicated).

## 15. Decisions Confirmed at Refinement Review (2026-07-23)

Was "Open Decisions Needing Explicit Approval" — all six resolved (the first three formally via the
Approval & Decision Log at the top of this document; the remaining three approved as originally
recommended, no changes requested). Kept as a consolidated index, not deleted, so the reasoning trail stays
visible — mirrors how `treatment-plans-design.md` handles the same situation:

1. **Invoice-centric model (Curve Dental-style), not ledger-centric (Open Dental/Dentrix-style)** — a named
   `Invoice`/`InvoiceItem` entity pair with its own lifecycle, matching every other DentalSuite module's data
   modeling convention (§0, §4-§6). **CONFIRMED** as recommended.
2. **No `paid`/`partially_paid` status in V1** — `Invoice` ships with only `draft`/`issued`/`void`; a
   placeholder `amountPaid` accessor returns `0` until the Payments module adds a real relation to sum from,
   with zero reshape of `Invoice` itself when that happens (§5). **CONFIRMED** (Decision Log item 1) — the
   "combined release with Payments" alternative originally flagged alongside this recommendation is
   explicitly rejected; Billing ships and closes on its own.
3. **Line item `kind` limited to `charge`/`discount`/`tax` in V1** — no generic `adjustment` kind (insurance
   write-offs, etc.) until insurance/Payments concepts actually exist to drive it (§2, §6). **CONFIRMED**,
   with one refinement (Decision Log item 2): `adjustment` is now explicitly named as the *future*
   correction mechanism for an issued invoice (alongside `void`), not just a deferred-and-forgotten line
   kind — see §8's immutability rule and §16.
4. **Discounts and tax are fixed-amount, staff-entered/prefilled snapshot lines — no percentage-calculation
   engine, no multi-jurisdiction tax rules** (§2, §6, §11). **CONFIRMED** as recommended.
5. **Permissions: admin + receptionist write, dentist read-only** — a deliberate divergence from Treatment
   Plans'/Dental Chart's admin+dentist pattern, because billing is front-desk/administrative work, mirroring
   Patients' own precedent instead (§10). **CONFIRMED** as recommended, no changes requested.
6. **`treatment_plan_item_id` stays a bare nullable, traceability-only FK with no reverse column on
   `treatment_plan_items` and no uniqueness constraint** — "already invoiced" is answered by querying
   `invoice_items`, not stored anywhere (§7). **CONFIRMED** as recommended, no changes requested — this is
   the direct implementation of this task's "do not tightly couple" instruction, now also restated as its
   own explicit relationship diagram (§20).

## 16. Potential Risks / Deferred Features / Future Improvements

**Risks:**
- **"Not yet payable" UX risk**: until Payments ships, an `issued` invoice has no way to be marked paid —
  worth a clear UI label ("Awaiting payment recording") so front-desk staff don't mistake this for a bug.
  Directly follows from Decision 2 (§15); a real, named trade-off, not a silent gap.
- **Double-invoicing risk**: because "already invoiced" is a derived query (§7), not a DB constraint, a race
  between two staff members simultaneously adding the same treatment-plan item to two different draft
  invoices is possible in theory (low real-world likelihood at realistic single-clinic concurrency, but
  worth naming). Mitigation: the picker query re-runs fresh on each dialog open; a true DB-level guard would
  need a unique partial index, deferred unless this proves to be a real problem in practice.

**Deferred (named explicitly, not silently dropped):**
- Payments, refunds, insurance claims/EOB, credit notes, PDF export/print, patient-facing online invoice
  viewing, percentage-based discounts, multi-tax-jurisdiction support, products/services catalog integration
  (§2, all explicitly named above with their own reasoning).
- A backfill note on `docs/modules/patients.md` acknowledging `patient_code`'s sequencing will need the same
  future `clinic_id`-scoping treatment as `invoice_number` (§14) — a tiny doc-only follow-up, not a code
  change, noted here so it isn't lost.

## 17. Future AI Integration Points (vision only — not built now)

Per the standing AI-layer vision: natural future domain events (not built now) — `InvoiceIssued`,
`InvoiceVoided` — could feed an AI Follow-up/Recall flow ("invoice issued 2 weeks ago, no payment recorded —
send a reminder," once Payments exists) or an AI Analytics Assistant answering "what's our total billed this
quarter?" directly. Billing's structured, API-first shape (Controller → Service → Resource only, §12) makes
this a pure future read integration, no reshape needed.

## 18. Testing Strategy

**Backend (Feature + Unit, mirrors Treatment Plans' test file structure exactly):**
- `InvoiceTest` (Feature): full CRUD, status transitions (`draft→issued→void`, invalid transitions
  rejected), `invoice_number` assignment-exactly-once, item-lock enforcement once `issued`.
- `InvoiceItemTest` (Feature): full CRUD, `kind`-specific validation (`discount`/`tax` reject a
  `treatment_plan_item_id`), `BelongsToPatient` cross-check, total computation across mixed `charge`/
  `discount`/`tax` lines.
- Unit: `InvoiceStatusTest`/`InvoiceItemKindTest` (enum tests), `InvoiceTest`/`InvoiceItemTest` (Models —
  relationships, `amount`/total accessors), `InvoiceServiceTest` (transition logic, numbering concurrency
  safety — a direct port of `PatientService`'s existing lock-based test pattern, snapshot/freeze
  enforcement), Form Request tests mirroring `StoreTreatmentPlanItemRequestTest`'s structure.

**Permissions tests:** `InvoicePolicyTest`/`InvoiceItemPolicyTest` — explicit per-role × per-action matrix
(admin/dentist/receptionist/guest), confirming the admin+receptionist-write/dentist-read-only split (§10) is
actually enforced, not just documented.

**Frontend component tests (Vitest):** stores (`invoices.ts`), components (invoice list/detail, item table,
status actions) — render states, role-based UI gating (dentist sees no write controls), i18n key
presence/parity.

**E2E (Playwright, permanent suite, CI-verified from the start)** — given the gap already logged for
Treatment Plans (`TECH_DEBT.md`), this module's E2E suite should be written and CI-verified as part of its
own implementation sequence, not deferred: golden path (create invoice from completed treatment plan items →
add manual charge → add discount → issue → verify frozen/numbered), manual-only invoice path, void path,
receptionist-write/dentist-read-only verification, RTL/dark-mode/currency-formatting smoke check.

## 19. Module Boundaries (clarified at Refinement Review, 2026-07-23)

Added per explicit request (Decision Log item 4) to state plainly, in one place, what was previously only
implied by the schema and relationships elsewhere in this document:

> **Treatment Plans produce billable *candidates*.** A `TreatmentPlanItem` reaching `completed` status
> means a piece of clinical work is done and *eligible* to be billed — it is not itself a financial record,
> carries no billing status, and nothing about completing it obligates an invoice to exist. Treatment Plans
> owns clinical recommendation-and-execution tracking only (§2 of `treatment-plans-design.md`); it has zero
> awareness that Billing exists (no column, no event, no callback).
>
> **Invoices represent the actual financial obligation.** The moment staff creates an `Invoice` — whether
> pre-seeded from completed treatment-plan items or entirely manual (§3) — that document, once `issued`, is
> the authoritative statement of what a patient owes for those line items. This is the first point in the
> whole `Patient → Treatment Plan → Treatment Plan Item → ... ` chain where "estimate" becomes "obligation."
>
> **Payments (future) apply against invoices, not against treatment plans.** When the Payments module is
> built, a `Payment` will reference an `Invoice` (or, per CareStack's precedent noted in §0, optionally sit
> unapplied against a patient's account) — it will have no reason to ever look at `TreatmentPlan`/
> `TreatmentPlanItem` directly, since by the time money changes hands, the financial record of what's owed
> has already been fully captured and frozen in the `Invoice`/`InvoiceItem` snapshot (§5, §8).

This is why the FK direction and cardinality in §7 are what they are: each layer only ever looks *backward*
for optional traceability (an `InvoiceItem` may point back to the `TreatmentPlanItem` it came from; a future
`Payment` will point back to the `Invoice` it settles), and never *forward* to require or assume the next
layer exists. A `TreatmentPlanItem` can be completed and never invoiced. An `Invoice` can be issued and
(today) never paid. Neither is an error state — each layer is a complete, independently meaningful record on
its own.

## 20. Relationship Diagram (added at Refinement Review, 2026-07-23)

Added per explicit request (Decision Log item 5) as its own linear chain, distinct from and simpler than the
full entity-relationship diagram in §7 — this one is about the *conceptual* traceability path across all
three modules (two shipped, one future), not the full set of belongsTo/hasMany relations within Billing
alone:

```
TreatmentPlanItem                    InvoiceItem                          Payment
(Treatment Plans module,             (this module)                        (future Payments module)
 already shipped)
─────────────────────                ─────────────────────                ─────────────────────
 status: completed          ┄┄┄▶      treatment_plan_item_id      ┄┄┄▶      invoice_id
 procedure_name                       (nullable FK,                       (FK — required, once built)
 unit_cost × quantity                  traceability only,
                                        §7 Decision Log item 6)             amount
                                                                            paid_at
                                       description  (own snapshot,
                                        §6 — never re-read from                      OR, per CareStack's
                                        the source above once set)                   precedent (§0):
                                       unit_amount  (own snapshot)                    left unapplied
                                                                                       against the patient
                                       kind: charge | discount | tax                  account generally,
                                                                                       with no invoice_id
                                                                                       set at all
```

Reading the dashed arrows (`┄┄┄▶`): **optional, traceability-only reference — not a structural dependency**.
An `InvoiceItem` is fully valid and fully self-contained with `treatment_plan_item_id = null` (a manual
charge, §2/§3); a future `Payment` will be fully valid applied against an `Invoice` or left unapplied on the
patient's account, per §0's CareStack finding. At no point does a lower layer require, cascade into, or
resolve live data from a higher layer once its own snapshot is written — each box in the diagram is a
record that stands on its own once created.

---

**This refined document is ready for a final check. No migrations, models, or code have been written.
Phase 2 implementation still requires its own separate, explicit go-ahead — this refinement-review approval
covers the design direction and the five items in the Decision Log above, not a green light to start
building.**
