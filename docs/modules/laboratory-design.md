# Laboratory — Module Design (Production Ready, 2026-07-27)

**Status: Design approved and implemented same-day (2026-07-27); CI-confirmed Production Ready the
same day.** Labs/Lab Cases catalogs, the `LabCaseStatus` lifecycle (draft→sent→received→
quality_checked, plus cancelled), and the full frontend (LabsView/LabCasesView/LabCaseDetailView,
Dashboard widget, printable slip) are all in place, backend and frontend. Backend: Pint/PHPStan
clean, 815/815 backend tests green (58 Laboratory-specific: Feature + Unit). Frontend:
`vue-tsc`/ESLint/Prettier clean, 626/627 Vitest tests green (13 new Laboratory-specific; the one
unrelated failure — `PatientDetailView.test.ts`, an untouched pre-existing file — confirmed flaky
under that run's environment load, passes cleanly in isolation), production build green. A
permanent Playwright E2E suite (`frontend/e2e/laboratory.spec.ts`) is **confirmed via the GitHub
Actions API** across two `workflow_dispatch` runs on `feature/laboratory` — the first surfaced one
real bug (a duplicate-worded toast on rapid back-to-back status transitions), fixed and
re-verified. Final run (`30294033562`): **Backend success, Frontend success, all three
`laboratory.spec.ts` tests green with no retries.** See `TECH_DEBT.md` for the full diagnostic
trail, including two pre-existing, proven-unrelated E2E issues (a suite-wide rate-limit capacity
issue affecting `dental-chart.spec.ts`, and the recurring local-only PHPStan container quirk
already logged against Inventory) surfaced but not caused by this module.

**Update, 2026-08-08 (Phase 2.4, Patient Profile integration)**: this module itself is unchanged —
`Lab`/`LabCase`/`LabCaseService`/`LabCasePolicy`/`LabPolicy` and the standalone
`LabsView.vue`/`LabCasesView.vue`/`LabCaseDetailView.vue` pages all still work exactly as designed
here. What changed is that Lab Cases are now *also* reachable from a patient's own record — a new
`GET /patients/{patient}/lab-cases` endpoint, `Patient::labCases()`, and a `PatientLabCasesPanel.vue`
tab in `PatientDetailView.vue` — see `docs/modules/patient-laboratory-redesign-design.md` for that
integration's own full design and implementation record. One real pre-existing bug in this module's
own `StoreLabCaseRequest`/`UpdateLabCaseRequest` (`App\Rules\BelongsToPatient` throwing a SQL error
against `treatment_plan_item_id`, open in `TECH_DEBT.md` since 2026-07-28) was fixed as part of that
work, not here.

## Implementation Summary (added at Final Review, 2026-07-27)

**Backend**: `Lab` (admin-managed vendor catalog, mirrors `Supplier` exactly — `is_active`
soft-disable, no `Auditable`/`SoftDeletes`) and `LabCase` (`Auditable`, `HasUuids`, `SoftDeletes` —
mirrors `PurchaseOrder`'s transactional shape, one record per case). `LabCaseStatus` backed enum
(draft/sent/received/quality_checked/cancelled) with the same `transitionsFrom`/`canTransitionTo`/
`isTerminal` shape as `PurchaseOrderStatus`. `LabCaseService` enforces transitions, auto-calculates
`due_at` from the lab's `default_turnaround_days` on `send()` unless already manually set, blocks
`cancel()` once `received_at` is set. Permissions exactly as approved: `admin`+`dentist`
create/update/cancel (clinical), `admin`+`receptionist` send/receive/qualityCheck (logistics),
admin-only delete (draft-only). `tooth_numbers` stored as a JSON array of FDI codes;
`treatment_plan_item_id`/`appointment_id` are one-way traceability FKs, exact convention as
`TreatmentPlanItem.diagnosis_entry_id`.

**Frontend**: `useLabsStore` mirrors `stores/suppliers.ts` line-for-line. `LabsView.vue`
(admin-only catalog CRUD, mirrors `SuppliersView.vue`), `LabCasesView.vue` (paginated list, no
store, mirrors `PurchaseOrdersView.vue`) + `LabCaseDetailView.vue` (overview card +
`LabCaseActionsBar.vue` + a browser-print slip via `print:hidden`/`window.print()`, no PDF
dependency) + `CreateLabCaseDialog.vue` (reuses `appointments/PatientSearchSelect.vue` and
`appointments/DentistSelect.vue` cross-module — the same precedent `CreateTreatmentPlanDialog.vue`
already set reusing `DentistSelect.vue`, since both are genuinely generic, not Appointments-specific
domain logic). New top-level **Laboratory** sidebar group (Decision 8) and a
`DueLabCasesWidget.vue` Dashboard card. Full en/ar/tr i18n, 938/938/938 key-parity verified.

**Deviation from a design-doc field, noted for the record**: §5's `LabCasePolicy` table lists
`cancel()` under `admin`+`dentist` (clinical, reversing a prescription decision) — implemented
exactly as designed, distinct from `send`/`receive`/`qualityCheck`'s `admin`+`receptionist` set.
No other deviations from the approved design.

## Approval & Decision Log (2026-07-27)

All eight open items from §7 resolved in a single approval pass:

1. **Create/edit vs. status-transition permission split** (§7 item 1) — **APPROVED as recommended.**
   Create/edit while Draft is `admin + dentist` (clinical prescription decision — lab/tooth/shade/material
   selection); send/receive/quality-check status transitions are `admin + receptionist` (front-desk
   logistics). This deliberately separates the clinical decision from the logistical action. See finalized
   §5.
2. **Printable Lab Case slip** (§7 item 2) — **APPROVED as recommended.** Included in V1, browser
   `@media print` CSS only — no new PDF library dependency.
3. **`case_type` as free text** (§7 item 3) — **APPROVED as recommended.** Free-text string field in V1; a
   dedicated catalog table is deferred until a real, demonstrated need for filtering/reporting by case type
   emerges (mirrors how `SupplyCategory` only became a real table because categories are genuinely reused
   across filtering/reporting — `case_type` isn't yet).
4. **File/photo/STL attachments** (§7 item 4) — **APPROVED as recommended.** Deferred to V2 — no
   file-upload infrastructure exists elsewhere in this codebase yet.
5. **Appointment/Calendar badge integration** (§7 item 5) — **APPROVED as recommended.** Deferred to V2 —
   the `appointment_id` traceability FK ships in V1 (schema-ready), Calendar UI changes do not.
6. **Remake/redo case chaining** (§7 item 6) — **APPROVED as recommended.** Deferred to V2 — a redone case
   is simply a new `LabCase` record in V1.
7. **Multi-tooth cases as a `tooth_numbers` array** (§7 item 7) — **APPROVED as recommended.** No separate
   `LabCaseItem` table.
8. **Top-level navigation placement** (§7 item 8) — **APPROVED as recommended.** New top-level
   **Laboratory** sidebar group, mirroring Inventory's precedent.

## 0. Competitive Research (required before any design, per standing product philosophy)

| Source | Finding | Taken / Rejected for this design |
|---|---|---|
| **Open Dental** ([Edit Lab Case](https://www.opendental.com/manual/labcaseedit.html), [Lab Cases list](https://www.opendental.com/manual/labcasemanage.html), [Laboratories setup](https://www.opendental.com/manual/laboratories.html), [blog](https://opendental.blog/love-lab-cases-in-open-dental/)) | A Lab Case captures Patient, Appointment (detachable), Planned Appt (detachable), Provider, Fee (tracking-only, not billing-linked), Invoice Number (free text), Lab, a due-date auto-calculated from the lab's published turnaround time, and free-text Instructions. Four tracking-date checkpoints: **Created → Sent → Received → Quality Checked**. Creatable from the Chart module, the Appointment/Planned-Appointment edit window, or the Treatment Plan module. The "Laboratory" vendor record itself is a lean contact-info entity (name/phone/address/email/notes/is-hidden) plus per-service **turnaround times**. | **Taken**: the four-checkpoint status lifecycle (renamed to match this codebase's enum-lifecycle convention). **Taken**: Fee is tracking-only, never wired into Invoice/Payment — mirrors this codebase's own discipline of not cross-wiring modules in V1 (Inventory did the same with Billing). **Taken**: turnaround-time-driven due-date suggestion on the `Lab` vendor record. **Taken (renamed)**: `Lab` as its own vendor entity, not reused from `Supplier` (see §3/point 6 of the codebase research below — `Supplier`'s only relations are Inventory-specific; every prior "generic-looking small catalog" in this codebase got its own model rather than reusing a sibling one). **Deliberately rejected**: creating Lab Cases from three different entry points (Chart/Appointment/Treatment Plan) in V1 — see §2, this is deferred to keep the module's UI surface contained to its own top-level views for V1, same discipline Inventory used for not touching Appointments/Treatment Plans. |
| **Dentrix / Dentrix Ascend** ([Lab Case Manager](https://magazine.dentrix.com/manage-your-lab-cases-in-dentrix/), [Attaching to appointments](https://blog.dentrixascend.com/2020/02/12/attaching-lab-cases-to-appointments/)) | The defining UX is a status icon on the Appointment itself (a blue "L") that shows in/out status and due date on hover; the most important tracked fields are **Lab, shipping method, tracking number, and Case #**. Simple two-state operational lifecycle in practice: created → Sent → Received. | **Taken**: `case_number` (sequence-generated, e.g. `LC-000001`) as a first-class field, mirroring this codebase's own `PurchaseOrder.order_number`/`Patient.patient_code` lock-and-increment convention. **Taken (as an optional field)**: an optional shipping/tracking-number free-text field. **Deliberately deferred to V2**: the Calendar/Appointment badge-and-hover integration — valuable, but it means touching the Appointments module's Calendar rendering, which is out of this module's contained V1 scope (see §7 decision 5). The schema still supports it additively later via a nullable `appointment_id` traceability FK (§3), so no reshape will be needed when that pass happens. |
| **CareStack** ([Lab Case Management](https://carestack.com/dental-software/features/lab-case-management)) | Tracks **tooth numbers, tooth shades**, sent/received dates, and cost; supports multiple labs tracked independently; produces "a complete log of every step" for QA; explicitly does **not** transmit anything to the lab electronically — it's an internal tracking system only, same as this proposal. | **Taken**: `tooth_numbers` and `shade` as first-class fields. **Confirms the scoping decision**: no EDI/electronic-transmission-to-lab integration in V1 — internal tracking only, matching CareStack's own choice and this codebase's "no new external package/integration without a clear need" discipline (`PROJECT_CONTEXT.md`). |
| **Eaglesoft** (via [The Crew Process](https://www.thecrewprocess.com/eaglesoft-blog/labtracking), [Patterson support](https://pattersonsupport.custhelp.com/app/answers/detail/a_id/451/~/laboratory-case-window)) | Lab case window records shade, fee, and a free-text description of the case; real-world practitioner feedback specifically criticizes Eaglesoft for "too many fail points" in the tracking process — i.e. too many manual steps/states for staff to forget to update. | **Taken as a design constraint, not a feature**: keep the status lifecycle to the minimum number of checkpoints that are still operationally meaningful (4, matching Open Dental — see §4), rather than adding more granular sub-statuses that increase the chance staff simply stop updating them (the exact failure mode this source names). |
| **Modern/emerging tooling** ([TrazaLab](https://trazalab.com/dental-lab-case-tracking.html)) | Newer lab-case platforms consolidate Rx, STL files, photos, and approvals into one case record ("chaos to Kanban"), reflecting a real trend toward digital-workflow (intraoral scans, 3D files) case management. | **Deliberately deferred to V2** (§7 decision 4): file/photo/STL attachments. No file-upload infrastructure was found elsewhere in this codebase during the architecture research below, and this is the one clearly leading-edge (not yet baseline-standard among Open Dental/Dentrix/CareStack/Eaglesoft) capability found — named here rather than silently designed in or silently ignored. |

**Net effect**: all four established competitors converge on the same core shape — a **Lab** vendor entity, a
**LabCase** record with patient/tooth/shade/fee/instructions and a short tracking-date lifecycle
(created→sent→received→quality-checked), optionally linked to an appointment for at-a-glance status. This
design keeps that shape, follows this codebase's own established conventions for how to implement it (FDI
tooth storage, backed-enum lifecycle, snapshot-free single-record-per-case rather than Inventory's
header+items shape, `is_active` vendor catalog), and explicitly names three things deferred rather than
silently built or silently skipped: appointment/calendar UI integration, remake/redo tracking, and file
attachments.

## 1. Module Goal / Purpose

Give the clinic a single source of truth for every case sent to an external (or in-house) dental laboratory
— what was sent, to which lab, for which patient/tooth/appliance, with what shade/material instructions, when
it's due, and its current status — closing the "case went out three weeks ago, is it back yet?" visibility
gap that every competitor above builds their entire lab-case module around. Like Inventory, this is a
back-office operational module: V1 does not modify Appointments/Treatment Plans/Dental Chart/Billing
themselves, it only *references* them for traceability (§3).

## 2. Scope (V1)

**In scope:**
- **Labs** (admin-managed vendor catalog): name, contact info, address, notes, default turnaround days,
  active/inactive — its own model, not a reuse of Inventory's `Supplier` (see codebase-conventions research,
  point 6: `Supplier`'s relations are Inventory-specific; every structurally-similar small catalog in this
  codebase — `AppointmentType`, `DentalCondition`, `Supplier` — already gets its own model rather than being
  merged into a sibling one).
- **Lab Cases**: one record per case sent to a lab — patient, lab, responsible dentist, tooth number(s),
  shade, material, case description, fee (tracking-only), instructions, optional tracking number, and the
  four-stage status lifecycle (§4).
- **Traceability links** (one-way, read-only, never mutated by this module — mirroring
  `TreatmentPlanItem.diagnosis_entry_id`'s exact convention): optional `treatment_plan_item_id` and optional
  `appointment_id`, so a case can be traced back to the plan item that prescribed it and the appointment it's
  meant to be ready for, without either of those modules knowing Laboratory exists.
- **Due-today / overdue Dashboard widget**, mirroring `LowStockWidget.vue`'s exact pattern.
- **Printable Lab Case slip** (browser print stylesheet, no new PDF dependency) — see §7 decision 2 /
  Approval Log item 2 for why this is in-scope rather than deferred.
- Full audit trail via `Auditable` on `LabCase` (not on `Lab`, matching the `Supplier`/vendor-catalog
  precedent) — no new mechanism.
- Full en/ar/tr i18n, dark mode, RTL, keyboard access — enterprise UX bar per standing philosophy.

**Explicitly out of scope for V1** (named, not silently dropped — see §7 for the corresponding decisions):
- **Appointment/Calendar UI integration** (the Dentrix "blue L" badge-on-appointment pattern) — the
  traceability FK exists in the schema so this can be added additively later without a reshape, but no
  Calendar rendering changes ship in V1.
- **Remake/redo case tracking** — a lab case that comes back wrong and must be resent is, in V1, simply a new
  `LabCase` record; no `remake_of_case_id` linking chain yet.
- **File/photo/STL/digital-impression attachments** — no file-upload infrastructure exists elsewhere in this
  codebase yet; this is a real, valuable, but clearly V2+ capability.
- **Electronic transmission to labs** (EDI/API) — this module tracks that a case was sent, not a live feed to
  the lab's own system, matching CareStack's own explicit scoping choice.
- **Billing/Payments integration** — `fee` is informational only in V1, never becomes an `InvoiceItem`.

## 3. Data Model (finalized — see §3a for exact migration column lists)

### `Lab` (vendor catalog — mirrors `Supplier`'s exact shape)
`id` (uuid), `name`, `contact_name` (nullable), `phone` (nullable), `email` (nullable), `address` (nullable),
`default_turnaround_days` (nullable int — powers the due-date suggestion, per Open Dental's turnaround-time
field), `notes` (nullable text), `is_active` (bool, default true) + `scopeActive()`. Traits: `HasFactory,
HasUuids` only — no `Auditable`, no `SoftDeletes`, matching `Supplier`/`AppointmentType`/`DentalCondition`.

### `LabCase` (the case record — mirrors `PurchaseOrder`'s transactional shape, one record per case rather
than Inventory's header+items split, since a real-world lab case is a single prescription even when it spans
multiple teeth — see §7 decision 7 for the multi-tooth reasoning)
- `id` (uuid), `case_number` (e.g. `LC-000001`, lock-highest-and-increment, mirroring
  `PurchaseOrder.order_number`/`Patient.patient_code`).
- `patient_id` (FK → patients), `lab_id` (FK → labs), `dentist_id` (nullable FK → users — the responsible
  provider, mirrors Open Dental's "Provider" field).
- `treatment_plan_item_id` (nullable FK → treatment_plan_items) — **traceability only**, one-way, never
  mutated by this module, exact convention as `TreatmentPlanItem.diagnosis_entry_id`.
- `appointment_id` (nullable FK → appointments) — **traceability only**, same convention (the intended
  delivery/seat appointment, if scheduled at case-creation time).
- `tooth_numbers` (nullable `json`, cast to `array`) — a list of FDI codes, validated against
  `ToothChart::isValidCode()` at the application layer, **not a DB foreign key** — same convention as
  `dental_chart_entries.tooth_number`/`treatment_plan_items.tooth_number`, but plural since a single lab case
  (e.g. a 3-unit bridge) commonly spans multiple teeth (see §7 decision 7).
- `case_type` (free-text string, e.g. "Crown", "Bridge", "Denture", "Night Guard") — deliberately free text,
  not a backed enum or new admin catalog table: real-world appliance types are numerous and clinic-specific
  in exactly the same way `SupplyCategory` justified a real table over an enum for supply categories, *but*
  unlike categories this value is rarely reused for filtering/reporting in the same way, so a lighter
  free-text field is used — **resolved as §7 decision 3 / Approval Log item 3**.
- `shade` (nullable string), `material` (nullable string), `instructions` (nullable text).
- `fee` (nullable decimal, tracking-only, never read by Billing/Payments — same explicit non-linkage as Open
  Dental's own Fee field).
- `tracking_number` (nullable string — shipping/carrier tracking, per Dentrix's key-fields list).
- `status` (`LabCaseStatus` backed enum, default `Draft` — see §4).
- `sent_at`, `due_at`, `received_at`, `quality_checked_at`, `cancelled_at` (all nullable timestamps, set by
  `LabCaseService` on each transition — mirrors `PurchaseOrderService` setting transition timestamps, not the
  frontend/controller).
- Traits: `Auditable, HasFactory, HasUuids, SoftDeletes` — mirrors `PurchaseOrder`/`TreatmentPlan` exactly
  (a transactional clinical/operational record, not an immutable ledger row or a small catalog).

No separate `LabCaseItem` table in V1 — see §7 decision 7 for why the header+items shape Inventory needed
(distinct suppliers/costs/quantities per line) doesn't apply here.

## 3a. Finalized Migrations

### `create_labs_table`

| Column | Type | Nullable | Default | Notes |
|---|---|---|---|---|
| `id` | uuid, PK | no | — | `HasUuids` |
| `name` | string(255) | no | — | |
| `contact_name` | string(255) | yes | null | |
| `phone` | string(50) | yes | null | |
| `email` | string(255) | yes | null | |
| `address` | text | yes | null | |
| `default_turnaround_days` | unsignedSmallInteger | yes | null | powers `due_at` auto-suggestion on `send()` |
| `notes` | text | yes | null | |
| `is_active` | boolean | no | true | soft-disable, not delete — mirrors `suppliers.is_active` |
| `timestamps` | — | — | — | |

No FKs, no soft-delete column. Index on `is_active` (mirrors `suppliers`' own index for `scopeActive()` list queries).

### `create_lab_cases_table`

| Column | Type | Nullable | Default | Notes |
|---|---|---|---|---|
| `id` | uuid, PK | no | — | `HasUuids` |
| `case_number` | string(20), unique | no | — | `LC-000001`, lock-highest-and-increment |
| `patient_id` | uuid FK → patients | no | — | cascade-restrict on delete (matches `appointments.patient_id`) |
| `lab_id` | uuid FK → labs | no | — | restrict on delete |
| `dentist_id` | uuid FK → users | yes | null | restrict on delete; nullable — not every case has a single named provider at creation |
| `treatment_plan_item_id` | uuid FK → treatment_plan_items | yes | null | **traceability only**, nullable, restrict on delete (never cascaded — same convention as `diagnosis_entry_id`) |
| `appointment_id` | uuid FK → appointments | yes | null | **traceability only**, nullable, `nullOnDelete()` (if the linked appointment is later deleted, the case simply loses the reference rather than being blocked or cascaded) |
| `tooth_numbers` | json | yes | null | array of FDI code strings, validated via `ToothChart::isValidCode()` at the Form Request layer, not a DB constraint |
| `case_type` | string(100) | yes | null | free text (Approval Log item 3) |
| `shade` | string(50) | yes | null | |
| `material` | string(100) | yes | null | |
| `instructions` | text | yes | null | |
| `fee` | decimal(10,2) | yes | null | tracking-only, never read by Billing/Payments |
| `tracking_number` | string(100) | yes | null | shipping/carrier tracking |
| `status` | string(20) | no | `'draft'` | `LabCaseStatus` enum cast |
| `sent_at` | timestamp | yes | null | set by `LabCaseService::send()` |
| `due_at` | timestamp | yes | null | auto-set from `lab.default_turnaround_days` on `send()` unless already manually set |
| `received_at` | timestamp | yes | null | set by `LabCaseService::receive()` |
| `quality_checked_at` | timestamp | yes | null | set by `LabCaseService::qualityCheck()` |
| `cancelled_at` | timestamp | yes | null | set by `LabCaseService::cancel()` |
| `timestamps`, `soft-deletes` | — | — | — | `SoftDeletes` |

Indexes: `status` (dashboard widget queries "due today/overdue"), `(patient_id)`, `(lab_id)`, `(due_at)`
(partial/composite with `status` if the query planner needs it — decided at implementation time, not a
design-doc-level commitment).

## 4. Status Lifecycle — `LabCaseStatus`

Backed `string` enum in `backend/app/Enums/`, same shape as every existing status enum
(`PurchaseOrderStatus`/`InvoiceStatus`/`TreatmentPlanStatus`/`ClinicalNoteStatus`): cases, a
`transitionsFrom(self $status): array` match, `canTransitionTo(self $target): bool`, `isTerminal(): bool`.
Enforced in `LabCaseService` via a fixed lookup table (this codebase's established convention — no
state-machine package).

```
Draft ──sent──▶ Sent ──received──▶ Received ──qualityChecked──▶ QualityChecked (terminal)
  │               │
  └──cancel───────┴──▶ Cancelled (terminal)
```

- **Draft → Sent**: user action (`send()`), requires `lab_id` set; sets `sent_at` = now, and `due_at` =
  `sent_at + lab.default_turnaround_days` if not already manually set (Open Dental's auto-due-date pattern).
- **Sent → Received**: user action (`receive()`), sets `received_at` = now.
- **Received → QualityChecked**: user action (`qualityCheck()`), sets `quality_checked_at` = now — terminal,
  matches the "quality-checked = ready to deliver to patient" meaning from Open Dental.
- **Draft/Sent → Cancelled**: user action (`cancel()`), blocked once `received_at` is set (mirrors
  `PurchaseOrder::cancel()`'s "blocked the instant any item has a real receipt" rule) — a case that's already
  physically back from the lab can't be silently cancelled, it has to go through the normal lifecycle (a
  remake, if needed, is a new case per §2's explicit V1 scope boundary).

Deliberately **four** checkpoints, not more — per §0's Eaglesoft finding that too many granular states is the
actual documented failure mode (staff stop updating them), and Open Dental (the most mature implementation
researched) itself uses exactly four.

### Finalized enum skeleton

```php
enum LabCaseStatus: string
{
    case Draft = 'draft';
    case Sent = 'sent';
    case Received = 'received';
    case QualityChecked = 'quality_checked';
    case Cancelled = 'cancelled';

    /** @return list<self> */
    public static function transitionsFrom(self $status): array
    {
        return match ($status) {
            self::Draft => [self::Sent, self::Cancelled],
            self::Sent => [self::Received, self::Cancelled],
            self::Received => [self::QualityChecked],
            self::QualityChecked, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, self::transitionsFrom($this), true);
    }

    public function isTerminal(): bool
    {
        return $this === self::QualityChecked || $this === self::Cancelled;
    }
}
```

## 5. Permissions (finalized, per Approval & Decision Log item 1)

| Action | Roles | Precedent |
|---|---|---|
| View (list/detail) | admin, dentist, receptionist | All three roles have a legitimate need to know case status (clinical follow-up, front-desk patient communication) — mirrors `PurchaseOrderPolicy`'s open view, not `ClinicalNotePolicy`'s receptionist-exclusion (this isn't sensitive clinical narrative, it's logistics). |
| Create / update while Draft | admin, dentist | Choosing lab/tooth/shade/material is a clinical prescription decision — mirrors `ClinicalNotePolicy`'s admin+dentist split rather than `PurchaseOrderPolicy`'s admin+receptionist. |
| `send()` / `receive()` / `qualityCheck()` (status transitions) | admin, receptionist | Once prescribed, packaging/shipping/receiving is front-desk logistics — mirrors `PurchaseOrderPolicy::place/receive` exactly. |
| `cancel()` | admin, dentist | Cancelling a case while still Draft/Sent reverses a clinical decision — same role set as create/update, not the logistics set. |
| Delete | admin only, Draft-only | Mirrors every prior module's identical stricter-than-everything-else precedent (`PurchaseOrderPolicy`, `InvoicePolicy`, `PaymentPolicy`). |
| Lab vendor catalog CRUD | admin only | Mirrors `SupplierPolicy` exactly — clinic configuration, not day-to-day entry. |

### `LabCasePolicy` (finalized method list)

```php
class LabCasePolicy
{
    public function viewAny(User $actor): bool  // true for all roles
    public function view(User $actor, LabCase $case): bool  // true for all roles
    public function create(User $actor): bool  // admin | dentist
    public function update(User $actor, LabCase $case): bool  // admin | dentist, Draft-only
    public function send(User $actor, LabCase $case): bool  // admin | receptionist
    public function receive(User $actor, LabCase $case): bool  // admin | receptionist
    public function qualityCheck(User $actor, LabCase $case): bool  // admin | receptionist
    public function cancel(User $actor, LabCase $case): bool  // admin | dentist, blocked once received_at is set
    public function delete(User $actor, LabCase $case): bool  // admin only, Draft-only
}
```

### `LabPolicy` (finalized — mirrors `SupplierPolicy` exactly)

```php
class LabPolicy
{
    public function viewAny(User $actor): bool  // true for all roles
    public function view(User $actor, Lab $lab): bool  // true for all roles
    public function create(User $actor): bool  // admin only
    public function update(User $actor, Lab $lab): bool  // admin only
    public function delete(User $actor, Lab $lab): bool  // admin only (soft-disable via is_active, not a real delete)
}
```

## 6. Frontend (finalized)

- `useLabsStore` (Pinia) — mirrors `stores/suppliers.ts` line-for-line: `items`/`loaded`/`loading` refs,
  `inFlight` guard, `fetchAll(force)`, `create`/`update`/`deactivate` (soft-disable, not delete).
- `LabsView.vue` — admin-only catalog CRUD, mirrors `SuppliersView.vue`.
- `LabCasesView.vue` — paginated list, **no store**, direct `api.get('/lab-cases', { params })` calls, mirrors
  `PurchaseOrdersView.vue` exactly (lazy PrimeVue `DataTable`, filters, `useLabsStore` only as a
  filter-dropdown source).
- `LabCaseDetailView.vue` — overview card + a `LabCaseActionsBar.vue` (send/receive/quality-check/cancel
  buttons gated by the current status + policy), mirrors `PurchaseOrderDetailView.vue`'s shape.
- `LabCaseFormDialog.vue` — create/edit while Draft.
- New top-level **Laboratory** sidebar group (mirrors Inventory's own new top-level group — a module with its
  own dedicated workflows, not nested under Patients/Dental Chart).
- `DueLabCasesWidget.vue` — Dashboard card (due today / overdue), mirrors `LowStockWidget.vue` exactly.
- Printable Lab Case slip: a print-stylesheet view on `LabCaseDetailView.vue` (browser `window.print()` +
  `@media print` CSS), no new PDF library dependency.
- Full en/ar/tr i18n, zero missing/extra keys, verified the same way as every prior module.

## 7. Open Decisions (RESOLVED — kept as historical record; see Approval & Decision Log above)

1. **Create/edit permission for Draft Lab Cases**: recommend **admin + dentist** (clinical prescription
   decision — lab/tooth/shade/material selection), with **admin + receptionist** for the status-transition
   actions (send/receive/quality-check, logistics). Alternative: admin+receptionist for everything including
   creation (treating the whole case as front-desk logistics, mirroring `PurchaseOrderPolicy` in full) — this
   is the one place the competitive research didn't converge on a single clear precedent, since none of Open
   Dental/Dentrix/CareStack/Eaglesoft's public docs specify their own internal role-permission model.
2. **Printable Lab Case slip in V1**: recommend **yes, include it** — every competitor researched treats the
   printable lab slip as the module's core real-world deliverable (the physical/digital work order that
   actually accompanies the case to the lab); shipping the module without it would leave out the one feature
   every source names as central. Proposed as a browser-print view, no new dependency.
3. **`case_type` as free text vs. a new admin-managed catalog** (mirroring `SupplyCategory`'s "real table, not
   an enum" reasoning): recommend **free text** for V1 — appliance types are rarely filtered/reported on the
   way supply categories are, and a rigid list risks the same "too many fail points" friction §0 flagged for
   Eaglesoft. Can become a real catalog later without a breaking change (add the table, migrate distinct
   existing string values).
4. **File/photo/STL attachments**: recommend **defer to V2** — no file-upload infrastructure exists elsewhere
   in this codebase yet, and this is the one place the research surfaced a clearly leading-edge (not
   yet-baseline-standard) capability rather than something all four established competitors already do.
5. **Appointment/Calendar badge integration** (Dentrix's signature "blue L on the appointment" pattern):
   recommend **defer to V2** — the `appointment_id` traceability FK ships in V1 so this is additive later, but
   actually rendering it means touching Appointments' Calendar component, which is outside this module's
   contained V1 scope (same discipline Inventory used for not touching other modules).
6. **Remake/redo case chaining**: recommend **defer to V2** — a redone case is simply a new `LabCase` record
   in V1; no `remake_of_case_id` self-referencing chain yet.
7. **Multi-tooth cases**: recommend `tooth_numbers` as a `json` array column on `LabCase` (no separate
   `LabCaseItem` table) — a lab case is, in the real world, one prescription/one lab slip even when it spans
   multiple teeth (a 3-unit bridge, a full denture), unlike a Purchase Order's genuinely distinct
   supplier/cost/quantity per line. If a future need for genuinely independent line items per case emerges
   (e.g. per-tooth fee breakdown), this can be split out additively later.
8. **Navigation placement**: recommend top-level **Laboratory** sidebar group, mirroring Inventory's own
   precedent (a module with dedicated multi-view workflows, not a tab nested inside another module).

## 8. Explicitly Out of Scope for V1 (summary — see §2/§7 for full reasoning)

- Appointment/Calendar UI integration (badge/hover) — schema-ready, UI deferred.
- Remake/redo case chaining.
- File/photo/STL/digital-impression attachments.
- Electronic transmission to labs (EDI/API).
- Billing/Payments integration for the `fee` field.
