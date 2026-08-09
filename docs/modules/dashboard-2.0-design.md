# Dashboard 2.0 — Design Doc

**Status: Design Phase — awaiting approval. No implementation code written yet.**

**Roadmap position**: Phase 3 of the 8-phase post-roadmap initiative (Stabilization → Patient Profile →
**Dashboard 2.0** → Advanced Permissions & Audit → SaaS Multi-Tenant Prep → PWA & Mobile → AI Assistant
Expansion → Launch Preparation). Reconciles and **absorbs**
`docs/modules/frontend-visual-redesign-design.md` §6 (the Premium Visual Redesign's Dashboard-restyle
plan, previously "Not started") — that section is superseded by this doc; §6 there should be marked
"superseded by `dashboard-2.0-design.md`" once this is approved, not implemented separately.

**Scope decision (confirmed with user)**: **Functional 2.0**, not a restyle-only ship. Visual polish (§6's
restyle) plus new data-backed widgets — but reusing existing backend services wherever they already exist,
same discipline as `DashboardService::monthlyRevenue()` already delegating to `ReportService::collections()`
rather than reimplementing it, and as `BillingSummaryService` (Phase 2.2) being a small standalone aggregate
service rather than modifying `InvoiceService`/`PaymentService`.

---

## 0. Audit — current state (ground truth, not assumption)

**Backend** (`backend/app/Services/DashboardService.php`, `backend/app/Http/Controllers/Api/DashboardController.php`):
- `GET /dashboard/summary` (`routes/api.php:47`) returns exactly `{total_patients, today_appointments,
  monthly_revenue}`. Sits inside `auth:sanctum` middleware only — **no Form Request, no `authorize()` call,
  no policy, no role check of any kind.**
- `monthly_revenue` is computed via `ReportService::collections()` for the current calendar month
  (`DashboardService.php:31-39`) — the exact same aggregation `reports/collections` exposes.

**Frontend** (`frontend/src/views/DashboardView.vue`): 3 unconditional stat cards (patients/appointments/
revenue, `h-11 w-11` icon badge, `text-xl` number, all `primary`-tinted) + `TodayScheduleWidget` +
`UpcomingAppointmentsWidget` (both scoped `own`/`all` via `auth.isDentist`) + `LowStockWidget` +
`DueLabCasesWidget` + `AiQuestionBox`. No trends, no role-gated widgets, no empty-state component reuse
(ad hoc `Message severity="error"` only).

**A real security gap, found during this audit, not previously logged**: `reports/collections` (identical
underlying data to `monthly_revenue`) is gated behind `Gate::define('view-financial-reports', fn (User $user)
=> $user->role === UserRole::Admin)` (`AppServiceProvider.php:47-48`) — **admin-only**. But
`/dashboard/summary` returns the same figure to **every authenticated role**, no gate at all. A receptionist
or dentist can already see monthly revenue through the Dashboard that they are explicitly blocked from
seeing through Reports. This is the same class of aggregation-point permission leak this project has
caught before (Timeline's per-category enforcement, §9A/Phase 2.6's Security Architecture Decision) — it
predates this phase and is being fixed as part of it, not introduced by it. See §3.

**Reusable data already computed elsewhere** — `ReportService.php` (all gated via Form Request
`authorize()`, not controller/route-level):
| Method | Gate | Shape |
|---|---|---|
| `production(dateFrom, dateTo, ?dentistId)` | `view-financial-reports` (admin-only) | `{summary: {total, by_dentist[]}, rows[]}` |
| `collections(dateFrom, dateTo, ?method)` | `view-financial-reports` (admin-only) | `{summary: {total, by_method[]}, rows[]}` |
| `arAging()` — point-in-time, no params | `view-financial-reports` (admin-only) | `{summary: {total, buckets: {current, 1_30, 31_60, 61_90, 90_plus}}, rows[]}` |
| `treatmentPlanAcceptance(dateFrom, dateTo, ?dentistId)` | `view-operational-reports` (all roles) | `{summary: {presented, accepted, rejected, acceptance_rate, accepted_value}, rows[]}` |

