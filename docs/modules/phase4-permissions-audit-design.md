# Phase 4 — Advanced Permissions & Audit — Design Doc

**Status: Design Phase — awaiting approval. No implementation code written yet.**

**Roadmap position**: Phase 4 of the 8-phase post-roadmap initiative (Stabilization → Patient Profile →
Dashboard 2.0 → **Advanced Permissions & Audit** → SaaS Multi-Tenant Prep → PWA & Mobile → AI Assistant
Expansion → Launch Preparation).

**Scope decisions (confirmed with user, 2026-08-09)**, each made after a ground-truth audit of the actual
current code (§0), not assumption:

1. **Fine-grained permissions layer on top of the current 3 roles** (Admin/Dentist/Receptionist) — not a
   full role-hierarchy rewrite. The `docs/decisions.md` 2026-08-07 entry flagging
   `Owner → Clinic Admin → {Dentist, Assistant, Receptionist, Accountant}` stays **flagged, still not
   decided** — revisit when Phase 5 (SaaS Multi-Tenant Prep) gives it a concrete multi-clinic reason to
   exist, rather than building it speculatively now (see §12).
2. **Full audit-trail overhaul**, closing both critical gaps this phase's audit found (neither was
   previously logged anywhere): the `User` model itself is not audited today, and there is zero logging of
   authentication events (login success/failure, logout). Adds before/after diffs (today only "new" values
   are stored), IP + User-Agent capture, and a general admin-facing Audit Log viewer (today's only viewer
   is patient-scoped).
3. **Simple immutability now** — no update/delete path for audit rows, enforced at both the application
   layer (no such route/controller/service method ever exists) and the Eloquent layer (belt-and-suspenders
   guard). **Retention/purge policy explicitly deferred** — logged as an open item requiring a concrete
   compliance/legal input before a TTL is chosen, not silently dropped (see §12).

---

## 0. Audit — current state (ground truth, not assumption)

Full audit performed via direct code reads (Explore-agent-assisted, cross-checked by direct file reads for
every uncertain finding) rather than trusting `docs/decisions.md`'s prior flag or memory of earlier phases.

**Permission model** (`backend/app/Enums/UserRole.php:5-9`): a plain string-backed enum, exactly 3 cases —
`Admin`, `Dentist`, `Receptionist`. No hierarchy, no permission concept separate from role.

**Authorization surface**: 27 Policy classes in `backend/app/Policies/`, plus exactly 2 named Gates
(`AppServiceProvider.php:47-48`: `view-financial-reports` admin-only, `view-operational-reports` open to
all) and one `Gate::policy()` registration for `MedicalHistoryPolicy` covering 3 models
(`AppServiceProvider.php:53-55`). **Zero route-level `can:` middleware** — every check happens inside
Controllers via `authorize()` (31 controller files). This means the blast radius of a permission-model
change is contained almost entirely to the 27 Policy classes, not scattered across routes/controllers.

Every Policy follows the same pattern: `$actor->role === UserRole::X` or `in_array($actor->role, [...])`,
or the `User::isAdmin()` helper (`app/Models/User.php:56-58`: `return $this->role === UserRole::Admin;`).
No fine-grained permission system exists anywhere today. No admin UI exists for managing
roles/permissions — `frontend/src/views/UsersView.vue` only offers a role dropdown per user, no
per-role capability configuration.

**Audit trail** (`Auditable` trait → `AuditObserver` → `audit_logs` table):
- `backend/app/Models/Concerns/Auditable.php:13-24` registers `AuditObserver` on `created`/`updated`/
  `deleted` only (no `restored`/`forceDeleted` handling).
