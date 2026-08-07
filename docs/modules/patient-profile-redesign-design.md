# Patient Profile Redesign — Design Document

| | |
|---|---|
| **Status** | **Approved 2026-08-07.** Phase 2.1 (Foundation) authorized to begin; Phase 2.2 (Billing) follows once 2.1 lands. §0's decisions and §9A's Security Architecture Decision are binding on every sub-phase below. |
| **Roadmap position** | 8-phase post-Stabilization roadmap, Phase 2 (see `docs/PROJECT_STATUS.md` §11-12) |
| **Author** | Claude Code, in collaboration with the user (decisions in §0 were specified directly, not inferred) |
| **Analysis basis** | Full audit of the current `PatientDetailView.vue` hub, all 8 target modules' backend/frontend integration points, and the design-system/component/permission layer — see the Step 1 findings this doc formalizes |
| **Security Architecture Decision** | §9A — Timeline is built on a dedicated `PatientActivity` event model, never `Auditable`, with mandatory server-side per-category role-filtering. See `docs/decisions.md`'s 2026-08-07 entry — this ruling applies to every future module that surfaces cross-module or aggregated patient data, not just Timeline. |

---

## 0. Governing decisions (specified by the user, applied throughout this doc)

These were decided before writing began and are not open questions — they shape every section below:

1. **Billing**: merge Invoices + Payments into one **Billing** tab (Summary / Invoices / Payments / Outstanding Balance / Payment History as sub-sections). Backend domain separation (`Invoice`/`Payment` models, `InvoiceService`/`PaymentService`) is preserved — this is a UI/IA merge only.
2. **Medical History**: becomes a structured foundation (Allergies / Medical Conditions / Current Medications / Notes), not free text only. Extensible schema, not a full EMR. Must stay AI-feature-compatible (structured rows, not prose, are what a future AI summarizer would need).
3. **Documents**: a generic patient-documents foundation ships this phase (model, upload/download, metadata, categories). Versioning, sharing, advanced per-document permissions, and OCR are explicitly deferred.
4. **Timeline**: not built on the existing `Auditable` trail as its source of truth. A dedicated `PatientActivity` event abstraction is designed instead, fed by domain events from every module, with `Auditable`/`PatientAuditLog` reused only as one input where useful. **Elevated to a formal Security Architecture Decision — see §9A.**

## 1. Vision and Objectives

The current `PatientDetailView.vue` (`frontend/src/views/PatientDetailView.vue`) is already a tabbed hub with 8 embedded panels (Overview, Appointments, Dental Chart, Imaging, Treatment Plans, Clinical Notes, Invoices, Payments) driven by a consistent, working pattern: `GET /patients/{id}/{resource}` → a model `scopeForPatient`/relationship → a Pinia store's `fetchForPatient()` → a dedicated `Patient*Panel.vue`. **This redesign extends that pattern to its logical conclusion, it does not replace it.**

**Objective**: make the Patient Profile the actual operational center of the clinic — the page a receptionist, dentist, or admin opens first and rarely needs to leave, because every patient-relevant fact (clinical, financial, administrative, historical) is one tab away, each respecting its own module's permission rules, none of it silently degrading performance as a patient's history grows over years.