**No "goal" concept exists anywhere** — confirmed by a full-backend search (models, migrations,
`ClinicSetting`, `BillingSetting`): no `monthly_goal`/`revenue_target`/`quota`/`budget` column or property
anywhere. Building actual-vs-goal would require a new settings field + Settings UI + migration — real new
scope. **Decision (confirmed with user): skip goal-setting. Use period-over-period trend instead** (this
month vs. last month, computed by calling `production()`/`collections()` twice with different date ranges)
— zero new storage, same "no fabricated trend" rule §6 already established. Goal-setting is logged as
explicitly deferred, not silently dropped (§7).

**"Accepted but not yet scheduled" treatment — answerable, but not as an existing query.**
`TreatmentPlanItemStatus` is deliberately only 3 states (`Planned, Completed, Cancelled`) — "scheduled" is
never stored, it's derived from `appointment_id` pointing to a non-cancelled `Appointment`
(`TreatmentPlanItemStatus.php:11-15` docblock). So "unscheduled accepted treatment" = `TreatmentPlan.status
= Accepted` AND `TreatmentPlanItem.status = Planned` AND (`appointment_id IS NULL` OR its linked
`Appointment.status = Cancelled`). No existing `ReportService` method assembles this join — a new query is
needed (§2.3), same pattern as `BillingSummaryService` being new-but-small rather than bolted onto an
existing service.

**Role-check helpers already available** (`frontend/src/stores/auth.ts:10-15`): `isAuthenticated, isAdmin,
isDentist, isReceptionist, canManageAppointments`. No `canViewFinancials`-style helper yet — needed for
this phase (§4).

---

## 1. New backend surface

Two endpoints, split by the same financial/operational boundary Reports already uses — not one endpoint
with conditional fields, so the gate is enforced at the route/Form-Request layer (this project's established
pattern), not by the frontend remembering to hide a field.

### 1.1 `GET /dashboard/summary` — operational, open to every role (unchanged gate: `auth:sanctum` only,
matching `view-operational-reports`' "open to all" semantics)

```
{
  "total_patients": 118,
  "today_appointments": 6,
  "unscheduled_accepted_treatment": {
    "count": 4,
    "items": [
      { "patient": "...", "patient_id": "...", "treatment_plan_id": "...", "item_description": "...", "accepted_at": "..." }
    ]
  }
}
```

`monthly_revenue` is **removed from this endpoint** — it moves to §1.2, closing the leak in §0. This is a
breaking response-shape change for this one field; frontend must stop rendering it here (§4).

`unscheduled_accepted_treatment` — new `DashboardService::unscheduledAcceptedTreatment(int $limit = 5):
array` method: joins `TreatmentPlan` (status `Accepted`) → `TreatmentPlanItem` (status `Planned`) →
left-join `Appointment`, filtered to `appointment_id IS NULL OR appointments.status = 'cancelled'`. Returns
a capped `items` list (widget-sized, not a full report — a full drill-down list is out of scope, §7) plus a
`count` computed from the same query without the limit.

### 1.2 `GET /dashboard/financial-summary` — new endpoint, gated `view-financial-reports` (admin-only,
enforced via a new `DashboardFinancialSummaryRequest` Form Request calling
`$this->user()->can('view-financial-reports')`, mirroring `ProductionReportRequest`/`CollectionsReportRequest`/
`ArAgingReportRequest` exactly — same Gate, same pattern, no new authorization concept invented)

```
{
  "monthly_revenue": "12500.00",
  "production_trend": { "current": "14200.00", "previous": "11800.00", "change_pct": 20.34 },
  "collections_trend": { "current": "12500.00", "previous": "13100.00", "change_pct": -4.58 },
  "ar_aging": { "total": "3400.00", "buckets": { "current": "1200.00", "1_30": "900.00", "31_60": "700.00", "61_90": "400.00", "90_plus": "200.00" } }
}
```

New `DashboardService` methods, each a thin wrapper calling existing `ReportService` methods twice (current
period, previous period of equal length) and computing `change_pct` — no new aggregation logic for
production/collections, only for the trend delta itself:
- `productionTrend(): array` → `ReportService::production()` for this calendar month and last calendar month.
- `collectionsTrend(): array` → `ReportService::collections()`, same two ranges (replaces the old
  `monthlyRevenue()` single-period call — `monthly_revenue` in the response above is
  `collections_trend.current`, kept as a named top-level field too since it's the headline number the
  restyled stat card shows).