- `backend/app/Observers/AuditObserver.php:8-25` — `created()` records `$model->getAttributes()` (full
  snapshot), `updated()` records `$model->getChanges()` (**dirty/new values only — no "old" state is ever
  captured**), `deleted()` records an **empty array** (the deleted row's data is not preserved).
- `backend/app/Services/AuditLogService.php:26-37` writes to `AuditLog` via `Auth::id()` for `user_id`,
  with a hardcoded exclusion list (`password`, `remember_token`, timestamps, `ai_assistant_api_key`) — this
  exclusion list is the reason adding `User` to the Auditable list is safe (line 19-21 already strips the
  password hash before persistence).
- `audit_logs` migration (`2026_07_14_000001_create_audit_logs_table.php:11-21`): `id`, `user_id`
  (nullable FK, null-on-delete), `auditable_type`/`auditable_id` (polymorphic), `action`, `changes` (json,
  nullable), `created_at` only (`$table->timestamps()` not used — no `updated_at`, matching `AuditLog`'s
  `public $timestamps = false`). **No `ip_address`, no `user_agent`, no "old values" column.**
- Only viewer: `GET patients/{patient}/audit-logs` (`routes/api.php:52`) →
  `PatientController::auditLogs()`, gated by `PatientPolicy::viewAuditLogs` (admin-only,
  `PatientPolicy.php:44-46`), rendered via `AuditLogResource`. **No general admin-facing audit log
  screen exists** — only this one patient-scoped view.
- 20 models use `Auditable` today (`Patient`, `Invoice`, `Payment`, `ClinicalNote`, `TreatmentPlan`, etc.
  — full list in the audit transcript). **`User` is not among them** — role/account changes are
  completely unaudited today, the single most audit-relevant gap for this phase's own name.
- **Authentication events**: `AuthController::login()`/`logout()` (`AuthController.php:15-32`) delegate to
  `AuthService::login()`/`logout()` (`AuthService.php:15-46`), which already has rate-limiting
  (`MAX_ATTEMPTS = 5`, `DECAY_SECONDS = 60`) and a `ValidationException` on bad credentials — but **no
  logging call of any kind** on success, failure, or logout. Confirmed by direct read, not grep — there is
  no `Log::`/audit call anywhere in either class.

**`PatientActivity` vs. `Auditable`** — confirmed **intentionally separate systems**, not a gap:
`PatientActivity` (`app/Models/PatientActivity.php`) is explicitly documented in its own docblock as "the
dedicated event feed the Security Architecture Decision (design doc §9A) requires instead of Auditable,
not another auditable record" — a patient-scoped, human-readable activity feed (category/summary), not an
admin-audit-shaped record (no before/after diff, no IP/UA, no general viewer). Both systems can fire for
the same underlying change on models present in both (e.g. `Appointment`, `Invoice`, `ClinicalNote`) — a
deliberate duplication serving two different audiences (compliance vs. in-context clinical UX). This phase
does **not** merge them — see §7 for how the boundary stays explicit.

**Immutability / retention**: none exist today. `AuditLog` has no `SoftDeletes`, no DB trigger blocking
UPDATE/DELETE, no checksum/hash-chaining. Confirmed absent, not assumed.

**Reverse-proxy / IP capture readiness**: no `TrustProxies` middleware configuration was found in
`backend/bootstrap/app.php`. Since production runs behind nginx (per `PROJECT_CONTEXT.md`'s deployment
topology), `$request->ip()` may currently resolve to nginx's internal IP rather than the real client IP
unless trusted proxies are configured — **flagged as an implementation-time verification item**, not
something to design around abstractly (§8).

---

## 1. Permission model

### 1.1 Permission catalog

A fixed, backend-owned catalog — not admin-editable (only the role→permission *assignment* is
admin-editable, not the permission list itself, to avoid an open-ended free-text permission system nobody
asked for). Structure: `permission_key` strings grouped by module, `resource.action` shaped
(e.g. `patients.delete`, `invoices.create`, `reports.view-financial`, `users.manage`,
`permissions.manage`, `audit.view`). The catalog is **derived 1:1 from every existing Policy's current
actual behavior** (per §0's audit) — this is a behavior-preserving migration, not a redesign of who can do
what. Day 1 after this phase ships, every role has exactly the same effective permissions it has today;
the only new capability is that an admin can now *change* that mapping without a code deploy.

Stored as a `permissions` table (`key` unique, `group` — for UI grouping, e.g. `"Patients"`,
`"Billing"`, `"Reports"`) — no translated label column; the frontend renders a label via i18n keyed by
`permission.key`, matching how every other backend-value-driven label already works in this app (statuses,
roles, etc.), so the permission catalog itself stays a stable, backend-controlled identifier list.

### 1.2 `role_permissions` — the admin-configurable matrix

A `role_permissions` table (`role` — the `UserRole` string value, `permission_key` — FK to
`permissions.key`, unique on the pair) is the single source of truth an admin can edit. Seeded at migration
time to exactly match today's Policy behavior (derived directly from §0's per-Policy audit findings), so
the seeder itself is the proof that nothing changes on day 1.

### 1.3 Policy refactor pattern

Each of the 27 Policy classes keeps existing (Laravel's per-model discovery convention, and the route
binding shape, both stay unchanged) but its internals change from a raw role comparison to a permission
check: `$actor->role === UserRole::Admin` becomes `$actor->hasPermission('patients.delete')`, where
`hasPermission()` (new method on `User`, `app/Models/User.php`) looks up the actor's role's permission set.
This is mechanical but wide — touches all 27 files, ~150+ individual call sites per §0's count — so it is
sequenced as its own batch of implementation steps (§14), not one giant commit, and every currently-tested
Policy's existing test suite (13 of 27 have one today) must stay green unchanged, proving behavior
preservation; the ~14 untested Policies get a test added as part of this same phase specifically because
they're being touched and currently have zero regression coverage (§13).

`hasPermission()` resolves via a per-request cache (`Cache::remember`, keyed by role, short TTL,
invalidated on any `role_permissions` write) — see §8 for why.

### 1.4 Safety rule: prevent Admin self-lockout

Two permissions — `users.manage` and `permissions.manage` — can **never** be revoked from the `Admin` role
through the matrix UI/API. This is enforced server-side (validation rule in §6, not just a UI
affordance) so there's no path to a clinic with no admin able to fix its own permission table. These two
"meta" capabilities (viewing/editing `role_permissions` itself, and the general Audit Log viewer's
`audit.view`) are additionally checked via `isAdmin()` directly rather than through the matrix they
themselves gate — avoids a chicken-and-egg scenario where a misconfigured matrix could lock admins out of
the screen that fixes the matrix. This is a deliberate, narrow exception to "everything goes through
permissions now" and is called out explicitly so it isn't mistaken for an oversight (§11).

### 1.5 New API surface

| Endpoint | Gate | Purpose |
|---|---|---|
| `GET /permissions` | admin-only (`isAdmin()`) | List the full catalog, grouped by module — powers the matrix UI |
| `GET /role-permissions` | admin-only (`isAdmin()`) | Current role→permission matrix |
| `PUT /role-permissions` | admin-only (`isAdmin()`) | Bulk-update the matrix; rejects any attempt to strip `users.manage`/`permissions.manage` from Admin (§1.4, §6) |

### 1.6 New Admin UI — Permissions screen

A new Sidebar item, admin-only (existing precedent — Settings already gates its own nav item this way),
`PermissionsView.vue`: a matrix (module-grouped rows, one column per role, toggle switches). The two
locked Admin cells (§1.4) render as disabled/locked, not just validated server-side, so the UI itself
explains the rule rather than surprising the admin with a rejected save.

---

## 2. Audit trail overhaul

### 2.1 Close gap 1 — `User` becomes `Auditable`

Add the `Auditable` trait to `app/Models/User.php`. Already safe by construction: `AuditLogService`'s
existing exclusion list already strips `password`/`remember_token` (`AuditLogService.php:19-21`) before
anything is persisted, so no credential material reaches `audit_logs`. This alone closes the biggest gap:
every role change, account creation, and deletion is now recorded with who did it and when.

### 2.2 Close gap 2 — authentication events

`AuthService::login()`/`logout()` (`AuthService.php`) each get one new call into `AuditLogService` (a new
method, `recordEvent()`, alongside the existing model-bound `record()` — see §2.7 for why one table, two
entry points):
- **Login success**: `action = 'login_succeeded'`, `auditable` = the authenticated `User`.
- **Login failure**: `action = 'login_failed'`, `auditable_id` null (no user resolved), but the attempted
  email is captured in a `context` field (§2.3) — never the password.
- **Logout**: `action = 'logged_out'`, `auditable` = the `User` being logged out.

Each call also captures IP + User-Agent (§2.4). Rate-limited login attempts (the existing
`RateLimiter::tooManyAttempts()` short-circuit in `AuthService.php:19-25`) are **not** separately logged as
a distinct action — they're a subset of `login_failed` in effect (the underlying attempt already failed
before hitting the limiter on a prior request); adding a third action type for this would be a distinction
without an operationally useful difference, so it's deliberately not built (§12 names it explicitly rather
than silently skipping it).

