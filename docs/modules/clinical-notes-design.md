# Clinical Notes Module — Design Approved (2026-07-25)

**Status: Design approved, Phase 1 complete. All four open decisions (§15) resolved at approval — see the
Decision Log at the end of §15. Phase 2 implementation is now authorized and in progress; this document is
the canonical design reference until superseded by a final `modules/clinical-notes.md` doc at Final Review.**

Grounded in the current, shipped state of the codebase (`DentalChartEntry`/`TreatmentPlanItem` — specifically
their draft/lock-on-commit and one-way-reference patterns, `Auditable` trait, `docs/database-design.md`,
`docs/api-guidelines.md`, `docs/decisions.md`) — verified directly, not assumed.

---

## 0. Competitive Research (required before any design, per standing product philosophy)

**Open Dental** — has no separate "clinical note" entity at all: procedure notes live on the procedure
record itself ("those are all just ordinary procedures that use a dummy procedure code" for freestanding
notes). Default/**Auto Notes** are per-procedure-code templates that auto-copy into a procedure when
treatment-planned or completed. **Group Notes** let one note be attached to several same-day completed
procedures. Notably: editing a saved procedure note **archives the previous version** rather than blocking
the edit outright — an audit trail substitutes for a hard lock. ([opendental.com/manual/notes.html](https://www.opendental.com/manual/notes.html))

**Dentrix (Ascend/Enterprise)** — clinical notes are a **dedicated entity**, distinct from procedures, and
every clinical action (procedure, exam, condition, treatment plan, referral, or a manually authored note)
automatically appends a line to a unified, chronological **Progress Notes** panel/timeline beneath the
graphic chart — a single view of "everything that happened," not just freeform text. A separate **Clinical
Notes** tab exists for the note bodies themselves, sortable by date or provider. Templates are supported for
efficiency and consistency. ([blog.dentrix.com](https://blog.dentrix.com/blog/2015/04/07/progress-notes-in-the-patient-chart/), [support.dentrixascend.com](https://support.dentrixascend.com/hc/en-us/articles/229955407-Entering-clinical-notes))

**CareStack** — dedicated clinical notes linked to treatment/CDT codes, with **auto-launch** on a procedure
being proposed or completed and an optional **mandatory-completion** enforcement (can't leave a procedure
without its note). Providers **sign off with a password**; signing does **not** permanently lock the note —
staff can **open an addendum at any time** afterward. Supports templates, merging multiple notes into one,
and an "incomplete notes" worklist per provider. ([carestack.com/clinical-notes](https://carestack.com/dental-software/features/clinical-notes))

**Patterns extracted for this design:**
1. A **dedicated note entity** (Dentrix/CareStack), not Open Dental's procedure-note trick — fits this
   project's existing convention of dedicated tables per concern (`dental_chart_entries`,
   `treatment_plan_items`, `invoices`) far better than repurposing another table.
2. **Sign, don't hard-delete-on-edit** — CareStack's "signed but addendum-able" model, not a permanently
   frozen record with zero correction path. This also matches the user's own stated principle ("Lock after
   signing; no silent edits... Addendum mechanism can be considered later") more closely than Open Dental's
   silent-archive-on-edit approach.
3. A **unified chronological timeline** (Dentrix's Progress Notes panel) is a genuinely valuable UX pattern,
   but it aggregates data this system doesn't have in one place today (chart entries + plan events +
   appointments + notes). Proposed as a **future improvement** (§16), not V1 scope — building it now would
   mean touching three already-shipped modules' read paths for a feature not yet requested.
4. **Templates/auto-launch** (Open Dental Auto Notes, CareStack templates) are real UX wins but add real
   scope (a template CRUD surface, per-procedure-code binding). Proposed as **out of scope for V1** (§16),
   consistent with this project's "don't build ahead of a real need" principle — V1 ships freeform structured
   text; templates can follow once real usage shows which notes get repeated most.

---

## 1. Module Goal / Purpose

Give dentists a place to record the **narrative clinical documentation** of a visit — what the patient
reported, what was examined, what was assessed, and what was done or planned — as a permanent, signed legal
record. This is the piece Dental Chart (structured tooth-level findings) and Treatment Plans (costed
procedure proposals) don't cover: neither captures free-text clinical reasoning, informed consent, or a
narrative account of what happened. Clinical Notes is the missing link between Dental Chart, Treatment
Plans, Appointments, and the day-to-day clinical workflow.

## 2. Scope (V1)

**In scope:**
- One clinical note per authoring session, structured as SOAP-style sections (Subjective / Objective /
  Assessment / Plan) plus a chief complaint field — all optional/nullable (not every note needs every
  section; a quick post-op check might only fill Objective).
- A `note_type` classification (`progress`, `consultation`, `phone`, `referral`, `other`) for filtering,
  mirroring Dentrix's categorization without building a full configurable-type system.
- Draft → Signed lifecycle: freely editable while `draft`; frozen once `signed`.
- A lightweight **Addendum** mechanism: append-only corrections/updates to an already-signed note (§15,
  Decision A — **approved for V1**). Addendums are permanent once created: no update or delete endpoint
  exists for them at any permission level, not even admin — a correction to an addendum is itself a new
  addendum, never an edit.
- Optional linkage to a specific `Appointment` (most notes are visit-scoped) — nullable, since some notes
  (a phone consult, a referral letter copy) have no associated appointment.
- Patient Detail "Clinical Notes" tab (list) + a dedicated detail view (author/edit/sign/addendum), following
  the existing tab-list + dedicated-detail-route pattern from Treatment Plans/Invoices.

**Explicitly out of scope for V1** (see §16 Future Improvements and `TECH_DEBT.md` once implemented):
note templates/auto-launch, mandatory-completion enforcement tied to procedures, a unified cross-module
"Patient Timeline" view, voice-to-text/AI-assisted drafting, e-signature beyond an in-app "Sign" action
(no cryptographic signature, no external ID verification), multi-provider co-signing, structured
ICD-10/CDT coding fields on the note itself (Treatment Plans/Dental Chart already own procedure/diagnosis
coding).

## 3. Full Workflow

1. Dentist (or admin) opens a patient's "Clinical Notes" tab and clicks **New Note**, optionally
   pre-selecting a recent/upcoming appointment to associate it with.
2. Note is created as `draft`. Dentist fills in chief complaint / SOAP sections, saves incrementally (like a
   normal draft — no separate "save draft" vs. "save" distinction, matching how Treatment Plans' `draft`
   status already works).
3. When satisfied, the dentist **Signs** the note. This is the same authenticated action, no separate
   password re-entry (V1 relies on the existing session, unlike CareStack's password-per-sign — §15 Decision
   B, approved). Signing is wrapped in a **DB transaction**: it sets `signed_at`/`signed_by_id` and freezes
   every content field atomically — never a partial sign.
4. If a correction or update is needed after signing, the dentist adds an **Addendum** — a short, separately
   timestamped, separately authored note appended below the original. The original body is never edited, and
   once created an addendum itself can never be edited or deleted (§8 rule 4).
5. Admin can soft-delete a note, including an already-signed one (§15 Decision C, approved) — fully
   audit-logged and recoverable, never a hard delete. Addendums can never be deleted, by anyone, regardless
   of role.

## 4. Core Concepts (definitions)

- **Clinical Note** — the primary record: one chief complaint + SOAP text, one author, one status.
- **Addendum** — a small, append-only child record (text + author + timestamp) attached to a note, used only
  after the parent note is `signed`. Never edits the parent; never has its own further children.
- **Author (`dentist_id`)** — the clinician of record, same "clinical attribution vs. system actor" split
  already used by `DentalChartEntry`/`TreatmentPlanItem` (`created_by_id` may differ, e.g. admin charting on
  a dentist's behalf).

## 5. Status Lifecycle

`ClinicalNoteStatus`: `draft` → `signed` (terminal). No `cancelled` state — a mistaken draft is simply
soft-deleted (admin-only, same as every other module's "delete is tighter-gated than a clinical transition"
precedent), and a signed note is corrected via Addendum, never transitioned back to draft. This is
deliberately simpler than Dental Chart (5 statuses) or Treatment Plans (5+4 statuses) — a note's lifecycle
genuinely has only one meaningful transition.

```
draft --sign--> signed (terminal)
  |
  +--delete (admin only, soft delete)
```

Addendums have no status and no lifecycle at all — they are created once and permanently retained. No
update or delete route exists for them at any permission level (§8 rule 4, §15 Decision A).

## 6. Database Design

```sql
clinical_notes
  id                  uuid primary key
  patient_id          uuid references patients            not null
  appointment_id      uuid references appointments         nullable  -- not every note is visit-scoped
  dentist_id          uuid references users                not null  -- clinical author of record
  note_type           varchar                              not null  -- cast to NoteType enum
  chief_complaint     text                                 nullable
  subjective          text                                 nullable
  objective           text                                 nullable
  assessment          text                                 nullable
  plan                text                                 nullable
  status              varchar                              not null default 'draft'  -- cast to ClinicalNoteStatus
  signed_at           timestamp                            nullable
  signed_by_id        uuid references users                nullable
  created_by_id       uuid references users                not null  -- system actor, mirrors existing pattern
  updated_by_id       uuid references users                nullable
  created_at / updated_at / deleted_at (soft deletes)

  index (patient_id, created_at)
  index (appointment_id)
  index (patient_id, status)

clinical_note_addendums
  id                  uuid primary key
  clinical_note_id    uuid references clinical_notes        not null
  author_id           uuid references users                 not null
  body                text                                  not null
  created_at                                                 -- created_at only, no updated_at/deleted_at

  index (clinical_note_id, created_at)
```

`clinical_notes` gets the `Auditable` trait, UUID PK, soft deletes — matching every prior module. Deliberate
deviation for `clinical_note_addendums` (§15 Decision A, approved): **no `updated_at`, no `deleted_at`, no
soft-delete trait, no update/delete model methods** — immutability is enforced at the schema level, not just
the policy level, so an addendum cannot be silently modified even by a future code change that forgets to
check the policy. Creation events are still fully captured by `Auditable` (inherited from the parent via the
service layer's own audit-log write) plus the row's own permanent `created_at`/`author_id`.

## 7. Table Relationships — and the No-Cross-Link Decision

`ClinicalNote` references `Patient` (required), `Appointment` (nullable), and two `User`s (author +
system actor) — the same shape as `DentalChartEntry`/`TreatmentPlanItem`.

**Deliberately no FK from `ClinicalNote` to `DentalChartEntry` or `TreatmentPlanItem`.** Both of those
modules already point *forward* in time (a plan item optionally cites the chart finding that prompted it);
a clinical note describing "what happened at today's visit" doesn't need to formally cite specific chart
entries or plan items to be useful — anyone reviewing the patient's record already sees the Dental Chart,
Treatment Plans, and Clinical Notes tabs side by side, correlated by date/patient. Adding explicit
cross-links now would be speculative coupling with no concrete use case yet (this project's established
"don't build ahead of a real need" principle — same reasoning already applied to `dental_conditions`'
pricing-catalog reuse and to Multi-Branch). If a real reporting or auto-fill need for such links appears
later, add a nullable FK then rather than now.

## 8. Business Rules (consolidated)

1. A note is fully editable only while `status = draft`.
2. Signing requires at least one non-empty content field (an entirely blank note cannot be signed) —
   prevents an accidental empty legal record.
3. Signing is atomic: `DB::transaction()` wraps setting `signed_at`/`signed_by_id` and freezing every
   content field (`chief_complaint`/`subjective`/`objective`/`assessment`/`plan`, `note_type`,
   `appointment_id`) in one unit — mirroring `TreatmentPlanItemLockedException`/`InvoiceLockedException`'s
   exact enforcement pattern (a dedicated `ClinicalNoteLockedException` blocks any further write to those
   fields at the service layer, checked before the transaction even opens for `update()`).
4. Addendums may only be **added** to a `signed` note (adding context to a draft is just editing the draft
   itself), and once created an addendum is permanent — no endpoint, at any permission level including
   admin, can update or delete one. A correction to an addendum is a new addendum.
5. No per-patient-assigned-dentist / ownership check — any dentist may author, sign, or addend any patient's
   note, inheriting Dental Chart's and Treatment Plans' already-approved precedent (no "assigned/primary
   dentist" concept exists in this system).
6. Delete of the parent `ClinicalNote` is admin-only, soft delete, allowed regardless of `draft`/`signed`
   status (§15 Decision C, approved) — never a hard delete, always fully `Auditable`-logged and recoverable
   via `restore()`. This never cascades to delete addendums, which have no delete path at all.

## 9. API Design

```
GET    /api/patients/{patient}/clinical-notes          index, paginated, newest first
POST   /api/patients/{patient}/clinical-notes           create (draft)
GET    /api/clinical-notes/{clinicalNote}                show (with addendums eager-loaded)
PATCH  /api/clinical-notes/{clinicalNote}                update (draft only — 409 via ClinicalNoteLockedException if signed)
POST   /api/clinical-notes/{clinicalNote}/sign           sign
DELETE /api/clinical-notes/{clinicalNote}                soft delete (admin only)

POST   /api/clinical-notes/{clinicalNote}/addendums      add addendum (signed notes only)
```

No `PATCH`/`DELETE` route for a single addendum exists anywhere — intentional, not an oversight (§8 rule 4).

Mirrors `InvoiceController`'s `/issue`/`/void` and `TreatmentPlanController`'s
`/present`/`/accept`/`/reject` action-route pattern rather than overloading `PATCH` with a status field —
consistent with every prior module's status-transition API shape.

## 10. Permissions

`ClinicalNotePolicy`:
- `viewAny`/`view`: **admin + dentist only, receptionist excluded entirely** (§15 Decision D, approved) — a
  deliberate divergence from Dental Chart/Treatment Plans (which both grant receptionist read access for
  scheduling context). Clinical narrative content (informed-consent language, behavioral observations, exam
  detail) is more sensitive than a structured finding or a cost estimate.
- `create`/`update` (draft only)/`sign`/`addendum`: admin + dentist, no ownership/IDOR check (§8 rule 5).
- `delete`: admin only, any status (§8 rule 6).
- No policy ability exists for updating or deleting an addendum — not "admin only," **none at all** (§8 rule 4).

## 11. Frontend UX Design (high-level — a full pass follows a later checkpoint)

- New **"Clinical Notes"** tab on `PatientDetailView.vue` (`PatientClinicalNotesPanel.vue`), sibling to
  Overview/Appointments/Dental Chart/Treatment Plans/Invoices/Payments — listing notes newest-first with a
  status chip (Draft/Signed), note type, author, and date.
- Dedicated `ClinicalNoteDetailView.vue` route (`/patients/:id/clinical-notes/:noteId`) — SOAP fields need
  more room than a tab panel comfortably gives, matching the precedent already set by
  `TreatmentPlanDetailView.vue`/`InvoiceDetailView.vue` ("detail is too much content for a tab panel").
- While `draft`: inline-editable SOAP fields, a prominent **Sign** action (with a confirmation dialog, since
  it's irreversible).
- Once `signed`: fields render read-only, addendums list chronologically below the original body, each
  addendum visually distinct (timestamp + author), with an **Add Addendum** action always available.
- Full en/ar/tr i18n (`clinicalNotes.*` namespace), RTL/dark-mode parity — same non-negotiable bar as every
  prior module.

## 12. Security Considerations

- Same Sanctum session-cookie auth + Policy-gated authorization as every module — no new auth mechanism.
- Receptionist exclusion (§10) needs the same import-path check other modules already handle correctly (no
  frontend route/component reachable without the backend policy also enforcing it — defense in depth, not
  a UI-only restriction).
- No sign-time password re-entry (§15 Decision B) — relies on the existing authenticated session being
  trusted, same trust boundary as every other "signing"/status-transition action in the system already
  (`InvoiceController::issue`, `TreatmentPlanController::accept`, etc.). Kept extensible: `signed_by_id`/
  `signed_at` are recorded independently of *how* signing was authenticated, so a future re-authentication or
  e-signature step could be inserted in front of the existing `sign()` action without a schema change.
- Sign is wrapped in `DB::transaction()` (§8 rule 3) so a failure partway through never leaves a note with
  `signed_at` set but un-frozen fields, or vice versa.

## 13. Performance & Scalability Considerations

Trivial volume relative to Appointments (one note per visit at most, vs. potentially several
appointment/chart-entry rows). `(patient_id, created_at)` index covers the tab's list query; no special
pagination concerns beyond the standard cursor/page pattern already used everywhere else.

## 14. SaaS Readiness

Same V1 posture as every other module: no `clinic_id`, single-organization by design. Clinical Notes carries
the most sensitive narrative PHI of any module shipped so far (informed consent language, clinical reasoning,
potentially sensitive patient-reported information) — worth flagging for extra scrutiny in a future
multi-tenant checkpoint, the same way Treatment Plans flagged its pricing data as commercially sensitive
beyond the standard patient-data isolation bar. Migration path (add `clinic_id`, extend indexes,
tenant-scoping trait, policy clinic-membership check) is identical to the already-documented pattern in
`docs/modules/dental-chart.md`'s SaaS Readiness section.

## 15. Decisions Confirmed at Approval (2026-07-25)

**A. Addendum mechanism — APPROVED for V1, built as fully immutable.** Signed notes are immutable, so an
official correction path is required from day one. Addendums themselves are append-only: no update or
delete endpoint exists for them at any permission level (§6, §8 rule 4) — a correction to an addendum is a
new addendum, never an edit.

**B. Sign-time re-authentication — NO password re-authentication for V1.** Uses the authenticated session;
records `signed_by_id`/`signed_at`. Deliberately kept extensible (§12) so a future e-signature or
re-authentication step can be inserted without a schema change.

**C. Deletion of signed notes — Soft delete ALLOWED for admin, on any status, no hard delete ever.** All
delete actions remain fully `Auditable`-logged and recoverable via `restore()`. Addendums have no delete
path at all, for any role, regardless of the parent note's status.

**D. Receptionist access — NO access at all.** Access limited to admin + dentist (clinical staff), per
policy (§10) — enforced backend-side, not just hidden in the frontend.

**Additional requirements set at approval, folded into the sections above:** the sign operation must be
transactional (§8 rule 3, §12); a complete backend Feature-test suite and a permanent Playwright E2E suite
are built during this module's own Phase 2 (§18), not deferred to `TECH_DEBT.md`.

## 16. Potential Risks / Deferred Features / Future Improvements

- **Templates/Auto Notes** (Open Dental/CareStack pattern) — deferred; revisit once real usage shows which
  note types get repeated often enough to justify a template CRUD surface.
- **Unified cross-module "Patient Timeline"** (Dentrix's Progress Notes panel, aggregating appointments +
  chart entries + plan events + notes chronologically) — genuinely valuable, explicitly deferred as its own
  future initiative since it touches three already-shipped modules' read paths, not a Clinical-Notes-only
  change.
- **Voice-to-text / AI-assisted drafting** — see §17.
- **Mandatory-completion enforcement** (CareStack: can't leave a procedure without its note) — deferred,
  no current workflow requirement for it.
- Same **Multi-Branch** non-scope as every other module — no table is branch-scoped; revisit only when a
  real second-location requirement appears.

## 17. Future AI Integration Points (vision only — not built now)

`PROJECT_CONTEXT.md` names Clinical Notes explicitly as an area AI may assist (never decide). Concrete,
future-only possibilities once this module exists and has real data: AI-assisted note drafting/summarization
from a dentist's shorthand, structured-data extraction suggestions back toward Dental Chart/Treatment Plans
(human-confirmed, never auto-written), and note-quality/completeness nudges before signing. None of this is
built in V1 — noted here only so the schema (plain text SOAP fields, no premature structure) doesn't
foreclose it later.

## 18. Testing Strategy

Backend: Unit tests for `ClinicalNoteStatus` enum, `ClinicalNoteService` (sign/addendum/lock enforcement),
`ClinicalNotePolicy`. **Feature tests (HTTP-level) included in this module's own Phase 2, not deferred** —
closing the gap `TECH_DEBT.md` already logged against Billing. Frontend: Vitest for the store/service layer,
component tests for the detail view's draft/signed/addendum states.

**Permanent Playwright E2E spec built within Phase 2 this time, not deferred to `TECH_DEBT.md` again** — three
consecutive modules (Treatment Plans, Billing, Payments) now carry this same open item; recommend Clinical
Notes closes it during implementation instead of extending the backlog further. Scenarios: create draft →
edit → sign → verify lock → add addendum → verify addendum appended, not edited → receptionist
no-access verification → RTL/dark-mode smoke check.

## 19. Proposed Implementation Sequence (mirrors prior modules' step-by-step checkpoints)

1. Migrations + Models (`ClinicalNote`, `ClinicalNoteAddendum`) + `ClinicalNoteStatus` enum.
2. `ClinicalNoteService` (create/update/sign/addendum/delete) + `ClinicalNoteLockedException`.
3. `ClinicalNotePolicy` + Form Requests + `ClinicalNoteController` + routes. Backend Unit + Feature tests.
4. Frontend: types, API service, Pinia store, `PatientClinicalNotesPanel.vue` tab, `ClinicalNoteDetailView.vue`
   (draft edit + sign + addendum UI), full en/ar/tr i18n.
5. Playwright E2E spec (`frontend/e2e/clinical-notes.spec.ts`).
6. Final Review: full test suite, PHPStan, Pint, `vue-tsc`, real-browser verification, Final Review Report
   with an explicit Production Ready verdict.

Each step follows the established loop: implement → real-browser verify (mandatory for UI steps) → report →
wait for explicit approval before the next step.
