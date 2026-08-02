# Design System

Status: **Implemented (2026-07-16), revised 2026-08-01** for the Premium Visual Redesign
(`docs/modules/frontend-visual-redesign-design.md`) — the "critical architectural reason" the original
freeze note below anticipated. §§1–7 below are still the baseline; §9 documents what changed and why. Once
that redesign reaches Production Ready, this doc is frozen again on the same terms: don't reintroduce
one-off styles without an equally deliberate reason — extend these tokens instead.

Scope: **Frontend only**, purely visual (typography, color, spacing, radius, elevation, component sizing,
hover/active states). No backend, database, or API changes; no component API changes.

---

## 1. Typography

Status: **Revised 2026-07-17** — Arabic face changed from IBM Plex Sans Arabic to **Alexandria**; both
faces moved from Google Fonts CDN to **self-hosted** files. Latin/Turkish face (Inter) is unchanged, only
its delivery method moved.

| Script | Font | Source |
|---|---|---|
| Latin (`en`, `tr`) | **Inter** | Self-hosted variable-weight `.woff2`, `frontend/src/assets/fonts/` |
| Arabic (`ar`, default locale) | **Alexandria** | Self-hosted variable-weight `.woff2`, same directory |

- **Why Alexandria over IBM Plex Sans Arabic**: IBM Plex Sans Arabic is a solid humanist sans but a generic
  choice with no product-specific identity. Alexandria is a geometric sans built as the Arabic companion to
  Montserrat, rooted in Kufic letterform proportions but redrawn for interface use — it reads as a
  deliberate, modern "Kufi-style" choice per the explicit product request, while staying legible at the
  small/dense sizes this app's data tables need (patient lists, appointment grids). **Noto Kufi Arabic** was
  considered and rejected as the *primary* UI face for the same reason: its own documentation recommends it
  "mainly for texts in larger font sizes" — traditional Kufi geometry trades away some legibility at dense
  table/form sizes, a real risk for a clinical admin app. A single typeface is used for both headings and
  body text (no display/body split), consistent with how Inter is used for Latin.