**Non-objectives** (explicitly out of scope this phase, carried over from the original roadmap wording but not decided in §0, so treated as backlog rather than dropped): tags/labels, advanced in-record search (search *within* one patient's own data across tabs), PDF export of a patient's record. These remain named, not silently forgotten — see §17.4.

## 2. Current vs. Proposed Experience

| | Current | Proposed |
|---|---|---|
| Tabs | 8: Overview, Appointments, Dental Chart, Imaging, Treatment Plans, Clinical Notes, Invoices, Payments | 10: Overview, Medical History, Dental Chart, Treatment Plans, Clinical Notes, Imaging, Laboratory, Billing, Documents, Timeline (Appointments folds into Overview + Timeline, see §4) |
| Billing | Invoices and Payments are separate tabs/stores, an explicitly open question in `docs/modules/payments-design.md` | One Billing tab, five sub-sections, backend stays split |
| Medical data | Two free-text columns (`allergies`, `medical_history`) shown in an Overview card | Dedicated tab, three structured entities + a notes field |
| Laboratory | Not visible from a patient's record at all (`LabCase` has no inverse `Patient::labCases()`) | Full tab, same pattern as every other clinical module |
| History/activity | Admin-only "Patient record changed" audit log (create/update/delete of the `patients` row only) | Cross-module Timeline tab, every staff role, filtered per-viewer permission |
| Files | Only clinically-typed diagnostic images (`PatientImage`) | + generic documents (consent forms, referrals, insurance paperwork) |
| Patient reactive data layer | Views call `@/lib/api` inline; no Pinia store | `frontend/src/stores/patients.ts`, matching every sibling module |
| Icons | `PrimeIcons` (`pi-*`) throughout | Lucide, closing the module's place in the app-wide migration queue |
| Pagination | Treatment Plans/Clinical Notes/Invoices/Payments patient-scoped endpoints are unpaginated `->get()` | Paginated, consistent with Imaging's and the Patients list's existing pattern |

## 3. Information Architecture

**Tab order**: Overview → Medical History → Dental Chart → Treatment Plans → Clinical Notes → Imaging → Laboratory → Billing → Documents → Timeline.

Ordering rationale: clinical-context tabs (Medical History, Dental Chart, Treatment Plans, Clinical Notes) grouped first since they're what a dentist opens the record for; Imaging/Laboratory next (diagnostic/workflow support); Billing/Documents (administrative); Timeline last (a cross-cutting *view onto* everything else, not a primary workspace).

**Overview tab** changes role: today it holds the demographics/contact/medical/insurance cards directly. Medical content moves to its own tab. Overview becomes a lighter dashboard: demographics + contact cards (kept), insurance card (kept), a new **quick-stats row** (last visit date, active treatment plan status, outstanding balance, next upcoming appointment — see §8.4 for the backend summary endpoint this needs), and a compact **recent activity** preview (last 5 Timeline entries, "View all" linking to the Timeline tab). The admin-only Patient-record audit table stays exactly where it is (unrelated concern, see §9A).

**Appointments** stops being a standalone tab. Its content (upcoming/recent appointments, bounded ±3/+6 months per the existing panel's deliberate scope limit) moves into the Overview quick-stats/recent-activity area and into Timeline (full history, paginated). This isn't a capability loss — the current panel already only shows a bounded window; Timeline actually gives more history, not less. `PatientAppointmentsPanel.vue` is retired; its component logic (the shared date-range cache, the reason it deliberately avoids a naive patient-scoped fetch — see that file's own comments) is *not* reused for Timeline, which needs true full-history pagination instead (§9A).

## 4. Page Layout Structure

```
┌─────────────────────────────────────────────────────────────┐
│ ← Back    [Patient Name]  [patient_code Tag]     Edit  Delete │  ← header, unchanged
├─────────────────────────────────────────────────────────────┤
│ Overview │ Medical Hx │ Dental Chart │ Treatment Plans │ ... │  ← ≥768px: scrollable Tabs
├─────────────────────────────────────────────────────────────┤
│                                                               │
│                     [ active TabPanel content ]              │
│                                                               │
└─────────────────────────────────────────────────────────────┘
```

**≥768px (`md:` and up)**: PrimeVue `Tabs` with `scrollable`, same component already in use, no new dependency. A `motion-safe:` fade-edge shadow on the scrollable tab list signals overflow (extends the existing fade/shadow conventions in `design-system.md` §1, doesn't invent a new one).

**<768px**: the tab strip is replaced by a `Select` dropdown ("Viewing: Medical History ▾") driving the same underlying `activeTab` ref — same `TabPanel`s render beneath it, only the switcher UI changes. Ten horizontally-scrolling tab labels on a 390px viewport is not a usable pattern (flagged as untested/unresolved in Step 1); a dropdown is the smallest change that fixes it without inventing a new navigation paradigm. This is a new, explicit mobile behavior for this page — no existing precedent to extend, only the `lg:`-breakpoint sidebar/`Drawer` pattern to stay consistent in *spirit* with (collapse to a simpler control below a breakpoint, don't try to cram the desktop layout into a smaller viewport).

**Billing tab internal layout** (§0 decision 1): a hero **Outstanding Balance** stat (amount + status badge: Paid/Partial/Overdue/No Activity) always visible at the top, a **Summary** stat row beneath it (Total Invoiced, Total Paid, Invoice Count, Last Payment Date), then a segmented control (PrimeVue `SelectButton`, not nested `Tabs` — avoids a tabs-in-tabs anti-pattern) switching between three content panels: **Invoices** (existing `PatientInvoicesPanel.vue`, reused as-is), **Payments** (existing `PatientPaymentsPanel.vue`, reused as-is — record/apply/refund actions stay here), **Payment History** (new: a read-only chronological ledger, implemented as the Timeline component filtered to the Billing category — see §9A, not a new bespoke feature).

## 5. Component Breakdown

### 5.1 Reused as-is
`PatientDentalChartPanel.vue`, `PatientTreatmentPlansPanel.vue`, `PatientClinicalNotesPanel.vue`, `PatientInvoicesPanel.vue`, `PatientPaymentsPanel.vue`, `PatientImagingPanel.vue` (data-layer retrofit only, see §14.3), all existing status-chip components.

### 5.2 New components
| Component | Purpose |
|---|---|
| `MedicalHistoryPanel.vue` | Medical History tab shell: three lists (Allergies/Conditions/Medications) + Notes field |
| `AllergyList.vue`, `MedicalConditionList.vue`, `MedicationList.vue` | Small CRUD list components for each Medical History entity, following the existing per-domain list convention (compare `InvoiceListTable.vue`) |
| `PatientLabCasesPanel.vue` | Laboratory tab — mirrors `PatientImagingPanel.vue`'s structure (list + filters), reusing `LabCaseStatusChip.vue` |
| `PatientBillingPanel.vue` | Billing tab shell — hosts the Outstanding Balance hero, Summary row, `SelectButton`, and the three sub-panels described in §4 |
| `BillingSummaryCard.vue` | Renders the Summary stat row + Outstanding Balance hero from `GET /patients/{patient}/billing-summary` |
| `PatientDocumentsPanel.vue` | Documents tab — grid/list of `PatientDocument`s using `AttachmentList.vue`, upload via `AttachmentUpload.vue` |
| `AttachmentUpload.vue` | Generalized from `UploadImagesDialog.vue`'s dropzone (drag-drop + native file input + progress), domain-agnostic fields (category select, title, notes) instead of imaging's tooth/image-type fields |
| `AttachmentList.vue` | Generalized from `ImageThumbnail.vue`'s grid-cell pattern — icon-by-mime-type for non-images, thumbnail for images, shared edit/delete overlay |
| `PatientTimelinePanel.vue` | Timeline tab — hosts `ActivityTimeline.vue` with category filter chips |
| `ActivityTimeline.vue` | Shared, reusable vertical activity feed component (also powers Billing's "Payment History" and Overview's "recent activity" preview) — see §15.2 |
| `EmptyState.vue` | Shared empty-state component (icon, title, description, optional action) — referenced as already built in `frontend-visual-redesign-design.md` §6 but does not exist; built here, then retrofitted into every panel currently hand-rolling its own empty text |
| `patients.ts` (Pinia store) | New store, see §14.1 |
| `medicalHistory.ts`, `patientDocuments.ts`, `patientActivities.ts`, `patientImages.ts` (Pinia stores) | New/retrofitted stores following the standardized `fetchForPatient` pattern (§14.2) |

## 6. Backend Requirements

### 6.1 New models
- `PatientAllergy` — `belongsTo(Patient)`
- `PatientMedicalCondition` — `belongsTo(Patient)`
- `PatientMedication` — `belongsTo(Patient)`
- `PatientDocument` — `belongsTo(Patient)`, `belongsTo(User, 'uploaded_by')` nullable
- `PatientActivity` — `belongsTo(Patient)`, polymorphic `morphTo('subject')`, `belongsTo(User, 'actor_id')` nullable

### 6.2 `Patient.php` additions
```php
labCases()            -> LabCase          // the missing inverse relationship, closes the Laboratory gap
allergies()           -> PatientAllergy
medicalConditions()   -> PatientMedicalCondition
medications()         -> PatientMedication
documents()           -> PatientDocument
activities()          -> PatientActivity
```

### 6.3 New services
- `MedicalHistoryService` — one service for all three Medical History entities (they're one logical module, not three; matches "avoid over-engineering" from §0.2 more than three near-identical services would). Methods: `addAllergy/updateAllergy/deleteAllergy`, `addCondition/updateCondition/deleteCondition`, `addMedication/updateMedication/deleteMedication`.
- `PatientDocumentService` — mirrors `PatientImageService`'s Storage-disk pattern (`upload`, `update`, `delete`) minus thumbnail generation, plus a `download`/streaming method mirroring `images/{id}/file`.
- `LabCaseService` — extended, not replaced: add a `forPatient(patientId)` scope-backed listing path, matching how `TreatmentPlanService`/`InvoiceService`/etc. are used from their controllers today (the listing logic lives in the controller via a model scope in every existing case — Laboratory should follow the identical convention, not a new one).
- `PatientActivityService` (or a slimmer `RecordsPatientActivity` listener — see §9A) — write-path only; the read path is a simple paginated/filtered query, no service abstraction needed beyond the Eloquent query itself.
- `BillingSummaryService` (or a method on `InvoiceService`/a small standalone class) — one cheap aggregate query: `SUM(invoice totals)`, `SUM(payments)`, outstanding = invoiced − paid, last payment date, invoice count. Must not fetch full Invoice/Payment rows to compute this (see §11.4).

### 6.4 New policies
- `MedicalHistoryPolicy` — proposed default: `view` = all staff (allergies are safety-relevant at the front desk, same reasoning as Dental Chart's read access), `create`/`update`/`delete` = Admin + Dentist only (clinical judgment). **Flagged for confirmation** — this is a reasonable default inferred from the existing Dental Chart permission shape, not an explicit user decision.
- `PatientDocumentPolicy` — proposed default: `view` = all staff, `create` = Admin + Receptionist + Dentist (referral letters, insurance paperwork, and consent forms can originate from any of these roles), `delete` = Admin only (matches Patient/Invoice's own delete-is-admin-only convention). **Flagged for confirmation.**
- `PatientActivityPolicy` — not a conventional CRUD policy; its `viewAny` must re-derive from the *underlying* module's policy per event (see §9A's security requirement) rather than grant blanket read access.

## 7. Database Changes

All new tables: UUID PK (`HasUuids`), standard `created_at`/`updated_at`, `SoftDeletes`, `Auditable` trait (these are clinical/financial-adjacent records — consistent with the project's existing "audit sensitive models" convention already applied to `Patient` and `LabCase`).

```
patient_allergies
  id, patient_id (fk→patients, restrict), allergen (string),
  severity (nullable enum: mild|moderate|severe), reaction (nullable string),
  notes (nullable text), created_by_id/updated_by_id (fk→users, nullable),
  timestamps, deleted_at

patient_medical_conditions
  id, patient_id (fk→patients, restrict), condition_name (string),
  status (enum: active|resolved|chronic, default active),
  diagnosed_date (nullable date), notes (nullable text),
  created_by_id/updated_by_id (fk→users, nullable), timestamps, deleted_at

patient_medications
  id, patient_id (fk→patients, restrict), medication_name (string),
  dosage (nullable string), frequency (nullable string),
  is_current (boolean, default true), start_date (nullable date),
  end_date (nullable date), notes (nullable text),
  created_by_id/updated_by_id (fk→users, nullable), timestamps, deleted_at

patient_documents
  id, patient_id (fk→patients, cascade — mirrors patient_images, the one
    existing precedent for a patient-owned file entity),
  category (enum: consent_form|insurance|referral|lab_report|correspondence|other),
  title (string), original_filename (string), disk (string), path (string),
  mime_type (string), size_bytes (unsigned integer),
  uploaded_by (fk→users, nullable, null-on-delete), notes (nullable text),
  timestamps, deleted_at

patient_activities
  id, patient_id (fk→patients, cascade),
  event_type (string, e.g. "appointment.completed", "invoice.issued"),
  category (string, e.g. "clinical" | "appointments" | "billing" | "imaging" |
    "laboratory" | "documents" | "administrative" — denormalized from
    event_type at write time so filtering never needs prefix/LIKE matching),
  subject_type, subject_id (polymorphic — the underlying Appointment/
    TreatmentPlan/ClinicalNote/Invoice/Payment/PatientImage/LabCase/
    PatientDocument row),
  actor_id (fk→users, nullable, null-on-delete),
  summary (string — precomputed human-readable text, e.g.
    "Invoice INV-00042 issued for $340.00", so the Timeline never needs to
    join back to the subject table just to render a list),
  metadata (nullable json — event-specific extra fields),
  occurred_at (timestamp, indexed),
  created_at
  -- composite index (patient_id, occurred_at)
  -- index (patient_id, category, occurred_at)
```

**`patients` table**: no destructive changes. The existing `allergies` (text) column is superseded by `patient_allergies` but **not dropped this phase** — a data migration backfills any existing free-text `allergies` value into a single best-effort `patient_allergies` row per patient (admin can split it manually afterward), and the old column is marked deprecated in `docs/database-design.md`/this doc, actually dropped in a later cleanup phase once the team confirms nothing still reads it. This mirrors the project's established pattern of not doing silent, hard-to-reverse column drops (compare how `PROJECT_CONTEXT.md`'s own convention is "append corrections, don't rewrite history"). The existing `medical_history` (text) column is **kept and repurposed** as the "Notes" field within the new Medical History tab — no migration needed for it, just a UI relabel.

## 8. API Changes

All new endpoints follow the one convention every existing patient-scoped endpoint already uses — `GET/POST /patients/{patient}/{resource}` backed by a model scope or direct relationship query, paginated. No query-param-filtering pattern (`?patient_id=`) is introduced for anything new; Laboratory's existing `?patient_id=` filtering is superseded by a real patient-scoped route for consistency (§8.3).

```
# Medical History
GET/POST   /patients/{patient}/allergies
PUT/DELETE /allergies/{id}
GET/POST   /patients/{patient}/medical-conditions
PUT/DELETE /medical-conditions/{id}
GET/POST   /patients/{patient}/medications
PUT/DELETE /medications/{id}

# Laboratory (closing the existing gap)
GET        /patients/{patient}/lab-cases          # paginated, new — was missing entirely

# Billing
GET        /patients/{patient}/billing-summary     # new — aggregate only, see §6.3/§11.4

# Documents
GET/POST   /patients/{patient}/documents
GET        /documents/{id}/file                    # authenticated stream, mirrors images/{id}/file
PUT/DELETE /documents/{id}

# Timeline / Activities
GET        /patients/{patient}/activities           # paginated, ?category=, ?from=&to=

# Overview quick-stats
GET        /patients/{patient}/summary              # new — last visit, active plan, balance,
                                                      # next appointment; avoids the Overview tab
                                                      # stitching together 5 separate tab fetches
                                                      # just to render its stat cards (see §11.4)
```

**Existing endpoints, pagination added** (removes a real, previously-undocumented scalability risk, see §11.2): `GET /patients/{patient}/treatment-plans`, `/clinical-notes`, `/invoices`, `/payments`. **Implementation update (Phase 2.1)**: Treatment Plans/Clinical Notes shipped paginated in Phase 2.1; Invoices/Payments were found mid-implementation to have a real coupling risk (`ApplyPaymentDialog.vue`'s invoice picker, `InvoicePaymentsPanel.vue`) that Treatment Plans/Clinical Notes don't share, so those two are deliberately deferred to Phase 2.2 alongside the Billing merge — see the Phase 2.1 `CHANGELOG.md` entry and the new `TECH_DEBT.md` item.

## 9. Timeline / `PatientActivity` — design detail

This is the highest-risk new piece, so it gets its own section rather than being folded into §6-8.

### 9.1 Why not `Auditable`/`PatientAuditLog`
`Auditable` (`backend/app/Models/Concerns/Auditable.php`) records field-level *changes to a model's own columns* — it answers "what changed on this row," not "what clinically/operationally happened to this patient." Its storage is JSON-based and already flagged elsewhere in the codebase as inefficient for aggregate/KPI-style queries. Building Timeline directly on top of it would mean either (a) parsing generic change-diffs into human-readable summaries at read time (slow, fragile), or (b) accepting a feature that can't cleanly support filtering/pagination/future notifications — the exact requirements this feature needs. A purpose-built table is the right call, per §0 decision 4.

### 9.2 How events get written
Each module's service dispatches a domain event at its key lifecycle moments (e.g. `AppointmentCompleted`, `InvoiceIssued`, `PaymentRecorded`, `TreatmentPlanStatusChanged`, `ClinicalNoteSigned`, `LabCaseStatusChanged`, `PatientImageUploaded`, `PatientDocumentUploaded`). A single listener (`RecordsPatientActivity`) subscribes to all of them and writes one `PatientActivity` row per event, computing `summary`/`category`/`event_type` from the event's own payload. This keeps the 8 module services decoupled from the activity log itself — none of them needs to know `PatientActivity` exists, they just fire an event they'd arguably want to fire anyway (useful groundwork for §17.4's deferred notifications feature too).

For the Patient row's own field changes (already captured by `Auditable`), the existing audit-write path additionally fires a `PatientUpdated` event so a `patient.updated` activity appears in Timeline too — **reusing the same trigger point, not re-reading `PatientAuditLog`'s storage** (§0 decision 4's "reuse existing audit data where useful," applied at the point of creation rather than at read time).

### 9.3 How events get read
`GET /patients/{patient}/activities?category=&from=&to=&page=` — a single indexed query against `patient_activities`, ordered by `occurred_at DESC`. The list renders entirely from `summary`+`metadata`+`category` — **no join back to the subject table for the list view**; a "View" link on each entry deep-links to the relevant tab/detail route using `subject_type`/`subject_id`, resolved only if the user clicks through.

### 9.4 Payment History reuse
Billing's "Payment History" sub-section (§4) is `ActivityTimeline.vue` pre-filtered to `category=billing` — not a separate feature, endpoint, or component. Same for Overview's "recent activity" preview (§3): same component, `?page_size=5`, no category filter.

## 9A. Security Architecture Decision — Timeline is not an audit log, and its permissions are enforced server-side only

**Status: Approved 2026-08-07.** This section is elevated out of the general Timeline design detail (§9) because the user explicitly designated it a binding architectural ruling, not an implementation detail local to this feature — it governs every future module that aggregates or surfaces cross-module patient data, not just Timeline. Also logged in `docs/decisions.md` (2026-08-07 entry) per this project's standing rule that architecture decisions live there, summarized here and in `docs/PROJECT_STATUS.md` §5.

**The decision, precisely stated**:
1. `PatientActivity`/Timeline is a **patient-events feed**, not a system audit log. It answers "what clinically/operationally happened to this patient," never "what changed on a database row." `Auditable`/`PatientAuditLog` remains the correct tool for the latter and is untouched by this decision — the two concepts are not merged, and Timeline does not replace the existing admin-only "Patient record changed" audit view.
2. **Timeline permissions are enforced exclusively server-side, at query time, per category.** A naive "show every activity for this patient" query is a real permission leak: a receptionist viewing Timeline would see summary lines for Clinical-Notes-derived events even though `ClinicalNotePolicy` bars receptionists from viewing Clinical Notes at all today (confirmed in Step 1 — `viewAny`/`view` are `Admin`/`Dentist`-only, not just write access). The same risk applies to any future category with narrower read access than the hub itself.
3. **Concretely**: `GET /patients/{patient}/activities` must filter by the requesting user's role against each `category`'s owning Policy *before* rows leave the database — `category=clinical` rows are excluded from the query itself, never fetched-then-hidden client-side. `PatientActivityPolicy::viewAny()` must be implemented as a per-category lookup against the real owning policies (`ClinicalNotePolicy`, `InvoicePolicy`, etc.), not a standalone rule that could drift out of sync with them.
4. **Why this is a hard requirement, not a preference**: it mirrors a principle this codebase already states explicitly elsewhere — "defense in depth... the backend still enforces this regardless of [a frontend] check," per `PatientDetailView.vue`'s own comment on the Clinical Notes tab. Aggregation features are exactly where that principle is easiest to accidentally violate (a query that unions across modules can silently forget one module's narrower read rule), so this decision exists to make that failure mode structurally impossible rather than trusting review to catch it.
5. **Enforcement, not just design intent**: §18 (Testing Strategy) requires this be covered by a dedicated backend Policy test *and* a Playwright E2E assertion — a receptionist-role request to `/activities` must never return `category=clinical` rows, verified directly, not inferred from the UI hiding a tab.

**Applies beyond Timeline**: any future feature that reads across module boundaries (a search-everything feature, a cross-module dashboard widget, a future AI summarizer reading `PatientActivity` rows) inherits this same rule — per-source permission must be re-checked at the aggregation point, never assumed inherited from "the user could already see the patient's hub."

## 10. Permission Model

No unified "can view patient hub" gate — the hub shell always renders; each `Tab`/`TabPanel` pair independently gates itself, exactly like the existing `canAccessClinicalNotes` pattern in `PatientDetailView.vue` today.

| Tab | View | Write | Backing Policy |
|---|---|---|---|
| Overview | All staff | Admin + Receptionist (demographics) | `PatientPolicy` (existing) |
| Medical History | All staff (proposed) | Admin + Dentist (proposed) | `MedicalHistoryPolicy` (new) |
| Dental Chart | All staff | Admin + Dentist | `DentalChartEntryPolicy` (existing) |
| Treatment Plans | All staff | Admin + Dentist | `TreatmentPlanPolicy` (existing) |
| Clinical Notes | **Admin + Dentist only** | Admin + Dentist | `ClinicalNotePolicy` (existing — receptionist excluded from read, confirmed) |
| Imaging | All staff | Admin + Dentist (proposed, matches clinical-content pattern) | existing (verify current rule during implementation) |
| Laboratory | All staff | Admin + Dentist | `LabCasePolicy` (existing) |
| Billing | All staff | Admin + Receptionist | `InvoicePolicy` + `PaymentPolicy` (existing, unchanged — UI merge only) |
| Documents | All staff (proposed) | Admin + Receptionist + Dentist (proposed) | `PatientDocumentPolicy` (new) |
| Timeline | All staff, **rows filtered per-category by the viewer's role** | N/A (read-only) | `PatientActivityPolicy` (new, see §9A) |

Every "(proposed)" marker above is a default inferred from the closest existing analog, not a decision made in §0 — worth a quick confirmation before implementation, not a blocker to writing the rest of this doc.

## 11. Performance Strategy

1. **Lazy loading tabs**: formalized as an explicit rule for every tab, existing and new — no tab's data is fetched until it's first activated, and each store caches its result so re-activating a tab doesn't refetch unless `force` is passed. This is already the de facto behavior of the existing panels; this phase makes it an explicit, documented requirement so the 5 new tabs follow it too.
2. **Pagination**: added to the 4 existing unpaginated endpoints (Treatment Plans, Clinical Notes shipped in Phase 2.1 at 15/page, matching `PatientService::paginate()`'s convention; Invoices, Payments deferred to Phase 2.2, see the Phase 2.1 implementation note above) and applied by default to every new list endpoint (Medical History sub-lists, Documents, Activities).
3. **Timeline query strategy**: composite index `(patient_id, occurred_at)` plus `(patient_id, category, occurred_at)`; list rendering never joins back to subject tables (§9.3); role-filtering happens in the query itself, not post-fetch (§9A).
4. **API response optimization**: two new aggregate-only endpoints — `billing-summary` and the Overview `summary` endpoint — exist specifically so the frontend never has to stitch together 5+ separate tab fetches just to render a handful of stat cards. Both must be implemented as `SUM`/`COUNT`/`MAX` queries, never as "fetch all rows and reduce in PHP."

## 12. Responsive / Mobile Behavior

Per the standing PWA/mobile-first policy (`docs/decisions.md`, applies to every module's design phase): the `<768px` dropdown-driven tab switcher (§4) is the primary new mobile-specific behavior this redesign introduces. Every new component (`MedicalHistoryPanel`, `PatientBillingPanel`, `PatientDocumentsPanel`, `PatientTimelinePanel`, `ActivityTimeline`, `AttachmentUpload`/`AttachmentList`) must be verified at a 390px viewport during implementation — this hasn't historically been screenshotted per-component the way `design-system.md` §8 does for some other screens, and shouldn't be skipped here given how dense this page already is. `AttachmentUpload.vue`'s native file input should keep `capture="environment"` for mobile camera capture, matching `UploadImagesDialog.vue`'s existing pattern (a patient's insurance card or a paper referral is exactly the kind of thing staff would photograph on a phone rather than scan).

## 13. RTL / LTR Considerations

No new RTL mechanism needed — the existing `dir="rtl"` cascade (driven by `frontend/src/locales/index.ts`'s `RTL_LOCALES`) already themes PrimeVue Aura components for free, and every new custom-CSS element in this redesign must use logical Tailwind properties (`ps-`/`pe-`/`border-s-`/`border-e-`) per the standing rule in `frontend-visual-redesign-design.md` §9 — this applies to the new dropdown tab switcher, `ActivityTimeline.vue`'s event-icon-plus-text rows, and `AttachmentList.vue`'s grid. Any new chevron/directional icon (e.g. a "View" link arrow on a Timeline entry) needs the existing `rtl:rotate-180` treatment. All new UI strings ship in `ar`/`en`/`tr` from the start, matching every prior module's i18n-parity discipline — Arabic is the default locale, not an afterthought translated later.

## 14. Architecture Improvements

### 14.1 Introduce a Patient Pinia store
New `frontend/src/stores/patients.ts`, matching the shape every sibling store already has: `fetchList(params)`, `fetchOne(id, force)`, `create`, `update`, `remove`, `fetchAuditLogs(id)`, `fetchSummary(id)`. `PatientsView.vue` and `PatientDetailView.vue` are refactored to use it instead of calling `@/lib/api` inline — closes the one module-level architectural inconsistency identified in Step 1.

### 14.2 Standardize patient-scoped data fetching
Document, in this file, the pattern every store above should follow (it's already implicit in `treatmentPlans.ts`/`clinicalNotes.ts`/`invoices.ts`/`payments.ts`/`dentalChartEntries.ts`, just never written down): a `fetchForPatient(patientId, force = false)` action that no-ops if already cached for that patient unless `force`, plus a `xForPatient(patientId)` getter/memo returning the cached slice. Every new store in this redesign (`medicalHistory.ts`, `patientDocuments.ts`, `patientActivities.ts`, `patientImages.ts`) follows this exact shape — no new pattern invented.

### 14.3 Resolve the Imaging inconsistency
`frontend/src/services/imaging/index.ts`'s existing functions (`fetchPatientImages`, `uploadImages`, `updatePatientImage`, `deletePatientImage`) get wrapped in a new `frontend/src/stores/patientImages.ts` Pinia store, giving Imaging the same caching/reactivity as every other tab. **Backend is untouched** — this is a pure frontend refactor, zero API risk.

### 14.4 Pagination strategy
Covered in §11.2 — stated here as an architecture principle: every patient-scoped list endpoint, existing or new, is paginated by default from this phase forward. No new unpaginated `->get()` patient-scoped endpoint should ship again.

## 15. New Shared Components — design detail

### 15.1 `EmptyState.vue`
Props: `icon` (Lucide component), `title`, `description`, optional `actionLabel`/`onAction`. Retrofitted into every panel currently hand-rolling its own empty text (`PatientImagingPanel.vue:215` and equivalents across other panels) as those files are touched during this phase — not a forced separate cleanup pass.

### 15.2 `ActivityTimeline.vue`
Props: `patientId`, optional `category` filter, optional `pageSize` (default 20, or 5 for the Overview preview usage). Renders a vertical feed: Lucide icon per `category`/`event_type` (extends `iconMap.ts`'s convention with a new `EVENT_TYPE_ICON` map), timestamp via `frontend/src/lib/date.ts` (mandatory — no raw `Date` handling, per the project's datetime Architecture Violation rule), `summary` text, optional "View" deep-link. Category filter chips at the top (reusing the `Tag`+severity-map convention where a colored chip makes sense). Paginated via "Load more" (simpler and more mobile-friendly than infinite scroll for a feed that's also embedded in a 5-item Overview preview).

### 15.3 `AttachmentUpload.vue` / `AttachmentList.vue`
Generalized from `UploadImagesDialog.vue`/`ImageThumbnail.vue` — same drag-drop/native-input/progress mechanics, domain-agnostic fields (`category`, `title`, `notes` instead of imaging's `image_type`/tooth number). `AttachmentList.vue` shows a thumbnail for image mime-types and a file-type icon otherwise. Built for Documents; not forced onto Imaging this phase (Imaging keeps its own specialized components — its domain fields don't generalize cleanly, and retrofitting it isn't necessary to ship Documents).

### 15.4 Mobile-friendly patient navigation pattern
Covered in §4/§12 — the `<768px` dropdown tab switcher. No separate component needed beyond a `Select` bound to the same `activeTab` ref the desktop `Tabs` uses.

## 16. Migration Tasks — Lucide

The Patients module is next in `frontend-visual-redesign-design.md` §8's own stated migration order and hasn't been done yet — this phase does it, since the same files (`PatientDetailView.vue`, the imaging components) are being touched anyway. Replace, per `frontend/src/config/iconMap.ts`'s existing mapping: `pi-arrow-left`→`ArrowLeft`, `pi-pencil`→`Pencil`, `pi-trash`→`Trash2`, `pi-exclamation-triangle`→`TriangleAlert` in `PatientDetailView.vue` (lines 99/123/133/139 today); `pi-camera`, `pi-images`, `pi-spin pi-spinner` and others in `PatientImagingPanel.vue`/`UploadImagesDialog.vue`/`ImageThumbnail.vue`. Do this as the **first** implementation step (§17, Phase 2.1) — small, mechanical, de-risks everything built on top of it, rather than interleaving icon swaps with new feature logic.

## 17. Implementation Phases

Sequenced to front-load low-risk foundational work, then well-understood "repeat the existing pattern" modules, then the genuinely new architecture:

| Sub-phase | Scope | Risk |
|---|---|---|
| **2.1 Foundation** ✅ done | `patients.ts` store, standardized `fetchForPatient` pattern documented, `patientImages.ts` store (Imaging retrofit), pagination added to Treatment Plans/Clinical Notes (Invoices/Payments deferred to 2.2 — see implementation note above), Lucide migration for the Patients module, `EmptyState.vue` built and retrofitted, `PatientDetailView.vue` tab list made config-driven | Low — no new features, no new schema |
| **2.2 Billing merge** | `PatientBillingPanel.vue`, `BillingSummaryCard.vue`, `billing-summary` endpoint, mobile dropdown tab switcher (needed now since Billing's merge changes the tab count) | Low-Medium — UI restructure + one new aggregate endpoint |
| **2.3 Medical History** | New tables/models/`MedicalHistoryService`/`MedicalHistoryPolicy`/controller, `MedicalHistoryPanel.vue` + 3 list components, data-migration of existing free-text `allergies` | Medium — new schema, new policy |
| **2.4 Laboratory integration** | `Patient::labCases()`, `forPatient` scope on `LabCase`, new patient-scoped route, `patientLabCases` store, `PatientLabCasesPanel.vue` | Low — repeats an existing, well-understood pattern exactly |
| **2.5 Documents** | New table/model/`PatientDocumentService`/`PatientDocumentPolicy`/controller, `AttachmentUpload.vue`/`AttachmentList.vue`, `PatientDocumentsPanel.vue` | Medium — new schema, new generalized components |
| **2.6 Timeline / `PatientActivity`** | Event abstraction, domain events wired into all 8 modules' services, `RecordsPatientActivity` listener, `activities` endpoint with role-filtering (§9A), `ActivityTimeline.vue`, Timeline tab, Overview quick-stats + recent-activity preview, Billing's Payment History sub-section wired to it | **Highest** — new architecture, touches every module's service, security-critical role-filtering |
| **2.7 Polish** | Full RTL/i18n parity pass, mobile verification per §12, a11y sweep, permanent Playwright E2E suite for the whole redesigned page | Low, but not skippable |

Each sub-phase should land as its own PR, CI-confirmed, following this project's established one-module(-slice)-per-PR convention rather than one large PR for all of Phase 2.

## 18. Testing Strategy

**Backend**: Feature tests per new controller (Medical History CRUD, Documents CRUD + authenticated file streaming, Activities listing/filtering/pagination, billing-summary and Overview summary correctness — including edge cases: partial payments, refunds, voided invoices, a patient with zero financial activity). Unit tests asserting each module's service actually dispatches its domain event (easy to silently miss when a future lifecycle action is added — worth a test per event, not just per controller). Policy tests for all 3 new policies, with **explicit test coverage for §9A's role-filtering requirement**: a receptionist-role request to `/activities` must never return `category=clinical` rows, asserted directly, not just inferred from the UI hiding them.

**Frontend**: Vitest store tests for `patients.ts`, `medicalHistory.ts`, `patientDocuments.ts`, `patientActivities.ts`, `patientImages.ts` (cache-hit/force-refresh behavior per §14.2's standardized contract). Component tests for `EmptyState.vue`, `ActivityTimeline.vue`, `AttachmentUpload.vue`.

**E2E (Playwright)**: extend/replace the appointments-and-patient-related specs to cover: all 10 tabs navigable, role-based tab visibility (receptionist doesn't see Clinical Notes tab *or* clinical-category Timeline entries — the security requirement from §9A needs an E2E assertion, not just a backend unit test, since it's a cross-cutting UX guarantee), Billing tab's merged sub-sections, a document upload→download round trip, Timeline pagination and category filtering, the mobile dropdown tab switcher at a narrow viewport.

**i18n**: parity check across `ar`/`en`/`tr` for every new string, per the project's existing per-module discipline.

## 19. Risks and Open Questions

1. **Timeline (2.6) is genuinely the hard part.** Wiring domain events into 8 existing services is real, cross-cutting work with room to silently miss an event type — the per-event dispatch tests in §18 exist specifically to catch that, not as boilerplate.
2. **Three "(proposed)" permission defaults** (§10: Medical History, Imaging write, Documents) need a quick confirm before 2.3/2.5 implementation — not a blocker to approving this doc, but worth resolving before those specific sub-phases start.
3. **The `allergies` column deprecation** (§7) leaves a temporarily-duplicated concept (old free-text column + new structured table) until a later cleanup phase removes the column — intentional, not an oversight; should get its own `TECH_DEBT.md` entry once 2.3 ships, naming the removal condition explicitly (mirrors how every other deferred item in this codebase is tracked).
4. **Explicitly deferred, not forgotten**: tags/labels, in-record search, PDF export (from the original roadmap wording, not decided in §0) stay off this phase's scope — worth a `docs/roadmap.md`/`TECH_DEBT.md` entry pointing at "Phase 2.x or later" once this doc is approved, so they don't quietly vanish from the record the way earlier staleness incidents did (see `docs/PROJECT_STATUS.md` §0's own history of exactly that failure mode).