- `arAgingSnapshot(): array` → `ReportService::arAging()` directly, unchanged shape, no new logic — a
  pure pass-through, kept as its own method only for naming symmetry with the other two.

`change_pct = null` (not `0`) when the previous period has zero activity — never divide by zero, never
fabricate a percentage from nothing (same "no fabricated trend" rule).

---

## 2. Security fix (§0's finding) — explicit remediation

- `monthly_revenue` moves behind `view-financial-reports`, closing the leak. This is logged as a security
  fix in `docs/decisions.md` at implementation time (same treatment as Timeline's §9A Security Architecture
  Decision) — the finding is real and predates this phase, so it belongs in the decisions log, not swept in
  silently as an incidental side effect of a restyle.
- New `DashboardFinancialSummaryRequest`/(if needed) `DashboardSummaryRequest` Form Requests, mirroring
  `ReportController`'s existing per-report Form Request `authorize()` convention exactly — no new
  authorization primitive, no new Gate, reusing `view-financial-reports`/`view-operational-reports` as-is.
- Regression test (backend Feature test): assert a receptionist/dentist session calling
  `/dashboard/financial-summary` gets `403`, and that `/dashboard/summary` never includes `monthly_revenue`
  or any financial key for any role — the same "assert directly against the response body, not just UI
  hiding" discipline the Timeline security test used (§9A precedent).

---

## 3. Frontend

### 3.1 Visual restyle (absorbs `frontend-visual-redesign-design.md` §6 in full, superseding it)
- Stat cards: `h-12 w-12` icon badge (was `h-11 w-11`), distinct soft-tinted ramp per card (patients=
  `primary`, appointments=`blue`, revenue=`purple`) instead of all-`primary-50`, `text-2xl` number (was
  `text-xl`).
- Trend badge (colored pill, `+/-N%`) on the revenue card only, fed by `collections_trend.change_pct` —
  never fabricated; renders nothing if `change_pct` is `null`. No new trend badge on `total_patients`/
  `today_appointments` — no equivalent historical comparison endpoint exists for those and building one is
  out of scope (§7).
- New shared `components/common/EmptyState.vue` (icon + message + optional action button) replaces the ad
  hoc `Message severity="error"` and each widget's own "no data" text — first cross-cutting use beyond
  Patient Profile's own `EmptyState.vue` from Phase 2.1 (confirm at implementation time whether that
  component is already generic enough to reuse as-is, or whether Phase 2.1's version is patient-profile-
  specific and this needs its own copy — audit before writing code, don't assume).
- `AiQuestionBox`: soft two-stop gradient (`from-primary-50 to-purple-50`, dark-mode token variants),
  rounded-xl, larger icon.
- Grid spacing: `gap-4` → `gap-6` throughout.

### 3.2 New widgets (role-gated by a new `auth.canViewFinancials` computed, added to
`frontend/src/stores/auth.ts` alongside the existing 5 helpers — `computed(() => isAdmin.value)`, named for
what it gates rather than duplicating `isAdmin` inline in every consuming component, matching this store's
existing naming convention like `canManageAppointments`)

- **`FinancialSnapshotWidget.vue`** (admin-only, `v-if="auth.canViewFinancials"`): production trend,
  collections trend, A/R aging total + bucket breakdown — one card, not three, to avoid crowding the grid
  with admin-only tiles most roles never see (design choice, not a hard requirement — revisit at
  implementation if it reads better split).
- **`UnscheduledTreatmentWidget.vue`** (all roles, matches `unscheduled_accepted_treatment`'s open gate):
  count + capped list, each row linking to that patient's Treatment Plans tab. Reuses `EmptyState.vue` when
  `count === 0` ("Nothing outstanding" framing, not an error state).
- Existing `LowStockWidget`/`DueLabCasesWidget`/`TodayScheduleWidget`/`UpcomingAppointmentsWidget` stay
  as-is, repositioned only if the new widgets' addition makes the grid layout worse (implementation-time
  call, not pre-decided here).