- **Why self-hosted over Google Fonts CDN (reversing the prior decision)**: explicit user request,
  re-evaluated and adopted — eliminates the external CDN round-trip entirely (0 third-party requests now,
  confirmed via network trace), improves offline/on-prem readiness without waiting for that to become a
  hard requirement, and both fonts turned out to ship as **single variable-weight files** (Google serves an
  identical URL for every requested static weight — the standard signal for a variable-font response), so
  self-hosting costs only 3 files total (~164 KB combined): one Arabic-subset file for Alexandria
  (`alexandria-arabic-var.woff2`, weights 400–700 via its `wght` axis) and two Latin-script subset files for
  Inter (`inter-latin-var.woff2` + `inter-latin-ext-var.woff2`, the latter carrying the Turkish-specific
  glyphs — İ/ı/Ş/ş/Ğ/ğ — outside plain Latin-1). No `@fontsource` npm package was added
  (`PROJECT_CONTEXT.md`'s "never introduce unnecessary packages" instruction) — the `.woff2` files are
  committed directly as static assets and referenced via `@font-face` in `style.css`.
- Font swap is driven by the existing `[dir]`/`lang` mechanism (`src/locales/index.ts`'s `setLocale()`), not
  new JS — `body` uses `--font-sans` (Inter) by default; `[dir='rtl'] body` switches to `--font-arabic`
  (`'Alexandria', 'Inter', system-ui, sans-serif` — Inter remains the fallback for any non-Arabic glyph
  inside RTL text, e.g. Latin digits, exactly as IBM Plex Sans Arabic's stack worked before).
- `index.html`'s initial `<html lang="ar" dir="rtl">` matches the app's default locale, so there's no
  flash-of-wrong-direction/font before Vue mounts; two `rel="preload"` hints (Alexandria + Inter's Latin
  subset — the two faces the default Arabic/RTL first paint needs) replace the old CDN
  `<link rel="preconnect">`/stylesheet pair, keeping first paint at the same or better speed with zero
  external DNS/TLS round-trips.
- `line-height` is 1.5 for Latin, 1.7 for Arabic (Arabic script — diacritics, descenders — needs more
  vertical room at the same font-size to stay comfortably readable).
- **No layout shift**: verified via Playwright screenshot diff against the real dev stack — Dashboard stat
  cards, sidebar nav, and the Appointment Types table sit at pixel-identical positions before/after the
  swap; `font-display: swap` plus the preload hints mean no invisible-text flash either.
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

## 9. Premium Visual Redesign Revisions (2026-08-01, in progress)

Full detail in `docs/modules/frontend-visual-redesign-design.md`. Summary of what this pass changes in the
baseline above:

- **Icon family**: application-authored icons (`config/navigation.ts`, every hand-written `<i class="pi
  pi-*">`) migrate from PrimeIcons to **Lucide** (`lucide-vue-next`), rendered as components
  (`<Users :size="20" />`) instead of icon-font classes. See `frontend/src/config/iconMap.ts` for the
  reviewed one-time mapping of every PrimeIcons class in use to its Lucide equivalent. **PrimeVue's own
  internal component icons** (Dialog close, DataTable sort carets, Dropdown chevrons, etc.) are unchanged —
  out of scope, PrimeVue already themes those consistently via Aura.
- **Motion**: no new custom CSS properties — Tailwind's own built-in duration scale is standardized on
  instead (`duration-150` for button/link hover, `duration-200` for chevron rotation and group
  expand/collapse), rather than inventing parallel `--motion-*` tokens for values Tailwind already provides.
  Every new transition/transform must be reachable by the existing global `prefers-reduced-motion` rule in
  `style.css`; a `:hover` `transform` (e.g. card lift) additionally needs its own `motion-safe:` prefix, since
  a bare hover transform isn't a `transition`/`animation` and so isn't caught by that global rule alone.
- **Sidebar width**: expanded rail `w-72` (288px) → `w-80` (320px). Collapsed rail (`w-[72px]`) unchanged.
- **Color usage**: still 100% PrimeVue semantic/primitive tokens (no hardcoded hex — §2 unchanged), but
  dashboard stat cards and category accents now draw from more of Aura's existing color ramps (`blue`,
  `purple`, `teal`, `orange`) instead of `primary` alone, for the "soft palette" per-card identity requested.
- **Active nav item**: adds a filled `rounded-xl` background spanning the row (not just a border-tint), a
  thicker `border-s-4` accent, `font-semibold`, and `shadow-sm` — evolving §6's existing 3px accent bar, not
  replacing it.
- **Removed**: the sidebar's "Recent Items" (auto-tracked recently-visited records) feature is deleted
  entirely, not merely restyled — a deliberate scope decision in the redesign doc, not a baseline change.
- **Real bug found via browser verification, fixed in `tailwind-base` (not sidebar-scoped)**: raw
  `<ul>`/`<a>` elements had no reset for native list bullets/indent or link color/underline — since this
  project intentionally skips Tailwind's full `preflight.css` (§7), nothing had ever suppressed them. This
  was invisible in Vitest (jsdom doesn't render bullets) and had been present since before this redesign
  (confirmed via screenshot: the sidebar and the header's breadcrumb trail both rendered bullets/
  always-underlined blue links pre-fix). Fixed with the same pattern as the existing button reset — `ul,
  ol { list-style: none; margin: 0; padding: 0; }` and `a { color: inherit; text-decoration: none; }`,
  added to `style.css`'s `tailwind-base` layer so PrimeVue's own `<ul>`/`<a>` usage (a later cascade layer)
  is unaffected. This also fixes a latent bug in `AppHeader.vue`'s breadcrumbs, whose `hover:underline`
  utility was a no-op while links were unconditionally underlined by the browser default.
