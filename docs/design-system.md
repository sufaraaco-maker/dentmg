# Design System

Status: **Implemented (2026-07-16).** Frozen — the application shell (`docs/modules/layout-architecture.md`)
plus this token set is now the visual foundation for every future module. Don't redesign the shell or
reintroduce one-off styles without a critical architectural reason; extend these tokens instead.

Scope: **Frontend only**, purely visual (typography, color, spacing, radius, elevation, component sizing,
hover/active states). No backend, database, or API changes; no component API changes.

---

## 1. Typography

| Script | Font | Source |
|---|---|---|
| Latin (`en`, `tr`) | **Inter** | Google Fonts, loaded via `<link>` in `index.html` (weights 400/500/600/700) |
| Arabic (`ar`, default locale) | **IBM Plex Sans Arabic** | Google Fonts, same `<link>`, same weights |

- Font swap is driven by the existing `[dir]`/`lang` mechanism (`src/locales/index.ts`'s `setLocale()`), not
  new JS — `body` uses `--font-sans` (Inter) by default; `[dir='rtl'] body` switches to `--font-arabic`.
- `index.html`'s initial `<html lang="ar" dir="rtl">` matches the app's default locale, so there's no
  flash-of-wrong-direction/font before Vue mounts.
- `line-height` is 1.5 for Latin, 1.7 for Arabic (Arabic script — diacritics, descenders — needs more
  vertical room at the same font-size to stay comfortably readable).
- **Why Google Fonts CDN over self-hosted `@fontsource` packages**: explicit user choice, trading the
  self-hosted offline-safety benefit for setup simplicity. If DentalSuite ever needs to run fully offline
  (e.g. an on-prem clinic deployment with no internet), revisit self-hosting at that point — no other code
  changes needed, just swap the `<link>` for local `@font-face` declarations.
- `.tabular-nums` utility class (and applied by default to `.p-datatable-tbody td`) turns on
  `font-variant-numeric: tabular-nums` — used anywhere numbers must align in a column (dates, currency,
  phone numbers, the dashboard stat cards).

## 2. Color

Unchanged from the existing convention — **100% PrimeVue semantic tokens** (`surface-*` scale, `primary`,
`text-*`), never raw Tailwind palette utilities (`bg-blue-500` etc.) or hardcoded hex values in components.
Primary color stays Aura's default **emerald** — already a fitting, calm "clinical/health" tone for a dental
SaaS product; changing brand color was out of scope for this pass.

## 3. Spacing

No new scale — Tailwind's default 4px-step scale, used consistently:

- Page-level wrapper: `flex flex-col gap-4`.
- Page header row (title + primary action): `flex items-center justify-between`.
- Card/section internal spacing: PrimeVue `Card`'s own `body` padding token (`1.25rem`), not overridden
  per-instance.
- Form field stacks: `flex flex-col gap-2` (label + control + inline error).

## 4. Border Radius

Nudged via a PrimeVue preset extension (`main.ts`'s `DentalSuitePreset = definePreset(Aura, { primitive: {
borderRadius: { md: '8px', xl: '16px' } } })`) rather than a Tailwind token, since most radius in the app
comes from PrimeVue component defaults (`Card`, `Dialog`, `Button`, form fields), not raw Tailwind `rounded-*`
utilities:

| Token | Old (Aura default) | New | Used by |
|---|---|---|---|
| `border.radius.md` | 6px | **8px** | Buttons, inputs, selects, list options (`{form.field.border.radius}`) |
| `border.radius.xl` | 12px | **16px** | Card, Dialog (modal) |

`sm`/`lg`/`none` left at Aura defaults. Hand-rolled Tailwind elements (sidebar nav rows) use `rounded-lg`
(Tailwind's own scale, unrelated to the PrimeVue tokens above) — consistent, not tied to the same variable on
purpose, since they're plain Tailwind, not PrimeVue-themed.

## 5. Elevation (Shadows)

- Card/Dialog/Popover/Select overlays already carry a shadow via Aura's own tokens — unchanged.
- Sticky `AppHeader` gets `shadow-sm` (previously just a bottom border) for a touch of depth separating it
  from scrolled content.
- Dashboard stat cards get `hover:shadow-md transition-shadow` — the one deliberate "lift on hover" instance,
  applied directly on that view (not globally on `Card`, since a hover-lift doesn't make sense for e.g. the
  login card or a settings panel).

## 6. Component Sizing / Hover-Active States

- **Sidebar nav rows** (`AppSidebarItem.vue`): active state now carries a 3px inline-start accent bar
  (`border-s-[3px] border-primary`) in addition to the existing background tint + colored text/icon —
  clearer at-a-glance active-state hierarchy, especially in the collapsed icon-only rail where the tint alone
  is subtle.
- **Icon badges**: dashboard stat-card icons moved from a bare colored icon to a circular tinted badge
  (`bg-primary-50 dark:bg-primary-400/10`, `rounded-full`) — the standard "premium SaaS stat tile" pattern.
- Buttons/inputs otherwise unchanged — PrimeVue's own hover/active/focus states (already themed by Aura) are
  used as-is; no per-page overrides.

## 7. Base Reset (found during this pass, not purely stylistic)

`style.css` imports Tailwind v4's `theme.css` + `utilities.css` only — **not** `preflight.css` (Tailwind's
reset), so PrimeVue's own component CSS doesn't have to fight a second, competing reset. This is a
deliberate, pre-existing setup choice, but it left two real gaps for any *raw* (non-PrimeVue) HTML element:

- No `box-sizing: border-box` default — Tailwind's own width/padding utilities assume border-box to compose
  correctly.
- No neutralizing of the browser's native `<button>` chrome (`border: 2px outset`, default background) —
  visible on hand-rolled buttons that don't go through PrimeVue's `<Button>` component, e.g. the sidebar's
  parent-with-children toggle row.

Fixed with a small, explicitly scoped reset inside `@layer tailwind-base` in `style.css` (box-sizing +
button-only reset), **not** a full preflight import — this keeps the fix inside the same cascade-layer
priority Tailwind's own reset would have occupied, so it still loses to PrimeVue's `primevue` layer and to any
Tailwind utility class, exactly as intended. A full preflight audit (checking every other raw-HTML-element
gap: headings, lists, forms, etc.) was not done — nothing else surfaced visually during manual review, but
this is worth another look if a future raw (non-PrimeVue) element shows unexpected native styling.

## 8. Verification Method

Manually reviewed in a real headless-Chromium session (Playwright driving the actual `docker compose` dev
stack) across: Arabic (RTL) / English (LTR) × light / dark × desktop (1440px) / tablet (834px) / mobile
(390px), covering Login, Dashboard, Patients, and Users. `npm run build` (includes `vue-tsc`), `npm run
lint:check`, and `npm run test` (88/88) all pass — see the module's Final Review Report for the full account,
including two incidental bugs found and fixed along the way (a stale-focus-ring glitch after login, and the
generic 404 page missing dark-mode/i18n support that `ForbiddenView.vue` already had).
