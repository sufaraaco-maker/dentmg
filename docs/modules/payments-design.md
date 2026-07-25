# Payments — Module Design (Implementation Complete, 2026-07-25)

**Status: Design approved and implemented (backend + frontend) same-day, 2026-07-25.** Migrations,
`Payment` model, `PaymentService`, `PaymentPolicy`, Form Requests, `PaymentController`/routes,
`PaymentResource`, the additive `InvoiceResource` fields, and the full frontend (types, API service,
Pinia store, dialogs, Patient Payments tab, Invoice Detail Payments panel, en/ar/tr i18n) are all in
place. Backend: `pint`/`phpstan analyse` clean, 619/619 backend Unit tests + 22/22 new `PaymentTest`
Feature tests green (the Feature-test coverage Billing itself is still missing — see
`TECH_DEBT.md`). Frontend: `vue-tsc`/`eslint` clean; the new `stores/payments.test.ts`/
`services/payments/errors.test.ts` (20 tests) pass cleanly. A full-suite run (561 tests) showed 2
failures confined to the pre-existing `router/index.test.ts` — unrelated to Payments (that file
imports nothing from this module) and confirmed passing 11/11 in isolation; logged in `TECH_DEBT.md`
as environment flakiness, not attributed to this module. A permanent Playwright E2E spec is
still open — see `TECH_DEBT.md`. Written per the roadmap's own explicit
sequencing (`docs/modules/billing-design.md` §1: "recording payments against an invoice is the next module's
job (Payments), sequenced immediately after") and the multiple forward-reservations Billing's own design and
implementation already made on this module's behalf (§5, §7, §14, §16, §19, §20 of that document — cited
throughout below rather than re-derived, since re-deriving already-settled decisions would just contradict
them).

## Approval & Decision Log (2026-07-25)

