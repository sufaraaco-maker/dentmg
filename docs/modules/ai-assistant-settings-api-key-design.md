# AI Assistant — Settings-Managed API Key — Design Document (Approved, 2026-08-02)

**Status: Done — Production Ready ✅.** Implemented as designed (commit `48c55f7`, with a follow-up E2E
locator fix in `e4a9b06`): encrypted `ai_assistant_api_key` column on `clinic_settings`,
`AuditLogService::EXCLUDED_KEYS` extended, `AiAssistantService::client()` prefers the Settings-stored key
over `.env`, `ClinicSettingResource` returns only `configured`/`last4`, and
`AiAssistantSettingsView.vue` gets the masked set/replace/remove UI. Backend 926/926 tests, Pint/PHPStan
clean; Frontend 749/749 tests, `vue-tsc`/ESLint/Prettier clean. Verified in a real browser (light/dark,
RTL, full set/replace/remove flow, toast confirmations). Originally committed onto
`feature/premium-visual-redesign` by mistake — that branch's own design doc scopes it as "frontend-only,
presentation-layer only" (`docs/modules/frontend-visual-redesign-design.md`), so this backend feature
(migration, encrypted column, audit-log change) was out of scope there. Split onto its own
`feature/ai-assistant-api-key` branch (2026-08-02) and shipped via its own PR instead.

