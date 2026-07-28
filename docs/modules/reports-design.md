# Reports — Module Design (Approved, 2026-07-28)

**Status: Design approved (2026-07-28); implementation starting on `feature/reports`.** This module follows
Imaging in the planned order (`PROJECT_CONTEXT.md`): Reports next, then Settings, then AI Assistant.

## Approval & Decision Log (2026-07-28)

All five proposed points approved as recommended:

1. **V1 report catalog** (§4) — **APPROVED as proposed**: Production, Collections, A/R Aging, Appointment
   Analytics, Treatment Plan Acceptance, New Patients.
2. **No new tables; `ReportService` over existing data** (§3) — **APPROVED**, with the added requirement
   that its queries stay written so a future per-clinic scoping clause (`WHERE clinic_id = ...`) or a
   materialized-view/cache layer could be added later without a reshape (see the new **multi-tenant** and
   **performance** requirements below — carried into §3/§4's finalized query notes).
3. **Permissions** (§5) — **APPROVED**: financial reports (Production/Collections/A-R Aging) admin-only;
   operational reports (Appointment Analytics/Treatment Plan Acceptance/New Patients) admin+dentist+
   receptionist. **Explicit added requirement**: enforced at the API layer (Gate + Form Request
   `authorize()`), not just hidden in the frontend nav — this was already the design (§5/§6), reconfirmed
   here since it's a hard requirement, not just a recommendation.
4. **Export** (§6/§7) — **APPROVED**: CSV only in V1; PDF, scheduled reports, and emailing deferred (§8
   decisions 3–4 stand as proposed).
5. **Dashboard `monthly_revenue`** (§1/§7) — **APPROVED**: replace the hardcoded `0` with a real value,
   **with the explicit requirement that the calculation lives in exactly one place** — `ReportService`. See
   the finalized §7 note: `DashboardService` calls `ReportService::collections()` directly; it does not
   reimplement the aggregation.

**Four standing architectural requirements applied to this module** (per `PROJECT_CONTEXT.md`'s permanent
SaaS-multi-tenant-readiness and PWA/mobile-first principles, plus two module-specific ones raised at
approval):

- **SaaS multi-tenant readiness**: no `ReportService` query may assume "there is only one clinic" in a way
  that would force a rewrite later — concretely, every query filters by explicit, passed-in criteria (date
  range, optional `dentist_id`) rather than an unscoped `Model::all()`/table-wide implicit assumption, so a
  future `clinic_id` column is an additive `WHERE` clause, not a redesign. No query in §4 reads from more
  than the tables it names, and none hardcodes a single-clinic assumption beyond what already holds
  system-wide (`PROJECT_CONTEXT.md`: "Single Organization (No Multi Tenant in Version 1)" — Reports doesn't
  introduce a new instance of that assumption, it inherits the existing one).
- **Responsive / PWA / mobile-first**: report tables, date-range filters, and the CSV export action must all
  work touch-first on a phone/tablet, same bar as every prior module — the frontend plan (§7) uses the same
  PrimeVue `DataTable` + responsive layout primitives already proven across Inventory/Laboratory/Imaging, no
  new pattern needed.
- **Data/presentation separation for reuse** (new, module-specific): `ReportService`'s public methods return
  plain data (arrays/DTOs), with zero knowledge of HTTP, CSV formatting, or the Dashboard — `ReportController`
  formats the HTTP response (JSON or CSV) and `DashboardService` calls `ReportService` directly for
  `monthly_revenue`. This is what makes decision 5 above possible without duplicated aggregation logic, and
  is also what would let a future scheduled-report job (§8 decision 4, still deferred) reuse the exact same
  methods later.
- **Performance notes documented, not pre-optimized** (new, module-specific): §4's finalized query notes
  call out which reports touch the largest/most-joined tables (Production, joining `invoice_items` through
  `treatment_plan_items`/`treatment_plans`) and note that indexing/materialized-views/caching are a future
  option if real usage shows a need — consistent with `PROJECT_CONTEXT.md`'s "do not over-engineer" /
  "performance before complexity" ordering: V1 ships plain, indexed queries; nothing is cached or
  precomputed until a real slow-query is observed.

## 0. Competitive Research (required before any design, per standing product philosophy)

| Source | Finding | Taken / Rejected for this design |
|---|---|---|
| **Open Dental** ([Reports overview](https://opendental.com/manual/reports.html), [Complex Report System / User Query](https://www.opendental.com/manual/reportcomplex.html)) | Reports sit under five buckets: Standard Reports, Standard Favorites, Graphic Reports, User Query, Query Favorites. Concrete named reports include Production and Income, Provider Payroll Production and Income, Daily Payments, Aging of Accounts Receivable, Outstanding Insurance Claims, Active Patients, New Patients, Appointments, Referral Analysis. Export is CSV/XLS/text only — **no PDF, no scheduling/emailing** anywhere in the manual. Reports are gated by per-user Report Setup security permissions, and can be marked as Favorites. | **Taken**: the core named-report shape (Production, A/R Aging, New Patients, Appointments) as the basis for this module's V1 catalog (§4). **Taken**: CSV-only export, no PDF — matches this codebase's own "no new dependency without a clear need" discipline (Laboratory/Imaging both chose browser-print over a PDF library). **Rejected**: the User Query ad-hoc SQL builder — directly contradicts `PROJECT_CONTEXT.md`'s "keep it simple, don't over-engineer" philosophy and has no precedent anywhere else in this codebase; a fixed, well-chosen report catalog covers the same ground with none of the security surface of exposing raw SQL to end users. |
| **Dentrix Ascend** ([Practice Data & Analytics](https://www.dentrixascend.com/dental-solutions/practice-data-and-analytics/)) | Explicitly splits into two product surfaces: a live **Performance Dashboard** (no export/build step, glanceable KPIs) and a separate **Reports / Power Reporting** layer (pivot-style builder: rows/columns/measures/filters, named reports like Aged Receivables, Provider A/R Totals) that supports **scheduling reports by role/location**. | **Taken**: the dashboard-vs-report split itself — this codebase already has this exact line (Inventory's `LowStockWidget`, Laboratory's `DueLabCasesWidget` are dashboard widgets; this module is the report layer). **Confirms** the existing `DashboardService::summary()`'s `monthly_revenue` placeholder belongs to this split too — it's a dashboard-tier number this module's Collections Report (§4.2) now makes real. **Deliberately rejected**: report scheduling/emailing — no mail/notification infrastructure exists anywhere in this codebase yet (`AppointmentReminder` has a `channel`/`sent_at` shape but nothing currently sends through it), so this would require building notification infra as a side effect of a reporting module. Deferred to V2. **Rejected**: the pivot-builder UI — same "don't over-engineer" reasoning as Open Dental's User Query. |
| **CareStack** ([Analytics & Reporting](https://carestack.com/dental-software/features/analytics-reporting), [Reporting](https://carestack.com/dental-software/features/reporting)) | Named reports include Aging Report, Daily Production Numbers, End of Day Report, Appointments by Users, New Patient Reports, Insurance Pending Procedures. Explicit **three-tier role-based permission model**: full restriction, selective/positional hiding of individual reports, and Super Admin full access. Filterable across provider × user × location. | **Taken**: the role-gating principle, adapted to this codebase's simpler two-role-tier reality (no location dimension yet, per `PROJECT_CONTEXT.md`'s single-organization V1 scope) — financial reports (Production/Collections/A-R Aging) are business-sensitive practice-wide numbers, gated stricter than this codebase's usual "everyone can view" precedent for per-patient records (§5). **Taken**: dentist-filterable views on the operational reports (Appointment Analytics, Treatment Plan Acceptance), mirroring the provider-filter pattern. |
| **Curve Dental** ([Business Analytics](https://www.curvedental.com/business-analytics), [Dental Report Software](https://www.curvedental.com/dental-report-software)) | Ships a fixed 6-KPI dashboard (A/R, Collections, Production, New Patients, Recare, Treatment Plan Value) as the daily-glance layer, with a separate customizable/save-as-template report builder underneath for anything deeper. | **Taken**: 4 of Curve's 6 KPIs map directly onto this module's report catalog (A/R, Collections, Production, New Patients). **Rejected/deferred**: "Recare" (recall) — DentalSuite's `Patient` model has no recall-due-date or last-visit-tracking field yet; building this report would require a schema addition to Patients that's out of this module's own scope (this module reports on data that exists, it doesn't add new tracked fields to other modules — same discipline Laboratory/Imaging used for not touching sibling modules). Named explicitly in §8/§9 rather than silently dropped. **Rejected**: the save-as-template custom builder, same reasoning as Open Dental's User Query above. |

**Net effect**: all four competitors converge on the same core report set — production, collections/payments,
A/R aging, new patients, and appointment activity — presented as a **distinct top-level module** separate
from per-record dashboards, financial reports gated stricter than operational ones, CSV (not PDF) as the
export format, and no scheduling/emailing without dedicated notification infrastructure. This design keeps
that shape and explicitly declines the two features every source treats as a power-user add-on beyond the
core (ad-hoc query/pivot builders, and scheduled/emailed delivery) as out of scope for V1 (§8/§9).

## 1. Module Goal / Purpose

Give the practice a single cross-module surface that answers the standard "how is the practice doing"
questions — production, collections, outstanding receivables, new-patient growth, appointment activity, and
treatment-plan case-acceptance rate — by reading across Patients, Appointments, Billing (Invoices/
InvoiceItems), Payments, and Treatment Plans. This closes a real, present gap: `DashboardService::summary()`
(`backend/app/Services/DashboardService.php:19`) has hardcoded `'monthly_revenue' => 0` with the comment "0
for modules that are not implemented yet" since Dashboard's original implementation — this module makes
that number real (§7 decision).

Like Inventory/Laboratory, this is a read-only, back-office module: it never mutates any other module's
data, it only queries it.

## 2. Scope (V1)

**In scope** — six reports (§4), each with a date-range filter (default: current month, using
`frontend/src/lib/date.ts` helpers per the project's Datetime Policy), a summary/KPI header, a detail
`DataTable`, and CSV export:
1. Production Report
2. Collections Report
3. Accounts Receivable (A/R) Aging Report
4. Appointment Analytics
5. Treatment Plan Acceptance Report
6. New Patients Report

Plus: wiring `DashboardService::summary()`'s `monthly_revenue` to the real Collections computation (§7
decision 2), and filling in the existing `nav.reports` sidebar scaffold (`frontend/src/config/navigation.ts:127-130`,
currently `comingSoon: true`) with real routes.

**Explicitly out of scope for V1** (named, not silently dropped — see §8/§9):
- Ad-hoc query/pivot-style report builder (Open Dental's User Query, Dentrix Ascend's Power Reporting).
- Scheduled or emailed reports — no notification/mail infrastructure exists in this codebase yet.
- PDF export — CSV + browser print only, no new dependency.
- Insurance/claims reports — DentalSuite has no insurance/claims module.
- Provider payroll reports — no payroll/compensation data exists anywhere in this codebase.
- Recall / unscheduled-treatment report — `Patient` has no recall-tracking field yet.
- Inventory- or Laboratory-specific reports — already served by their own Dashboard widgets
  (`LowStockWidget`, `DueLabCasesWidget`); duplicating them here would be redundant, not additive.
- Multi-location/clinic roll-up views — V1 is single-organization (`PROJECT_CONTEXT.md`).

## 3. Data Model

**No new database tables.** Every report is a live, on-demand aggregate query over existing tables
(`invoices`, `invoice_items`, `payments`, `appointments`, `patients`, `treatment_plans`,
`treatment_plan_items`) — nothing is persisted or snapshotted, mirroring how `DashboardService` already
works and this codebase's standing discipline against introducing a new abstraction (a "saved report" or
"report run" table) without a demonstrated need. A single `ReportService` class (one public method per
report) is the only new backend component beyond controllers/requests/routes.

Each report method accepts a plain filter shape: `date_from`, `date_to` (both required, validated by a
per-endpoint Form Request), and `dentist_id` (nullable, where applicable — see §4). Every method returns
plain arrays/DTOs only — no HTTP, CSV, or view concerns leak into `ReportService` (per the Approval Log's
data/presentation-separation requirement), which is what lets `ReportController` format JSON or CSV from the
same call, and lets `DashboardService` call `ReportService::collections()` directly for `monthly_revenue`
instead of reimplementing the aggregation.

**Performance note (documented per Approval Log, not pre-optimized)**: §4.1's Production Report is the one
query that joins three tables deep (`invoice_items` → `treatment_plan_items` → `treatment_plans`) to
attribute a dentist; every other report reads at most one join away from its primary table. All date-range
filters hit columns already indexed for their owning module's own use (`invoices.issue_date`,
`payments.received_at`, `appointments.scheduled_at`, `treatment_plans.presented_at`, `patients.created_at`),
so no new index is anticipated for V1. If real usage later shows the Production query is slow at scale, the
options (added index on `treatment_plan_items.treatment_plan_id`, a materialized/cached monthly rollup) are
additive changes to `ReportService`'s internals only — nothing in the API or frontend contract would need to
change, since callers only ever see the returned data shape.

## 4. Report Catalog

### 4.1 Production Report
**Question answered**: how much dentistry was billed out, and by whom, in a period (Open Dental's
"Production and Income", CareStack's "Daily Production Numbers", Curve's "Production" KPI).
- **Source**: `InvoiceItem` rows of `kind = charge` belonging to non-`Draft` invoices (an amount hasn't
  really been "produced" until the invoice is issued), joined through `treatment_plan_item_id →
  TreatmentPlanItem.treatment_plan_id → TreatmentPlan.dentist_id` to attribute a provider; items with no
  `treatment_plan_item_id` (manual invoice lines) are grouped under "Unassigned".
- **Filters**: `date_from`/`date_to` (against `invoices.issue_date`), optional `dentist_id`.
- **Output**: total production for the period, a by-dentist breakdown table (production amount, item
  count), and a detail grid (invoice #, patient, date, description, dentist, amount).

### 4.2 Collections Report
**Question answered**: how much cash actually came in (Open Dental's "Daily Payments", Curve's
"Collections" KPI) — distinct from Production, since production is billed, collections is paid.
- **Source**: `Payment` rows where `received_at` falls in range, excluding refund rows (`refunded_payment_id`
  is not null marks a row as a refund of an earlier payment — refunds net out of the total, not counted
  as a separate negative "collection" line, matching how `Payment.amount`'s sign already works per its own
  model).
- **Filters**: `date_from`/`date_to` (against `received_at`), optional `method` (`PaymentMethod` enum).
- **Output**: total collected for the period, a by-method breakdown (cash/card/bank_transfer/other), and a
  detail grid (date, patient, invoice #, method, amount).
- **This is the report that feeds `DashboardService::summary()`'s `monthly_revenue`** (§7 decision 2) —
  current calendar month's total, computed via this same method with no `method` filter.

### 4.3 Accounts Receivable (A/R) Aging Report
**Question answered**: how much is owed, and how overdue is it (Open Dental's "Aging of Accounts
Receivable", Dentrix Ascend's "Aged Receivables Report", CareStack's "Aging Report", Curve's "A/R" KPI —
the one report every single competitor researched has, unprompted).
- **Source**: every `Invoice` with `status = Issued` where `balance_due > 0` (reusing
  `InvoiceResource`'s existing `amount_paid`/`balance_due` computation — no new formula). Bucketed by days
  since `due_date`: **Current** (not yet due), **1–30**, **31–60**, **61–90**, **90+**.
- **Filters**: none (this report is a point-in-time snapshot, not a date-range query — matches every
  competitor's own framing of "aging" as "as of today", not "as of a past range").
- **Output**: total outstanding + a bucket breakdown, and a detail grid (patient, invoice #, due date, days
  overdue, balance due).

### 4.4 Appointment Analytics
**Question answered**: how the schedule actually played out — completed vs. cancelled vs. no-show rates
(Open Dental's "Appointments"/"Broken Appointments" graphic report, CareStack's "Appointments by Users").
- **Source**: `Appointment` rows where `scheduled_at` (or the equivalent start-time column) falls in
  range, grouped by `AppointmentStatus`.
- **Filters**: `date_from`/`date_to`, optional `dentist_id`.
- **Output**: total appointments, counts + percentage by status (`Completed`/`Cancelled`/`NoShow`/etc. per
  the existing `AppointmentStatus` enum), a computed **no-show rate** and **cancellation rate** headline
  KPI (§8 decision 7), and a detail grid (date, patient, dentist, type, status).

### 4.5 Treatment Plan Acceptance Report
**Question answered**: of the treatment presented to patients, how much gets accepted — the case
acceptance rate every practice-management KPI list tracks, though none of the four sources named it as a
single distinct report the way they did A/R/Production (it's the closest analogue to Curve's "Treatment
Plan Value" KPI).
- **Source**: `TreatmentPlan` rows where `presented_at` falls in range, grouped by final `status`
  (`Accepted`/`Rejected`/still-`Presented`/`Cancelled`).
- **Filters**: `date_from`/`date_to` (against `presented_at`), optional `dentist_id`.
- **Output**: count and **acceptance rate** (accepted ÷ presented) as the headline KPI, plus accepted-plan
  dollar value (sum of each accepted plan's items' `estimated_cost`, the same derived value
  `TreatmentPlanItem` already computes elsewhere — nothing new is computed here), and a detail grid (patient,
  dentist, presented date, status, value).

### 4.6 New Patients Report
**Question answered**: patient-growth trend over a period (Open Dental's "New Patients" list report,
CareStack's "New Patient Reports", Curve's "New Patients" KPI).
- **Source**: `Patient` rows where `created_at` falls in range.
- **Filters**: `date_from`/`date_to`.
- **Output**: total new patients for the period (+ a simple month-over-month trend line when the range
  spans multiple months), and a detail grid (name, patient code, registration date).

## 5. Permissions

Financial reports (§4.1–4.3) expose practice-wide revenue and receivables — categorically more sensitive
than this codebase's usual "everyone can view, write is restricted" precedent (`InvoicePolicy`,
`PaymentPolicy`, etc. all give dentists read access to an *individual patient's* billing, in the context of
a specific clinical conversation; these reports expose the *entire clinic's* aggregate financial position at
once). CareStack's explicit three-tier role model (§0) is the clearest precedent for treating this
differently. Operational reports (§4.4–4.6) carry no such sensitivity and follow the normal "everyone can
view" convention every other module's `viewAny` already uses.

Since there is no natural Eloquent model to attach a Policy to (unlike every prior module), this uses two
plain Laravel Gate abilities defined in `AppServiceProvider::boot()` — the standard Laravel pattern for an
ability that isn't tied to a specific resource:

```php
Gate::define('view-financial-reports', fn (User $user) => $user->role === UserRole::Admin);
Gate::define('view-operational-reports', fn (User $user) => true);
```

Each report's Form Request `authorize()` calls `$this->user()->can('view-financial-reports')` or
`can('view-operational-reports')` accordingly — same convention every existing Form Request already uses
(`$this->user()->can($ability, $model)`), just against a Gate ability instead of a model-bound Policy
method.

| Report | Roles |
|---|---|
| Production | admin only |
| Collections | admin only |
| A/R Aging | admin only |
| Appointment Analytics | admin, dentist, receptionist |
| Treatment Plan Acceptance | admin, dentist, receptionist |
| New Patients | admin, dentist, receptionist |

## 6. API Design

Six read-only `GET` endpoints under `/api/reports/*`, one per report, each backed by its own Form Request
(query-param validation + the `authorize()` gate check above) and delegating to `ReportService`:

```
GET /api/reports/production?date_from=&date_to=&dentist_id=
GET /api/reports/collections?date_from=&date_to=&method=
GET /api/reports/ar-aging
GET /api/reports/appointments?date_from=&date_to=&dentist_id=
GET /api/reports/treatment-plan-acceptance?date_from=&date_to=&dentist_id=
GET /api/reports/new-patients?date_from=&date_to=
```

**CSV export**: `?format=csv` on any of the above streams a `text/csv` download via PHP's native
`fputcsv`/`StreamedResponse` — no new Composer dependency, matching every prior module's "no new package
without a clear need" discipline (and Open Dental's own CSV/text-only precedent, §0).

## 7. Frontend

- Fill in the existing `nav.reports` scaffold (`frontend/src/config/navigation.ts:127-130`) as a real
  top-level group, exact convention as Inventory/Laboratory's own child-list shape — each child report is
  its own route, financial ones carrying `roles: ['admin']` on the nav item (same pattern as
  `inventory.nav.suppliers`/`laboratory.nav.labs`):
  ```
  nav.reports (top-level, routeName: 'reports')
    ├─ reports.nav.production            roles: ['admin']
    ├─ reports.nav.collections           roles: ['admin']
    ├─ reports.nav.arAging               roles: ['admin']
    ├─ reports.nav.appointments
    ├─ reports.nav.treatmentPlanAcceptance
    └─ reports.nav.newPatients
  ```
  (Router-level guards mirror the nav gate, same double-enforcement convention every prior module used —
  nav visibility is not the only access control.)
- `ReportsHomeView.vue` — a card grid landing page (one card per report the current role can see),
  mirroring Dashboard's own card-based landing pattern.
- One view per report, each composed from shared pieces (kept in `components/reports/`):
  - A `ReportDateRangeFilter.vue` (shared across all date-ranged reports, using
    `frontend/src/lib/date.ts`'s `toLocalDateString`/`parseLocalDate` per the project's Datetime Policy —
    never raw `Date` handling).
  - A summary/KPI header (stat cards — total production, collection total, acceptance rate, etc.).
  - A PrimeVue `DataTable` detail grid with the report's row-level data.
  - An **Export CSV** button (hits the same endpoint with `?format=csv`) and a **Print** button
    (`@media print` browser stylesheet, same no-new-dependency approach as Laboratory's printable slip) —
    no PDF library.
- `DashboardService::summary()` gains a real `monthly_revenue` value by calling
  `ReportService::collections()` for the current calendar month, replacing the hardcoded `0` (§1, §4.2).
- Full en/ar/tr i18n, dark mode, RTL, keyboard access, responsive/PWA — enterprise UX bar per standing
  philosophy, and the standing SaaS-multi-tenant/PWA-mobile-first principles (§9 of `PROJECT_CONTEXT.md`):
  every query here is already scoped to "the whole (single) organization's data", which is exactly the
  shape that would become "the current tenant's data" under a future multi-tenant model — no rework
  implied by this design.

## 8. Open Decisions (for approval)

1. **Financial reports admin-only, operational reports open to all roles** (§5) — recommend **approve as
   proposed**. This is a new, stricter tier than this codebase's usual "everyone can view" convention, but
   directly justified by CareStack's explicit precedent (§0) and by these reports exposing practice-wide
   aggregate revenue rather than one patient's billing in clinical context.
2. **Wire `DashboardService`'s `monthly_revenue` to real data as part of this module** (§1, §7) — recommend
   **yes, fix now**. It's a one-line change once `ReportService::collections()` exists, and leaving a
   known-hardcoded `0` in place while building the very module that computes the real number would be
   deferring a trivial, directly-related fix for no reason.
3. **CSV export only, no PDF** — recommend **approve as proposed**, matching Open Dental's own precedent
   (§0) and this codebase's "browser print instead of a PDF dependency" convention (Laboratory's slip,
   Imaging's non-persisted viewer).
4. **No scheduled/emailed reports in V1** — recommend **defer to V2**. No mail/notification infrastructure
   exists anywhere in this codebase (`AppointmentReminder`'s `channel`/`sent_at` fields are unused by any
   sending code found in this repo) — building that infra as a side effect of Reports would be a much
   larger, separate piece of work than this module's own scope.
5. **No ad-hoc/custom report builder (Open Dental's User Query, Dentrix Ascend's Power Reporting)** —
   recommend **out of scope permanently, not just V2**, per `PROJECT_CONTEXT.md`'s explicit "keep it
   simple, do not over-engineer" philosophy; exposing raw/pivot query access to end users is also a
   meaningfully larger security surface than a fixed report catalog.
6. **No Recall / unscheduled-treatment report** — recommend **defer until `Patient` gains real
   recall-tracking fields** (a separate, future decision for the Patients module, not this one) — this
   module reports on data that already exists elsewhere; it doesn't add new tracked fields to a sibling
   module to make a report possible.
7. **Appointment Analytics includes a computed no-show-rate/cancellation-rate KPI** (§4.4) — recommend
   **approve as proposed**; cheap to compute from data already being read, and named as a de facto standard
   metric across all four competitors researched.

## 9. Explicitly Out of Scope for V1 (summary — see §2/§8 for full reasoning)

- Ad-hoc/custom report builder or pivot tool.
- Scheduled or emailed reports.
- PDF export.
- Insurance/claims reports (no insurance module exists).
- Provider payroll reports.
- Recall/unscheduled-treatment report (no recall-tracking field on `Patient` yet).
- Inventory/Laboratory-specific reports (already covered by their own Dashboard widgets).
- Multi-location/clinic roll-up views (V1 is single-organization).