### 2.3 Before/after diff capture

`AuditObserver::updated()` currently only captures `getChanges()` (new values). Add `getOriginal()`
(scoped to the same changed keys, so the diff is symmetric) as a new `old_values` payload. `deleted()`
currently captures nothing — change it to capture the model's full attributes (its state immediately
before deletion) into the same `old_values` field, so a deleted record's last-known state is recoverable
from the audit trail even though the row itself is gone (soft-deletes already handle most deletions in
this app, but audit shouldn't depend on that being true for every model). `created()` keeps `old_values`
null (nothing existed before).

### 2.4 IP address + User-Agent capture

`AuditLogService::record()`/new `recordEvent()` both pull `request()->ip()` and
`request()->userAgent()` at write time. Per §0's finding, **verify/configure trusted-proxy handling**
(Laravel's `TrustProxies` middleware, pointed at the production nginx layer) as part of implementation —
without it, every captured IP could be nginx's internal address rather than the real client, which would
make this feature look complete while being silently useless. This is called out as its own implementation
checkpoint (§14), not assumed to already work.

### 2.5 Immutability

No `AuditLogController::update()`/`destroy()` method is ever written — the application-level guarantee is
"no route exists," same as today. Additionally, `AuditLog::boot()` gets a static guard that throws if
`update()`/`delete()` is ever called on the model directly (belt-and-suspenders against a future accidental
`$log->update(...)` slipping through code review). No DB-level trigger is added — that's a heavier
mechanism than this phase's scope calls for; if a real tamper-evidence requirement shows up later (e.g. an
external compliance audit), that's the point to revisit with a concrete requirement in hand, not before
(§12).

### 2.6 General Audit Log viewer

New `GET /audit-logs` endpoint (admin-only via `isAdmin()`, §1.4's exception), paginated, filterable by
`user_id`, `auditable_type`, `action`, and a date range. New `AuditLogsView.vue`, a new admin-only Sidebar
item, reusing the existing `AuditLogResource` shape (extended with `old_values`/`ip_address`/`user_agent`).
Row-expand shows the old/new diff side-by-side. The existing per-patient audit viewer
(`patients/{patient}/audit-logs`) is unchanged and stays useful for in-context review — this is an
additional, cross-cutting view, not a replacement.

### 2.7 One `audit_logs` table, not a separate `auth_logs` table (trade-off, explicit)

Authentication events reuse `audit_logs` (via the new `recordEvent()` entry point) rather than a dedicated
table, so the new general Audit Log viewer (§2.6) is one screen covering everything, not two. The
trade-off: `audit_logs.auditable_type`/`auditable_id` are nullable-in-spirit for a `login_failed` row
(no user resolved) — handled by allowing `auditable_id` null (the column is already nullable-compatible
since `user_id` already allows null) and putting the attempted email into the new `context` json column
instead of forcing it through the `auditable` polymorphic pair. Documented here so a future reader doesn't
mistake this for an oversight.

---

## 3. Database design

**New table `permissions`**:
| Column | Type | Notes |
|---|---|---|
| `key` | string, PK | e.g. `patients.delete` |
| `group` | string | e.g. `Patients` — UI grouping only |
| `description` | string, nullable | internal/developer-facing, not shown to end users |

**New table `role_permissions`**:
| Column | Type | Notes |
|---|---|---|
| `id` | uuid, PK | |
| `role` | string | `UserRole` value |
| `permission_key` | string, FK → `permissions.key` | |
| `created_at`/`updated_at` | timestamps | for knowing when the matrix last changed (the change itself is separately audited, §3's next item) |

Unique index on (`role`, `permission_key`). Writes to this table go through `AuditLogService` too (a
`role_permissions` matrix change is itself audit-worthy — "who changed what a Receptionist can do, and
when" is exactly the kind of event this phase exists to capture) — via the existing generic `record()`
call, `auditable` pointing at a lightweight synthetic record of the change, not a new Auditable model
(`role_permissions` rows are managed as a set via `PUT`, not individually CRUD'd, so per-row `Auditable`
doesn't fit — the controller calls `AuditLogService::recordEvent()` directly with `action =
'role_permissions_updated'` and the full before/after matrix as `old_values`/`changes`).

**Alter `audit_logs`** (additive migration, no renames — avoids touching every existing consumer of
`changes`):
| New column | Type | Notes |
|---|---|---|
| `old_values` | json, nullable | §2.3 |
| `ip_address` | string, nullable | §2.4 |
| `user_agent` | string, nullable | §2.4 |
| `context` | json, nullable | §2.7 — e.g. attempted email on `login_failed` |

`auditable_id` stays required-by-column-type today (`uuid`, not nullable) — confirm during implementation
whether a `login_failed` row with no resolved user needs `auditable_id` relaxed to nullable, or whether a
sentinel/omitted-row approach is cleaner; this is a small implementation-time decision, not a scope one.

No changes to `patient_activities` (§0 — deliberately out of scope, different system).

---

## 4. Validation rules

- `PUT /role-permissions`: every `role` must be a valid `UserRole` value; every `permission_key` must
  exist in the `permissions` catalog; the request is rejected (422) if it would remove `users.manage` or
  `permissions.manage` from `Admin` (§1.4) — checked server-side regardless of what the UI sent.
- `GET /audit-logs` filters: `date_from <= date_to`; `action` must be a known value if provided (not an
  open free-text filter, to keep the query indexable — §8).

---

## 5. Security considerations

- **Privilege escalation**: only `isAdmin()` can read or write `role_permissions` (§1.4's exception to
  "everything goes through the matrix") — the matrix can never grant itself broader access than an
  existing admin already had.
- **Audit tampering**: §2.5's two-layer immutability guard (no route + Eloquent-level block).
- **Credential safety**: `login_failed` logs the attempted email (useful for spotting credential-stuffing
  patterns) but never the password — enforced by construction, since `AuthService` never passes the
  password to the audit call in the first place, not by a filter that could be forgotten.
- **IP spoofing**: trusted-proxy configuration (§2.4) is a prerequisite for the IP column to mean anything
  in production — flagged as a hard implementation checkpoint, not optional polish.
- **Self-lockout**: covered by §1.4 and tested explicitly (§13).

---

## 6. Performance considerations

- `hasPermission()` is called on effectively every authorized request across the app (27 Policies). A
  per-request/per-role cache (§1.3) keeps this to one query per role per cache window, not one query per
  permission check.
- `audit_logs` write volume increases materially (every `User` change + every login/logout, on top of the
  existing 20 models). New indexes on `user_id`, `action`, and `created_at` (in addition to the existing
  `(auditable_type, auditable_id)` index) support the new filterable admin viewer without full scans.

---

## 7. Scalability considerations

- `role_permissions` is global (single-org V1), matching every other Phase 1-3 decision. Adding a
  `clinic_id` column later (Phase 5) is additive, not a redesign — same pattern already used for
  `dental_conditions`'s "approved with caution, flagged for revisit" treatment.
- `audit_logs` growth is unbounded under this phase's retention decision (§12) — acceptable for V1 volume,
  but flagged as a pre-launch item once a real retention period is chosen.

---

## 8. i18n

New keys under `permissions.*` (module group labels, permission-key labels keyed by the stable backend
`permission.key`) and `auditLog.*` (action labels: `login_succeeded`, `login_failed`, `logged_out`,
`created`, `updated`, `deleted`, `role_permissions_updated`). 3-locale parity verified programmatically,
same method every prior phase has used.

---

## 9. Testing plan

- **Backend**: `hasPermission()` unit tests (role has/doesn't have a permission, cache invalidation on
  matrix write); a Feature test per touched Policy confirming identical authorize()/deny() outcomes
  before/after the refactor for a representative action per role (regression proof, not just new-feature
  coverage); the ~14 currently-untested Policies get a baseline test added as part of this phase since
  they're being modified; `AuditLogServiceTest` (new — none exists today) covering old/new diff capture,
  IP/UA capture, the exclusion list still working post-refactor, and the `User` model now producing audit
  rows without leaking `password`; `AuthServiceTest` extended for `login_succeeded`/`login_failed`/
  `logged_out` logging; a dedicated self-lockout test (§1.4) attempting to strip `users.manage` from
  `Admin` and asserting rejection.
- **Frontend**: `PermissionsView.vue`/`AuditLogsView.vue` component tests (matrix toggle, locked-cell
  rendering, filter controls), a `permissions.ts` store.
- **E2E**: a receptionist session confirms it still has exactly today's effective permissions after the
  migration (behavior-preservation, asserted end-to-end, not just at the unit level); a non-admin session
  gets `403` on all 4 new endpoints (§1.5, §2.6); an admin successfully edits the matrix and the effect is
  visible in a subsequent request from an affected role's session within the same test.

---

## 10. Standing principles check

- **SaaS multi-tenant readiness**: `role_permissions` schema is additive-ready for a future `clinic_id`
  (§7) — not built now, but not foreclosed either.
- **PWA & mobile-first**: both new screens (Permissions matrix, Audit Log) are responsive/touch-friendly
  from first implementation — the matrix collapses to a stacked accordion per role on narrow viewports
  (a grid of toggle switches doesn't fit a phone width), the Audit Log table follows this app's existing
  card-list pattern on mobile.

---

## 11. Trade-offs / architectural decisions (summary)

- Role-level matrix, not per-user overrides (§1.1) — covers the "advanced permissions" ask without
  speculative per-individual complexity nobody has asked for yet (§12).
- Two meta-permissions (`users.manage`, `permissions.manage`) checked via `isAdmin()` directly rather than
  through the matrix they gate (§1.4) — a narrow, explicit exception to avoid lockout, not an oversight.
- One `audit_logs` table for both model-change and authentication events (§2.7) — one unified viewer, at
  the cost of a slightly looser polymorphic relationship for auth rows.
- Additive `audit_logs` migration, no column renames (§3) — avoids touching every existing consumer of the
  `changes` column for a phase whose actual goal is closing coverage gaps, not restructuring what already
  works.

---

## 12. Explicitly deferred (named, not silently dropped)

- **Full role hierarchy** (Owner/Clinic Admin/Accountant/Assistant, `docs/decisions.md`'s 2026-08-07 flag)
  — revisit when Phase 5 gives it a concrete multi-clinic reason to exist.
- **Per-user permission overrides** beyond the role-level matrix.
- **Retention/purge policy** for `audit_logs` — no TTL chosen; needs a concrete compliance/legal input,
  not an arbitrary number invented here.
- **WORM storage / hash-chaining tamper-evidence** beyond the application+Eloquent-level guard in §2.5 —
  revisit only if a concrete external compliance requirement (e.g. an actual HIPAA/GDPR audit) calls for
  it.
- **A distinct `login_rate_limited` action type** separate from `login_failed` (§2.2) — not operationally
  distinct enough to justify a third action value.
- **Merging `Auditable` and `PatientActivity`** — confirmed (§0, §2.7-adjacent) to stay two intentionally
  separate systems serving different audiences.

---

## 13. Potential risks

- **Silent behavior drift across 27 Policy refactors** — mitigated by the seeder proving the matrix starts
  identical to today, plus the regression-proof Feature tests in §9 covering representative actions per
  role per touched Policy, not just the new feature surface.
- **IP capture being meaningless behind a misconfigured reverse proxy** (§0, §2.4) — mitigated by making
  trusted-proxy verification an explicit implementation checkpoint (§14), not an assumption.
- **Self-lockout bug** if §1.4's protection has a gap — mitigated by a dedicated test asserting the
  rejection path, not just documenting the intent.

---

## 14. Implementation sequence (per this project's standing rule: not chained, each step implemented →
verified → reported → wait for approval before the next)

1. **Backend — permission model foundation**: `permissions`/`role_permissions` migrations + seeder (built
   directly from §0's per-Policy audit, proving day-1 behavior parity), `User::hasPermission()` +
   per-role cache, the 4 new endpoints (§1.5), the self-lockout validation rule (§1.4/§6).
2. **Backend — Policy refactor batch**: all 27 Policies converted to `hasPermission()` calls, regression
   Feature tests per touched Policy, new baseline tests for the ~14 previously-untested ones.
3. **Backend — audit overhaul**: `User` → `Auditable`, old/new diff capture, IP/UA capture (with
   trusted-proxy verification), `AuthService` login/logout logging, immutability guard, `GET /audit-logs`
   endpoint.
4. **Frontend**: `permissions.ts`/`auditLogs.ts` stores, `PermissionsView.vue`, `AuditLogsView.vue`,
   Sidebar entries, i18n.
5. **E2E, final i18n parity pass, docs sync** — behavior-preservation E2E case (§9), self-lockout E2E case,
   `PROJECT_STATUS.md`/`CHANGELOG.md`/`decisions.md` updated (the role-hierarchy flag gets an update
   noting it's still deferred, not resolved, by this phase).