All six open items from §15 resolved in a single approval pass, implementation authorized immediately (no
separate refinement-review cycle, unlike Billing's two-pass approval):

1. **No paysplit-style fan-out** (§15 item 1) — **APPROVED as recommended.** One `Payment` applies to at
   most one `Invoice`, or none. No join table in V1.
2. **Partial refunds, capped at remaining un-refunded balance** (§15 item 2) — **APPROVED as recommended.**
3. **No time-based void window** (§15 item 3) — **APPROVED as recommended.** Refund is the only correction
   mechanism for a real money-movement error, regardless of the payment's age.
4. **`PaymentMethod` enum values** (§15 item 4) — **RESOLVED**: `cash`, `card`, `bank_transfer`, `other`
   only. `check`/`insurance` explicitly excluded from V1 — insurance belongs to a future Insurance module,
   checks are rare enough that `other` (with the free-text `reference` field) covers them.
5. **Frontend placement** (§15 item 5) — **RESOLVED**: a dedicated **Payments** tab on
   `PatientDetailView.vue`, sibling to the Invoices tab, in addition to the per-invoice Payments panel
   (§11) — not folded into the Invoices tab.
6. **Patient Detail "Outstanding Balance" widget** (§15 item 6) — **RESOLVED: not for V1.** Per-invoice
   `balanceDue` (§4/§11) is sufficient; an aggregate patient-level balance is future Reports-module scope,
   consistent with `billing-design.md` §13's own "outstanding balance = reporting-readiness note, not a
   widget to build now" framing.

Implementation expectations restated by this approval (already this module's own stated discipline, §8/§12,
now explicitly confirmed): issued invoices and completed payments stay immutable financial history — no
in-place mutation of accounting facts; the service/repository/resource/test architecture mirrors Billing's
exactly; no `tenant_id`/`clinic_id` introduced yet (§14 already covers the future migration path); design
stays extensible toward payment allocation/insurance/payment plans/gateway integrations (§2's named
out-of-scope list) without building unused abstractions now.

## 0. Competitive Research (builds on Billing's own research, doesn't repeat it)

`billing-design.md` §0 already researched Open Dental/Dentrix/CareStack for the payments-adjacent parts of
their billing model and explicitly flagged three findings as "worth carrying forward into the Payments
module's own design later" — this section confirms and extends those with payment-specific research now that
this module is actually being designed.

| Source | Finding | Taken / Rejected for this design |
|---|---|---|
| **Open Dental** ([Payment](https://www.opendental.com/manual/payment.html), [Paysplit](https://opendental.com/manual/paysplit.html), [Unearned Types](https://opendental.com/manual/definitionspaysplitunearned.html)) | A single payment can be split ("paysplit") across multiple procedures/providers/clinics — including an "unearned income" split type for money received but not yet allocated to production. | **Taken (partially)**: "unearned/unapplied income can exist on its own" confirms the CareStack-sourced `invoice_id`-nullable design already reserved in `billing-design.md` §7/§20. **Rejected**: full paysplit (one payment fanned out across *multiple* invoices/allocations in one transaction) — real capability, but adds a join-table layer of complexity V1 doesn't need; see Decision 1 below. If a patient's single payment genuinely covers two invoices, staff records two `Payment` rows — a small extra data-entry step, not a missing capability. |
| **Dentrix Ascend** ([Refunding Credit Card Payments](https://support.dentrixascend.com/hc/en-us/articles/360052973094-Refunding-credit-card-payments), [Refunds by Line Item](https://learn.dentrixascend.com/refunds-by-line-item/)) | Same-day, undeposited payments are *voided*; anything older must be *refunded* (a distinct transaction type), and partial refunds are supported directly from the ledger without cancelling the original payment. | **Taken**: partial refunds without touching the original record — directly confirms Decision 2 below. **Rejected**: the time-based void-vs-refund split — real UX polish, but two correction mechanisms (a same-day in-place void *and* a separate refund-as-new-record) is more machinery than V1 needs when a single refund mechanism, used consistently regardless of age, already covers every case correctly (mirrors Billing's own decision to give `Invoice` one correction mechanism — `void` — rather than void *and* a separate credit-note entity, §8/§17 of that doc). |
| **CareStack** ([Unapplied Credit Management](https://carestack.zendesk.com/hc/en-us/articles/46410377496852-Overview-of-Unapplied-Credit-Management), [Add and View Unapplied Credits](https://carestack.zendesk.com/hc/en-us/articles/34138439966100-Add-and-View-Unapplied-Credits)) | A payment can be posted as an "Advance Payment"/unapplied credit against the patient account generally, with no invoice chosen at entry time, and applied to a specific charge later. | **Taken** — this *is* the shape already reserved in `billing-design.md`'s relationship diagram (§20): `Payment.invoice_id` nullable, `Payment.patient_id` required. Confirmed twice now (once during Billing's own research, once here) as the real industry-standard shape, not a one-off idiosyncrasy of a single vendor. |

**Net effect**: nothing above changes the shape Billing already reserved — this research confirms it was the
right call and adds one explicit, named simplification (Decision 1: no paysplit-style one-to-many fan-out) and
one explicit, named omission (Decision 4: no time-based void window) rather than silently under-building.

## 1. Purpose

Record money actually collected from (or returned to) a patient, and connect it back to what Billing already
established is owed. This is the module that turns Billing's "what is owed" into "what has actually been
collected" — the exact boundary `billing-design.md` §1/§19 drew in advance.

## 2. Scope (V1)

**In scope:**
- Recording a payment against a specific `Invoice`, or leaving it **unapplied** against the patient's account
  generally (CareStack precedent, §0/§7).
- **Applying** an already-recorded unapplied payment to a specific invoice later, once one exists (§8).
- **Refunding** a payment — fully or partially — without editing or deleting the original record (§8, mirrors
  Invoice's `void`-not-edit rule).
- `Invoice` gains real `amountPaid` / `balanceDue` / `paymentStatus` (`unpaid` / `partially_paid` / `paid`)
  accessors, exactly as reserved in `billing-design.md` §5 — no new column, no new `InvoiceStatus` value, a
  pure additive read on top of what already exists.
- A simple, recording-only `PaymentMethod` (`cash`, `card`, `bank_transfer`, `other`) — no processor
  integration (§2 below).
- Soft delete as the pure data-entry-error correction path (admin-only, and only for a payment that has never
  been refunded — §8), distinct from `refund` (real money actually returned), mirroring Billing's
  delete-vs-void split exactly.
- Full audit trail via the existing `Auditable` trait — no new mechanism, matching every prior module.

**Explicitly out of scope for V1** (named, not silently dropped — same discipline `billing-design.md` §2/§16
used):
- **Real payment processor / gateway integration** (Stripe, Square, CareCredit, cards-on-file, ACH capture) —
  this module *records* that money changed hands; it never moves money itself. No new package dependency, per
  `PROJECT_CONTEXT.md`'s "never introduce unnecessary packages."
- **Paysplit-style one-to-many allocation** (one payment fanned out across several invoices in a single
  transaction) — §0/Decision 1. A future enhancement, not a V1 gap silently accepted as permanent.
- **Insurance claims / EOB-driven payments** — nothing insurance-related exists in DentalSuite yet, same
  reasoning `billing-design.md` §2 already gave for deferring insurance adjustments.
- **Payment plans / installment scheduling** — a genuinely different, larger feature (recurring scheduled
  charges), not a natural extension of "record one payment"; would need its own design.
- **Patient-facing self-service payment** (portal, pay-by-text, pay-by-link) — belongs with a future Patient
  Portal, per the standing AI-layer vision's "integrations in a separate layer" principle, identical reasoning
  to Billing's own §2 deferral of online invoice viewing.
- **Receipts / PDF generation** — pure rendering over this module's data, same "cheap near-term follow-up, not
  built now" treatment Billing gave invoice PDF export (§2).
- **Time-based void window** (Decision 4) — a single refund mechanism handles every correction, regardless of
  how old the payment is.

## 3. Full Workflow

```
Invoice (existing — Billing module, status = issued)
  → Payment recorded                                    (this module — applied to that invoice, or unapplied)
  → [optional] Payment applied later                     (this module — an unapplied payment gets an invoice_id)
  → [optional] Payment refunded (full or partial)         (this module — a new, negative Payment row)
  → Invoice.paymentStatus reflects unpaid/partially_paid/paid (computed, never stored)
```

**Primary flow — pay an issued invoice (admin, receptionist):**
1. From an invoice's detail page, "Record Payment" — amount, method, optional reference/notes, defaults to
   today. `invoice_id` is pre-filled from the current invoice.
2. `Invoice.amountPaid` updates immediately (computed, §6); once `amountPaid >= total`, `paymentStatus`
   reads `paid`.

**Secondary flow — unapplied/advance payment (admin, receptionist):**
1. From a patient's account (not tied to any one invoice yet), "Record Payment" with no invoice selected.
2. Later, from that payment (or from a specific invoice wanting to pull in existing credit), "Apply to
   Invoice" sets its `invoice_id` — one-time, full-amount only (§8, Decision 1's simplification carried
   through here too).

**Correction flow — refund (full or partial):**
1. From a payment, "Refund" — enter an amount up to the payment's own remaining un-refunded balance.
2. Creates a new `Payment` row: negative `amount`, same `invoice_id`/`patient_id`/`currency_code` as the
   original, `refunded_payment_id` pointing back at it. The original is never edited.

**Correction flow — genuine data-entry error (admin only):**
1. Soft-delete a payment that was entered by mistake (e.g., duplicate entry, no real money involved) — only
   permitted when it has never been refunded (§8).

## 4. Core Concepts (definitions)

- **Payment**: one point-in-time financial fact — money received from, or returned to, a patient. Always
  belongs to a `Patient`; optionally linked to one `Invoice`.
- **Applied payment**: a `Payment` with `invoice_id` set — counts toward that invoice's `amountPaid`.
- **Unapplied payment / credit**: a `Payment` with `invoice_id = null` — sits on the patient's account, counts
  toward nothing yet, discoverable and appliable later (§8).
- **Refund**: not a distinct entity — a `Payment` row with a negative `amount` and `refunded_payment_id` set,
  pointing at the payment it reverses. Inherits the original's `invoice_id` (or lack thereof), so summing all
  of an invoice's linked payments (refunds included) always nets to the true amount still applied.
- **`amountPaid`** (Invoice, computed): `SUM(payments.amount)` for that invoice, refunds included in the sum
  (so a full refund nets back to `0`) — never stored, same principle as `InvoiceItem.amount`/`Invoice.total`.
- **`balanceDue`** (Invoice, computed): `total - amountPaid`. Can go negative (an overpayment/credit)); the
  UI shows a negative balance as a credit, not a stored concept.
- **`paymentStatus`** (Invoice, computed): `unpaid` (`amountPaid == 0`), `partially_paid`
  (`0 < amountPaid < total`), `paid` (`amountPaid >= total`) — meaningful only for `issued` invoices; a
  `draft` invoice has no real total yet and a `void` invoice is excluded from balance-due reporting entirely
  (both already-established Billing rules, unchanged here).

## 5. Status Lifecycle

**No `PaymentStatus` enum** — deliberately, same "don't store derivable state" principle already applied to
`InvoiceItem` (design doc §5: "no separate status enum for `InvoiceItem`... fully derived from its parent
invoice's status"). A `Payment`'s only "state" is how much of it has been refunded, which is a live `SUM`
over rows referencing it via `refunded_payment_id` — never a stored column. Soft-delete (`deleted_at`) is the
only lifecycle bit a `Payment` actually has.

## 6. Database Design

### `payments` (new table)

| Column | Type | Notes |
|---|---|---|
| `id` | uuid | PK |
| `patient_id` | uuid, FK → `patients`, not nullable | Immutable after creation, mirrors every prior module |
| `invoice_id` | uuid, **nullable**, FK → `invoices`, `nullOnDelete` | Set at creation (applied) or later via `/apply` (§3/§8); `null` = unapplied credit (§4) |
| `refunded_payment_id` | uuid, **nullable**, FK → `payments` (self), `nullOnDelete` | Set only on a refund row; points at the payment it reverses. `null` for an ordinary payment. |
| `method` | string, cast to `PaymentMethod` enum (`cash`, `card`, `bank_transfer`, `other`) | Recording only — no processor integration (§2) |
| `amount` | decimal(10,2), not nullable | **Positive** for an ordinary payment, **negative** for a refund row — the only place in this codebase's financial tables a signed stored amount is correct, because a refund's entire meaning is "money moving the other way," unlike `InvoiceItem.unit_amount` (always positive, sign comes from `kind`) — there is no equivalent "kind" enum here to carry the sign instead. |
| `currency_code` | string(3) | Snapshotted from `billing_settings` at creation, same reasoning as `Invoice.currency_code` (`billing-design.md` §6) — never assume a single global currency |
| `reference` | string, nullable | e.g. a check/transaction number |
| `notes` | text, nullable | e.g. refund justification |
| `received_at` | date, not nullable | When the payment was actually collected/returned — staff-editable (e.g. backdating), mirrors `Invoice.issue_date` |
| `created_by_id` | uuid, FK → `users`, not nullable | |
| `deleted_at` | timestamp, nullable | Soft delete — data-entry-error correction only, never a refund substitute (§8) |
| `created_at` / `updated_at` | timestamp | |

Indexes: `(patient_id)`, `(invoice_id)`, `(refunded_payment_id)`.

Both `Payment` and every other financial table in this module get `Auditable`, `HasUuids`, `SoftDeletes` — no
exceptions, matching Billing's own tables and every prior module's convention.

**No new table for "applications"/"allocations"** — Decision 1 (§0) means a single nullable `invoice_id`
column is the entire mechanism; no join table is needed for V1's one-payment-at-most-one-invoice model.

## 7. Table Relationships

```
Patient (existing)
  └─┬─ hasMany ─→ Payment
    │               ├─ belongsTo → Invoice (nullable — §4)
    │               ├─ belongsTo → Payment (refunded_payment_id, self, nullable — §4)
    │               └─ belongsTo → User (created_by_id)
Invoice (existing — Billing module)
  └─ hasMany ─→ Payment   (the inverse of the FK above; used only to compute amountPaid/balanceDue/
                            paymentStatus, §6 — never eager-loaded onto InvoiceItem, never reaches back
                            into TreatmentPlanItem)
```

This is the same "look backward only, never forward" discipline `billing-design.md` §19 named explicitly for
the Treatment Plans → Billing boundary, now applied one layer further: `Payment` may look back at the
`Invoice` it settles; `Invoice`/`InvoiceItem` will never be made aware `Payment` exists (no column, no event,
no callback added to either).

## 8. Business Rules (consolidated)

- A payment's `patient_id` is immutable after creation (mirrors every prior module).
- A payment's `amount`, `method`, `currency_code`, `patient_id` are immutable once created — a genuine
  correction is a refund (§4) or, for a real data-entry error with no real money involved, a soft delete
  (below) — never an in-place edit. `reference`/`notes`/`received_at` remain editable (administrative
  metadata, mirrors `Invoice.notes`/`issue_date`/`due_date` staying editable while draft).
- **Apply** (`invoice_id` set on a currently-unapplied payment): allowed exactly once, and only to an
  `issued` invoice belonging to the same patient (`BelongsToPatient`-style check, directly reusing the rule
  already built for Treatment Plan Items/Invoice Items). Full-amount only — no partial-apply splitting
  (Decision 1's simplification carried through, §3).
- **Refund**: the refund `amount` must be positive as entered (staff types a positive number, the service
  negates it) and can be at most the original payment's remaining un-refunded balance —
  `original.amount + SUM(existing refunds against it) - requested >= 0`. A refund of a refund is not a
  distinct case — it's just another `Payment` row, and the same "does not exceed remaining balance" rule
  applies to it too, recursively, with no special-casing needed.
- A refund is never issued against a payment that has been soft-deleted, and a payment that has any refund
  against it (`SUM(refunds) != 0`) can never itself be soft-deleted (§2/§6) — the refund is the audit trail;
  deleting the original out from under it would orphan that trail's meaning.
- `amount` is validated server-side as a non-zero decimal; refunds are validated as described above — never
  trust a client-computed running balance.

## 9. API Design

```
GET    /api/patients/{patient}/payments        (list, all payments incl. refund rows, not paginated — same
                                                  documented exception class as Invoices/Treatment Plans)
POST   /api/patients/{patient}/payments         (record a payment — invoice_id optional in the body)
GET    /api/payments/{payment}                  (detail)
PUT    /api/payments/{payment}                  (edit reference/notes/received_at only — §8)
POST   /api/payments/{payment}/apply            (set invoice_id on a currently-unapplied payment)
POST   /api/payments/{payment}/refund            (create the linked negative Payment row; body: amount, notes)
DELETE /api/payments/{payment}                  (soft delete — admin-only, only if never refunded, §8)
```

**No `GET /api/invoices/{invoice}/payments`** — deliberately (§7): a payment isn't structurally owned by one
invoice the way an `InvoiceItem` is. The frontend fetches a patient's full payment list once (same
store-cache pattern as `invoices.ts`/`treatmentPlans.ts`) and filters client-side by `invoice_id` when
rendering one invoice's "Payments" panel — the exact technique already used for the billable-treatment-plan-
items derived view (`billing-design.md` §7).

`InvoiceResource` gains three new fields (`amount_paid`, `balance_due`, `payment_status`) — purely additive,
no reshape of the response envelope, matching the "zero reshape when Payments ships" promise made in
`billing-design.md` §6/§14.

Error shapes: standard `422`/`403`/`401`/`404` per `api-guidelines.md`, matching every prior module.

## 10. Permissions

Mirrors Billing's own admin+receptionist-write/dentist-read-only split exactly — billing and payment
recording are front-desk/administrative work, not clinical actions (same reasoning `billing-design.md` §10
and `docs/decisions.md`'s 2026-07-14 Patients entry already established).

| Action | admin | dentist | receptionist |
|---|---|---|---|
| View payments | ✅ | ✅ (read-only) | ✅ |
| Record payment / apply / refund | ✅ | ❌ | ✅ |
| Edit reference/notes/received_at | ✅ | ❌ | ✅ |
| Delete (soft, data correction) | ✅ | ❌ | ❌ |

## 11. Frontend UX Design (high-level)

- **Invoice Detail** (existing view, `InvoiceDetailView.vue`) gains a **Payments** panel below the items
  table: applied payments for this invoice (filtered client-side, §9), a **Balance** readout next to the
  existing Total panel (`amountPaid` / `balanceDue` / a `paymentStatus` chip reusing the existing
  `Tag`-severity-map convention from `InvoiceStatusChip.vue`), and "Record Payment"/"Refund" actions gated
  the same way `InvoiceActionsBar.vue` gates issue/void (admin+receptionist, visible only on an `issued`
  invoice).
- **Patient Detail** gains a lightweight **Payments** tab (or a section within the existing Invoices tab —
  an open question, see Decision 5) listing every payment for the patient, including unapplied credits with
  an explicit "Unapplied" badge and an inline "Apply to Invoice" picker.
- Currency is read from each `Payment`/`Invoice`'s own `currency_code`, never hardcoded — same rule already
  enforced in the Billing frontend.

## 12. Security Considerations

- Every write endpoint has a dedicated `FormRequest` whose `authorize()` delegates to `PaymentPolicy` — no
  exceptions, per `api-guidelines.md`.
- `Payment` uses `Auditable` from its first migration — financial data, at least as sensitive as `Invoice`.
- Amount fields validated server-side; refund-amount-does-not-exceed-remaining-balance is enforced in the
  Service layer as a hard backstop (mirrors `InvoiceService::assertEditable()`'s role), not just Form Request
  validation.
- No direct DB access from any consumer — Controller → Service → Resource only, consistent with every prior
  module.

## 13. Performance & Scalability Considerations

- All queries are `patient_id`-scoped — cost stays flat as total patient/payment count grows, same reasoning
  class as every prior module.
- `Invoice.amountPaid`/`balanceDue`/`paymentStatus` are single aggregate queries per invoice-detail fetch —
  cheap at realistic scale (an invoice realistically has single-digit payments/refunds against it).

## 14. SaaS Readiness

**Current V1 assumptions** (matches every existing module, including Billing's own §14):
- No `tenant_id`/`clinic_id` anywhere in the new `payments` table — single-organization, per
  `PROJECT_CONTEXT.md`.
- Every index above already leads with `patient_id` (or `invoice_id`, itself already `patient_id`-scoped) —
  the same shape every prior SaaS-readiness review (Dental Chart, Treatment Plans, Billing) confirmed needs
  no restructuring, only a future `clinic_id` prefix.
- `payments.currency_code` is read from the same `billing_settings` row `Invoice` already uses — when that
  table becomes genuinely `clinic_id`-scoped (Billing §14's own documented migration path), `Payment`
  inherits the same scoping with zero additional design work, since it was never given its own separate
  currency/settings source to begin with.

**Future multi-tenant migration impact** (identical shape to Billing's own §14 entry, extended one table):
1. Add `clinic_id` to `payments`, backfilled from a `clinics` table — same additive pattern as every other
   module, including `invoices`/`invoice_items`/`billing_settings` themselves.
2. Extend the composite indexes above to lead with `clinic_id`.
3. No reshape of the `invoice_id`/`refunded_payment_id` relationships — both already scope naturally through
   `patient_id`/`invoice_id`, which will themselves already carry `clinic_id` by the time this module needs
   to.

**Conclusion**: no blockers found. This module, designed as above, introduces no assumption that would need
undoing at multi-tenant time — the same conclusion every prior module's SaaS-readiness review reached,
confirmed here before any implementation begins rather than as a later retrofit checkpoint.

## 15. Decisions Confirmed at Approval (2026-07-25)

All six resolved in the single approval pass recorded in the Decision Log at the top of this document —
kept here as the original recommendation record.

1. **No paysplit-style fan-out** (§0, §3): one `Payment` applies to at most one `Invoice`, or none. A single
   collected amount covering two invoices becomes two `Payment` rows. *Recommendation: approve as proposed* —
   avoids a join-table layer of complexity with no current real-world driver; easy to add later if it proves
   to be a real need (Open Dental's own paysplit precedent stays available as prior art if/when that happens).
2. **Refunds support partial amounts**, capped at the original payment's remaining un-refunded balance (§0,
   §8). *Recommendation: approve as proposed* — costs no extra schema over full-refund-only, and Dentrix's
   own precedent confirms it's a real, common need (overpayment correction, patient credit-back).
3. **No time-based void window** (§0, Dentrix's same-day-void-vs-refund split) — a single refund mechanism,
   used consistently regardless of the payment's age. *Recommendation: approve as proposed* — mirrors
   Billing's own single-correction-mechanism philosophy; add a same-day void later only if staff feedback
   makes the extra step (a full refund for a payment entered five minutes ago) a real friction point.
4. **`PaymentMethod` enum values**: proposed `cash`, `card`, `bank_transfer`, `other`. Open question: does
   the clinic need `check`/`insurance` as distinct method values in V1, or does `other` (with the free-text
   `reference` field) cover those adequately until a real need names them explicitly?
5. **Frontend placement**: a dedicated **Payments** tab on `PatientDetailView.vue` (sibling to the existing
   Invoices tab), or a **Payments** section folded into the existing Invoices tab instead? The Invoice Detail
   page's own Payments panel (§11) exists either way — this only affects whether there's *also* a
   patient-wide, cross-invoice payments view one tab click away. *No recommendation either way* — genuinely
   a product-shape question, not an architectural one.
6. **Does `paymentStatus` warrant a Patient Detail-level "Outstanding Balance" summary** (e.g., on the
   Overview tab, alongside the existing demographic cards), or does per-invoice `balanceDue` (§11) fully
   cover the real need for now? `billing-design.md` §13 already named "outstanding balance = total billed,
   in full" as a *reporting*-readiness note for a future Reports module, not something Billing/Payments
   itself was asked to surface as a UI widget — flagging here so it isn't silently decided either way.

## 16. Testing Strategy

Same discipline as Billing's own §18, including its explicitly-corrected lesson (Treatment Plans shipped
without a permanent E2E suite — logged as debt, not repeated): this module's E2E coverage should be written
and CI-verified as part of its own implementation sequence, not deferred.

- **Backend**: `PaymentTest` (Feature — record/apply/refund/delete, patient/invoice scoping, permission
  matrix), `PaymentServiceTest` (Unit — refund-cannot-exceed-remaining-balance, apply-once-only,
  delete-blocked-once-refunded), `InvoiceTest` additions (amountPaid/balanceDue/paymentStatus accessor
  correctness across mixed applied/unapplied/refunded payments).
- **Frontend**: `stores/payments.ts` tests, Payments panel/dialog component tests (role-gated UI, i18n
  parity across en/ar/tr).
- **E2E**: record payment against an invoice → verify `paymentStatus` updates → partial refund → verify
  balance recalculates → record unapplied credit → apply it to a different invoice → delete-blocked-once-
  refunded verification → receptionist-write/dentist-read-only check → RTL/dark-mode/currency-formatting
  smoke check.

## 17. Module Boundaries (restates `billing-design.md` §19, now from Payments' side)

`Payment` looks backward at `Invoice` only, optionally, for traceability of what it settles — it has zero
awareness that `TreatmentPlan`/`TreatmentPlanItem` exist, and never will (no column, no event, no callback).
`Invoice`/`InvoiceItem` remain completely unaware `Payment` exists as a concept beyond the additive
`amountPaid`/`balanceDue`/`paymentStatus` accessors computed *from* `Payment` — Billing's own tables gain no
new column, no new relationship declaration is added on the `Invoice`/`InvoiceItem` model that reaches
*into* Payments' internals, only a `hasMany` used purely for that read. This keeps the same one-directional,
backward-only dependency chain `billing-design.md` §19/§20 already established, now extended one link
further without weakening it anywhere along the chain.

---

**Design approved 2026-07-25 (see Decision Log at top). Implementation proceeds in the same step-by-step
sequence Billing used (migrations/models/services → Form Requests/Controllers/Routes → Frontend →
tests/final docs).**