Amends [`ai-assistant-design.md`](./ai-assistant-design.md) (the original AI Assistant module design,
merged via PR #9). That design deliberately kept the Anthropic API key as an ops-managed secret
(`ANTHROPIC_API_KEY` in `backend/.env`, read via `config('services.anthropic.key')`) — an admin could
enable/disable the module and acknowledge the PHI BAA precondition, but never touched the key itself. This
document adds a **self-service path**: an admin enters/updates/removes the key directly from Settings →
AI Assistant, without needing infrastructure/`.env` access. This is a genuine new feature with real
security implications (a secret now has a database + HTTP-request path it didn't have before), not a
visual tweak — hence a full design pass before any code, per this project's standing two-phase workflow.

## 1. Why this exists

Explicit user request (2026-08-02): asked to connect the system to an API key "through Settings" rather
than by editing `.env` directly. For a commercial multi-clinic SaaS product, this is also the right
direction independent of the immediate ask: an ops-managed `.env` key works for a single self-hosted
deployment, but doesn't fit a future where different clinics might supply their own key (cost
attribution, independent rate limits, a clinic revoking its own key without redeploying the app) — see
§9 for how this stays forward-compatible with that, without building it now.

## 2. Current State (from reading the actual code)

- `clinic_settings` is a "single implicit row" table (`ClinicSetting.php`'s own doc comment) — no
  `clinic_id` yet, by design, matching every other V1-single-org table in this app.
- `AiAssistantService::client()` (line ~548) does exactly one thing: `config('services.anthropic.key')`
  → if blank, throws `AiAssistantUnavailableException`; otherwise constructs the SDK `Client`. Nothing
  else in the codebase reads this key.
- `ClinicSetting` uses the `Auditable` trait — every `create`/`update` is recorded via `AuditObserver` →
  `AuditLogService::record()`, which persists `$model->getChanges()` (the actual new attribute values)
  into `audit_logs.changes` (JSON). **Critical finding**: `AuditLogService` already has a
  `EXCLUDED_KEYS` constant (`password`, `remember_token`, `created_at`, `updated_at`, `deleted_at`) for
  exactly this class of problem — any new sensitive column on an `Auditable` model must be added there,
  or its value would land in the audit trail. This is not a hypothetical risk; it is the actual existing
  mechanism and the actual list I would need to extend.
- `AiAssistantSettingsView.vue` today has exactly two `ToggleSwitch`es (enable, PHI-ack) and calls
  `updateClinicSettings()` with only those two fields — no key field exists anywhere in the UI.
- `ClinicSettingResource` never returns a secret today (only non-sensitive practice info + the two
  booleans) — there is no existing precedent in this resource for a field that must stay write-only.

## 3. Goal

Let an admin (only) set, update, and remove the Anthropic API key from Settings → AI Assistant, with the
key encrypted at rest, never returned by the API after being saved, never written to the audit trail, and
never displayed in full in the UI after saving — while the existing `.env`-based `ANTHROPIC_API_KEY`
continues to work unchanged as a fallback (so nothing breaks for the current deployment if no key is ever
entered in Settings).

## 4. Database Design

Add one nullable column to the existing `clinic_settings` table (no new table — this is clinic-level
configuration, same as the two existing AI toggles):

```php
Schema::table('clinic_settings', function (Blueprint $table) {
    $table->text('ai_assistant_api_key')->nullable()->after('ai_assistant_phi_features_acknowledged');
});
```

`ClinicSetting.php`:
- Add `'ai_assistant_api_key'` to `$fillable`.
- Add `'ai_assistant_api_key' => 'encrypted'` to `$casts` — Laravel's built-in cast, AES-256-CBC via the
  app's own `APP_KEY` (already relied on elsewhere in the framework, e.g. session/cookie encryption; no
  new crypto dependency, no new key-management surface beyond what already exists).
- **`AuditLogService::EXCLUDED_KEYS`** gets `'ai_assistant_api_key'` added — verified this is the exact
  mechanism, confirmed by reading `AuditLogService.php` directly (§2). Without this, the decrypted value
  would be written into `audit_logs.changes` on every save, defeating the encryption-at-rest entirely for
  anyone with audit-log read access.

No `clinic_id` column is added now — matches every other column on this table; if/when multi-tenancy
lands, this rides along with the same migration that adds `clinic_id` to the whole table, not a special
case (see §9).

## 5. API Design

`UpdateClinicSettingRequest`: add
```php
'ai_assistant_api_key' => ['sometimes', 'nullable', 'string', 'max:255'],
```
`sometimes` (matches the existing two AI fields) so the Practice Settings screen's own save never has to
know or resend this field. An explicit `null` clears the key (the "Remove key" action, §7); omitting the
field entirely leaves the stored key untouched (the general "Save" button on this screen must not
silently wipe a previously-set key just because the request didn't include it — this is why `sometimes`,
not `required`/always-sent, matters here specifically).

`ClinicSettingResource`: **never returns the raw key.** Adds two derived, safe fields instead:
```php
'ai_assistant_api_key_configured' => (bool) $this->ai_assistant_api_key,
'ai_assistant_api_key_last4' => $this->ai_assistant_api_key
    ? substr($this->ai_assistant_api_key, -4)
    : null,
```
(`last4` computed from the **decrypted** value at read time — the `encrypted` cast already decrypts on
attribute access, so this is a plain `substr`, not new crypto code.) This mirrors the standard SaaS
pattern (Stripe, GitHub PATs, etc.) of showing "configured, ending in •••1234" rather than the value
itself, so an admin can recognize which key is active without it ever crossing the network again after
the initial save.

`ClinicSettingPolicy`: unchanged — this field lives on the same singleton every other AI Assistant field
already goes through, already admin-gated.

## 6. Service Layer

`AiAssistantService::client()` becomes:
```php
private function client(): Client
{
    $settings = $this->clinicSettingService->current();
    $apiKey = $settings->ai_assistant_api_key ?: config('services.anthropic.key');

    if (blank($apiKey)) {
        throw new AiAssistantUnavailableException;
    }

    return new Client(apiKey: $apiKey);
}
```
**Decision: Settings-stored key wins over the `.env` value when both are present.** Rationale: this is
the one behavior that actually delivers "manage it from Settings" — if `.env` silently won regardless, an
admin who sets a key in Settings would see no effect and no error, a confusing dead end. The existing
`.env` value keeps working exactly as today for any deployment that never touches this new UI (backward
compatible, zero migration burden on existing setups).

No change to `assertFeatureAvailable()` or any of the five feature methods — they already only care
about the enabled/PHI-ack toggles, not the key itself; `client()` is the single chokepoint every one of
them already goes through.

## 7. UI/UX Design (`AiAssistantSettingsView.vue`)

Added inside the existing `v-if="form.ai_assistant_enabled"` block (the key is meaningless if the module
is off), below the PHI-acknowledgment block:

- **Not configured**: a `Password` field (PrimeVue `Password`, `:feedback="false"` — this isn't a
  strength-scored password, just a masked secret input) labeled "Anthropic API Key", placeholder
  `sk-ant-...`, plus its own "Save Key" button (separate from the screen's main Save, so setting the key
  doesn't require also touching the toggles, and vice versa).
- **Configured**: replaces the input with a read-only chip: "API key configured, ending in •••{last4}"
  plus two actions: "Replace" (reveals the same masked input to overwrite) and "Remove" (confirm dialog →
  sends `ai_assistant_api_key: null`).
- The input is **never pre-filled** with any real value (the API never sends one) — "Replace" always
  starts from an empty field, matching the write-only design in §5.
- A small note clarifies: "Falls back to the server's configured key if none is set here" — so an admin
  isn't confused about why AI features already work before ever touching this field (the existing `.env`
  path).

No change to the enable/PHI-ack toggles' existing behavior or copy.

## 8. Security Considerations (the core of this design)

- **Encryption at rest**: Laravel `encrypted` cast (AES-256-CBC, `APP_KEY`-derived) — same guarantee
  level as any other encrypted column in a Laravel app; no new key-management system introduced.
- **Never logged**: `AuditLogService::EXCLUDED_KEYS` extended (§4) — verified this is necessary by
  reading the actual observer code, not assumed.
- **Never re-transmitted**: API resource only ever returns `configured: bool` + `last4` (§5); the true
  value never appears in any HTTP response after the initial save.
- **Transport**: same HTTPS-only assumption already in place for the whole app (login, sessions) — no
  new consideration specific to this field.
- **Access control**: identical to every existing `ClinicSetting` field — admin-only via the existing
  `ClinicSettingPolicy`, not a new gate.
- **Key rotation / `APP_KEY` risk**: if `APP_KEY` is ever rotated without re-encrypting existing encrypted
  columns, this column becomes undecryptable (a `DecryptException` on read) — identical, pre-existing
  risk profile to Laravel's own session/cookie encryption, not a new category of risk this feature
  introduces; worth a one-line callout in `docs/deployment.md` if `APP_KEY` rotation is ever documented
  there, not a blocker here.
- **What this does *not* need**: a separate secrets-manager integration, key versioning, or per-request
  scoping — those are meaningful only at a scale/threat-model this single-key, single-admin-managed field
  doesn't reach yet; noted in §11 as a future option, not built now.

## 9. Multi-Tenant Readiness Check ([[policy_saas_multitenant_readiness]])

The new column rides on `clinic_settings`, which is explicitly documented (`ClinicSetting.php`) as
"clinic-scoped-ready even though nothing reads/writes a `clinic_id` column yet." This field introduces no
new coupling — when multi-tenancy lands, this column moves with the rest of the table under the same
future `clinic_id` migration, giving each clinic its own key naturally (the exact "different clinics
might supply their own key" scenario named in §1) without any rework of this feature's own logic
(`client()`'s fallback chain stays `clinic's own key → env default` unchanged in shape). No shortcut here
would need to be revisited later.

## 10. PWA / Mobile-First Check ([[policy_pwa_mobile_first]])

One `Password` field + two buttons inside the existing responsive Settings `Card` — no new layout pattern,
inherits the same mobile-first behavior the rest of `AiAssistantSettingsView.vue` already has (confirmed
by reading the current file: a single `max-w-2xl` card, already stacks correctly at narrow widths).

## 11. Testing Plan

- **Backend** (`ClinicSettingTest.php` additions + a small new test class): setting a key persists
  encrypted and round-trips through `client()`; `ClinicSettingResource` never exposes the raw value;
  saving/updating/clearing the key produces an `audit_logs` row whose `changes` JSON does **not** contain
  the key value (explicit assertion — this is the one regression that would silently reintroduce the
  exact risk this design exists to avoid); non-admin `403`s on the update endpoint (already covered by
  existing policy tests, re-confirmed for this field).
- **Frontend** (`AiAssistantSettingsView.test.ts` additions): not-configured → configured → replace →
  remove flow; input is never pre-filled with a real value; "Save Key" and the toggles' "Save" are
  independent actions.

## 12. Explicitly Out of Scope

- Per-clinic keys / multi-tenant key management (§9 keeps the door open, doesn't build it).
- A secrets-manager (Vault/AWS Secrets Manager/etc.) integration.
- Key expiry/rotation reminders or usage/cost dashboards.
- Changing anything about the five existing AI Assistant feature methods, the enable/PHI-ack toggles, or
  `AiAssistantGatingTest.php`'s existing coverage — this design touches exactly one method
  (`client()`'s key resolution) and one settings screen's one new field.

## 13. Decision Summary (for approval)

1. New nullable, `encrypted`-cast `ai_assistant_api_key` column on `clinic_settings`.
2. `AuditLogService::EXCLUDED_KEYS` extended — mandatory, not optional, given the existing audit
   mechanism would otherwise capture it in plaintext.
3. Settings-stored key takes precedence over `.env`'s `ANTHROPIC_API_KEY` when both are present; `.env`
   remains a working fallback for existing/ops-managed deployments.
4. API never returns the raw key — only `configured: bool` + `last4`; UI never pre-fills a real value.
5. Admin-only, same policy as every other `ClinicSetting` field — no new permission introduced.
