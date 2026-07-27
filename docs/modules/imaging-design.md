# Imaging — Module Design (Draft, awaiting approval)

**Status: Design Phase — not yet approved, no code written.** Per [[workflow_two_phase_process]], this
document is presented in full for review; implementation does not start until explicitly approved.

## 0. Competitive Research (required before any design, per standing product philosophy)

| Source | Finding | Taken / Rejected for this design |
|---|---|---|
| **Open Dental** (Imaging module manual, Bridge/TWAIN device-capture docs) | Imaging is built around a **mount** concept: a template arranging multiple exposures (e.g. a 4-image or 18-image Full Mouth Series) into one on-screen layout, with device capture wired directly to sensor/scanner hardware via TWAIN/Vixwin/Romexis bridges. Supports basic enhancement (brightness/contrast/sharpen/invert/colorize) and freehand/measurement annotation tools, calibrated per image. | **Taken**: brightness/contrast/invert as a non-destructive, frontend-only viewer feature (cheap, high clinical value for reading X-rays). **Rejected for V1**: mount templates and direct sensor/TWAIN hardware integration — a browser-based SPA cannot talk to local TWAIN drivers at all (that requires a native bridge app, out of scope for this stack); V1 instead accepts standard image files however they reach the clinic's computer (sensor software's own export, a scanned file, or a phone/tablet camera). **Deferred to V2**: persistent drawing/measurement annotation tools — real value, but non-trivial scope (canvas layer, calibration, storage of annotation data separate from the source image). |
| **Dentrix / Dentrix Ascend** (Imaging Center docs) | Groups images per patient into date-stamped "visits," supports the same TWAIN-based direct capture, and a side-by-side **before/after** comparison view for treatment-planning conversations with patients. | **Taken**: filtering the patient gallery by date implicitly gives the same "grouped by visit" effect without needing a dedicated grouping table (§2, deferred formal FMX/series grouping to V2 exactly as Laboratory deferred `case_type`'s own catalog table until real need is shown). **Taken**: a simple two-image side-by-side compare view in the lightbox — low effort, direct patient-communication value. |
| **CareStack** (cloud-native imaging docs) | Being cloud-native (not a local Windows install like Open Dental/Dentrix), CareStack's imaging explicitly supports **browser/mobile upload** of photos alongside traditional sensor X-rays, and stores images in cloud object storage per clinic — the closest architectural analog to this project's own web-based, S3-ready stack. | **Taken directly**: this design's whole storage/access model (object storage, browser/mobile file upload including camera capture, no local hardware dependency) mirrors CareStack's approach rather than Open Dental/Dentrix's desktop-hardware-first model, since DentalSuite is a web SaaS product, not a local Windows install. |
| **Curve Dental** (also cloud-native) | Similarly cloud/browser-first; markets **DICOM support** for panoramic/CBCT machines that export in that format, plus standard JPEG intraoral photos. | **Explicitly named as a V1 scope decision (§7 item 1)**: full DICOM (study/series/instance hierarchy, a DICOM-aware viewer like cornerstone.js, WADO retrieval) is a materially larger undertaking than a standard-image gallery and is not needed for a general dentistry practice's day-to-day workflow (CBCT/3D is typically referred out to a radiology center, which sends back a JPEG/PDF report, not raw DICOM). Recommended: **out of scope for V1**, revisit if a real need for in-house CBCT/panoramic DICOM machines appears. |
| **Eaglesoft** (via practitioner community feedback) | Recurring practitioner complaint: images get uploaded but never correctly tagged to the right tooth/procedure, making them hard to find later during a treatment-planning conversation. | **Taken as a design constraint**: make tooth/type tagging fast (reuse Dental Chart's own tooth-picker UI) but never *required* to block upload — an untagged image must still be easy to find later via patient-level date/type filters, so a rushed front-desk upload is never lost, only less well-organized. |

**Net effect**: every competitor converges on the same core shape for a web-based product — a per-patient
image gallery, categorized by type, optionally tagged to a tooth, with basic non-destructive viewing
enhancements. This design takes that shape and deliberately excludes the two features that only make sense
for a desktop/local-hardware product (TWAIN device capture, mount templates) or that represent a large,
separate scope increase (DICOM), naming both explicitly rather than silently building or silently skipping
them (§7).

## 1. Module Goal / Purpose

Give the clinic a single, organized place to store and review every patient's diagnostic images —
intraoral/extraoral photos and X-rays — replacing loose files on a front-desk PC or a separate imaging
system with no link back to the patient chart. Like Laboratory, this is a clinical-support module: V1 does
not modify Dental Chart/Clinical Notes/Treatment Plans/Appointments themselves, it only *references* them
for traceability (§3), following the exact precedent Laboratory set for `treatment_plan_item_id`/
`appointment_id`.

## 2. Scope (V1)

**In scope:**
- Upload one or more images per patient (file picker, or a mobile browser's camera capture) — JPEG/PNG/WebP.
- Categorize each image by **type**: `intraoral_photo`, `extraoral_photo`, `xray_periapical`,
  `xray_bitewing`, `xray_panoramic`, `xray_cephalometric`, `other`.
- Optionally tag an image to a **tooth** (single FDI `tooth_number`, reusing `ToothChart`/Dental Chart's
  exact validation) and/or **surfaces** (reusing the same `M/D/F/L/O/I` json convention as
  `DentalChartEntry`/`TreatmentPlanItem`) — optional, not required (§0's Eaglesoft-feedback constraint).
- Record a **taken-at** date, distinct from upload time (a scanned older X-ray or a batch-uploaded external
  referral image was not necessarily *taken* today).
- Optional traceability links: `treatment_plan_item_id`, `appointment_id` (nullable, one-way, `nullOnDelete`
  — exact convention as Laboratory's `LabCase`).
- Per-patient gallery: grid view, filterable by type/tooth/date range, lightbox viewer with zoom/pan,
  brightness/contrast/invert (frontend-only, non-destructive), and a simple two-image side-by-side compare.
- Soft delete, admin-only (§5) — images are part of the clinical record, not casually removable.

**Explicitly out of scope for V1** (see §7 for the full decision log):
1. DICOM support / CBCT-specific handling.
2. Direct sensor/TWAIN hardware capture integration.
3. Persistent drawing/measurement annotation tools.
4. Formal "mount"/FMX-series grouping as its own data structure.
5. A general-purpose document/attachment system (insurance cards, signed consent forms, etc.) — this module
   is scoped narrowly to clinical images; a broader document system is a plausible *future* module, not
   something to build speculatively now (`PROJECT_CONTEXT.md`'s "build only what is needed").

## 3. Database Design

### `patient_images`
| Column | Type | Notes |
|---|---|---|
| `id` | uuid, PK | matches project-wide UUID PK convention |
| `patient_id` | uuid, FK → patients, `cascadeOnDelete` | required |
| `uploaded_by` | uuid, FK → users, `nullOnDelete` | audit trail beyond the generic `Auditable` trait |
| `image_type` | string, backed-enum-validated | `ImageType` enum, see below |
| `tooth_number` | string(2), nullable | FDI code, validated against `ToothChart` — **not** an FK, exact convention as `DentalChartEntry.tooth_number` |
| `surfaces` | json, nullable, cast `array` | subset of `M/D/F/L/O/I`, exact convention as `DentalChartEntry.surfaces` |
| `taken_at` | date, required | when the image was captured, not when it was uploaded |
| `treatment_plan_item_id` | uuid, FK, nullable, `nullOnDelete` | one-way traceability, exact convention as `LabCase.treatment_plan_item_id` |
| `appointment_id` | uuid, FK, nullable, `nullOnDelete` | one-way traceability, exact convention as `LabCase.appointment_id` |
| `disk` | string | which filesystem disk the file lives on (`local`/`s3`) — stored explicitly so a future disk migration doesn't silently break old rows |
| `path` | string | storage path, see §3.1 for the naming scheme |
| `thumbnail_path` | string, nullable | generated on upload, see §10 |
| `mime_type` | string | from the actual uploaded file, validated server-side |
| `file_size` | unsigned integer | bytes |
| `width` / `height` | unsigned integer, nullable | pixel dimensions, read at upload time for gallery layout |
| `notes` | text, nullable | free-text, e.g. "referred from Dr. X" |
| `created_at` / `updated_at` | timestamps | |
| `deleted_at` | timestamp, nullable | `SoftDeletes` |

Uses `HasUuids`, `SoftDeletes`, `Auditable` (matching `LabCase`/`Patient`/every other clinical-record
model in this codebase).

### 3.1 Storage path scheme (multi-tenant-readiness relevant, see the standing-principle check below)
`patient-images/{patient_id}/{image_id}.{ext}`, with `patient-images/{patient_id}/thumbnails/{image_id}.{ext}`
for thumbnails. No clinic/tenant segment in the path in V1 (there is no `clinic_id` anywhere in the schema
yet), but `patient_id` already gives every file a globally-unique, non-colliding location — if/when a
`clinic_id` column is ever added, prefixing the path becomes an additive rename during that migration, not
a reshape of this table's own columns. Same reasoning already applied to `dental_conditions`/multi-branch in
`TECH_DEBT.md`.

### `ImageType` enum (backed string enum, mirrors `LabCaseStatus`'s shape)
`intraoral_photo`, `extraoral_photo`, `xray_periapical`, `xray_bitewing`, `xray_panoramic`,
`xray_cephalometric`, `other`.

## 4. Workflow

No status lifecycle (unlike Laboratory/Inventory) — an image is either present or soft-deleted. The
"workflow" here is simpler and purely CRUD-shaped:

1. Staff opens a patient's Imaging tab and uploads one or more files (file picker or mobile camera capture).
2. Each file is validated (mime/size), stored, and a thumbnail generated synchronously (§10).
3. Staff optionally tags type/tooth/surfaces/taken-at/notes/links per image (a sensible default `image_type`
   can be pre-selected in the upload dialog to keep this fast, per §0's Eaglesoft constraint).
4. Images appear immediately in the patient's gallery, filterable and viewable in the lightbox.
5. Metadata (type/tooth/surfaces/taken-at/notes/links) can be edited later; the image file itself cannot be
   replaced in-place (delete + re-upload if a wrong file was uploaded) — keeps the model simple and avoids
   silently rewriting what could be a legal clinical record.

## 5. Permissions (`PatientImagePolicy`)

Following the two established patterns from prior modules (research summary: clinical/prescriptive actions
gated `admin+dentist`, administrative/logistics actions gated `admin+receptionist`, `delete` always
tightest):

| Action | Roles | Rationale |
|---|---|---|
| `viewAny` / `view` | admin, dentist, receptionist | Unlike Clinical Notes' narrative-text exclusion of receptionist, images support front-desk/insurance workflows (e.g. printing an X-ray for an insurance claim) — closer to Dental Chart's/Lab's precedent than Clinical Notes' stricter one. Flagged as an open decision (§7 item 4) since it's a judgment call, not a mechanical precedent match. |
| `create` (upload) | admin, dentist, receptionist | Deliberately **wider** than Laboratory/Clinical Notes' `admin+dentist`-only create: in practice, front-desk staff frequently do the actual uploading/scanning even when a dentist ordered the X-ray. Flagged as an open decision (§7 item 4). |
| `update` (metadata) | admin, dentist, receptionist | Same reasoning as `create`. |
| `delete` | admin only | Matches every other policy in the codebase without exception. |

## 6. API Design

- `GET /api/patients/{patient}/images` — paginated, filterable (`image_type`, `tooth_number`, `taken_at`
  range).
- `POST /api/patients/{patient}/images` — multipart upload, accepts multiple files in one request.
- `PATCH /api/images/{image}` — update metadata fields only (§4).
- `DELETE /api/images/{image}` — soft delete, admin-only (policy-enforced).
- `GET /api/images/{image}/file` — streams the original image, **through an authenticated, policy-checked
  controller action** (§9) — never a public/static URL.
- `GET /api/images/{image}/thumbnail` — same, streams the thumbnail.

No `GET /api/images/{image}` (show) — the index response already carries full metadata; a lightbox opens
directly from an index-response row, matching how `DentalChartEntry`'s list-only shape already works.

## 7. Open Decisions (recommendation given for each, awaiting confirmation)

1. **DICOM / CBCT support** — **Recommend: out of scope for V1** (§0). Revisit only if a real in-house
   CBCT/panoramic DICOM machine need appears; would need its own dedicated design pass (viewer library,
   study/series/instance data model) rather than being folded into this one.
2. **Persistent annotation/drawing tools** — **Recommend: deferred to V2.** V1 ships non-destructive
   brightness/contrast/invert + zoom/pan only (frontend-only, no backend storage needed). Real value, but
   a canvas annotation layer with calibrated measurement is its own scoped effort.
3. **Formal FMX/series grouping** — **Recommend: deferred to V2.** The date-range filter on the gallery
   already gives an implicit "images from this visit" view without a dedicated grouping table; add a real
   `image_sessions` concept only if the flat-list-plus-filter approach proves insufficient in practice.
4. **Permission width for `create`/`update`/`view`** (§5) — **Recommend: admin+dentist+receptionist for
   all three**, wider than Laboratory/Clinical Notes' clinical-only gating, because uploading/organizing
   images is largely a front-desk/logistics task in real clinics, not an exclusively clinical judgment call
   like prescribing a lab case or authoring a SOAP note. Confirm or tighten.
5. **Storage disk for V1 deployments** — **Recommend: `local` disk in dev/V1 production (matching every
   other module's current default), explicitly documented that a real multi-server/SaaS production
   deployment should move this specific module's disk to `s3`** (already configured in
   `config/filesystems.php`, currently unused anywhere) before horizontal scaling, since local-disk images
   don't survive/replicate across multiple app servers the way a database row does. This is a deployment
   config decision, not a schema decision — `disk` is already a per-row column (§3) so no migration would
   be needed to switch later, only a config change plus a one-time file copy.

## 8. UI/UX Design

- New **Imaging** tab on `PatientDetailView`, alongside Dental Chart/Clinical Notes/Treatment
  Plans/Payments tabs — not a new top-level sidebar item (unlike Laboratory/Inventory), since images are
  always viewed in the context of one patient, never browsed clinic-wide in V1 (mirrors Dental Chart's own
  patient-scoped-tab placement, not Laboratory's top-level-catalog placement).
- Upload: a dialog with drag-and-drop + a file input using the standard HTML5 `capture` attribute for
  mobile camera access (works in any modern mobile browser, no native app or extra library needed) — see
  the PWA/Mobile-First check below.
- Gallery: responsive CSS grid (not a DataTable — images need visual thumbnails, not rows), touch-friendly
  tap targets, filter bar (type/tooth/date) collapsing to a mobile-friendly sheet below the tablet
  breakpoint, matching the existing responsive pattern other modules already use.
- Lightbox: full-screen viewer with pinch-zoom/pan (touch) and scroll-zoom (desktop), brightness/contrast/
  invert sliders, prev/next navigation, and the two-image compare mode from §0.
- Reuses Dental Chart's existing tooth-picker component for the optional tooth-tagging field, rather than
  building a second one.

## 9. Security Considerations

- **Images are sensitive PHI-equivalent clinical data — never served from the `public` disk with a static,
  guessable URL.** Both `GET /api/images/{image}/file` and `.../thumbnail` are authenticated Laravel
  routes that run `PatientImagePolicy::view()` before streaming the file, exactly like every other
  patient-scoped resource in this codebase. This is a deliberate deviation from Laravel's out-of-the-box
  `public` disk + symlink pattern, which would otherwise be the "default" easy path.
- Server-side MIME validation against the actual file content (Laravel's `file`/`mimes` validation rules),
  not just the client-supplied filename extension.
- File size cap (recommend 15MB/file — a high-resolution intraoral photo or scanned X-ray comfortably fits
  well under this) enforced both client-side (fast feedback) and server-side (authoritative).
- Malware/virus scanning of uploads: **not in V1** (no such infrastructure exists anywhere in this codebase
  today) — named explicitly as a gap rather than silently ignored, worth revisiting before onboarding
  clinics that allow broad staff upload access in a genuinely multi-tenant SaaS deployment.

## 10. Performance Considerations

- Thumbnail generation at upload time using PHP's built-in **GD extension** (already enabled — confirmed
  present in `ci.yml`'s PHP extension list) — no new Composer dependency needed, matching
  `PROJECT_CONTEXT.md`'s "never introduce unnecessary packages" instruction. Generated synchronously during
  the upload request in V1 (simple, and image counts per clinic are modest); revisit moving this to a
  queued job (Redis queue already configured project-wide) only if upload volume in practice makes the
  request noticeably slow.
- Gallery grid loads thumbnails, not full-resolution originals; the lightbox fetches the full image only
  when actually opened.
- Index endpoint paginated, matching every other list endpoint in the codebase.

## 11. Scalability Considerations

- Schema and storage-path scheme (§3.1) are additive-migration-friendly for a future `clinic_id` column,
  matching the standing SaaS multi-tenant-readiness principle (see explicit check below).
- Local-disk storage is a known V1 limitation for horizontal scaling (§7 item 5) — already flagged with a
  concrete, low-cost migration path (change the `disk` config, not the schema) rather than left implicit.

## 12. Trade-offs / Architectural Decisions

- **Own model (`PatientImage`), not a generic `Attachment`/`Document` system**: scoped narrowly to what's
  actually needed now, per this project's consistent "don't build ahead of a demonstrated need" discipline
  (same reasoning Laboratory applied to `case_type` staying free-text instead of its own catalog table). A
  general document-storage module (insurance cards, consent forms, referral letters) is a plausible future
  module, not built speculatively here.
- **Metadata-editable, file-immutable**: once uploaded, an image's binary content cannot be replaced —
  only re-uploaded as a new record and the old one soft-deleted. Keeps the audit trail honest (an
  `Auditable`-logged "updated" event on an image should never mean "the picture itself silently changed").

## 13. Potential Risks

- Storage growth: clinical images accumulate indefinitely (soft-delete only, admin-restore-capable in
  principle even though no restore UI is planned for V1) — worth monitoring disk usage in production,
  same operational concern already logged for backups in `TECH_DEBT.md`.
- Mobile camera capture quality varies a lot by device — no server-side image quality/resolution floor is
  enforced in V1; a blurry chairside phone photo is still accepted. Not treated as a blocker (matches how
  every competitor's own mobile capture path works the same way), but worth knowing.

## 14. Future Improvements (V2+, named not built)

- DICOM/CBCT support (§7 item 1).
- Persistent annotation/measurement tools (§7 item 2).
- Formal FMX/series grouping (§7 item 3).
- Direct sensor/TWAIN hardware capture bridge, if a real clinic hardware-integration need appears.
- A general-purpose document/attachment module, if a real need beyond clinical images appears (§2/§12).

---

## Standing Architectural Principle Checks (per [[workflow_two_phase_process]], added 2026-07-27)

**SaaS Multi-Tenant Readiness** ([[policy_saas_multitenant_readiness]]): checked. No column or path
scheme in this design assumes single-organization in a way that would block adding tenant scoping later —
`patient_id`-namespaced storage paths (§3.1) and a schema with no hardcoded global assumptions mean a
future `clinic_id` column/path-prefix is a pure addition, not a reshape. No blockers found.

**PWA / Mobile-First** ([[policy_pwa_mobile_first]]): checked. Upload uses the standard HTML5 file-input
`capture` attribute for camera access — works in any mobile browser today and inside an installed PWA
shell later, no native code required. Gallery grid and lightbox are designed responsive/touch-first from
the start (§8), not as a desktop-first afterthought. No PWA infrastructure (manifest/service worker) exists
yet project-wide and none is being built here — this module simply avoids any pattern that would need
reworking once that infrastructure lands.

**New tech debt introduced**: none identified that affects scalability or mobile UX — the two items
flagged (§7 item 5 storage disk, §9 malware scanning) are pre-existing/deployment-level gaps common to
every module storing anything, not new debt specific to this design.
