# Patient Profile — Phase 2.6: Timeline Integration — Design Document

| | |
|---|---|
| **Status** | **Approved 2026-08-08.** All 7 decisions in §16 were approved by the user as recommended. A real category-taxonomy conflict was found and fixed pre-implementation (§5's correction note). **2.6a (Foundation) merged via PR #31. 2.6b (UI) merged via PR #32** (a post-merge-CI-caught E2E test fix via PR #33) — **Phase 2.6 (Timeline), and Phase 2 (Patient Profile Redesign) in full, are complete.** See `docs/PROJECT_STATUS.md` §12 for the full merge/CI record. |
| **Roadmap position** | Phase 2 (Patient Profile Redesign), sub-phase 2.6 of 7 — the final sub-phase. See `docs/PROJECT_STATUS.md` §12 and `docs/modules/patient-profile-redesign-design.md` §17. Follows 2.1 (Foundation, PR #18), 2.2 (Billing, PR #20), 2.3 (Medical History, PR #22), 2.4 (Laboratory, PR #24), 2.5 (Documents, PR #27) — all merged to `main`. |
| **Governing decisions inherited** | **§9A of `docs/modules/patient-profile-redesign-design.md` (the Security Architecture Decision) is binding here unchanged and is not revisited anywhere in this doc** — see §0 below for its full text. §0 item 4 and §3's tab-order rationale are also inherited. This doc is a **drill-down**, not a replacement: the umbrella doc's §6/§7/§8/§9/§9A/§10/§15.2/§17 already sketch this sub-phase in unusual detail (more than any prior phase had); this doc verifies that sketch against the actual code on `main` today and resolves the structural decisions it left open. |
| **Analysis basis** | Direct inspection of every Timeline-adjacent backend/frontend file on `main` (post-PR #28, commit `933ee90`) — confirmed nothing Timeline/`PatientActivity`-related exists yet, confirmed zero event-driven infrastructure exists anywhere in this codebase (no `app/Events`, no `app/Listeners`, no service dispatches any event), and confirmed no queue worker actually runs despite `QUEUE_CONNECTION=redis` being configured (no `ShouldQueue` usage, no queue-worker service in `docker-compose.yml`). Read all 9 candidate services' full public method lists to build the per-service event inventory in §5. Read `App\Models\Concerns\Auditable`/`AuditObserver`/`AuditLog` end-to-end to confirm exactly why it can't serve as Timeline's source, corroborating §9A's own reasoning. Read the umbrella doc's full §9/§9A verbatim and `docs/decisions.md`'s 2026-08-07 entry verbatim. Read `patient-documents-redesign-design.md` as this doc's structural template. |
| **Author** | Claude Code, for user review. Every recommendation below is a proposal, not a decision — see §16 for the explicit approval list. |

---

## Implementation Summary — 2.6a Foundation (added post-implementation, 2026-08-08)

All 7 §16 decisions implemented as recommended, plus the pre-implementation category-taxonomy fix
(§5's correction note). No further deviations found during implementation:

- **§6**: `PatientActivity` model/migration built exactly as specced — append-only (`const
  UPDATED_AT = null`, no `SoftDeletes`), `uuidMorphs('subject')`, both composite indexes.
  `Patient::activities()` added.
- **§4/§16 decision 1**: implemented as **one generic `PatientActivityOccurred` event** (not 24
  near-identical classes) — the event carries `$subject`/`$actor`/`$eventType`/`$category`/
  `$summary`/`$metadata`, with the calling service method building the summary string inline at
  its own call site. This is a refinement of §4's original phrasing ("each event class implements
  a `summary(): string` method"), not a reopening of decision 1 — decision 1 was about mechanism
  (explicit `event()` calls, one synchronous listener, no queue), not file-per-type granularity;
  24 near-identical event classes would have been the kind of premature duplication this project's
  own conventions avoid. One synchronous `RecordsPatientActivity` listener, relying on Laravel's
  default auto-discovery (no manual registration — matches how `PatientImagePolicy`/
  `PatientDocumentPolicy` already rely on naming-convention auto-discovery, not `Gate::policy()`).
  **Confirmed genuinely wired, not just assumed**: a dedicated integration test calls a service
  method without faking events and asserts a real `patient_activities` row via
  `assertDatabaseHas()` — auto-discovery works end to end in this Laravel 12 app.
- **§5**: **24 event dispatch call sites**, not 22 — the design doc's own decision-list prose
  miscounted its own table (which always had 24 rows); fixed in §16 decision 2's text in the same
  pass. All 24 fire from the corrected categories (§5's pre-implementation fix): `appointments`
  (6), `treatment_plans` (5), `clinical_notes` (1), `billing` (4: 2 invoice + 2 payment),
  `medical_history` (3), `laboratory` (3), `imaging` (1), `documents` (1).
- **§7/§16 decision 3**: `PatientActivityPolicy::CATEGORY_SUBJECT_MAP` implemented exactly as
  specced — one category per real policy class, `allowedCategories()` checks each once per
  request via `$actor->can('viewAny', ...)`, feeding the controller's `whereIn('category', ...)`.
- **§8.1**: `GET /patients/{patient}/activities` — patient-scoped, paginated at 15/page,
  `?category=`/`?from=`/`?to=` filters, ordered most-recent-first.
- **§13/§16 decision 3's enforcement requirement**: the security-critical test §9A itself mandates
  is implemented and passing — a receptionist's request never returns `clinical_notes`-category
  rows, asserted directly against the response body (not inferred from a 403). One dispatch test
  per event (25 tests: 24 events + 1 idempotency test confirming `ClinicalNoteService::sign()`'s
  already-signed early-return never double-dispatches) plus 9 controller/pagination/filter/security
  tests — 34 new tests total. Backend: 1054/1054 green (1020 + 34, Pint clean).
- **One real type-safety finding, fixed**: `RecordsPatientActivity` originally read
  `$event->subject->patient_id` (the magic property), which PHPStan correctly flagged as
  undefined on the generic base `Model` type (every one of the 11 possible subject classes has
  `patient_id`, but the base type itself doesn't declare it) — switched to
  `$event->subject->getAttribute('patient_id')`, a properly-typed method call with identical
  runtime behavior. Confirmed via a targeted PHPStan run on the new files, not the full
  known-noisy local run.
- §16 decisions 4-6 (no backfill, `patient.updated` excluded, Appointments-tab retirement
  deferred) are scope decisions with nothing to "implement" in 2.6a; they remain in force for 2.6b
  and beyond.

## Implementation Summary — 2.6b UI (added post-implementation, 2026-08-09)

- **§8.1's "one embeddable component" requirement**: `ActivityTimeline.vue` (props `patientId`,
  optional `category`, `pageSize`, `compact`) backs all three call sites — the new `timeline` tab
  (unfiltered, category chips), Billing's Payment History (`category="billing"`, replacing its
  `FutureFeaturePlaceholder`), and an Overview preview card (`compact`, a "View Timeline" button
  emitting up to `PatientDetailView.vue` to switch tabs) — exactly as §9.4/§15.2 require.
- **A necessary deviation from every sibling store's shape**: `patientActivities.ts` keys its cache
  by `patientId::category::perPage`, not patient id alone. Reason, discovered during
  implementation, not anticipated in this doc: `PatientDetailView.vue`'s `Tabs` isn't `lazy`, so
  PrimeVue's `TabPanel` mounts every tab's content via `v-show`, not `v-if` — up to three
  `ActivityTimeline` instances (Overview preview, Billing embed, Timeline tab) are live
  simultaneously on one patient page, each wanting a different category/page-size slice. A
  patient-id-only cache (`patientDocuments.ts`'s convention) would let one instance's fetch
  silently overwrite another's.
- **§13's "Load more" pagination**: implemented as page-appending (`fetchNextPage` grows the
  query's accumulated id list), not `Paginator`-style page-replacement.
- **§12's mobile chip-row citation corrected**: this doc recommended matching "Laboratory's
  established mobile chip-row pattern" — that pattern doesn't exist. Laboratory's status filter is
  a plain PrimeVue `Select` on every viewport; no chip-row component exists anywhere in the
  codebase. Flagged and confirmed with the user before implementation (the same design-vs-codebase
  conflict protocol as §5's pre-implementation category-taxonomy fix) — the user chose to build the
  chip row as originally specified (`flex overflow-x-auto` on `<768px`, `flex-wrap` above) rather
  than fall back to a dropdown.
- **§18's testing requirements**: 27 new frontend tests (`patientActivities.test.ts`'s query-key
  isolation, `ActivityTimeline.test.ts`'s filter/pagination/compact-mode behavior), plus updated
  `PatientBillingPanel.test.ts`/`PatientDetailView.test.ts` assertions for the real wiring — 933/933
  frontend tests green, `vue-tsc`/ESLint/Prettier clean. `e2e/timeline.spec.ts` covers cross-module
  aggregation, category filtering, the Overview preview's tab-jump, and §18's security-critical
  case — a receptionist session never sees `clinical_notes`-category rows even when explicitly
  filtering for them — reusing `laboratory.spec.ts`'s and `clinical-notes.spec.ts`'s proven UI flows
  to generate real cross-module activity rather than seeding data directly.
- **i18n**: `patients.tabs.timeline` and a new `patients.timelinePanel.*` block added to all 3
  locales; the now-dead `patients.billingPanel.paymentHistoryTitle` key (no longer referenced by any
  component once `FutureFeaturePlaceholder` was replaced) was removed.
- **Merged via PR #32** (`70b6ba6`). **Post-merge CI's real E2E run** (PR-triggered runs skip this
  job by design; only a push to `main` executes it) **caught 2 real bugs in the E2E spec itself**,
  not application code: `case_number` read from `<h1>` before its async fetch replaced a fallback
  string, and an unscoped `getByText()` hitting a strict-mode violation across the exact
  simultaneously-mounted-instances scenario this doc's own store design (above) exists to handle —
  proof the multi-instance concern was real, not speculative. Fixed and **merged via PR #33**
  (`83c584b`); post-merge CI green on all three jobs. **Phase 2.6, and Phase 2 in full, complete.**

## 0. Why this doc exists separately from the umbrella design doc, and what's already binding

`docs/modules/patient-profile-redesign-design.md` sketches Phase 2.6 in more detail than any prior sub-phase had at its own drill-down stage: a full `patient_activities` schema (§7), model/relations (§6.1-6.2), API shape (§8), a named write-path architecture (§9.2 — domain events + one listener), a named read-path shape (§9.3), and — most importantly — a **binding Security Architecture Decision (§9A)**, approved 2026-08-07, quoted here in full because every design choice below must satisfy it without exception:

> **The decision, precisely stated**:
> 1. `PatientActivity`/Timeline is a **patient-events feed**, not a system audit log. It answers "what clinically/operationally happened to this patient," never "what changed on a database row." `Auditable`/`PatientAuditLog` remains the correct tool for the latter and is untouched by this decision — the two concepts are not merged.
> 2. **Timeline permissions are enforced exclusively server-side, at query time, per category.** A naive "show every activity for this patient" query is a real permission leak: a receptionist viewing Timeline would see summary lines for Clinical-Notes-derived events even though `ClinicalNotePolicy` bars receptionists from viewing Clinical Notes at all today.
> 3. **Concretely**: `GET /patients/{patient}/activities` must filter by the requesting user's role against each `category`'s owning Policy *before* rows leave the database — `category=clinical` rows are excluded from the query itself, never fetched-then-hidden client-side. `PatientActivityPolicy::viewAny()` must be implemented as a per-category lookup against the real owning policies, not a standalone rule that could drift out of sync with them.
> 4. Aggregation features are exactly where per-source permission is easiest to accidentally violate — this decision exists to make that failure mode structurally impossible.
> 5. **Enforcement, not just design intent**: covered by a dedicated backend Policy test *and* a Playwright E2E assertion — a receptionist-role request to `/activities` must never return `category=clinical` rows.

Despite that unusually complete sketch, this audit confirms real, unresolved structural decisions remain — the same "audit finds judgment calls the umbrella doc left implicit" pattern every prior phase encountered (§5's SQL bug for Laboratory, §7's category overlap for Documents). Here, the open items are:

1. **Event-dispatch mechanism is unbuilt from zero** — no `app/Events`/`app/Listeners` directory exists at all today. This is Laravel's event system being introduced to this codebase for the first time, not an extension of an existing pattern.
2. **No per-service event inventory exists** — the umbrella doc names 8 example event types but the 9 candidate services have many more lifecycle points than that (§19 of the umbrella doc itself names this the acknowledged risk: "room to silently miss an event type").
3. **`PatientActivityPolicy`'s category-to-policy lookup mechanism is unspecified** — §9A mandates it but doesn't say *how*, and gets the tension between correctness (checking the real owning policy) and performance (not doing it per-row) exactly right as the thing to resolve.
4. Whether `patient.updated` (§9.2's suggestion to reuse the `Auditable` write path as one more trigger) blurs §9A's "the two concepts are not merged" line.
5. Whether historical data gets backfilled into `patient_activities` or Timeline starts empty.
6. Whether the Appointments tab's retirement into Overview + Timeline (umbrella doc §3) is in scope for this phase or deferred.

## 1. Current State Audit (read directly from `main` @ `933ee90`, not assumed)

### 1.1 Backend — confirmed absent, and no event infrastructure exists at all

| Piece | Search performed | Result |
|---|---|---|
| `PatientActivity` model / `patient_activities` migration | `backend/app/Models/`, `backend/database/migrations/` | **Do not exist.** |
| `app/Events/`, `app/Listeners/` | `backend/app/` | **Directories do not exist at all.** No Laravel event/listener has ever been used in this codebase. |
| Any `event()`/`Event::`/`::dispatch()` call | Every file in `backend/app/Services/` | **Zero matches.** No service dispatches anything today. |
| `Patient::activities()` relation | `backend/app/Models/Patient.php` (lines 61-119 read in full) | **Does not exist** — 12 relations present (`appointments`, `dentalChartEntries`, `treatmentPlans`, `invoices`, `payments`, `clinicalNotes`, `images`, `allergies`, `labCases`, `documents`, `medicalConditions`, `medications`), no `activities`. |
| `PatientActivityController`/route | `backend/routes/api.php` | **No Activity/Timeline route of any kind.** The only existing patient-history endpoint is `GET /patients/{patient}/audit-logs` (admin-only, `PatientController::auditLogs()`), which returns only field-diffs on the `Patient` row itself via `Auditable` — not a cross-module feed, and cannot become one. |
| Queue worker actually running | `docker-compose.yml`, `grep ShouldQueue` | **No queue-worker service, no job ever implements `ShouldQueue`.** `QUEUE_CONNECTION=redis` is configured but unused — Redis here backs cache/sessions only, not actual job queuing. |

### 1.2 `App\Models\Concerns\Auditable` — read in full, confirming why §9A forbids reusing it

```php
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::observe(AuditObserver::class);
    }

    public function auditLogs(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'auditable')->latest('created_at');
    }
}
```
`AuditObserver` fires on Eloquent `created`/`updated`/`deleted` and writes one `audit_logs` row per event via `AuditLogService::record()` — `action` (string), `changes` (raw column-diff JSON), no `category`, no human-readable summary, no cross-model union capability. It answers "what changed on this row," never "what happened to this patient" — a categorically different shape than Timeline needs, exactly as §9A states. **19 models** currently `use Auditable` (confirmed via grep), including every model this phase would need to source events from (`Appointment`, `TreatmentPlan`, `TreatmentPlanItem`, `ClinicalNote`, `Invoice`, `InvoiceItem`, `Payment`, `PatientAllergy`, `PatientMedicalCondition`, `PatientMedication`, `LabCase`, `PatientImage`, `PatientDocument`) — so field-level audit logging already exists everywhere, but per §9A it is explicitly the wrong source to build Timeline on.

### 1.3 Frontend — confirmed absent, identical starting point to Documents

`PatientDetailView.vue`'s `tabDefinitions` (lines 76-85) currently lists exactly 10 keys: `overview, medicalHistory, appointments, dentalChart, imaging, laboratory, treatmentPlans, clinicalNotes, billing, documents`. **No `timeline` entry, no `comingSoon` stub.** The only forward-reference is a code comment ("...redesign adds (Laboratory, Documents, Timeline) extends this array..."). No Timeline store/service/component exists anywhere in `frontend/src` — the only `Timeline`-named file, `AppointmentTimeline.vue`, is an unrelated per-appointment status stepper on `AppointmentDetailView.vue`, not a cross-module feed.

**One real placeholder already points at this phase**: `PatientBillingPanel.vue`'s Payment History sub-section currently renders `FutureFeaturePlaceholder.vue` with `patients.billingPanel.paymentHistoryTitle` — `docs/PROJECT_STATUS.md` already states this explicitly: "the real Timeline-backed feature isn't buildable until Phase 2.6." Wiring it live is real, in-scope work for this phase (§8.2).

### 1.4 What this confirms vs. the umbrella doc's 2.6 outline

The umbrella doc's Timeline sketch is the most complete of any prior phase's starting point (full schema, named architecture, binding security decision) — but §0's own six open items above are genuinely unresolved. §2-§9 below resolve them.

## 2. Relationship to the 9 candidate modules and to `Auditable`

Timeline (`PatientActivity`) is a **new, additive, read-mostly aggregation layer** — it does not replace, modify, or read from any existing module's tables directly at write time (writes flow through explicit domain events, §4), and it does not touch `Auditable`/`AuditLog`/the existing admin-only Patient audit-log view, which remain exactly as they are. No existing service's business logic changes; each service gains one additional line (`event(new X(...))`) at its key lifecycle moments, per §5.

## 3. Scope for Phase 2.6

**In scope**: `PatientActivity` model/migration, `Patient::activities()`, a first-cut domain-event inventory across the 9 candidate services (§5), one `RecordsPatientActivity` listener, `PatientActivityPolicy` with the per-category lookup mechanism (§7), `GET /patients/{patient}/activities` (paginated, `?category=`, `?from=&to=`), `ActivityTimeline.vue` (embeddable — full Timeline tab **and** Billing's Payment History sub-section **and** an Overview recent-activity preview, per umbrella doc §9.4), the new `timeline` tab (last, per umbrella doc §3).

**Recommended out of scope for this phase** (§16 decisions 4-6): historical backfill, `patient.updated` as a Timeline-sourced event, and Appointments-tab retirement into Overview — each flagged as its own decision below, not silently dropped.

## 4. Event-Dispatch Architecture

**Recommend**: explicit `event(new XActivity(...))` calls added inline at the end of each relevant service method — **not** model observers. A model observer (mirroring `AuditObserver`) fires on *any* `created`/`updated`/`deleted`, indistinguishable from *which* business action caused it — e.g. `InvoiceService::issue()` and `InvoiceService::void()` both call `$invoice->update(...)`, but they are semantically different activity types (`invoice.issued` vs `invoice.voided`) that only the service method itself knows. Explicit per-method dispatch is the only mechanism that captures intent, not just "a row changed."

**Recommend**: a **single synchronous listener**, `RecordsPatientActivity`, subscribing to all event classes (matches umbrella doc §9.2's "single listener" design) — not queued. No queue worker actually runs in this project today (§1.1); introducing real background-job infrastructure just for this would be a second, larger architectural change this phase shouldn't also take on. A synchronous listener writing one indexed INSERT is cheap and matches every other service's fully-synchronous request lifecycle.

**Recommend**: each event class carries `(Model $subject, ?User $actor, array $context = [])` — enough for the listener to derive `patient_id` (via each subject's own `patient_id`/`patient()` relation, or `treatmentPlan->patient_id` for `TreatmentPlanItem`, etc.), `category` (denormalized per event class), `event_type` (the event class's own short name, e.g. `invoice.issued`), and `summary` (each event class implements a `summary(): string` method producing the human-readable line, e.g. `"Invoice INV-00042 issued for $340.00"` — keeps summary-formatting logic co-located with the event that knows its own subject's shape, not centralized in the listener).

## 5. Per-Service Event Inventory (recommended first-cut, addresses umbrella doc §19's named risk)

Read directly from each service's actual public methods (not assumed) — recommend firing an activity event at exactly these points, one per row.

**Correction found during implementation prep (post-approval)**: the category column below was originally a single `clinical` value covering Treatment Plans, Clinical Notes, and Medical History. Verifying each subject's real `viewAny()` before writing §7's `CATEGORY_SUBJECT_MAP` found this was wrong: `TreatmentPlanPolicy::viewAny()` and `MedicalHistoryPolicy::viewAny()` both return `true` (all staff), but `ClinicalNotePolicy::viewAny()` is `Admin`/`Dentist`-only — three subject types under one category with three different permission rules is exactly the leak §9A exists to prevent, just relocated from query-time to category-design-time. Split into `treatment_plans`, `clinical_notes`, and `medical_history` below, each mapping 1:1 to its real owning policy, so §7's per-category-check mechanism is actually correct. No event types, methods, or other categories changed — `billing` (`Invoice`/`Payment`, both `viewAny: true`) and `appointments` (single subject) were checked and don't have this problem.

| Service | Method | Event / `event_type` | `category` |
|---|---|---|---|
| `AppointmentService` | `confirm()` | `appointment.confirmed` | `appointments` |
| | `checkIn()` | `appointment.checked_in` | `appointments` |
| | `start()` | `appointment.started` | `appointments` |
| | `complete()` | `appointment.completed` | `appointments` |
| | `cancel()` | `appointment.cancelled` | `appointments` |
| | `markNoShow()` | `appointment.no_show` | `appointments` |
| `TreatmentPlanService` | `present()` | `treatment_plan.presented` | `treatment_plans` |
| | `accept()` | `treatment_plan.accepted` | `treatment_plans` |
| | `reject()` | `treatment_plan.rejected` | `treatment_plans` |
| | `complete()` | `treatment_plan.completed` | `treatment_plans` |
| | `cancel()` | `treatment_plan.cancelled` | `treatment_plans` |
| `ClinicalNoteService` | `sign()` | `clinical_note.signed` | `clinical_notes` |
| `InvoiceService` | `issue()` | `invoice.issued` | `billing` |
| | `void()` | `invoice.voided` | `billing` |
| `PaymentService` | `record()` | `payment.recorded` | `billing` |
| | `refund()` | `payment.refunded` | `billing` |
| `MedicalHistoryService` | `addAllergy()` | `medical_history.allergy_added` | `medical_history` |
| | `addCondition()` | `medical_history.condition_added` | `medical_history` |
| | `addMedication()` | `medical_history.medication_added` | `medical_history` |
| `LabCaseService` | `send()` | `lab_case.sent` | `laboratory` |
| | `receive()` | `lab_case.received` | `laboratory` |
| | `qualityCheck()` | `lab_case.quality_checked` | `laboratory` |
| `PatientImageService` | `storeOne()` (once per uploaded file) | `image.uploaded` | `imaging` |
| `PatientDocumentService` | `storeOne()` (via `upload()`) | `document.uploaded` | `documents` |

**Deliberately excluded from this first cut** (documented, not silently dropped): draft-stage/administrative mutations that aren't clinically or operationally meaningful on a patient-facing feed — `TreatmentPlanService::createPlan/updatePlan/addItem/updateItem` (item-level editing, too granular — the plan-level `present/accept/reject/complete/cancel` transitions are the meaningful activity), `InvoiceService::createDraft/updateDraft/addItem` (same reasoning — `issue()` is the meaningful moment), `ClinicalNoteService::create/update` (only `sign()` — an unsigned draft isn't yet a clinical fact), `MedicalHistoryService`'s `update*`/`delete*` variants (an allergy being *added* is activity-worthy; a typo correction to its notes field is not). §18 Testing requires a dispatch test per included event — this table is the authoritative list that testing verifies against, and is itself a §16 decision (any addition/removal should be agreed before implementation, not discovered mid-PR).

## 6. Database Changes

```
patient_activities
  id (uuid, pk)
  patient_id     (fk -> patients, cascade-delete)
  event_type     (string, e.g. "invoice.issued" — plain column, one-line PHP
                   enum/const change to add a case, matching every other
                   status-string column in this codebase)
  category       (string — one of "appointments" | "treatment_plans" |
                   "clinical_notes" | "billing" | "medical_history" |
                   "laboratory" | "imaging" | "documents", each mapping 1:1
                   to one real Policy's viewAny() per §7 — denormalized at
                   write time so §7's per-category filter is a plain
                   WHERE IN, never a prefix/LIKE match on event_type)
  subject_type, subject_id  (polymorphic — the underlying Appointment/
                   TreatmentPlan/ClinicalNote/Invoice/Payment/PatientImage/
                   LabCase/PatientDocument row)
  actor_id       (fk -> users, nullable, null-on-delete)
  summary        (string — precomputed human-readable text, produced by the
                   event class itself per §4, so Timeline never joins back
                   to the subject table just to render a list)
  metadata       (nullable json — event-specific extra fields, e.g. an
                   invoice's amount, so summary can be redone if unused)
  occurred_at    (timestamp, indexed)
  created_at

  index (patient_id, occurred_at)
  index (patient_id, category, occurred_at)
```
No `updated_at`/`deleted_at` — activity rows are immutable, append-only facts, never edited or soft-deleted (matches `AuditLog`'s own create-only shape).

## 7. Permission Enforcement Mechanism (resolves §0 item 3 — the load-bearing decision)

**Recommend**: a static `CATEGORY_SUBJECT_MAP` constant on `PatientActivityPolicy` — `category => fully-qualified subject model class`, **one category per real policy, never a category spanning two policies with different rules** (the reason for §5's correction): `'appointments' => Appointment::class`, `'treatment_plans' => TreatmentPlan::class`, `'clinical_notes' => ClinicalNote::class`, `'billing' => Invoice::class` (`Payment::class`'s `viewAny()` is identically `true`, so either subject class works as the category's representative), `'medical_history' => PatientAllergy::class` (one of `MedicalHistoryPolicy`'s three covered models — all three share one policy, so any is representative), `'laboratory' => LabCase::class`, `'imaging' => PatientImage::class`, `'documents' => PatientDocument::class`. At query time, `PatientActivityController::index()` computes the allowed-categories list **once per request** — `array_filter(array_keys(CATEGORY_SUBJECT_MAP), fn ($category) => $actor->can('viewAny', CATEGORY_SUBJECT_MAP[$category]))` — then adds a single `whereIn('category', $allowedCategories)` to the query.

This resolves the exact tension §0 item 3 named:
- **Correctness, no drift**: it calls each category's *real* owning policy's actual `viewAny()` — e.g. `$actor->can('viewAny', ClinicalNote::class)` literally invokes `ClinicalNotePolicy::viewAny()`, the same method every other Clinical Notes read-check already uses. If that policy's role rule ever changes, Timeline's filtering changes with it automatically — no separate role map to fall out of sync.
- **Performance**: the `can()` check runs **once per category** (at most 6-7 times per request, a fixed small number independent of row count), not once per row — a plain indexed `WHERE category IN (...)` then does the actual row filtering in SQL. This is the same class-level-check shape every existing `viewAny()` in this codebase already uses (none of them take a model instance — confirmed by reading `ClinicalNotePolicy::viewAny()`), so this pattern needs no new capability from Laravel's authorization system, just applying an existing one at aggregation time.

`patient_activities` itself needs no dedicated per-category migration column beyond `category` (already in §6's schema) — `CATEGORY_SUBJECT_MAP` is pure PHP, versioned with the code, reviewed the same way any other policy change is.

## 8. Patient Profile Integration

### 8.1 Tab position
Last, after `documents` — matches the umbrella doc's own §3 rationale ("a cross-cutting *view onto* everything else, not a primary workspace") and requires no reordering of any existing tab.

### 8.2 Reuse: Billing's Payment History + Overview preview
`ActivityTimeline.vue` takes `patientId`, an optional `category` filter (Billing passes `category="billing"`), and an optional `pageSize` (Overview passes a small number for a preview card) — per umbrella doc §9.4/§15.2, one component backs all three call sites, not three separate features. `PatientBillingPanel.vue`'s `FutureFeaturePlaceholder` is replaced with `<ActivityTimeline :patient-id="patientId" category="billing" />` in this phase — real, in-scope work (§0/§1.3), not a separate deferred item.

## 9. Permission Matrix

| Action | Rule |
|---|---|
| `viewAny` (read Timeline) | All staff, **but rows filtered per-category by §7's mechanism** — not a flat role check |
| Write | N/A — `patient_activities` rows are written only by `RecordsPatientActivity`, never via a user-facing create/update/delete endpoint |

## 10. Pagination Strategy

15/page on `GET /patients/{patient}/activities`, matching every other patient-scoped list endpoint. `?category=` and `?from=&to=` filters per umbrella doc §8. The Overview preview and Billing's embed pass a smaller `per_page` directly (same param, different value), not a separate endpoint.

## 11. i18n Requirements

New keys: `patients.tabs.timeline`, `patients.timelinePanel.*` (empty state, category filter labels), and a top-level `timeline.*` namespace for `event_type`-keyed summary/label strings if any client-side re-formatting is needed beyond the server-computed `summary` (recommend: **prefer the server-computed `summary` string as-is**, avoid a client-side per-`event_type` i18n map that could drift out of sync with §5's inventory — only category *filter chip labels* need i18n, not per-event-type strings). Added in `ar`/`en`/`tr` with parity verified programmatically, matching every prior phase.

## 12. Mobile UX

`ActivityTimeline.vue` uses "Load more" pagination (not infinite scroll), per umbrella doc §15.2 — chosen there because the same component embeds as a small preview card on Overview, where infinite scroll doesn't make sense. Category filter renders as a horizontal scrollable chip row on `<768px`, matching this app's established mobile-filter pattern elsewhere (e.g. Laboratory's status filter).

## 13. Testing Strategy

- **Backend**: one dispatch test per §5 event (asserting the service method fires the right event class with the right subject — "worth a test per event, not just per controller," per umbrella doc §18), Feature tests for `GET /activities` pagination/category-filter/date-range, and **the security-critical test §9A itself mandates**: a receptionist-role request to `/activities` for a patient with `clinical_notes`-category activity must never return those rows, asserted directly against the response body — not inferred from a 403 or from UI hiding.
- **Frontend**: Vitest tests for a `patientActivities.ts` store (or a lighter fetch-only composable, since Timeline has no mutation actions — see §16 decision 7), `ActivityTimeline.vue` rendering/pagination/category-filter tests, and Playwright E2E per umbrella doc §18: role-based tab visibility, and a receptionist session confirmed not to see `clinical_notes`-category entries end-to-end (not just unit-tested).

## 14. Implementation Sub-phases (proposed)

Given umbrella doc §17/§19's own "**Highest** risk — touches every module's service" rating (the only phase rated above Medium), **recommend splitting this phase into 2 PRs**, unlike every prior sub-phase's single-PR convention:
1. **2.6a — Foundation**: `PatientActivity` model/migration, event classes + `RecordsPatientActivity` listener wired into all 9 services per §5's inventory, `PatientActivityPolicy` + §7's mechanism, `GET /activities` endpoint, backend tests (including the §9A security test). No frontend UI yet — verifiable end-to-end via API tests alone.
2. **2.6b — UI**: `ActivityTimeline.vue`, the `timeline` tab, Billing's Payment History wiring, Overview preview, frontend tests, i18n.

This mirrors how a "touches everything" risk is best reviewed — a reviewer can verify the event inventory and security enforcement in one focused PR before a second, lower-risk UI PR lands on top of already-tested foundations.

## 15. Deferred Items / Tech Debt Carried Forward

- **No historical backfill** (§16 decision 4) — Timeline starts empty at ship and only shows activity from that point forward. Backfilling would mean reconstructing human-readable `summary` text for years of pre-existing Appointments/Invoices/etc. rows across 9 different tables — a large, error-prone one-time script disproportionate to a V1 ship, unlike Medical History's Phase 2.3 backfill (a single legacy text column, not a multi-module reconstruction).
- **`patient.updated` not included as a Timeline event** (§16 decision 5) — keeps §9A's "the two concepts are not merged" boundary unambiguous; the existing admin-only Patient audit-log view remains the sole place to see field-level `Patient` row changes.
- **Appointments tab retirement deferred, not part of this phase** (§16 decision 6) — umbrella doc §3 envisions Appointments folding into Overview + Timeline eventually; bundling that IA change into the same phase as "build Timeline from zero" compounds an already-Highest-risk phase with an unrelated navigation restructuring. Recommend a future Phase 2.7 (or later) if the user still wants it.
- Tags/labels, in-record search, PDF export remain named as deferred backlog (umbrella doc §19.4), not dropped, unaffected by this phase.

## 16. Decisions Requiring Approval Before Implementation

1. **§4 — Event-dispatch mechanism: explicit inline `event()` calls per service method, one synchronous listener (`RecordsPatientActivity`), no queue.** Recommend **yes** — a model-observer approach can't distinguish business intent (§4), and no queue infrastructure actually runs in this project today, confirmed by audit. Alternative: introduce real queued jobs — a materially bigger change this phase shouldn't also absorb.
2. **§5 — The 24-event first-cut inventory across 9 services** (the table has 24 rows; an earlier draft of this decision text miscounted it as 22), deliberately excluding draft/administrative-only mutations (full table in §5). Recommend **as listed** — reviewable now as the authoritative list §13's dispatch tests verify against. Alternative: add/remove specific events (e.g. include `treatment_plan.item_completed` at item granularity) — flag any specific additions/removals now, not mid-implementation.
3. **§7 — Permission mechanism: a static `CATEGORY_SUBJECT_MAP` + one `viewAny()` check per category per request, feeding a `WHERE IN` filter.** Recommend **yes** — resolves §0 item 3's correctness-vs-performance tension by reusing each category's real owning policy at the class level (no drift, no per-row cost). Alternative: a hand-maintained role-to-category map — explicitly what §9A's own text warns against ("could drift out of sync").
4. **§15 — No historical backfill; Timeline starts empty and populates forward only.** Recommend **yes** — reconstructing multi-module historical summaries is large, error-prone, and disproportionate to V1. Alternative: backfill some bounded window (e.g. last 90 days) — a real option if the user wants Timeline to feel populated on day one, at added implementation cost.
5. **§15 — `patient.updated` is NOT wired as a Timeline-sourced event in this phase.** Recommend **yes**, to keep §9A's "not merged" boundary unambiguous. Alternative: wire it as umbrella doc §9.2 originally suggested — technically fine, just needs explicit confirmation it doesn't blur the boundary.
6. **§15 — Appointments-tab retirement into Overview + Timeline is deferred, not part of 2.6.** Recommend **yes** — keeps this already-Highest-risk phase scoped to "build Timeline," not "build Timeline and restructure another tab." Alternative: bundle it in now per the umbrella doc's original vision — larger diff, more review surface, in exchange for finishing the full IA vision in one phase instead of two.
7. **§14 — Split into two PRs (2.6a foundation, 2.6b UI)**, a deviation from every prior sub-phase's single-PR convention. Recommend **yes**, given this is the only phase rated "Highest risk" in the umbrella doc's own §17 table. Alternative: one PR as usual — larger review surface for the riskiest phase of the whole initiative.

No implementation begins until these are resolved.
