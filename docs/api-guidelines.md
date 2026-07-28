# API Guidelines

## Shape

- Base path: `/api/*`.
- Single-resource responses are unwrapped: `GET /api/users/{id}` → `{ "id": "uuid", ... }` (via `JsonResource::withoutWrapping()`).
- Paginated collections use Laravel's standard envelope: `{ "data": [...], "links": {...}, "meta": {...} }`.
- No other envelope conventions (no `{success, data}`, no custom wrappers).

## Auth

- Laravel Sanctum, SPA/cookie mode — not bearer tokens.
- Flow: `GET /sanctum/csrf-cookie` → `POST /api/login` (with `X-XSRF-TOKEN`) → subsequent requests ride the session cookie.
- All routes except `/api/ping`, `/sanctum/csrf-cookie`, and `/api/login` require `auth:sanctum`.

## Validation & Authorization

- Every write endpoint has a dedicated `FormRequest` (`Store{X}Request`, `Update{X}Request}`).
- `FormRequest::authorize()` is not a rubber stamp — it delegates to the model's Policy (`$this->user()->can('create', X::class)`), so authorization and validation both fail closed at the same layer.
- Read endpoints that are open to any authenticated role skip the Policy call in the request but the Policy still defines `viewAny`/`view` for consistency and future tightening.

## Errors

Standard Laravel shapes — do not invent custom error envelopes:

- `422 Unprocessable Entity` — `{ "message": "...", "errors": { "field": ["..."] } }`
- `403 Forbidden` — `{ "message": "..." }`
- `401 Unauthorized` — `{ "message": "Unauthenticated." }`
- `404 Not Found` — `{ "message": "..." }`

### `409`/`code`/`overridable` — domain-conflict shape (introduced by Appointments)

Some business-rule failures are a genuine resource *conflict* (two things can't both be true at
once — e.g. a dentist double-booking) rather than a shape problem with the request body, and some
of those conflicts are soft warnings the frontend should let staff explicitly override, not hard
errors. Neither fits the plain `422 { message, errors }` shape above, so domain exceptions that
need this render their own response (a `render(Request $request): JsonResponse` method directly on
the exception class — Laravel's handler auto-invokes it, so no controller `try`/`catch` and no
`bootstrap/app.php` change is needed):

```json
{ "message": "This patient already has an overlapping appointment.", "code": "patient_conflict", "overridable": true, "override_field": "override_patient_conflict" }
```

- `code` — a stable machine-readable identifier the frontend switches on (don't parse `message`).
- `overridable` + `override_field` — present only when the conflict is a soft warning the caller
  can explicitly force past by resubmitting the same request with `{override_field}: true`. Absent
  entirely for hard blocks and other 422 business-rule failures that have no override.
- Status is `409 Conflict` for true resource conflicts (dentist/patient double-booking) and `422
  Unprocessable Entity` for business-rule failures on the request as submitted that aren't a
  conflict between two resources (outside working hours, early no-show, invalid status
  transition) — see `docs/modules/appointments-design-draft.md`'s "API Layer" section for the full
  mapping table and worked example.

## Conventions for New Endpoints

- REST resource routes (`Route::apiResource`) unless the action genuinely isn't CRUD-shaped (e.g. `/api/dashboard/summary`).
- List endpoints accept `?search=` where a text search makes sense, and are always paginated (never return unbounded collections).
  - **Exception: Reports** (`/api/reports/*`) — each response is bounded by the caller's own required date
    range (or, for A/R Aging, a point-in-time snapshot) rather than open-ended, and CSV export
    (`?format=csv`) needs the complete result set in one response. See `docs/modules/reports-design.md` §6.
- Controllers stay thin: parse request → call Service → return Resource. No query building, no business rules in the controller.
