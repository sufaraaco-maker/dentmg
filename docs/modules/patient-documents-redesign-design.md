# Patient Profile — Phase 2.5: Documents Integration — Design Document

| | |
|---|---|
| **Status** | **Approved, implemented, and merged 2026-08-08 (PR #27, `98ae299`).** All 5 decisions in §16 were approved by the user as recommended (drop `lab_report` for `clinical_summary`, clone `PatientImagePolicy` exactly, tab last after Billing, patient-scoped-only route, clone `PatientImageService`'s storage-disk convention). `main`'s post-merge CI confirmed fully green (Backend, Frontend, E2E all `success`). |
| **Roadmap position** | Phase 2 (Patient Profile Redesign), sub-phase 2.5 of 7 — see `docs/PROJECT_STATUS.md` §12 and `docs/modules/patient-profile-redesign-design.md` §17. Follows 2.1 (Foundation, PR #18), 2.2 (Billing, PR #20), 2.3 (Medical History, PR #22), 2.4 (Laboratory, PR #24) — all merged to `main`. |
| **Governing decisions inherited** | §0 item 3 and §9A of `docs/modules/patient-profile-redesign-design.md` are binding here unchanged — nothing in this doc revisits them. This doc is a **drill-down**, not a replacement: the umbrella doc's §5.2/§6/§7/§8/§10/§12/§17 already sketch this sub-phase in outline; this doc verifies that outline against the actual code on `main` today and turns it into an implementable spec. |
| **Analysis basis** | Direct inspection of every Documents-adjacent backend/frontend file on `main` (post-PR #25, commit `21f594a`) — confirmed nothing Documents-related exists yet — plus the one real precedent for a patient-owned file entity, the Imaging module (`PatientImage`), read end-to-end: model, migration, service, controller, policy, routes, and frontend store. Also read the umbrella `patient-profile-redesign-design.md`'s Documents sections verbatim and `patient-laboratory-redesign-design.md` as this doc's structural template. **Not** an assumption that the umbrella doc's 2.5 outline still matches current code — see §1 for what that audit confirmed. |
| **Author** | Claude Code, for user review. Every recommendation below is a proposal, not a decision — see §16 for the explicit approval list. |

---

## Implementation Summary (added post-implementation, 2026-08-08)

All 5 §16 decisions approved as recommended — no deviations. Implemented essentially as specced in
§3-§11, with two small implementation-time judgment calls this doc's §4/§5 left open:

- **§16 decisions 1-5**: all implemented exactly as recommended — `clinical_summary` category (not
  `lab_report`), `PatientDocumentPolicy` auto-discovered with the exact `PatientImagePolicy` role
  split, Documents tab appended last (after `billing`, matching `PatientDetailView.vue`'s existing
  tab order), `GET/POST patients/{patient}/documents` + `GET documents/{id}/file` +
  `PUT/DELETE documents/{id}` with no flat counterpart, and `PatientDocumentService::upload()`
  resolves `config('filesystems.default')` once per call and stores it on the row exactly like
  `PatientImageService`.
- **§4.1/§6 relation/scope**: `Patient::documents()` and `PatientDocument::scopeForPatient()` added
  exactly as specced (§1.4 finding 1's flagged gap).
- **§9 policy registration**: confirmed auto-discovered, no `Gate::policy()` call added to
  `AppServiceProvider.php` (§1.4 finding 2's flagged gap resolved as predicted).
- **Judgment call not pinned by this doc: one file per upload, not a batch.** §4.3/§4.5 described
  cloning `PatientImageService`'s upload pattern without settling batch-vs-single explicitly. Imaging
  batches because a set of exposures from one visit genuinely shares one `image_type`/`taken_at`; a
  document's title is naturally per-file, so a batch would force an awkward shared title across
  unrelated files. `StorePatientDocumentRequest` takes a single `file`, not `files[]` — a small,
  deliberate deviation from `StorePatientImageRequest`'s convention, documented inline in both the
  Form Request and `PatientDocumentService`.
- **Judgment call not pinned by this doc: `AttachmentList.vue` is a row list, not a photo grid.**
  §4.5 described it as "generalized from `ImageThumbnail.vue`'s grid-cell pattern," which this
  implementation follows for the *single-item component* shape, but renders as a row (mirroring
  `PatientLabCasesPanel.vue`'s card-row list) rather than `PatientImagingPanel.vue`'s photo grid —
  a document's title/category/filename metadata is the primary identifying information, unlike
  Imaging's uniformly-visual photos, so a row reads better. Documented inline in the component.
- **§9 permission matrix implemented in `PatientDocumentController`/`PatientDocumentPolicy`**: no new
  policy methods beyond the 5 cloned from `PatientImagePolicy`.
- **§10 pagination**: 15/page on the new endpoint, matching every other patient-scoped list.
- **§11 i18n**: `patients.tabs.documents`, `patients.documentsPanel.*` (panel-local: title/upload/
  empty), and a top-level `documents.*` namespace (shared field labels + `documents.categories.*`) —
  31/31 keys verified programmatically across `ar`/`en`/`tr`.
- **§13 Testing**: backend +13 tests (1020/1020 total, Pint clean; PHPStan's pre-existing local-only
  `casts()`-model false positives confirmed unrelated — no `PatientDocument`/`DocumentCategory`
  findings at all). Frontend +21 tests (10 `patientDocuments.ts` store tests, 10
  `PatientDocumentsPanel.vue` tests, +1 `PatientDetailView.test.ts` tab-rendering assertion) — full
  suite 915/915 green, type-check/ESLint/Prettier clean (re-verified against a stashed clean
  baseline: 249 pre-existing flagged files codebase-wide, unrelated to this phase; this branch nets
  to 247 after also fixing 2 pre-existing drift files it happened to touch).
- **§14**: landed as one PR, as proposed (not split).
- **§15**: no new deferrals — Timeline remains the only tab not yet started after this phase.

## 0. Why this doc exists separately from the umbrella design doc

`docs/modules/patient-profile-redesign-design.md` already sketches Phase 2.5 in several places (§0 item 3 governing decision, §5.2 new components, §6 model/relations, §7 schema draft, §8 API changes, §10 permission model, §12 mobile, §17 phase table) and rates it **Medium risk — new schema, new generalized components**. Per this project's established practice (2.1–2.4 each re-derived their spec from actual code rather than trusting the umbrella outline), this doc does the same: it confirms the outline's proposed shape against the real state of `main` today. Unlike Phase 2.4's audit — which found the Laboratory outline *directionally correct but incomplete* (missing a live SQL bug, an undecided product question) — this audit finds the Documents outline **accurate about current state** (it correctly says nothing exists yet) but **under-specified on four judgment calls** a drill-down doc needs to resolve before implementation: the category taxonomy's overlap with the now-shipped Laboratory module (§7), the policy-registration mechanism (§9), the storage-disk convention (§6), and the fact that — unlike Billing/Treatment Plans, which had working routes before their nav entries were "flipped" — Documents starts from **zero** frontend scaffolding, not even a `comingSoon` stub (§8).

## 1. Current State Audit (read directly from `main` @ `21f594a`, not assumed)

### 1.1 Backend — confirmed absent

| Piece | Search performed | Result |
|---|---|---|
| `PatientDocument` model | `backend/app/Models/` | **Does not exist.** |
| Migration / table | `backend/database/migrations/` | **No `patient_documents` migration.** |
| `PatientDocumentService` | `backend/app/Services/` | **Does not exist.** |
| `DocumentController` / route | `backend/routes/api.php` | **No Documents route of any kind.** |
| `PatientDocumentPolicy` | `backend/app/Policies/` | **Does not exist.** |
| `Patient::documents()` relation | `backend/app/Models/Patient.php` | **Does not exist** — confirmed by direct read (lines 76–114): `invoices`, `payments`, `clinicalNotes`, `images` (line 91), `allergies`, `labCases` (line 101), `medicalConditions`, `medications` are all present; there is no `documents` entry. |

### 1.2 Frontend — confirmed absent

| Piece | Search performed | Result |
|---|---|---|
| `PatientDocumentsPanel.vue` / `AttachmentUpload.vue` / `AttachmentList.vue` | `frontend/src/components/` | **None exist.** |
| `patientDocuments.ts` store | `frontend/src/stores/` | **Does not exist.** |
| Documents API service | `frontend/src/services/` | **Does not exist.** |
| Tab placeholder | `frontend/src/views/PatientDetailView.vue` `tabDefinitions` (lines 69–79) | **No entry at all** — 9 tabs currently defined (`overview, medicalHistory, appointments, dentalChart, imaging, laboratory, treatmentPlans, clinicalNotes, billing`). Documents is mentioned only in a code comment (line 49) listing future tabs the config array will extend for. |
| Sidebar `comingSoon` stub | `frontend/src/config/navigation.ts` | **No entry, real or `comingSoon: true`.** The `comingSoon` flag exists as a general mechanism but nothing currently uses it — both Treatment Plans and Billing had it and were already flipped to real routes (comments at lines 101–104, 159–162). Documents was never even added as a stub. |

**This means Phase 2.5 is a full build on the frontend, not a "flip a flag" job** — worth stating explicitly since it differs from how some earlier sub-phases started.

### 1.3 Existing precedent: the Imaging module's `PatientImage` pattern

This is the one existing patient-owned-file entity in the codebase, and it is a complete, reusable pattern:

- **Model** (`backend/app/Models/PatientImage.php`): UUID PK, `HasUuids`, `SoftDeletes`, `Auditable`, `belongsTo(Patient)`, `belongsTo(User, 'uploaded_by')`.
- **Migration** (`backend/database/migrations/2026_07_27_000003_create_patient_images_table.php`): `patient_id` (FK, cascade-delete), `uploaded_by` (FK, nullable, null-on-delete), domain fields, **`disk` (string, stored explicitly per row — "so a future disk migration (local → s3) never silently breaks already-uploaded rows")**, `path`, `thumbnail_path` (nullable), `mime_type`, `file_size`, `notes`, timestamps, `softDeletes()`.
- **Service** (`backend/app/Services/PatientImageService.php`): `upload()` resolves `$disk = config('filesystems.default')` once per batch (line 34) and writes via `Storage::disk($disk)->put($path, ...)` (line 69) with path convention `patient-images/{patient_id}/{uuid}.{ext}` (line 67); `delete()` is soft-delete-only and **deliberately does not remove the file from storage** ("a restored record must still have its file"); no hard-delete/purge path exists.
- **Authenticated streaming** (`backend/app/Http/Controllers/Api/PatientImageController.php::file()`, lines 65–74):
  ```php
  public function file(PatientImage $patient_image)
  {
      $this->authorize('view', $patient_image);
      return Storage::disk($patient_image->disk)->response($patient_image->path);
  }
  ```
  Never a public/static URL — always an authenticated, policy-checked route.
- **Routes** (`backend/routes/api.php` lines 182–187): `GET/POST patients/{patient}/images`, `GET images/{patient_image}/file` (named `patient-images.file`), `GET images/{patient_image}/thumbnail`, `PUT/DELETE images/{patient_image}`.
- **Policy** (`backend/app/Policies/PatientImagePolicy.php`): `viewAny`/`view` → all staff; `create`/`update` → Admin + Dentist + Receptionist; `delete` → Admin only ("matching every other policy in the codebase without exception"). **Not explicitly registered anywhere** — `backend/app/Providers/AppServiceProvider.php`'s only `Gate::policy()` calls (lines 53–55) are for `MedicalHistoryPolicy` covering 3 models; `PatientImagePolicy` relies on Laravel's default `Model` → `{Model}Policy` naming-convention auto-discovery.
- **Storage config** (`backend/config/filesystems.php`): `local` (default, `storage/app/private`, not web-served), `public` (`storage/app/public`, symlinked), `s3` (env-driven, currently unconfigured). `FILESYSTEM_DISK` env var picks the default.
- **Frontend precedent**: `frontend/src/components/imaging/UploadImagesDialog.vue` — drag-drop + native file input + progress, and its native input already sets `capture="environment"` (line 183) for mobile camera capture — the umbrella doc names this exact file as the generalization source for `AttachmentUpload.vue`.

Documents can clone this pattern almost entirely: same disk-per-row convention, same authenticated-stream controller method, same Storage-facade discipline, same soft-delete-retains-file semantics, same auto-discovered single-model policy — **minus thumbnail generation** (PatientImage's GD-based thumbnailing, lines 99–143 of `PatientImageService.php`, is imaging-specific; generic documents like PDFs/DOCX don't need it, per the umbrella doc's own scope).

### 1.4 What this confirms vs. the umbrella doc's 2.5 outline

The umbrella doc's Documents outline (§5.2, §6, §7, §8, §10) is **accurate about current state** — it never claims something exists that doesn't. Four things are under-specified relative to what a drill-down needs to pin down before implementation, addressed in §6, §7, §8, and §9 below:

1. It doesn't flag that `Patient::documents()` needs to be *added*, the way §6.2 of the Laboratory doc explicitly called out `Patient::labCases()` as "the missing inverse relationship." The umbrella doc's relations table (line 111) just lists `documents() -> PatientDocument` as if describing existing shape.
2. It doesn't say whether `PatientDocumentPolicy` auto-discovers (like `PatientImagePolicy`) or needs `Gate::policy()` registration (like `MedicalHistoryPolicy`). Since Documents is one model/one policy, it should auto-discover — but this should be stated, not left implicit, since 2.3's implementation needed registration code the umbrella doc's §6.4 for Medical History didn't call out either.
3. Its proposed `category` enum (`consent_form|insurance|referral|lab_report|correspondence|other`) includes `lab_report`, which was drafted before Phase 2.4 (Laboratory) shipped. Laboratory's own `LabCase` model has no file/attachment field today (confirmed: no hits for `file`/`attachment`/`document` in `backend/app/Models/LabCase.php` or its migrations) — so there's no **technical** collision, but there is a **naming** one: a "lab report" uploaded via Documents and a `LabCase` are conceptually adjacent, and a receptionist filing paperwork could reasonably expect either to be "where lab results go." Worth resolving explicitly (§7) rather than shipping an ambiguous category silently.
4. It doesn't state whether the Storage disk convention should be single-default-disk (`config('filesystems.default')`, `PatientImageService`'s actual behavior) or something else — the umbrella doc just says "mirrors `PatientImageService`'s Storage-disk pattern" without confirming *which* part of that pattern is load-bearing (§6).

## 2. Relationship to Imaging and Laboratory

Documents is explicitly a **generic** file-attachment foundation, distinct from both:
- **Imaging** stays the home for clinical diagnostic images (radiographs, intraoral photos) with tooth/surface tagging and thumbnail generation — unchanged, untouched by this phase.
- **Laboratory** stays the home for lab case lifecycle tracking (vendor, status, dates) — unchanged, untouched by this phase. It has no file-attachment capability today and this phase does not add one to it.
- **Documents** is for everything else administrative/clinical paperwork that isn't a diagnostic image or a lab case: consent forms, insurance cards, referral letters, correspondence, and (per §7's resolution) possibly scanned lab reports that arrive as paperwork rather than through the Laboratory workflow.

## 3. Scope for Phase 2.5

**In scope** (per umbrella doc §0 item 3, confirmed unchanged): a generic patient-documents foundation — model, upload/download, metadata, categories, one new Patient Profile tab.

**Explicitly out of scope** (per umbrella doc §0 item 3 and §19, confirmed unchanged): versioning, sharing/external access, advanced per-document permissions beyond the flat policy in §9, and OCR/text extraction.

## 4. Architecture: Route → Scope → Store → Panel

Following the same pattern as Laboratory (2.4) and Imaging's own patient-scoped retrofit (2.1) — this phase does **not** need a `scopeForPatient()` in the Laboratory/Treatment-Plans sense, because (like Imaging) the patient-scoped route is the *only* route from day one; there is no pre-existing flat `GET /documents` endpoint to add a nested alternative to.

### 4.1 Route
New, patient-scoped only (mirrors Imaging's `patients/{patient}/images`, not Laboratory's flat-plus-nested pair, since there is no pre-existing flat Documents endpoint to reconcile with):
```
GET/POST   patients/{patient}/documents
GET        documents/{document}/file      # authenticated stream, named `patient-documents.file`
PUT/DELETE documents/{document}
```

### 4.2 Model relation
```php
// Patient.php
public function documents(): HasMany
{
    return $this->hasMany(PatientDocument::class);
}
```

### 4.3 Controller / Service
`PatientDocumentController` (`index`, `store`, `file`, `update`, `destroy`) — same shape as `PatientImageController`. `PatientDocumentService`:
```php
class PatientDocumentService
{
    public function upload(Patient $patient, array $files, array $metadata, User $uploader): array { /* clone PatientImageService::upload(), minus thumbnail generation */ }
    public function update(PatientDocument $document, array $data): PatientDocument { /* clone */ }
    public function delete(PatientDocument $document): void { /* soft-delete only, file retained — same as PatientImageService */ }
}
```

### 4.4 Store
`patientDocuments.ts` — same id-keyed cache + per-patient pagination shape as `patientLabCases.ts`/`treatmentPlans.ts` (standard `fetchForPatient(id, force)` convention; Documents has no filter/pagination complexity that would push it toward Imaging's non-standard store shape).

### 4.5 Panel / components
- `PatientDocumentsPanel.vue` — new Documents tab: grid/list of documents + upload trigger.
- `AttachmentUpload.vue` — generalized from `UploadImagesDialog.vue`'s dropzone (drag-drop + native input + progress + `capture="environment"`), with domain-agnostic fields (`category` select, `title`, `notes`) instead of imaging's `image_type`/tooth fields.
- `AttachmentList.vue` — generalized from `ImageThumbnail.vue`'s grid-cell pattern: real thumbnail for images (if any are uploaded as documents), a mime-type icon for everything else (PDF/DOCX/etc.), shared edit/delete overlay.

## 5. Database Changes

```
patient_documents
  id (uuid, pk)
  patient_id       (fk -> patients, cascade-delete — mirrors patient_images)
  uploaded_by      (fk -> users, nullable, null-on-delete — mirrors patient_images)
  category         (string, cast to a PHP backed enum — plain column, not a DB enum type,
                     matching patient_images.image_type's explicit "one-line PHP enum change,
                     never an ALTER TYPE migration" convention)
  title            (string)
  original_filename (string)
  disk             (string — explicit per-row, same rationale as patient_images.disk)
  path             (string)
  mime_type        (string)
  file_size        (unsigned integer)
  notes            (text, nullable)
  timestamps
  deleted_at       (soft delete — admin-only per §9, documents are part of the clinical/
                     administrative record, same rationale as patient_images)

  index: patient_id, category
```

No `thumbnail_path`, `width`/`height`, `tooth_number`, or `surfaces` columns — those are Imaging-specific and out of scope here (§3).

## 6. Storage & authenticated streaming

**Recommend: clone `PatientImageService`'s convention exactly** — resolve `$disk = config('filesystems.default')` once per upload batch, store it explicitly on each row, and always read/write/stream through `Storage::disk($document->disk)`, never a hardcoded disk. This is what "mirrors `PatientImageService`'s Storage-disk pattern" (umbrella doc) concretely means, stated explicitly per §1.4 finding 4. The `documents/{document}/file` route uses the identical `authorize('view', ...)` + `Storage::disk($document->disk)->response($document->path)` pattern as Imaging's `file()` method — never a public/static URL.

## 7. Category taxonomy — open product question

Per §1.4 finding 3, the umbrella doc's proposed enum (`consent_form|insurance|referral|lab_report|correspondence|other`) predates Laboratory shipping. **Recommend dropping `lab_report` from the Documents category list** and replacing it with `clinical_summary` (or similar) — Documents should not offer a category that reads as "where lab results go" when the Laboratory module (with its own status-tracked `LabCase` entity) already owns that workflow. Anyone with an actual physical/scanned lab report to file can use `correspondence` or `other`. Proposed final list: `consent_form | insurance | referral | clinical_summary | correspondence | other`.

## 8. Patient Profile Tab Integration

### 8.1 Tab position
**Recommend: last tab, immediately after `billing`.** This matches the umbrella doc's IA rationale (§3: "Billing/Documents (administrative)") and requires no reordering of any existing tab — `billing` is already the last entry in `tabDefinitions` (`PatientDetailView.vue` line 78) today.

### 8.2 Config change
```js
{ key: 'documents', labelKey: 'patients.tabs.documents', visible: true }
```
appended to `tabDefinitions` (`PatientDetailView.vue`, after line 78). All staff can read (matches the proposed `viewAny: true` in §9) — same visibility shape as Imaging/Laboratory, not the narrower Clinical Notes gating.

## 9. Permission Matrix

**Recommend: clone `PatientImagePolicy` exactly**, since PatientImage is both the closest precedent and the umbrella doc's own §10 proposal already matches it field-for-field:

| Action | Roles | Rationale |
|---|---|---|
| `viewAny` / `view` | All staff | Matches `PatientImagePolicy` — filing/finding paperwork is a front-desk task, not clinical-only |
| `create` / `update` | Admin, Dentist, Receptionist | Matches `PatientImagePolicy` exactly |
| `delete` | Admin only | Matches `PatientImagePolicy` and every other policy in the codebase without exception |

**Registration**: auto-discovered via Laravel's `PatientDocument` → `PatientDocumentPolicy` naming convention — **no** `Gate::policy()` call needed in `AppServiceProvider.php` (unlike `MedicalHistoryPolicy`, which needed explicit registration because it covers 3 models). This resolves §1.4 finding 2.

## 10. Pagination Strategy

15/page on `GET /patients/{patient}/documents`, matching every other patient-scoped list endpoint (Treatment Plans, Clinical Notes, Medical History, Laboratory).

## 11. i18n Requirements

New keys: `patients.tabs.documents`, `patients.documentsPanel.*` (panel-local strings: empty state, upload dialog labels, category names). Added in `ar`/`en`/`tr` with parity verified programmatically (same convention as Phase 2.3/2.4). `documents.*` reused where field labels are domain-generic (title, notes, category) rather than duplicated per-panel.

## 12. Mobile UX

`AttachmentUpload.vue`'s native file input keeps `capture="environment"`, cloned from `UploadImagesDialog.vue:183`, so uploading a photographed paper document from a phone camera works identically to Imaging's existing mobile upload flow. `PatientDetailView.vue`'s existing `<768px` dropdown tab switcher (added in Phase 2.2) picks up the new `documents` tab automatically — no separate mobile-specific work needed.

## 13. Testing Strategy

- **Backend**: Feature tests for `PatientDocumentController` CRUD + authenticated `file()` streaming (mirrors `PatientImageController`'s existing test suite), Policy tests for the 3-tier permission matrix (§9), one regression test confirming a soft-deleted document's file remains readable via `Storage::disk()` (matches the "restore must still have its file" invariant).
- **Frontend**: Vitest store tests for `patientDocuments.ts` (same shape as `patientLabCases.test.ts`), component tests for `AttachmentUpload.vue` (drag-drop, native input, category/title/notes fields) and `PatientDocumentsPanel.vue`, +1 `PatientDetailView.test.ts` tab-rendering assertion.

## 14. Implementation Sub-phases (proposed)

Single PR, matching every prior sub-phase (2.1–2.4 each landed as one PR) — the diff is additive (new table/model/service/policy/controller/store/components/tab), with no existing code modified except `Patient.php` (+1 relation) and `PatientDetailView.vue` (+1 tab entry). No natural split point that would reduce review risk.

## 15. Deferred Items / Tech Debt Carried Forward

- Versioning, sharing/external access, per-document granular permissions, OCR — all explicitly out of scope (§3), matching the umbrella doc's original governing decision, not a new deferral.
- Thumbnail generation for image-type documents — deliberately dropped from the cloned `PatientImageService` pattern (§1.3); `AttachmentList.vue` falls back to a mime-type icon for every file type, including images, in V1. Revisit only if user feedback specifically asks for image previews inside Documents.
- Timeline (Phase 2.6) remains the only tab not yet started after this phase.

## 16. Decisions Requiring Approval Before Implementation

1. **§7 — Drop `lab_report` from the category enum, replace with `clinical_summary`.** Recommend **yes**, to avoid conceptual overlap with the now-shipped Laboratory module. Alternative: keep `lab_report` as originally drafted in the umbrella doc — there's no technical collision (Laboratory has no file field), only a naming ambiguity, so this is a low-stakes call either way.
2. **§9 — Permission matrix: clone `PatientImagePolicy` exactly** (viewAny/view all staff; create/update Admin+Dentist+Receptionist; delete Admin-only), auto-discovered, no `Gate::policy()` registration. Recommend **yes** — this is also exactly what the umbrella doc's own §10 already proposed. Alternative: any different role split the user prefers.
3. **§8.1 — Tab insertion point: last, after `billing`.** Recommend as stated — matches the umbrella doc's own IA rationale and requires no reordering. Alternative: any other position; low-stakes.
4. **§4.1 — Patient-scoped route only, no flat `GET /documents`.** Recommend **yes** — there's no existing flat endpoint to reconcile with (unlike Laboratory, which had one and had to decide whether to keep a redundant filter). Alternative: add a flat admin-facing `GET /documents` endpoint too, if a future cross-patient documents view is anticipated — not currently requested by any doc.
5. **§6 — Storage disk convention: single resolved-per-batch disk (`config('filesystems.default')`), stored per row.** Recommend **yes**, exact clone of `PatientImageService`. Alternative: none identified — this is the established codebase-wide convention, not really a live choice.

No implementation begins until these are resolved.
