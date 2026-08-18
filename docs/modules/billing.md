# Billing (Invoices) Module

**Status: Production Ready ✅** — implemented on `feature/treatment-plans` (commit `0677128`, 2026-07-25),
merged to `main`; production-hardening pass (Feature-level HTTP test coverage, permanent E2E suite, this doc)
completed 2026-08-18.

This is the module doc, produced during the production-hardening pass, superseding
[`billing-design.md`](billing-design.md) (the approved design document) as the canonical reference. The
design doc is kept for historical/decision-record purposes — its full business-rule reasoning and
open-decision resolutions are not repeated here in full; this doc reflects what actually shipped.

## Scope (V1)

Invoicing for a patient's billable work: draft an invoice, add itemized lines (manual charges/discounts/tax,
or pulled from a completed Treatment Plan item), issue it (freezing every line and assigning a permanent
sequential invoice number), and void it if needed (a cancellation, never a delete, once issued — the real
correction path for an issued invoice is a new corrected invoice). Payment collection against an invoice is a
sibling module ([Payments](payments-design.md)) reading `Invoice`/`InvoiceItem` but never writing to them
directly — `amount_paid`/`balance_due`/`payment_status` are derived from `Payment` rows at read time, not
stored columns Billing itself maintains.

**Explicitly out of scope for V1**: insurance claims, multi-currency on one invoice (currency is
patient/clinic-wide, snapshotted per invoice from `billing_settings` at creation), recurring/subscription
billing, PDF generation/emailing, tax-rate catalogs (a `tax`-kind line is a manual amount, not computed from a
rate table), a dedicated procedure-pricing catalog (Billing reuses whatever `TreatmentPlanItem` already
snapshotted — see Treatment Plans' own identical, explicitly-logged tech-debt item).

## Architecture

**Backend** (Laravel 12, PHP 8.4): same conventions as every other module — thin Controllers
(`InvoiceController`, `InvoiceItemController`, `BillingSettingController`, `BillingSummaryController`),
business logic in `InvoiceService` (one service for both invoice- and item-level operations, mirroring
Treatment Plans' identical consolidation rationale — item mutations need to check parent-invoice state).
`InvoiceStatus` (`draft`/`issued`/`void`) is a fixed lookup-table enum (`transitionsFrom()`), not a
state-machine package. `InvoiceService::assertEditable()` is the single, unavoidable gate every mutation
method (add/update/delete item, update invoice metadata, delete invoice) routes through — the actual
enforcement of "no silent mutation of an issued invoice," reachable from every call path, not just HTTP.

**Frontend** (Vue 3 + TypeScript + PrimeVue + Tailwind): a dedicated `InvoiceDetailView.vue` route
(`/patients/:id/invoices/:invoiceId`, matching `TreatmentPlanDetailView.vue`'s "detail is too much content
for a tab panel" precedent), with the invoice **list** hosted inline on `PatientDetailView.vue`'s "Billing"
tab (`PatientBillingPanel.vue`) via a `SelectButton` switching between Invoices/Payments/Payment History
sub-sections — Invoices and Payments panels are each their own component, reused as-is from the Patient tab
and Invoice Detail's own Payments panel alike.

## Key Architectural Decisions

- **Frozen snapshot at `issue`, not `draft`** — every line's `description`/`unit_amount` (and the invoice's
  own `invoice_number`/`sequence_number`) become permanently locked the instant an invoice is issued
  (`InvoiceItemLockedException`). A later catalog rename or treatment-plan-item edit can never silently change
  what was actually billed.
- **Void, never delete, once issued** — a real financial record is never destroyed; only a still-`draft`
  invoice can be soft-deleted (admin-only, data-correction action, mirroring Treatment Plans'/Dental Chart's
  identical Delete-vs-Cancel(Void) split). Voiding preserves the invoice number rather than reissuing one.
- **Sequence numbers are reserved atomically inside `issue()`'s own row-locked transaction** — via
  `billing_settings`' own next-sequence counter (not `MAX(invoices.sequence_number)`, which would race under
  concurrent issues), and never burned on a rejected transition (issuing an already-issued invoice doesn't
  consume a number it then discards).
- **Currency is snapshotted per invoice from `billing_settings` at creation**, with a `USD` fallback if no
  settings row exists yet (`createDraft()`) and a self-healing default row created on first `issue()` if one
  still doesn't exist by then — an invoice never ends up with a null currency.
- **`amount_paid`/`balance_due`/`payment_status` are derived, not stored** — computed from the invoice's
  `payments` relation at read/resource-serialization time, the same "don't store derivable financial state"
  principle Treatment Plans applies to `estimated_cost`. Payments is the only writer of `Payment` rows;
  Billing only ever reads them.
- **A `treatment_plan_item_id`-linked line snapshots `description`/`unit_amount` from that item at add-time**,
  with an explicit override still allowed (`AddFromTreatmentPlanDialog.vue`'s picker vs. manual entry into the
  same field) — only a `charge`-kind line may carry this link (`InvoiceService::assertKindAllowsSource()`); a
  `treatment_plan_item_id` already referenced by a non-deleted item on a non-void invoice is excluded from the
  "billable" picker source, but becomes billable again if that invoice is later voided (a corrected invoice's
  lines aren't permanently absorbed).
- **No dentist-ownership/IDOR restriction, and receptionist is a full write role** — the inverse permission
  split from Treatment Plans/Dental Chart: billing is front-desk/administrative work, not a clinical action,
  so admin+receptionist write, dentist read-only (still full clinic-wide read, for billing context during a
  clinical conversation).

## Backend

| Layer | Files |
|---|---|
| Migrations | `2026_07_23_000001_create_billing_settings_table.php`, `..._000002_create_invoices_table.php`, `..._000003_create_invoice_items_table.php` |
| Enums | `app/Enums/InvoiceStatus.php` (`draft`, `issued`, `void`), `app/Enums/InvoiceItemKind.php` (`charge`, `discount`, `tax`) |
| Models | `Invoice.php` (`Auditable`), `InvoiceItem.php` (`Auditable`), `BillingSetting.php` |
| Form Requests | `Invoice/{Store,Update}InvoiceRequest.php`, `InvoiceItem/{Store,Update}InvoiceItemRequest.php`, `UpdateBillingSettingRequest.php` |
| Services | `InvoiceService.php` (invoice- and item-level operations in one service, by design), `BillingSummaryService.php` |
| Policies | `InvoicePolicy.php`, `InvoiceItemPolicy.php`, `BillingSettingPolicy.php` |
| Exceptions | `app/Exceptions/Invoice/{InvalidInvoiceStatusTransitionException,InvalidInvoiceItemException,InvoiceItemLockedException}.php` |
| Controllers | `InvoiceController.php`, `InvoiceItemController.php`, `BillingSettingController.php`, `BillingSummaryController.php` |
| Resources | `InvoiceResource.php` (eager-loads items + relations, derives `amount_paid`/`balance_due`/`payment_status`), `InvoiceItemResource.php` |
| Factories | `InvoiceFactory.php` (`issued()`/`void()` states), `InvoiceItemFactory.php` |
| Tests | Feature: `InvoiceControllerTest.php` (list/filter/paginate), `InvoiceTest.php` (lifecycle: store/update/issue/void/destroy, permissions), `InvoiceItemTest.php` (item mutations, permissions), `BillingSettingTest.php`, `BillingSummaryTest.php`. Unit: `Models/{InvoiceTest,InvoiceItemTest}.php`, `Policies/{InvoiceItemPolicyTest}.php`, `Services/InvoiceServiceTest.php` (36 tests — every business rule) |

## Database

**`billing_settings`**: singleton-style settings row — `currency_code`, `invoice_number_prefix`,
`next_sequence_number` (the atomic counter `issue()` reserves from).

**`invoices`**: `id` (uuid), `patient_id` (FK), `created_by_id` (FK → `users`), `sequence_number`/
`invoice_number` (both nullable — assigned only at `issue()`), `currency_code` (snapshotted at creation),
`status` (`InvoiceStatus` enum), `notes`, `issue_date`/`due_date` (nullable, editable while draft),
`issued_at`/`voided_at` (nullable, set only on their respective transitions), soft-deleted. Indexes:
`(patient_id)`, `(patient_id, status)`, unique `(sequence_number)`.

**`invoice_items`**: `id` (uuid), `invoice_id` (FK), `treatment_plan_item_id` (nullable FK, read-only
traceability — only for `charge`-kind items), `kind` (`InvoiceItemKind` enum, determines its sign in the
total formula), `description`/`unit_amount` (snapshotted, not live-joined to the catalog once set),
`quantity` (default 1), `sequence` (nullable), `notes`, `created_by_id` (FK → `users`), soft-deleted. Indexes:
`(invoice_id)`, `(treatment_plan_item_id)`.

`total`/`amount_paid`/`balance_due`/`payment_status` are **not** columns on either table — all computed at
read time (see Key Architectural Decisions). Both tables get `Auditable`, `HasUuids`, `SoftDeletes`.

## API

```
GET    /api/patients/{patient}/invoices          (list, paginated 15/page, ?status= filter)
POST   /api/patients/{patient}/invoices           (create, status=draft, no required fields)
GET    /api/invoices                              (clinic-wide, paginated, ?search=/?status=)
GET    /api/invoices/{invoice}                    (single-resource detail — eager-loads items + relations)
PUT    /api/invoices/{invoice}                    (draft-only: notes/issue_date/due_date)
POST   /api/invoices/{invoice}/issue
POST   /api/invoices/{invoice}/void
DELETE /api/invoices/{invoice}                    (soft delete — admin-only, draft-only)
GET    /api/patients/{patient}/treatment-plan-items/billable   (completed items not yet invoiced)

POST   /api/invoices/{invoice}/items              (add item, manual or treatment-plan-linked)
PUT    /api/invoice-items/{invoice_item}
DELETE /api/invoice-items/{invoice_item}

GET    /api/billing-settings
PUT    /api/billing-settings                      (admin-only)
GET    /api/patients/{patient}/billing-summary
```

Every invoice/item mutation endpoint returns the **full updated `Invoice`** with items eager-loaded (not just
the mutated item), so the frontend re-hydrates from one response — the same N+1-per-mutation gap already
logged as `TECH_DEBT.md` debt for Appointments/Dental Chart is not repeated here.

## Permissions

| Action | admin | dentist | receptionist |
|---|---|---|---|
| View invoices / items / billing summary | ✅ | ✅ | ✅ (read-only) |
| Create invoice / add items | ✅ | ❌ | ✅ |
| Edit invoice metadata / items (draft only) | ✅ | ❌ | ✅ |
| Issue / Void | ✅ | ❌ | ✅ |
| Delete (soft, draft only, data correction) | ✅ | ❌ | ❌ |
| Manage billing settings | ✅ | ❌ | ❌ |

No clinic-membership check exists in any policy — deliberate, matching every other module (V1 has no
tenant/clinic concept to scope against).

## Frontend

| Layer | Files |
|---|---|
| Types | `src/types/invoice.ts`, `src/types/billing.ts` |
| Services | `src/services/invoices.ts`, `src/services/billingSettings.ts` |
| Stores | `src/stores/invoices.ts`, `src/stores/billingSummary.ts` |
| Views | `InvoiceDetailView.vue` (dedicated route), `InvoicesView.vue` (clinic-wide list), `PracticeSettingsView.vue`'s billing section |
| Components | `src/components/invoices/` — `PatientInvoicesPanel.vue`, `InvoiceListTable.vue`, `InvoiceStatusChip.vue`, `InvoiceActionsBar.vue`, `InvoiceItemsTable.vue`, `InvoiceItemDialog.vue` (manual add/edit), `AddFromTreatmentPlanDialog.vue` (picker), `EditInvoiceDialog.vue`; `src/components/billing/` — `PatientBillingPanel.vue` (tab shell + Invoices/Payments/Payment History `SelectButton`), `BillingSummaryCard.vue` |
| Router | `invoice-detail` route: `patients/:id/invoices/:invoiceId` |
| PatientDetailView.vue | `billing` tab (`?tab=billing` deep-link supported), hosted by `PatientBillingPanel.vue` |
| i18n | `invoices.*`/`patients.billingPanel.*`/`patients.invoicesPanel.*` namespaces — parity-checked across en/ar/tr like every other module |
| Datetime handling | Every timestamp field routes through `frontend/src/lib/date.ts` exclusively |

## Testing & Verification

- Backend: `InvoiceServiceTest.php` (36 Unit tests — every business rule: draft/update/issue/void/delete,
  item add/update/delete including treatment-plan-item snapshotting, total computation). **Production
  hardening pass (2026-08-18)** closed the HTTP-layer gap `InvoiceControllerTest.php` left open (it only ever
  covered listing): new `InvoiceTest.php` (28 tests) and `InvoiceItemTest.php` (21 tests) exercise the actual
  routes — Policy-driven 403s, FormRequest validation, and JSON response shape through real HTTP requests, not
  just the Service directly. All verified locally against a real PostgreSQL container, not just SQLite.
- Frontend: store/service Vitest coverage (matching the level this module shipped at); no per-component tests
  yet for Invoice Vue components (a smaller, narrower gap than the E2E one — not blocking, see Known
  Limitations).
- **Permanent Playwright E2E suite added 2026-08-18** — `frontend/e2e/billing.spec.ts`: golden path (draft →
  add a manual charge → issue → verify the frozen snapshot, invoice number, and that item-add controls
  disappear, including a direct-API-422 check that editing is now server-side blocked too → void → verify the
  invoice number is preserved), a receptionist/dentist permission matrix (receptionist can create/issue/void
  but not delete — UI-hidden and direct-API-403/422 checks — dentist is fully read-only), and an
  RTL/currency-isolation smoke check.

## Known Limitations / Deferred (non-blocking)

Full detail and revisit conditions live in `TECH_DEBT.md`; summarized here for this module:

- **No per-component Vitest coverage yet** for the Invoice Vue components (stores/services only) — a smaller
  gap than the E2E one was; not blocking, worth closing opportunistically.
- **No "Add from Treatment Plan" E2E coverage** — the golden path exercises the manual-charge add path only;
  `AddFromTreatmentPlanDialog.vue`'s picker flow is covered by `InvoiceItemTest.php`'s Feature-level
  `test_add_item_snapshots_from_a_completed_treatment_plan_item` at the HTTP layer, but not driven through
  its own UI in the E2E suite. Small, worth adding alongside any future substantive touch to that dialog.
- **No dark-mode-toggle E2E check** — consistent with the rest of this codebase: no Playwright spec anywhere
  automates a dark-mode toggle (confirmed by a repo-wide search during this same hardening pass).
- Insurance claims, multi-currency-per-invoice, recurring billing, PDF generation/emailing, tax-rate catalogs,
  a dedicated procedure-pricing catalog — all deferred per the design doc, none block V1 production use.

None of the above block production use of the module as scoped.

## SaaS Readiness

Reviewed at design time, not re-litigated here — summary: DentalSuite V1 is single-organization; this module
introduces no new tenancy gap beyond what already exists system-wide. Like Treatment Plans, it carries
commercially sensitive pricing/financial data — worth extra verification attention whenever multi-tenancy is
actually built. Full migration-path detail mirrors Dental Chart's/Treatment Plans' already-documented path —
no new mechanism needed for this module.

## Completion

Migration, Model, Validation, Service, Policy, API, Vue Pages, Documentation — all present. Backend HTTP-layer
test coverage and a permanent E2E suite — the two real gaps this module carried relative to
Appointments/Dental Chart's bar — were closed in the 2026-08-18 production-hardening pass. **Verdict:
Production Ready**, with the two small, non-blocking items above (per-component Vitest coverage,
"Add from Treatment Plan" E2E coverage) left as future opportunistic follow-ups, not open gaps against this
project's Production Ready bar.

Already merged to `main` (`feature/treatment-plans`, commit `0677128`, 2026-07-25) — the production-hardening
work itself lands via its own `test/billing-e2e` branch/PR, per the project's standing git workflow.
