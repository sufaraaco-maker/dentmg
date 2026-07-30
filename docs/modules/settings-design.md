# Settings — Module Design (Approved, 2026-07-30)

**Status: Done — Production Ready ✅.** CI-confirmed 2026-07-30 on `feature/settings` (not yet merged
to `main`): `ClinicSetting`/`BillingSetting` API+UI, My Account self-service, Lab Case slip
integration. 877/877 backend tests (22 Settings-specific) + 672/672 frontend Vitest tests green,
`vue-tsc`/ESLint/Pint/Prettier clean, permanent E2E suite (`frontend/e2e/settings.spec.ts`) confirmed
32/32 green via the GitHub Actions API (`workflow_dispatch` run `30562178951`). A first CI run
(`30561623931`) surfaced two real, small issues — a Prettier formatting gap in three new files and a
wrong error-message string in the My Account E2E test (Laravel's `current_password` rule actually
returns "The password is incorrect.", not the test's assumed "The provided password is incorrect.")
— both fixed; the second run is fully green. See `TECH_DEBT.md` for the full diagnostic trail
(including the same pre-existing local PHPStan-container quirk and Alpine/glibc Playwright mismatch
already logged against Reports, both confirmed clean via CI) and `docs/roadmap.md` for current status.
This module followed Reports in the planned order (`PROJECT_CONTEXT.md`); AI Assistant is next.

**Approval notes**: confirmed SaaS/multi-tenant readiness (§3's `clinic_id`-ready singleton pattern),
PWA/mobile-first compatibility (§7), full en/ar/tr i18n with no hardcoded strings, Clean
Architecture/SOLID layering (Policy → Form Request → Service → Controller), and My Account security
(current-password-gated password change, structural IDOR-proofing via `$request->user()` — §5) before
starting implementation.

## 0. Competitive Research (required before any design, per standing product philosophy)

| Source | Finding | Taken / Rejected for this design |
|---|---|---|
| **Open Dental** ([Preferences](https://www.opendental.com/manual/preferences.html), [Practice Setup](https://www.opendental.com/manual/practice.html), [Clinic](https://www.opendental.com/manual/cliniceditwindow.html)) | A sprawling flat "Setup" menu of ~40 discrete screens (Practice Setup, Preferences — itself a ~15-category left-nav dialog, Definitions, Clinic, Provider, Security, Fee Schedules, Enterprise...). Practice Setup holds practice-wide identity (name, phone, addresses, default provider/billing type). Each `Clinic` record can override specific practice-wide fields ("use global preference" pattern) — the clearest explicit practice-default-vs-location-override hierarchy of the four researched. | **Taken**: a dedicated "Practice/Clinic identity" settings screen, separate from financial config — mirrors Open Dental's Practice-Setup-vs-Preferences split (identity vs. behavior/financial). **Rejected**: the ~40-screen flat menu — named explicitly by our own research as a cautionary example, not a model to follow; this codebase's "keep it simple" philosophy argues for the smallest coherent screen set that closes real, demonstrated gaps (§2), not a sprawling settings tree built ahead of need. **Rejected for V1**: the Clinic-override hierarchy — no second location exists yet (`TECH_DEBT.md`'s "Multi-branch" item: "do not build speculatively"); taken instead is the cheaper, already-proven `BillingSetting` pattern (§3) which reaches the same eventual multi-tenant destination without building real multi-row Location infrastructure now. |
| **Dentrix Ascend** ([Settings to Succeed](https://support.dentrixascend.com/hc/en-us/articles/360021060914-Settings-to-Succeed-with-Dentrix-Ascend), [Location Configuration](https://support.dentrixascend.com/hc/en-us/articles/229954647-Adding-providers)) | Cloud/multi-location-first: settings explicitly separated into **user-level** (role + allowed/default locations), **location-level** (providers, operatories, working hours), and **org-wide** (transaction-locking window, write-off defaults, permission defaults) tiers. | **Taken**: the three-tier separation as a *conceptual* model — this design's "My Account" (user-level), "Practice Settings" + "Billing Settings" (org-wide) split follows the same shape, just without a location tier (none exists yet). **Confirms** the decision to keep personal/self-service settings (My Account) structurally distinct from admin-only practice configuration, not one undifferentiated "Settings" screen. |
| **CareStack** ([Practice Settings hub](https://carestack.zendesk.com/hc/en-us/articles/26584059054100-Manage-All-Locations-in-Practice-Settings), [Manage User Settings](https://carestack.zendesk.com/hc/en-us/articles/26111665014548-Manage-User-Settings)) | The most modern "settings-home-as-hub-with-categories" of the four — a `System Menu > Practice Settings` landing page linking to named sub-sections (Users, Location Assignment, Scheduler Settings). Global objects (appointment reasons, templates) are defined once and *tagged/scoped* to locations rather than duplicated per-location. | **Taken**: the settings-home-as-a-card-grid landing pattern — mirrors this codebase's own `ReportsHomeView.vue` precedent (a role-filtered card grid linking to each sub-area), reused here for consistency rather than inventing a new settings-navigation idiom. **Confirms**: the tagging/scoping-over-duplication principle is exactly why `TECH_DEBT.md`'s Multi-branch item and this design both defer real per-location rows — the `clinic_id`-column-later path (§3) is the tagging-style approach applied to a not-yet-existing location dimension. |
| **Denticon (Planet DDS)** ([Account Setup](https://support.planetdds.com/hc/en-us/articles/37669881129499-Denticon-Setup-Guide-Account-Setup), [Office Setup](https://support.planetdds.com/hc/en-us/articles/37669915467419-Denticon-Setup-Guide-Office-Setup)) | The simplest, most explicit naming convention for the practice-default/location-override distinction: **Account Setup = organization-wide** ("any changes make global impacts to all locations"), **Office Setup = per-location**. | **Taken**: the naming clarity — this design's "Practice Settings" (identity) and "Billing Settings" (financial config) are both, in Denticon's terms, "Account Setup"-tier: organization-wide, no location tier, matching DentalSuite's actual current single-organization reality (`PROJECT_CONTEXT.md`) rather than pretending a location tier already exists. |

**Net effect**: all four competitors converge on the same shape — practice-wide identity/config settings kept
structurally separate from per-user account settings, with multi-location systems adding a third tier this
codebase doesn't need yet. Two things are taken as design principles rather than literal features: (1) a
settings-home landing page (CareStack's hub, already precedented by this codebase's own `ReportsHomeView`),
and (2) the "practice-wide singleton, `clinic_id`-ready" pattern this codebase already built for
`BillingSetting` — extended here rather than replaced, so the whole system reaches multi-tenant-readiness
the same way once, not via two different patterns.

## 1. Module Goal / Purpose

Close three concrete, already-identified gaps rather than build a speculative settings tree:

1. **`BillingSetting` has no API or UI at all.** The model/table have existed since the Billing module
   (`backend/app/Models/BillingSetting.php`), and `InvoiceService` already *reads* `currency_code`/
   `tax_rate`/`invoice_number_prefix` from it — but nothing lets an admin ever *set* them. Every clinic
   today is stuck with whatever the seeded/factory defaults happen to be.
2. **No clinic identity exists anywhere in the system.** Grep-confirmed: no name/phone/address/email for
   the clinic itself is stored, read, or displayed anywhere — not on the printable Lab Case slip
   (`LabCaseDetailView.vue`), not on an Invoice, nowhere. A real clinic printing and handing a Lab Case slip
   to a courier today has no way to put its own contact info on that piece of paper.
3. **No user can manage their own account.** `UserPolicy::update()` is unconditionally `$actor->isAdmin()`
   — even a user editing *their own* name, email, or password requires an admin to do it for them via the
   Users screen. Every competitor researched (and every mainstream SaaS app) treats "my account" as
   self-service, distinct from admin-managed staff records.

This module closes exactly these three gaps: **Practice Settings**, **Billing Settings**, and **My Account**.

## 2. Scope (V1)

**In scope:**
- **Practice Settings** (new `ClinicSetting` singleton table, admin-only): clinic name, phone, address,
  email. Wired into the existing Laboratory printable Lab Case slip (§4.1) as its first real consumer —
  a small, additive touch to an existing view, not a new module dependency.
- **Billing Settings** (existing `BillingSetting` table, first real API + UI, admin-only): edit
  `currency_code`, `tax_rate`, `invoice_number_prefix`; `next_invoice_sequence` shown **read-only**
  (system-managed by `InvoiceService`, never user-editable — editing it risks duplicate/skipped invoice
  numbers).
- **My Account** (self-service, every role): view/edit own `name`/`email`; change own password (requires
  current password). Reachable from the existing user-avatar menu (`AppHeader.vue`'s `Menu` — currently
  just "Logout"), not the admin Settings nav, mirroring every competitor's user-vs-practice-tier split (§0).
- A **Settings** home landing page (admin-only nav entry, the existing `nav.settings` `comingSoon`
  scaffold filled in) — an admin-only card grid linking to Practice Settings and Billing Settings,
  mirroring `ReportsHomeView.vue`'s exact pattern.
- Full en/ar/tr i18n, dark mode, RTL, keyboard access, responsive — enterprise UX bar per standing
  philosophy.

**Explicitly out of scope for V1** (named, not silently dropped — see §9):
- Multi-branch/multi-location settings (a `Clinic`/`Location` table with real per-location overrides) —
  `TECH_DEBT.md`'s existing "Multi-branch" item is explicit: "documented, not implemented... do not build
  speculatively." `ClinicSetting`/`BillingSetting` stay clinic-scoped-*ready* singletons, not real
  multi-row location infrastructure (§3).
- Clinic logo upload — no current consumer would render it (no PDF/letterhead exists yet); adding an
  unused upload field is settings for their own sake. Revisit once a real document (an Invoice PDF, a
  branded Lab Case slip) would actually display it.
- Notification/reminder channel settings (email/SMS templates, reminder lead time) — no notification
  infrastructure exists anywhere in this codebase (`AppointmentReminder` is a dormant, unused table/model;
  confirmed via Reports' own design doc research). Nothing to configure yet.
- Appearance/theme toggle and personal locale/language — both already exist as a **per-user** header
  control (dark-mode toggle, locale switcher in `AppHeader.vue`); not a practice-wide "Settings" concern,
  and duplicating them here would fragment where a user goes to change them.
- Any general-purpose "system preferences" catalog, feature-flag toggles, or Open-Dental-style sprawling
  Preferences dialog — no demonstrated need for any of it yet (§0's own explicit rejection of that model).

## 3. Data Model

### `ClinicSetting` (new — mirrors `BillingSetting`'s exact shape and reasoning)
A single-implicit-row settings table, designed clinic-scoped-*ready* even though nothing reads/writes a
`clinic_id` column yet — the same pattern `billing_settings` already established, continued here rather
than replaced with a different convention. `name` (string, required), `phone` (nullable string), `address`
(nullable text), `email` (nullable string). Traits: `Auditable, HasFactory, HasUuids` — **no `SoftDeletes`**,
same exception class `docs/database-design.md` already carves out for lookup/config tables (a settings row
is configuration, not a real-world record a clinic recovers).

### `BillingSetting` (existing — no schema change)
No migration needed. This module adds its first-ever `BillingSettingController`/`BillingSettingPolicy`/
Form Requests/routes — the table and model are untouched.

### Why two tables, not one
`ClinicSetting` (identity/contact) and `BillingSetting` (financial config) stay separate rather than merged
into one wide "Settings" table — same single-responsibility reasoning Open Dental's own Practice-Setup-vs-
Preferences split follows (§0), and it keeps each table's own future `clinic_id` migration fully independent
of the other (§8 decision 5).

## 4. Feature Catalog

### 4.1 Practice Settings
**Question answered**: what is this clinic's own name/contact info, and where does it show up?
- **Fields**: `name` (required), `phone`, `address`, `email` — all simple strings/text, no structured
  address parsing (matches this codebase's existing `Patient.address` free-text convention, not a
  city/state/zip breakdown nobody asked for).
- **First real consumer**: `LabCaseDetailView.vue`'s printable slip (`@media print` browser stylesheet,
  no PDF dependency, same convention Laboratory's own design already established) gains a small clinic-
  identity header block — the concrete gap named in §1.

### 4.2 Billing Settings
**Question answered**: what currency/tax rate/invoice numbering does this clinic use?
- **Fields**: `currency_code` (string, e.g. `USD`), `tax_rate` (decimal, already `decimal:2`-cast on the
  model), `invoice_number_prefix` (string, e.g. `INV-`). `next_invoice_sequence` is displayed **read-only**
  in the UI — `InvoiceService` owns writing it via its own lock-and-increment logic; exposing it as editable
  risks a duplicate or skipped invoice number the moment an admin fat-fingers it.

### 4.3 My Account
**Question answered**: how does a signed-in user manage their own name/email/password without an admin?
- **Profile fields**: `name`, `email` (both `sometimes`/`required`, mirrors `UpdateUserRequest`'s exact
  validation shape minus `role` — a user can never change their own role here).
- **Password change**: requires `current_password` (Laravel's built-in validation rule) + `password`
  (`confirmed`, `Password::defaults()`) — exact same password-strength rule `StoreUserRequest`/
  `UpdateUserRequest` already use.
- **No self-role-change, no self-delete** — both remain admin-only via the existing `UserPolicy`/
  `UserController`, untouched by this module.

## 5. Permissions

| Action | Roles | Precedent |
|---|---|---|
| View Practice Settings | every role | **Deviation from the original proposal, caught during implementation**: the Lab Case printable slip (§4.1) — reachable by every role with lab-case access — needs to read clinic name/phone/address to render its print header, so `view` cannot be admin-only or non-admins 403 printing a slip. Mirrors `SupplierPolicy`'s `viewAny: true` / mutate-only-admin shape; clinic contact info carries no more sensitivity than a business card. |
| Update Practice Settings | admin only | Unchanged from the original proposal — mirrors `SupplierPolicy`/`AppointmentTypePolicy` mutation gating. |
| View/update Billing Settings | admin only | Same tier as Practice Settings — financial configuration is at least as sensitive as Treatment Plans' pricing data (mirrors Billing's own design doc §14 reasoning). |
| View/update own profile, change own password | every role (self only) | New territory for this codebase — no prior module has self-service. Enforced structurally, not just by policy: routes resolve the target from `$request->user()`, never a route-model-bound `{user}` parameter, so there is no ID to substitute into an IDOR attempt in the first place. |

### `ClinicSettingPolicy` / `BillingSettingPolicy` (both finalized — singleton resources, no per-row `{id}`)

```php
class ClinicSettingPolicy
{
    public function view(User $actor): bool { return true; } // see deviation note above
    public function update(User $actor): bool { return $actor->isAdmin(); }
}

class BillingSettingPolicy
{
    public function view(User $actor): bool { return $actor->isAdmin(); }
    public function update(User $actor): bool { return $actor->isAdmin(); }
}
```
Both are checked against the model *class* (`$this->user()->can('view', ClinicSetting::class)`), the
standard Laravel idiom for a singleton resource with no meaningful per-instance identity to bind a route
parameter to.

**My Account has no Policy** — `ProfileController`'s actions operate exclusively on `$request->user()`, so
"can the actor edit this profile" is definitionally always true for their own profile and never reachable
for anyone else's; a Policy class would only add ceremony around a check that's already structurally
impossible to get wrong.

## 6. API Design

```
GET   /api/clinic-settings              # admin only
PUT   /api/clinic-settings               # admin only

GET   /api/billing-settings              # admin only
PUT   /api/billing-settings               # admin only

GET   /api/profile                       # any authenticated user — returns $request->user()
PUT   /api/profile                       # any authenticated user — name/email
PUT   /api/profile/password              # any authenticated user — current_password + new password
```

Not REST-resource routes (`Route::apiResource`) — both `clinic-settings`/`billing-settings` are true
singletons (no `{id}`, no `index`/`store`/`destroy`; only `show`+`update` make sense), and `profile` acts
on the authenticated user implicitly, exactly like the existing `GET /api/user` (`AuthController::user()`)
already does — this is the documented exception to the apiResource default (`docs/api-guidelines.md`:
"...unless the action genuinely isn't CRUD-shaped").

`GET /api/clinic-settings`/`GET /api/billing-settings` return the single existing row (created by a
migration-time seed/factory default, mirroring how `billing_settings` presumably already gets its one row
today — confirmed at implementation time) rather than ever needing a `store`.

## 7. Frontend

- Fill in the existing `nav.settings` scaffold (`frontend/src/config/navigation.ts`, currently
  `comingSoon: true`) as a real top-level admin-only entry (`roles: ['admin']` at the top level itself,
  since every child is admin-only — no mixed-visibility group needed here, unlike Reports).
- `SettingsHomeView.vue` — a two-card grid (Practice Settings, Billing Settings), mirroring
  `ReportsHomeView.vue`'s exact card-grid pattern (§0's CareStack-inspired decision).
- `PracticeSettingsView.vue` / `BillingSettingsView.vue` — a single form each (PrimeVue `InputText`/
  `Textarea`/`InputNumber`, no dialog — these are singleton edit screens, not list+dialog CRUD), Save
  button, success/error toast, matching the form-screen convention already used by e.g. `LabsView.vue`'s
  edit dialog content (just promoted to a full page instead of a modal, since there's exactly one record).
- **My Account**: a new "My Account" item added to `AppHeader.vue`'s existing user-avatar `Menu` (currently
  only "Logout"), linking to `AccountView.vue` — a profile form (name/email) plus a separate "Change
  Password" panel (current password + new password + confirm), available to every authenticated role,
  reachable from the header regardless of the admin-only Settings nav.
- Full en/ar/tr i18n, dark mode, RTL, keyboard access, responsive/PWA — enterprise UX bar per standing
  philosophy. (Noting for the record, not as this module's problem to solve: this codebase has no
  app-wide PWA manifest/service-worker yet — confirmed absent via `frontend/vite.config.ts`/`package.json`
  — so "PWA-installable" per module has meant responsive/touch-ready design compatible with a future PWA
  wrapper, not an actually-installable app today. Worth its own `TECH_DEBT.md` entry as a cross-cutting,
  whole-app item, not something Settings should build speculatively as a side effect of its own screens.)

## 8. Open Decisions (for approval)

1. **Two singleton tables (`ClinicSetting` new, `BillingSetting` existing) rather than one merged
   "Settings" table** — recommend **approve as proposed** (§3), for single-responsibility and independent
   future `clinic_id` migrations.
2. **My Account reachable via the user-avatar menu, not the admin Settings nav** — recommend **approve as
   proposed**, matching every competitor's user-tier-vs-practice-tier separation (§0) and reflecting that
   every role (not just admins) needs it.
3. **`next_invoice_sequence` shown read-only, never editable** — recommend **approve as proposed**; editing
   it bypasses `InvoiceService`'s own lock-and-increment invariant and risks duplicate/skipped invoice
   numbers.
4. **No clinic logo upload in V1** — recommend **defer until a real consumer exists** (an Invoice PDF or a
   branded printable document) rather than build an unused upload field now.
5. **Wire Practice Settings into the existing Lab Case printable slip as this module's first real
   consumer** — recommend **approve as proposed**; a small, additive touch to `LabCaseDetailView.vue`
   (adding a clinic-identity header block to the existing `@media print` stylesheet), not a new
   cross-module dependency, and it closes a real, concrete gap named in §1 rather than shipping settings
   with no visible effect anywhere.
6. **No multi-branch/location tier** — recommend **approve as proposed**, per `TECH_DEBT.md`'s own explicit
   "do not build speculatively" instruction; the clinic-scoped-ready singleton pattern already reaches the
   same eventual multi-tenant destination.

## 9. Explicitly Out of Scope for V1 (summary — see §2/§8 for full reasoning)

- Multi-branch/multi-location settings (real per-location override infrastructure).
- Clinic logo upload (no current consumer).
- Notification/reminder channel settings (no notification infrastructure exists).
- Appearance/theme and personal locale settings (already per-user, in the header, elsewhere).
- Any general-purpose feature-flag/preferences catalog beyond the three named gaps in §1.
- Fixing the whole-app "no PWA manifest/service-worker yet" gap — a cross-cutting item worth its own
  `TECH_DEBT.md` entry, not this module's responsibility to solve as a side effect.