### 3.3 API/store
`dashboard.ts` Pinia store (new — none exists today, `DashboardView.vue` currently calls `api.get` directly
inline) fetching both endpoints; `financial-summary` fetch only triggered `if (auth.canViewFinancials)` —
skip the network call entirely for roles that would get `403`, not just hide the result.

---

## 4. i18n

New keys needed under `dashboard.*` (exact key names decided at implementation time, verified 3-locale
parity the same programmatic way every prior phase has done — see Phase 2.3/2.5's parity checks):
financial widget labels (production/collections/A-R aging bucket names — buckets already have precedent
copy in the Reports module's A/R Aging report view, reuse that phrasing rather than inventing new terms),
unscheduled-treatment widget labels, trend badge `+/-N%` formatting (LTR-forced like the existing
`dir="ltr"` on stat numbers — datetime/number formatting stays consistent with
`frontend/src/lib/date.ts`'s policy for anything crossing the API boundary).

---

## 5. Testing plan

- **Backend**: Feature tests for both endpoints per role (admin sees financial data, dentist/receptionist
  get `403` on `/dashboard/financial-summary` and never see financial keys on `/dashboard/summary`), unit
  tests for `unscheduledAcceptedTreatment()`'s join logic (cases: null `appointment_id`, cancelled-
  appointment `appointment_id`, active-appointment `appointment_id` — only the first two should count),
  unit tests for trend `change_pct` including the zero-previous-period `null` case.
- **Frontend**: `dashboard.ts` store tests (conditional financial fetch), widget component tests
  (`FinancialSnapshotWidget`/`UnscheduledTreatmentWidget` render/empty-state/role-gating), updated
  `DashboardView.test.ts`.
- **E2E**: extend `dashboard.spec.ts` (confirm it exists; if not, this is the first one) with the
  security-critical case this project always writes for a new aggregation point per §9A's precedent — a
  receptionist/dentist session's dashboard never shows financial data, asserted against rendered DOM *and*
  the network response is never even requested for that role.

---

## 6. Standing principles check

- **SaaS multi-tenant readiness**: both new endpoints are patient/clinic-implicit-scoped exactly like every
  existing endpoint (single-org V1) — no schema decision here narrows a future multi-clinic model any more
  than the existing `ClinicSetting`/`ReportService` already do.
- **PWA & mobile-first**: new widgets follow the existing responsive grid (`grid-cols-1` → `sm:grid-cols-3`
  etc.) and touch-target sizing already established; no new desktop-only assumption introduced.

---

## 7. Explicitly deferred (named, not silently dropped)

- **Goal-setting** (production/collections targets) — needs new `ClinicSetting` field(s) + Settings UI;
  revisit if period-over-period trend turns out to be insufficient in practice.
- **Customizable/draggable dashboard layout** — no such infrastructure exists; out of scope.
- **Per-provider (per-dentist) breakdown widgets** — `ReportService::production()` already supports a
  `dentistId` filter, so this is cheap to add later, but no widget consumes it this phase.
- **Full unscheduled-treatment drill-down list/report** — the widget is capped and summary-only; a full
  report (with CSV export, like the other 6) is a separate, smaller follow-up if this proves valuable, not
  bundled here.
- **Notification-bell integration** for the new widgets — notifications remain functionally inert
  app-wide (`TECH_DEBT.md`), unchanged by this phase.

---

## 8. Implementation sequence (per this project's standing rule: not chained, each step implemented →
verified → reported → wait for approval before the next)

1. **Backend**: `unscheduledAcceptedTreatment()` + `productionTrend()`/`collectionsTrend()`/
   `arAgingSnapshot()` on `DashboardService`, the two Form Requests, route split, Feature tests, the
   `monthly_revenue` leak fix.
2. **Frontend data layer**: `dashboard.ts` store, `auth.canViewFinancials`, wired to the real backend.
3. **Frontend widgets + restyle**: `FinancialSnapshotWidget.vue`, `UnscheduledTreatmentWidget.vue`,
   `EmptyState.vue` reuse/creation, stat-card restyle, `AiQuestionBox` gradient, grid spacing — §3 in full.
4. **E2E** + final i18n parity pass + docs sync (`PROJECT_STATUS.md`, `CHANGELOG.md`, `decisions.md` for
   the security fix, `frontend-visual-redesign-design.md` §6 marked superseded).
