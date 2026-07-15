# Coding Standards

## Backend (PHP / Laravel)

- PSR-12, enforced via Laravel Pint (`vendor/bin/pint`).
- Static analysis via PHPStan (Larastan), configured in `backend/phpstan.neon`, currently level 5 — see that file's comments for the reasoning and the plan to raise it over time. Run via `vendor/bin/phpstan analyse`.
- Model `casts()` methods (Laravel 11+ style) need `parseModelCastsMethod: true` in `phpstan.neon` for Larastan to infer cast types (enums, dates) correctly — without it, every cast attribute is seen as its raw string/int DB type, producing false "always false" warnings on legitimate enum comparisons.
- Relations returning a specific related model (`belongsTo`, `hasMany`, etc.) should have a generic PHPDoc, e.g. `@return BelongsTo<User, $this>`, so Larastan can type the relation's properties instead of falling back to the base `Model` class.
- Controllers: thin. Orchestration only — parse request, call one Service method, return a Resource.
- Services: own all business logic. One Service per module (`{Module}Service`), injected via constructor.
- Policies: the only place authorization decisions are made. Form Requests call into them; controllers never inline `$user->role === ...` checks.
- Enums: PHP backed enums for small fixed value sets, cast on the model via `casts()`.
- No unnecessary packages — check for a native Laravel solution first (see `PROJECT_CONTEXT.md` rule 7). Every third-party package addition should be called out explicitly, not silently added to `composer.json`.
- Tests: Feature tests covering the full request/response cycle (including authorization edge cases) for every module. Unit tests where a Service has non-trivial logic worth isolating.

## Frontend (Vue 3 / TypeScript)

- Composition API, `<script setup lang="ts">`.
- Pinia for state, one store per domain concern (`auth`, `ui`, and one per module as needed).
- PrimeVue components for UI primitives, Tailwind for layout/spacing.
- All user-facing strings go through the locale files (`ar`, `en`, `tr`) — no hardcoded UI strings.
- API calls go through the shared `lib/api.ts` axios instance — no ad-hoc `fetch`/`axios` calls in components.

## General

- No duplicated code — if the same logic appears twice, extract it.
- Prefer readability over cleverness; no premature abstraction.
- Every module ships with docs in [modules/](modules/) before it's considered done.
