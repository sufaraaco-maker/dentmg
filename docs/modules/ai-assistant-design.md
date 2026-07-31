# AI Assistant — Module Design (Approved)

**Status: Done — Production Ready ✅.** CI-confirmed 2026-07-31 on `feature/ai-assistant` (not yet
merged to `main`): Dashboard Insights, Smart Search, Writing Reports (zero-PHI, enabled-eligible) plus
the framework, data model, and feature flags for Clinical Notes draft-assist and Treatment Suggestions
(built but disabled-by-default and absent from the UI until an admin acknowledges a signed BAA). 905/905
backend tests (28 AI-Assistant-specific) + 694/694 frontend Vitest tests green (22 new), `vue-tsc`/
ESLint/Pint/Prettier clean, permanent E2E suite (`frontend/e2e/ai-assistant.spec.ts`) confirmed 34/34
green via the GitHub Actions API (`workflow_dispatch` run `30605056813`). Four earlier CI iterations
surfaced one real backend bug (`LengthAwarePaginator` contract doesn't expose `getCollection()`) and
three real, spec-only E2E bugs (unscoped role query, tight assertion timeouts, a nonexistent
`/dashboard` route) — see `TECH_DEBT.md`'s "AI Assistant module" section for the full account. This is
the final module named in `PROJECT_CONTEXT.md`'s Main Modules list, following Settings (merged to
`main` 2026-07-30 via PR #8).

## Approval & Decisions (2026-07-31)

Design approved as proposed, with the following decisions confirmed and now binding on
implementation (supersedes the tentative "recommend approve" framing in §8, which is kept below for
its reasoning but should be read as **decided**, not pending):

1. **BAA requirement — approved, hard product requirement.** Clinical Notes draft-assist and
   Treatment Suggestions (§4.4/§4.5) must remain completely unavailable to end users unless an
   administrator has explicitly confirmed a signed BAA with Anthropic is in place
   (`ai_assistant_phi_features_acknowledged`, §3/§5 Layer 2). Not a soft default — a hard gate.
2. **V1 build order**: the three zero-PHI features ship first — Dashboard Insights (§4.1), Smart
   Search (§4.2), Writing Reports (§4.3).
3. **PHI features are built, not deferred** — the framework, data model, tool-use plumbing, and
   feature flags for Clinical Notes draft-assist and Treatment Suggestions (§4.4/§4.5) are
   implemented in V1, but ship **disabled by default** behind the BAA gate (decision 1) and must
   not be reachable in any end-user UI (nav, buttons, routes) until the gate is satisfied — not just
   "off in settings" but absent from the experience, same as a feature-flagged route.
4. **Decision support only, no autonomous writes** — confirmed as already designed in §0 Layer 3/§5
   Layer 3: AI never creates, modifies, signs, approves, or persists a clinical or financial record
   on its own. Every suggestion passes through the existing Policy-gated service layer and requires
   explicit user confirmation before any write occurs.
5. **Auditability** — the existing `AiInteractionLog` (§3) plus its `accepted` column satisfies the
   audit requirement (who asked what, what AI returned, whether it was accepted). Additionally,
   wherever AI-generated content is presented in the UI before acceptance (SOAP draft panel §4.4,
   treatment-item checklist §4.5), it must be visually and programmatically distinguishable from
   user-authored content (e.g. a persistent "AI-suggested, unreviewed" label/badge on the field or
   row) until the user has explicitly accepted or edited it — added as an explicit UI requirement in
   §4.4/§4.5 below.
6. **Implementation process**: standard module workflow — Design → Backend → Frontend → Tests → CI →
   Documentation → PR, per [[workflow_two_phase_process]].

## 0. Competitive Research (required before any design, per standing product philosophy)

| Source | Finding | Taken / Rejected for this design |
|---|---|---|
| **Denti.AI**, **VideaHealth**, **Skriber**, **Curve Dental** (AI Scribe) | Ambient ("chairside microphone") AI scribes listen to the clinician–patient conversation and auto-draft SOAP notes — a well-established, real product category, not speculative. VideaHealth reports 90,000+ clinicians on its documentation layer; Curve ships it as a native cloud feature. | **Taken (shape, not audio)**: "draft-assist" for Clinical Notes is a real, demonstrated use case — validates `PROJECT_CONTEXT.md`'s own "Clinical Notes" AI item. **Rejected**: ambient audio capture/transcription. DentalSuite has no microphone/audio pipeline, no speech-to-text infrastructure, and no consent-capture flow for recording a patient encounter — building one is a multi-module effort orthogonal to this one. V1's draft-assist works from **typed input** (a dentist's own shorthand/bullet points), never live audio. |
| **Overjet**, **Pearl**, **VideaHealth** (imaging AI) | The dominant "dental AI" category is FDA-cleared **computer-vision radiograph analysis** — caries/calculus/bone-level detection on X-rays, 91%+ precision on Overjet's own reported figures. This is a completely different technical category (image ML models, regulatory clearance pathway) from an LLM copilot. | **Explicitly rejected as this module's scope** — named here precisely so the boundary is documented, not silently conflated. DentalSuite has no imaging-ML infrastructure, and FDA/CE clearance is out of reach for a single-clinic SaaS product. If diagnostic image analysis is ever wanted, it is a *separate*, ML-infrastructure-heavy module — not "AI Assistant." This module is text/data-only: it reads structured records (chart findings, report aggregates, note drafts) through the Claude API, the same way a human staff member reads a screen — it never processes an X-ray image. |
| **ANSI/ADA Standard No. 1110-1:2025** (first US standard on AI in dentistry); PMC literature on AI treatment-planning liability | Consistent, unambiguous industry consensus: AI in a treatment-planning role is **clinical decision support only** — liability for a diagnostic or treatment error sits with the licensed dentist, not the software; a clinician who signs off on an AI suggestion without independent review risks disciplinary action; documentation must show AI suggestion + practitioner interpretation + the clinical decision made, as a demonstrable record of appropriate use. | **Taken as a hard architectural constraint, not a UI suggestion**: no AI output in this module is ever auto-applied to a patient record. Every AI suggestion (a drafted note section, a proposed treatment plan item, a search result) lands in a **review-and-accept** UI state; accepting it runs through the exact same Policy-gated, `Auditable` service-layer write path a human-entered value would — the AI never gets a privileged write path. This mirrors `PROJECT_CONTEXT.md`'s own existing rule ("Never allow AI to make medical decisions") with an enforcement mechanism, not just a sentence. |
| **Weave AI Receptionist** (formerly TrueLark), **Viva**, **Arini** (AI booking/receptionist chatbots) | A distinct, mature product category: inbound call/chat/SMS triage, booking, and patient-communication automation, integrating bidirectionally with PMS scheduling. | **Explicitly out of scope**, and already correctly deferred by this project's own standing memory (`product_future_vision_ai_layer`: "AI Receptionist" and "AI Booking Agent" are named future-phase items, not current scope, requiring a separate Integration Layer this codebase does not have). This module does not touch inbound patient communication at all. |
| **Sisense AI Assistant**, **Databricks Genie**, **Bold BI** (natural-language BI / "ask your data") | A mature, well-understood pattern: a chat interface translates a plain-language question into a query against existing dashboards/reports and returns a numeric answer or chart — the assistant does not invent new data, it narrates existing aggregates. | **Taken directly** as the shape for "Dashboard Insights": the AI's role is to pick the right existing `ReportService` method/parameters and phrase the numeric result in prose — never to run its own SQL, and never to fabricate a number it wasn't hint. This is also, not incidentally, the **lowest-PHI-risk** feature in this module (see §5) — aggregate totals, not patient-identified rows. |
| **Anthropic / Claude API — HIPAA & BAA requirements** (Anthropic's own published BAA terms; general HIPAA-and-LLM guidance) | Sending PHI (patient name + clinical content, e.g. a note draft or a chart finding) to any third-party API, including an LLM, is a HIPAA violation **unless** a signed Business Associate Agreement (BAA) is in place with that vendor first. Anthropic's BAA covers the Claude API under a "HIPAA-Enabled Organization" configuration — it is a contractual/administrative prerequisite between the clinic (or DentalSuite's operator) and Anthropic, not something this codebase can satisfy by writing code. | **Taken as the single most important open decision in this design** — see §5 and §8 decision 1. This is a legal/business precondition, not an engineering one, and it directly shapes which of the four named features (Clinical Notes, Treatment Suggestions, Smart Search, Dashboard Insights) are safe to ship by default vs. require an explicit admin acknowledgment before enabling. |

**Net effect**: the four features named in `PROJECT_CONTEXT.md` split cleanly into two risk tiers by how
much PHI they need to send to the LLM — this split, not the four feature names themselves, is the
organizing principle of this design (§2/§5).

## 1. Module Goal / Purpose

`PROJECT_CONTEXT.md` names this module's scope explicitly and narrowly:

> AI may help with: Clinical Notes, Treatment Suggestions, Smart Search, Dashboard Insights, Writing
> Reports. Never allow AI to make medical decisions.

This is the last module on the roadmap, by explicit design (per `PROJECT_CONTEXT.md`/`docs/roadmap.md`):
it depends on every other module's data (Patients, Appointments, Dental Chart, Treatment Plans, Clinical
Notes, Billing, Inventory, Laboratory, Reports) already existing and being stable, since an AI assistant
with nothing to read is not useful. `PROJECT_CONTEXT.md` also states plainly: **"Claude API integration
is optional"** — this module must be fully absent-by-default (no `ANTHROPIC_API_KEY` configured =
zero behavior change anywhere else in the app), never a hard dependency.

## 2. Scope (V1)

**In scope**, ordered by PHI-exposure risk, lowest first (§0's "net effect", detailed in §5):

1. **Dashboard Insights** (near-zero PHI): a natural-language question box on the Dashboard
   ("What was our collections rate last month?", "Which dentist had the most no-shows this
   quarter?") that Claude answers by calling into `ReportService`'s existing six report methods
   (§4.1) — never raw SQL, never patient-row detail sent to the LLM, only the `summary` aggregate
   blocks each method already returns.
2. **Smart Search** (structured-filter translation, not raw content): a natural-language patient/
   appointment search box ("patients overdue on payment who haven't been seen in 6 months") that
   Claude translates into calls against existing, already-authorized search/filter endpoints
   (Patients, Appointments, Reports) — the LLM never receives patient PHI as input, only the
   user's own query text, and only receives back what that user's role could already see via the
   normal UI.
3. **Writing Reports** (already-aggregate data, admin-only): an "Explain this report" action on
   each Reports view that asks Claude to turn a report's existing `summary` numbers into a short
   prose narrative for e.g. a practice-owner memo — reuses the same aggregate-only data boundary as
   Dashboard Insights.
4. **Clinical Notes draft-assist** (PHI-bearing — admin-gated, see §5/§8 decision 1): a "Draft with
   AI" action inside a **draft** (never signed) Clinical Note that takes the dentist's own typed
   shorthand and expands it into SOAP-structured prose in the same four fields
   (`subjective`/`objective`/`assessment`/`plan`) — inserted as an editable draft the dentist must
   review and explicitly save through the *existing* `ClinicalNoteService::update()` path (§0's
   review-and-accept constraint). Never available on a signed note (mirrors the existing
   Draft→Signed lock every other edit path already respects).
5. **Treatment Suggestions** (PHI-bearing — admin-gated, see §5/§8 decision 1): a "Suggest items"
   action on a **draft** Treatment Plan that reads the patient's active (non-cancelled,
   non-completed) Dental Chart findings plus the `dental_conditions` procedure catalog, and
   proposes a list of candidate `TreatmentPlanItem` rows (procedure + tooth + rationale) that the
   dentist reviews, edits, and explicitly adds one-by-one through the *existing*
   `TreatmentPlanService` item-creation path — the AI never creates a `TreatmentPlanItem` row
   directly.
6. A dedicated **AI Interaction Log** (its own append-only table, §3) recording every prompt sent
   and response received — not the generic `Auditable` trait (which logs one model's own
   create/update/delete), because a single AI interaction can read across several models at once
   without writing to any of them until the user explicitly accepts a suggestion.
7. A **Settings-module-style admin toggle** ("AI Assistant" section, reusing the Settings screen
   pattern) to enable/disable the feature per-install, plus a separate acknowledgment toggle
   specifically for the two PHI-bearing features (§5/§8 decision 1) — both default **off**. Until
   `ai_assistant_phi_features_acknowledged` is set, the Clinical Notes draft-assist and Treatment
   Suggestions entry points (buttons, panels, routes) must be **absent from the UI entirely** for
   every role, not merely disabled/greyed-out — the framework and flag exist, but end users never
   see a feature they can't use (Approval decision 3, 2026-07-31).
8. Full en/ar/tr i18n, dark mode, RTL, keyboard access, responsive — enterprise UX bar per standing
   philosophy, same as every prior module.

**Explicitly out of scope for V1** (named, not silently dropped — see §9):

- Ambient/audio clinical scribing (no microphone/speech-to-text pipeline exists — §0).
- Diagnostic image analysis of X-rays/photos (a different technology category entirely — §0). The
  existing Imaging module's patient photo/X-ray gallery is untouched by this module.
- AI Receptionist / booking chatbot / any inbound patient communication (explicitly deferred to a
  future Integration Layer per the standing `product_future_vision_ai_layer` direction — §0).
- Any AI action that writes directly to a clinical or financial record without a human
  accept/save step (§0's hard constraint).
- Streaming/real-time chat UI in V1 — request/response only (see §6); a full conversational
  multi-turn assistant UI is a natural V2 extension once the four single-purpose actions above are
  proven useful.
- Fine-tuning, embeddings/vector search, or any persisted model beyond the stock Claude API — V1 is
  prompt + existing-endpoint-as-tool only (§6).

## 3. Data Model

### `AiInteractionLog` (new — audit trail, not a generic `Auditable` model)

A single new table, purpose-built for cross-model AI interactions (a Dashboard Insight read touches
`ReportService`, not any one Eloquent model's own row) — the existing `Auditable` trait doesn't fit
because it hooks a single model's own lifecycle events, not an arbitrary read+external-API-call.

| Column | Type | Notes |
|---|---|---|
| `id` | uuid PK | |
| `user_id` | uuid FK → `users` | Who triggered the interaction. |
| `feature` | string | One of `dashboard_insight`, `smart_search`, `report_narrative`, `clinical_note_draft`, `treatment_suggestion` — an enum-backed column, mirroring every other module's string-backed-enum convention. |
| `patient_id` | uuid FK → `patients`, nullable | Set only for the two patient-scoped, PHI-bearing features; null for Dashboard Insights/Smart Search/report narratives. |
| `prompt_summary` | text | The user's own input text (their typed question or shorthand) — **never** the assembled system prompt or the patient-record content that was interpolated into it, so this table itself never becomes a second copy of PHI beyond what the user directly typed. |
| `response_summary` | text | Claude's response text, or the accepted/edited final version once the user acts on a suggestion (kept in sync by the same request that performs the accept). |
| `accepted` | boolean, nullable | `null` = informational only (Dashboard Insights/Smart Search have nothing to "accept"); `true`/`false` once a Clinical-Notes-draft or Treatment-Suggestion action is reviewed. |
| `model` | string | The exact Claude model ID used (e.g. `claude-opus-5`) — for future migration/audit, mirroring this skill's own model-ID-tracking convention. |
| `created_at` | timestamp | No `updated_at` — append-only, same convention as `ClinicalNoteAddendum`. |

No `SoftDeletes` (a log row is not a real-world record to recover, same exception class as
`BillingSetting`/`ClinicSetting`). `patient_id` being nullable and a plain FK (not a morph) keeps this
table simple; a `feature` value that isn't patient-scoped just leaves it null.

### No changes to any existing table

Every AI feature in V1 reads existing tables (`dental_chart_entries`, `dental_conditions`, invoices/
payments via `ReportService`, `patients`, `appointments`) and, when a suggestion is accepted, writes
through each table's own existing Service (`ClinicalNoteService`, `TreatmentPlanService`) — never a new
write path. This is deliberate: it means the "no AI shortcut around a Policy" constraint (§0) is
structural, not a promise — there is no second write path to audit for a gap.

### `ClinicSetting` extension (reuse, not a new table)

Two additive nullable columns on the existing `clinic_settings` singleton (Settings module,
merged 2026-07-30): `ai_assistant_enabled` (boolean, default `false`) and
`ai_assistant_phi_features_acknowledged` (boolean, default `false`) — the second is the explicit
admin acknowledgment gate for Clinical Notes draft-assist/Treatment Suggestions specifically (§5/§8
decision 1), kept separate from the first so an admin can turn on the zero-PHI features (Dashboard
Insights, Smart Search, report narratives) without also acknowledging the PHI-bearing ones.

## 4. Feature Catalog

### 4.1 Dashboard Insights
**Question answered**: what does this number mean, in plain language, without opening the Reports
screen? A text input on the Dashboard; Claude is given (as tool definitions, §6) the six
`ReportService` method signatures and their parameter shapes, picks one, and the backend calls it
server-side (the LLM never executes code or receives DB credentials) — only the `summary` block
of the result (never `rows`, which contain patient names) is sent back to Claude to phrase as prose.
Available to every role that can already view the underlying report per `ReportPolicy`'s existing
`view-financial-reports`/`view-operational-reports` Gates — a receptionist's Dashboard Insight box
literally cannot ask about Production/Collections/A-R Aging, the same restriction already enforced
today by the Reports module's own Gates, just re-checked here before the tool call runs.

### 4.2 Smart Search
**Question answered**: find patients/appointments matching a plain-language description, without
learning the Patients screen's filter UI. Claude translates the query into parameters for the
existing `PatientService::paginate($search)` (name/phone/national_id/email substring match — no
change to that method) or `AppointmentService`'s existing filters — never a raw SQL string, never
patient PHI as *input* to the model (only the searcher's own typed query text is sent). Results
returned are exactly what `PatientPolicy`/`AppointmentPolicy` already allow that role to see.

### 4.3 Writing Reports (narrative)
**Question answered**: turn a report's numbers into two sentences of prose for a memo. "Explain
this report" button on each Reports view (admin-only for the three financial reports, matching
`ReportPolicy`); sends only the already-computed `summary` block (identical data boundary to §4.1).

### 4.4 Clinical Notes draft-assist (PHI-bearing, admin-gated)
**Question answered**: expand a dentist's own shorthand into SOAP-structured prose. Available only
inside a **draft** `ClinicalNote` (never signed — the existing `ClinicalNoteLockedException` gate
already blocks any edit to a signed note, and this reuses that exact same check). The dentist types
rough notes into a side panel; Claude expands them into the four SOAP fields; the dentist reviews
and edits inline, then saves through the unchanged `ClinicalNoteController::update()` →
`ClinicalNoteService::update()` path — the AI-drafted text is never written to the database until
that explicit save. Requires `ai_assistant_phi_features_acknowledged` (§3) in addition to the
general toggle. Admin+dentist only (mirrors `ClinicalNotePolicy` exactly — receptionist has no
access to Clinical Notes at all today, and this feature doesn't change that). **UI requirement**:
the AI-drafted panel/fields carry a persistent "AI-suggested, unreviewed" label until the dentist
explicitly accepts or edits the content, so AI-authored text is never visually indistinguishable
from the dentist's own typing (Approval decision 5, 2026-07-31).

### 4.5 Treatment Suggestions (PHI-bearing, admin-gated)
**Question answered**: given this patient's chart findings, what treatment plan items might a
dentist consider? Available only inside a **draft** `TreatmentPlan`. Reads the patient's
non-cancelled `DentalChartEntry` rows (finding + tooth + surfaces) and the `dental_conditions`
catalog (procedure names/default pricing, the same catalog `TreatmentPlanItem` already reuses);
Claude proposes a list of `{dental_condition_id, tooth_number, surfaces, rationale}` candidates,
rendered as a checklist the dentist selects from — each accepted row is added through the existing
`TreatmentPlanService`'s item-creation call, one row at a time, exactly as if the dentist had typed
it manually. No `TreatmentPlanItem` is ever created directly by this feature. Requires the same PHI
acknowledgment gate as §4.4. Admin+dentist only (mirrors `TreatmentPlanPolicy`'s existing
create-item gating). **UI requirement**: each candidate row in the checklist is labeled
"AI-suggested" until individually accepted, same distinguishability rule as §4.4 (Approval
decision 5, 2026-07-31).

## 5. Security & Compliance — the central design constraint

This section is unusually load-bearing for this module (more than any prior module's §5), because the
data involved is clinical/financial PHI and the processor is a third-party API, not a local database
query. Three layers, each independently enforced:

**Layer 1 — PHI minimization by feature design (§2's risk-tiering).** Dashboard Insights/Smart
Search/Writing Reports (§4.1–4.3) are designed so that **no patient-identifying clinical content
ever leaves the server as model input** — only aggregate numbers (already-public-within-the-clinic
report totals) or the user's own typed query text. These three features can reasonably ship
enabled-by-default once the general toggle is on, because the HIPAA/BAA question in Layer 2 doesn't
even arise for them — no PHI is transmitted.

**Layer 2 — explicit admin acknowledgment gate for the two PHI-bearing features (§4.4/§4.5).**
Sending a patient's clinical note shorthand or dental chart findings to the Claude API is sending
PHI to a third-party processor. Per Anthropic's own published terms (§0), this requires the
clinic/operator to have a **signed Business Associate Agreement (BAA)** with Anthropic (available
under a HIPAA-Enabled Organization configuration on the Claude API) *before* enabling these two
features — this is a legal/contractual precondition this codebase cannot satisfy by writing code,
only gate behind an honest checkbox. `ai_assistant_phi_features_acknowledged` (§3) is exactly that
checkbox: enabling it in Practice Settings shows explicit copy ("Enabling this sends patient
clinical data to Anthropic's Claude API. Confirm your organization has a Business Associate
Agreement (BAA) with Anthropic covering this use before enabling.") and requires a second
confirmation click — the same "confirm before an irreversible/compliance-relevant action" pattern
this codebase already uses for e.g. delete confirmations, just for a legal precondition instead of a
data-loss one.

**Layer 3 — never a privileged write path (§0/§2's "review and accept" constraint, structurally enforced).**
Every AI-produced value — a drafted SOAP paragraph, a proposed treatment item, a search result — is
rendered in a review UI state and only reaches the database through the *exact same* Policy-gated
Service method a human-typed value would use. There is no `AiClinicalNoteController` or
`AiTreatmentPlanItemController` — the existing controllers are reused unchanged. This means every
existing Policy (`ClinicalNotePolicy`, `TreatmentPlanPolicy`) continues to be the single source of
truth for who can write what; the AI Assistant module adds a new *source* of proposed input, never a
new *path* around authorization.

**Additional practical safeguards:**
- `AiInteractionLog.prompt_summary`/`response_summary` store only the user's own typed text and
  Claude's reply — never the full assembled prompt (which does contain interpolated PHI for the two
  gated features) — so the log itself doesn't become an uncontrolled second PHI store. The full
  request/response body is not persisted anywhere; only what's needed to show "you asked X, AI
  suggested Y, you accepted/edited/rejected it" in a per-patient or per-user activity view.
- No conversation history is retained server-side between requests (§6) — each request is
  self-contained, so there's no session-level PHI accumulation to leak.
- The `ANTHROPIC_API_KEY` is a `config/services.php` entry read from `env()`, following this
  codebase's exact existing third-party-credential convention (Postmark/Resend/AWS SES already do
  this) — never hardcoded, never logged.

## 6. Architecture / Data Flow

```
Frontend (Vue)          Backend (Laravel)                    Claude API
─────────────           ──────────────────                   ──────────
"Ask" input   ───POST──▶ AiAssistantController
                            │
                            ├─ Policy check (per-feature, §4)
                            ├─ AiAssistantService::ask($feature, $input, $actor)
                            │     ├─ builds a feature-specific system prompt
                            │     │  + a small, fixed tool list (§ below)
                            │     ├─ for PHI-gated features: checks
                            │     │  ai_assistant_phi_features_acknowledged first
                            │     └─ calls Anthropic\Client (server-side only —
                            │        the frontend never holds an API key)  ──▶ POST /v1/messages
                            │                                              ◀── tool_use / text
                            ├─ executes the requested tool call locally
                            │  (e.g. ReportService::collections(...)) —
                            │  the LLM never runs its own DB query
                            ├─ (loop once more if Claude asks for another
                            │  tool call, same tool-use pattern)
                            ├─ writes one AiInteractionLog row
                            └─ returns the final text/suggestion to the frontend
◀── suggestion/answer ──────┘
Review UI (accept/edit/
reject) — accepted rows
go through the EXISTING
ClinicalNoteService /
TreatmentPlanService,
unchanged
```

**Model & SDK**: the official `anthropic-php` SDK (this project's stack is PHP/Laravel — see the
`claude-api` skill's PHP reference), called via `client.messages.create()`, never raw HTTP. Default
model: `claude-opus-5` per Anthropic's own current guidance, configurable via `.env`
(`ANTHROPIC_MODEL`) so a future cost/latency retune doesn't require a code change. **Tool use, not
embeddings/RAG**: each feature declares a small, fixed set of tool definitions matching existing
Service methods (`report_production`, `report_collections`, `search_patients`, etc.) with JSON
Schema inputs — Claude picks a tool and the backend executes the *real* PHP method, so there is no
way for a hallucinated number to reach the user: every figure the model narrates was computed by
existing, already-tested application code, not generated by the model itself.

**No streaming in V1** (§2) — `client.messages.create()` synchronous request/response, matching this
module's request/response (not chat-session) UI shape. Response time budget: these are short,
single-turn requests (a report summary, a note draft, a handful of treatment items) — no
long-running agentic loop, no Managed Agents surface (out of scope entirely — that product tier is
for autonomous multi-step agents, not a bounded "expand this shorthand" or "pick a report method"
call).

**Multi-tenant readiness** (standing principle, checked per `policy_saas_multitenant_readiness`):
`AiInteractionLog` has no `clinic_id` column yet (V1 stays single-organization, matching every other
table in this codebase), but it's a plain per-row table with no cross-clinic aggregation logic
baked in — the same "add the column later" path already used for every other table.

**PWA/mobile-first** (standing principle): the Dashboard Insights/Smart Search input boxes and the
Clinical Notes/Treatment Suggestions review panels are simple text-input-plus-card UI, already
proven responsive/touch-friendly by every prior module's forms — no new interaction pattern that
would need special mobile treatment.

## 7. Permissions

| Action | Roles | Precedent |
|---|---|---|
| Dashboard Insights — operational reports | every role | Mirrors `ReportPolicy`'s `view-operational-reports` Gate exactly. |
| Dashboard Insights — financial reports (Production/Collections/A-R Aging) | admin only | Mirrors `ReportPolicy`'s `view-financial-reports` Gate exactly. |
| Smart Search (patients/appointments) | every role | Mirrors `PatientPolicy`/`AppointmentPolicy`'s existing `viewAny` — a receptionist can already search patients today. |
| Writing Reports narrative | same as the report itself | Inherits `ReportPolicy` per report type, unchanged. |
| Clinical Notes draft-assist | admin + dentist | Mirrors `ClinicalNotePolicy::update()` exactly — receptionist has zero Clinical Notes access today (design doc precedent explicitly named in §0), unchanged here. |
| Treatment Suggestions | admin + dentist | Mirrors `TreatmentPlanPolicy`'s item-creation gating — receptionist cannot add treatment plan items today, unchanged here. |
| Enable/disable AI Assistant, PHI acknowledgment toggle | admin only | New — mirrors `ClinicSettingPolicy::update()`'s admin-only gating exactly (Settings module precedent). |
| View own `AiInteractionLog` entries | every role, own entries only | New — self-service, mirrors My Account's "own data only" precedent (Settings module). |
| View any user's `AiInteractionLog` entries | admin only | New — an oversight/audit capability, admin-only like every other cross-user visibility in this codebase. |

No new Policy class per se — `AiAssistantPolicy` exists only to gate the toggle settings
(`ClinicSettingPolicy`-shaped) and the interaction-log visibility; every actual *feature* action
re-checks the **existing** Policy for the underlying resource (Reports/Patients/ClinicalNotes/
TreatmentPlans), so there is exactly one source of truth for "can this role see this data" per
resource, never a duplicated or drifting second copy of that rule inside the AI module.

## 8. Decisions (approved 2026-07-31 — see Approval & Decisions section above)

1. **PHI-bearing features (Clinical Notes draft-assist, Treatment Suggestions) require an explicit
   admin acknowledgment of a Business Associate Agreement with Anthropic before they can be
   enabled; the three aggregate-only features (Dashboard Insights, Smart Search, Writing Reports)
   do not, since no PHI is ever transmitted for them** — **approved** (§5). This is the single most
   consequential decision in this design: it is a legal/compliance precondition this codebase
   cannot verify or enforce beyond an honest checkbox with clear copy, and getting the PHI/no-PHI
   boundary between features right is what makes that checkbox meaningful rather than theatrical.
2. **Tool-use architecture (Claude calls existing Service methods as tools) rather than giving the
   model raw database/query access** — **approved** (§6); this is what makes "the AI never
   hallucinates a number" and "the AI never gets a privileged write path" true by construction
   rather than by prompt instruction alone.
3. **No streaming, no multi-turn conversation UI, no Managed Agents in V1** — **approved** (§2/§6);
   every named use case is a single bounded request, and reaching for a heavier agent surface
   before the simple version is proven useful would be over-engineering per standing project
   philosophy.
4. **A dedicated `AiInteractionLog` table rather than reusing the generic `Auditable` trait** —
   **approved** (§3); `Auditable` is scoped to one model's own lifecycle events, and an AI
   interaction routinely reads across several models before writing to none of them (until
   accepted), which doesn't fit that trait's shape.
5. **Default model `claude-opus-5`, configurable via `.env`** — **approved** (§6); matches
   Anthropic's own current guidance, and the `.env`-configurable model ID means a future model
   migration is a config change, not a code change.
6. **No ambient/audio scribing, no diagnostic imaging AI, no AI receptionist/booking in V1** —
   **approved** (§0/§2); each is a different technology category (audio pipeline,
   computer-vision/regulatory clearance, inbound-communication integration layer) already named as
   future/separate work by this project's own standing direction.
7. **Build order: zero-PHI features (§4.1–4.3) first; PHI-bearing features (§4.4/§4.5) built in
   full but shipped disabled-by-default and absent from the UI until the BAA gate is satisfied** —
   **approved**, per user decisions 2–3, 2026-07-31.
8. **AI-generated content must be visually/programmatically distinguishable from user-authored
   content until explicitly accepted** — **approved**, per user decision 5, 2026-07-31; implemented
   as the UI labeling requirement added to §4.4/§4.5.

## 9. Explicitly Out of Scope for V1 (summary — see §2/§8 for full reasoning)

- Ambient/audio clinical scribing (no microphone/speech-to-text infrastructure).
- Diagnostic image analysis of X-rays/photos (computer-vision category, not an LLM-copilot one).
- AI Receptionist / booking chatbot / inbound patient communication automation.
- Any AI write path that bypasses an existing Policy-gated Service method.
- Streaming responses, multi-turn conversational UI, Managed Agents (autonomous multi-step agent
  sessions) — all deferred until the four bounded, single-purpose actions in §2 are proven useful.
- Fine-tuning, embeddings, vector search, or any persisted model artifact beyond the stock Claude
  API called per-request.
