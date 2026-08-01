# Premium Visual Redesign — Design Document (Pending Approval)

**Status: Design Phase — awaiting explicit approval. No code written yet.**

This document amends and supersedes parts of [`frontend-ux-redesign.md`](./frontend-ux-redesign.md) (the
existing, tracked cross-cutting frontend initiative). Phase 1 of that doc (Navigation Shell — sidebar
grouping, Command Palette, keyboard shortcuts) is already **Production Ready ✅** on `main` (PR #10). This
document:

- **Supersedes** Phase 1's "Recent Items" feature — removes it (decision below, §1).
- **Replaces** Phase 2 ("Dashboard") with a fully specified premium redesign (§6).
- **Adds a new cross-cutting workstream** not previously scoped: **app-wide icon migration**,
  PrimeIcons → Lucide (§4).
- **Pulls forward** pieces of Phase 4 (motion, accessibility, responsive) as mandatory acceptance criteria
  for every step here, rather than a deferred final pass (§9).
- Leaves Phase 3 (Data Tables System) untouched and still not started — out of scope for this pass.
- Requires revising [`docs/design-system.md`](../design-system.md), which is currently marked "Implemented /
  Frozen" — this redesign is the "critical architectural reason" that doc's own freeze note anticipates.

Scope is **frontend-only, presentation-layer only**: no changes to routes, permissions, Pinia store business
logic, API calls, Laravel code, or tests' assertions about *behavior* (tests asserting on now-removed markup,
e.g. Recent Items, will need updating as part of the work, per usual).

## 0. Competitive Research

Extends the research already on file in `frontend-ux-redesign.md` §0 (Linear, Notion, Stripe Dashboard,
Vercel), adding Raycast and modern PrimeVue/Tailwind admin templates per this pass's explicit brief:

- **Raycast**: extremely restrained chrome — no borders where spacing alone can separate regions, active-item
  treatment is a soft full-width rounded highlight (not just a text color change), keyboard-first but never
  at the expense of pointer users. Icon-to-label rhythm is tight and consistent (single icon size across the
  entire list, never mixed sizes).
- **Linear's sidebar** specifically: section labels are barely-there — small caps, low-contrast, generous
  top margin before each group, no visible rule/border between groups (space *is* the separator). Active item
  gets a filled rounded rect that spans the full row width (not just behind the text), plus a colored icon.
- **Stripe Dashboard**: stat cards use a large, legible number with tabular figures, a small muted label
  above or below, and a trend delta as a colored pill (green/red background chip, not just colored text) —
  never a raw sparkline crammed into a small card without labeling.
- **Vercel**: near-zero saturation in structural chrome (grays only), color reserved entirely for
  status/accent (active nav, primary actions, trend badges) — this is the single biggest lever for a
  "premium" feel and the one existing DentalSuite screens already partially violate (info icons/badges in
  several tables use ad hoc color).
- **Modern PrimeVue admin templates (e.g. Sakai, Apollo)**: validate that PrimeVue + Tailwind can hit this bar
  without a framework change — they rely on the same primitives already in this codebase (Aura preset tokens,
  `surface-*` scale) plus disciplined spacing, which is exactly the direction below.

**Takeaway used to justify every decision in this doc**: the "premium" feel the user is asking for is
achieved almost entirely through **restraint + consistent spacing/motion + one well-chosen accent
treatment for active/hover states** — not through decoration. This keeps the redesign compatible with the
existing "100% PrimeVue semantic tokens, no hardcoded hex" convention (`design-system.md` §2), which is
preserved, not relaxed.

## 1. Decisions Already Confirmed With User

1. **Icon migration: full app-wide**, PrimeIcons (`pi pi-*`) → Lucide, across all ~66 `.vue` + 5 `.ts` files
   currently using PrimeIcons (68 distinct icon classes) — not just the redesigned surfaces. See §4 for the
   migration plan and blast-radius mitigation (this is the single biggest risk in this doc, addressed by
   splitting it into its own verified implementation steps, module by module).
2. **"Recently Viewed" (Recent Items) is removed entirely** — component, `sidebarPreferences` store fields
   (`recentItems`, its `router.afterEach` tracker), its dedicated tests, and its section in
   `frontend-ux-redesign.md` §5.1/§11 are deleted, not just hidden. Favorites (a different, separate feature
   — user-chosen pins, not auto-tracked history) are **kept** — nothing in the user's request targeted
   Favorites, and removing it isn't implied by "remove recently viewed."

## 2. Goal / Purpose

Bring every screen's presentation layer to a standard consistent with Linear/Stripe/Notion/Vercel/Raycast —
generous spacing, one consistent icon family, a restrained color palette reserved for state/accent, and a
sidebar/dashboard/header that read as deliberately designed rather than a default admin-template look —
while changing zero business logic, routes, permissions, or API contracts.

## 3. Design Tokens (extends `design-system.md`, does not replace its conventions)

All still expressed as **PrimeVue preset tokens or Tailwind's existing default scale** — never new hardcoded
hex values, per the existing (and preserved) `design-system.md` §2 rule. "Soft color palette" is achieved by
using more of Aura's *existing* primitive color ramps (it already ships `emerald`, `teal`, `blue`, `purple`,
`orange`, `sky`, etc. as full 50–950 ramps) for accent purposes — e.g. dashboard trend badges, category
accent lines — not by introducing a new palette.

| Token area | Current | New |
|---|---|---|
| Sidebar width (expanded) | `w-72` (288px) | `w-80` (320px) — "increase width slightly" per brief |
| Sidebar width (collapsed) | `w-[72px]` | unchanged — brief asks to keep a clean collapsed rail |
| Nav item vertical padding | `py-2` (8px) | `py-2.5` (10px), `py-3` (12px) for top-level items — "comfortable vertical padding" |
| Nav item gap (within group) | `gap-1` (4px) | `gap-1.5` (6px) |
| Gap between groups | `mt-4` (16px) | `mt-6` (24px) — "even larger spacing between groups" |
| Icon size (nav/header) | `text-base` (16px, PrimeIcons) | Lucide `:size="20"` (sidebar), `:size="18"` (header/inline) — one consistent scale, up from the current mixed 14–16px |
| Active nav item | `border-s-[3px] bg-primary-50` | rounded full-row highlight (`rounded-xl`), soft mint background (`bg-primary-50 dark:bg-primary-400/10`), colored left accent bar kept and thickened (`border-s-4`), `font-semibold`, `shadow-sm`, icon color `text-primary` at full opacity vs. body text |
| Section headers | `text-xs uppercase tracking-wide text-surface-400`, `pb-1` | same type scale (kept — already matches Linear's "barely-there" pattern) but `pt-1 pb-2` and a `1px` bottom accent line (`border-b border-surface-100 dark:border-surface-800`) per the brief's "thin accent line" option |
| Card radius | `xl` = 16px (existing) | unchanged, already premium-appropriate |
| Card shadow | `hover:shadow-md` (dashboard only) | extended to a resting `shadow-sm` + `hover:shadow-md hover:-translate-y-0.5` (subtle lift), gated by `motion-safe:` so `prefers-reduced-motion` users get the shadow change without the transform |
| Motion duration | ad hoc (`duration-200` in a few places) | standardized on Tailwind's **existing** built-in scale — `duration-200` (chevrons, group collapse) / `duration-150` (button/link hover) — consistently, rather than adding new `--motion-*` custom properties for values Tailwind already provides (revised during Step 1: no new token needed, just consistent usage — see `design-system.md` §9) |

## 4. Icon Migration — PrimeIcons → Lucide (app-wide)

**Package**: `lucide-vue-next` (official Vue bindings, tree-shakeable per-icon ES exports — importing 68
distinct icons costs only those 68 icons in the bundle, not the full set).

**Why full migration, given the risk**: the user confirmed this explicitly after being shown the
alternative (partial migration). Mitigation for the blast radius:

- **No big-bang PR.** Migration is split into its own implementation steps *by module* (§8), each with its
  own build/lint/test/visual-verify checkpoint — consistent with this project's standing "no chained steps
  without a checkpoint" rule.
- **A mapping table is produced first** (one-time, in `frontend/src/config/iconMap.ts` or inline per-file —
  decided at implementation time) covering all 68 current `pi pi-*` classes → their nearest Lucide
  equivalent, reviewed once rather than re-decided per file. Where PrimeIcons has no clean 1:1 Lucide
  equivalent (a handful of dental-domain-adjacent icons like `pi-sitemap` for Dental Chart), the closest
  semantic match is chosen and noted in the mapping file's comments, not silently guessed per occurrence.
- **PrimeVue's own internal component icons** (`Dialog`'s close X, `DataTable`'s sort carets, `Dropdown`'s
  chevron, `Checkbox`/`RadioButton` marks, etc.) are **out of scope** — those are rendered by PrimeVue
  internals, not the app's own `pi pi-*` usages, and PrimeVue's Aura preset already themes them consistently.
  Only *application-authored* icon usage (the 66+5 files found) is migrated. Overriding PrimeVue's internal
  icon set app-wide is a materially bigger, separate undertaking (every component's icon slot) and was not
  part of the ask — flagged here so it's an explicit, visible exclusion rather than a silent gap.
- **Transitional coexistence is expected and fine**: for the duration of the multi-step rollout, some files
  use Lucide and some still use PrimeIcons — this is a temporary, tracked state (a TECH_DEBT.md entry marks
  it "in progress" until the last step lands), not a shipped inconsistency.
- **Usage pattern**: Lucide components render directly (`<UserIcon :size="20" />`), replacing the current
  `<i class="pi pi-users" />` pattern. Color/opacity is still controlled via Tailwind text-color utilities
  (Lucide icons inherit `currentColor` by default, same mental model as PrimeIcons' font-icon approach) — no
  new styling mechanism, just a different element.

## 5. Sidebar Redesign (`AppSidebar.vue` / `AppSidebarItem.vue`)

- Remove any residual list/tree-view affordances — confirmed in current code there are no visible bullets
  today (it's already a flex `<ul>` with no `list-style`), but the brief's intent (no tree-view feel) is
  matched by: no vertical connector lines for nested children, indentation via padding only, and a
  first-load-collapsed state for **all** non-active groups (currently only auto-expands the group containing
  the active route) — reduces initial visual density, closer to Linear's default-collapsed groups.
- Category header labels: keep the *existing* `NAV_SECTIONS` keys (`clinical`, `operations`, `insights`,
  `admin`) and their i18n structure (`nav.sections.*`, already localized in `ar`/`en`/`tr`) — only their
  *displayed* copy changes, to content-accurate versions of the brief's examples: **Clinical Care**,
  **Operations**, **Insights**, **Administration** (the brief's own "Scheduling" example doesn't map to any
  current section's actual contents — Appointments/Dental Chart/Treatment Plans are grouped under `clinical`,
  not a separate scheduling group — so the label is chosen for accuracy over literal brief wording; flagged
  here rather than silently substituted).
- Expand/collapse chevron: `transition-transform duration-200`, already present — kept, extended to also
  fade/height-animate the revealed `<ul>` (currently an abrupt `v-if`) via a Vue `<Transition>` with
  `grid-template-rows: 0fr → 1fr` (avoids animating to `height: auto`, which CSS can't transition natively).
- Remove the "Recent Items" block entirely (§1.2): delete its template block (`AppSidebar.vue` lines ~117–138),
  its `recentItems` state/`toggleSection`-adjacent logic in `stores/sidebarPreferences.ts`, the
  `router.afterEach` tracker that populates it, `nav.recent` i18n keys, and its dedicated test file/cases.
  Favorites logic is untouched.
- Active state per §3's token table — the highlight box, not just the text/icon color change.
- Icon size bump to Lucide `:size="20"` (top-level) / `:size="18"` (nested children, depth > 0) — currently
  both render at the same `text-base`.

## 6. Dashboard Redesign (`DashboardView.vue`)

Replaces `frontend-ux-redesign.md` Phase 2 in full (that phase was "Not started," so this is a clean
replacement, not a conflicting change):

- **Stat cards**: larger icon badge (`h-12 w-12` vs current `h-11 w-11`, using a distinct soft-tinted ramp
  per card — e.g. patients=`primary`, appointments=`blue`, revenue=`purple` — instead of all three using the
  same `primary-50` tint, giving each card its own identity per the brief's "soft mint/teal/blue/purple"
  palette request), bigger number (`text-2xl` vs `text-xl`), a trend badge (colored pill, `+/-N%` vs. prior
  period) **only where the existing `/dashboard/summary` endpoint or a same-shape historical call already
  supports it** — no new backend endpoint (per the standing frontend-only framing carried over from
  `frontend-ux-redesign.md` §10). If the current endpoint doesn't return enough history for a real trend,
  the card ships without a trend badge rather than a fabricated one — confirmed as the same rule
  `frontend-ux-redesign.md` §6 already set for this exact situation.
- **Empty states**: today's `Message severity="error"` on load failure, and each widget's own ad hoc "no
  data" text, are replaced by a small shared `EmptyState.vue` (icon + message + optional action button) —
  pulled forward from Phase 3's plan since Phase 2/redesign needs it now; Phase 3 (Data Tables) reuses the
  same component later rather than duplicating it.
- **AI Assistant card** (`AiQuestionBox.vue`): soft gradient background (two-stop, both PrimeVue token-based
  — e.g. `from-primary-50 to-purple-50 dark:from-primary-400/10 dark:to-purple-400/10`, not a raw CSS
  gradient with hex stops), rounded-xl, larger icon, more internal padding — visually distinct from the plain
  `Card` stat tiles above it, signaling "this one's different/smart" the way the brief asks.
- **Grid spacing**: `gap-4` → `gap-6` throughout, matching the "increase spacing throughout the dashboard
  grid" instruction; also fixes the pre-existing 2-of-3-column widget row gap already logged in
  `frontend-ux-redesign.md` §2's audit.

## 7. Top Navigation Bar (`AppHeader.vue`)

- User avatar: `h-8 w-8` → `h-10 w-10`, initials text bumped to match.
- Locale `Select`: restyled only (PrimeVue component, no structural change) — wider touch target, consistent
  radius with the new token set.
- Global search entry point (already exists, opens Command Palette): rounded-full or `rounded-xl` (matching
  new radius scale), refined placeholder copy, `kbd` shortcut badge restyled with the new shadow/radius
  tokens — functionally unchanged (still opens `CommandPalette.vue` on click, same `Ctrl+K`/`Cmd+K`).
- Notification bell, dark-mode toggle: restyled only, still functionally inert on notifications (unchanged
  scope, per `frontend-ux-redesign.md` §10 — a real notification backend is a separate, already-tracked
  item in `TECH_DEBT.md`).

## 8. Implementation Sequence (each step: implement → real browser verification → report → wait for approval)

Per this project's standing rule, steps are not chained. Order chosen so risk (icon migration) is validated
early on a small surface before the mechanical rollout:

1. **Foundation**: install `lucide-vue-next`; add the `@theme` motion tokens; build the icon mapping
   reference; revise `design-system.md` (un-freeze, document the token changes in §3); update
   `frontend-ux-redesign.md`'s status header/phase table to point at this doc. No visible UI change yet
   beyond a dependency addition — smallest possible first step to confirm setup is clean.
2. **Sidebar** (`AppSidebar.vue`, `AppSidebarItem.vue`, `sidebarPreferences` store): full redesign per §5,
   including Recent Items removal and the Lucide icon swap for every icon the sidebar itself renders
   (nav icons via `config/navigation.ts` — this file's `icon` field changes shape from a PrimeIcons class
   string to a Lucide component reference, a typed change, not a string convention).
3. **Header** (`AppHeader.vue`) + Command Palette icon usages: §7 restyle + Lucide swap.
4. **Dashboard** (`DashboardView.vue`, `EmptyState.vue` new shared component, `AiQuestionBox.vue` restyle):
   §6 in full.
5. **App-wide icon migration, remaining files, module by module** (Patients → Appointments → Dental Chart →
   Treatment Plans → Billing/Invoices → Payments → Clinical Notes → Inventory → Laboratory → Imaging →
   Reports → Settings → Users/Auth) — each module is its own checkpoint given ~5–8 files per module; this is
   the longest step in wall-clock terms but the lowest-risk per individual change (pure icon swap, no layout
   change).
6. **Cross-cutting verification pass**: RTL (ar) + LTR (en/tr) × light/dark × desktop/tablet/mobile on every
   touched surface; `prefers-reduced-motion` check on the new transitions; full keyboard walkthrough of
   sidebar → header → command palette; remove the transitional TECH_DEBT.md entry from §4.
7. **Final Review Report** (per standing workflow): full test suite, PHPStan N/A (frontend-only), `vue-tsc`,
   ESLint/Prettier, Vitest, a fresh Playwright pass including the existing
   `frontend/e2e/frontend-nav-shell.spec.ts` (will need updates for removed Recent Items assertions and any
   selector changes from the icon swap) — verdict on Production Readiness.

## 9. Non-Functional Requirements

- **RTL**: every new spacing/border/icon-position utility uses logical properties (`ps-`/`pe-`/`border-s-`,
  already this codebase's convention) — no new `ml-`/`mr-`/`text-left` introduced. Lucide icons are
  direction-agnostic by default except a few (chevrons, arrows) which need explicit `rtl:rotate-180` exactly
  as the current PrimeIcons chevrons already do (pattern carries over 1:1).
- **Accessibility**: active-state redesign must keep (not just visually imply) `aria-current="page"` on the
  active link; section header collapse keeps `aria-expanded`; the new hover-lift/shadow transform is wrapped
  in `motion-safe:` so `prefers-reduced-motion: reduce` users get zero transform (already-global CSS rule in
  `style.css` §"reduced-motion" catches transitions/animations, but a CSS `transform` on `:hover` outside a
  transition isn't caught by that rule alone — confirmed as a real gap to fix here, not an assumption).
- **Multi-tenant readiness** ([[policy_saas_multitenant_readiness]]): purely presentational change, no
  schema/query/service change — nothing here narrows future multi-tenant work. Confirmed, no action needed.
- **PWA / mobile-first** ([[policy_pwa_mobile_first]]): sidebar drawer (mobile) variant reuses the same
  `AppSidebarItem.vue` component being redesigned, so mobile gets the same spacing/active-state/icon
  treatment automatically — verified explicitly in step 2's browser check at the 390px breakpoint, not
  assumed from desktop-only testing.
- **Performance**: `lucide-vue-next` tree-shakes to only the ~68 icons actually imported; PrimeIcons' CSS
  (`primeicons.css`, imported wholesale today) is only removed from `style.css` in the final step once no
  file references `pi pi-*` anymore — checked via a repo-wide grep gate before removing the import, not
  assumed complete.

## 10. Trade-offs / Risks

- **Icon migration is the largest risk in this doc** by file count (71 files) even though each individual
  change is low-complexity. Mitigated by per-module steps with checkpoints (§8.5) rather than one pass.
- **Transitional two-icon-system state** during rollout is visually acceptable (different modules, not mixed
  within one screen at any point since migration is module-by-module) but should not be left mid-way if work
  is paused — flagged in TECH_DEBT.md so it's never mistaken for "done."
- **Existing E2E suite (`frontend-nav-shell.spec.ts`) will need edits**, not just re-runs, since it currently
  asserts Recent Items behavior that this doc removes — called out explicitly rather than discovered as a
  surprise CI failure later.
- **"Content-accurate" category labels vs. the brief's literal examples** (§5) is a judgment call flagged for
  visibility, not hidden — easy to override at approval time if the user prefers the literal brief wording
  even where it doesn't match current grouping.

## 11. Future Improvements (not in this pass)

- Phase 3 (Data Tables System) and the remainder of Phase 4 (full WCAG audit, global shortcuts help overlay)
  from `frontend-ux-redesign.md` remain queued, unaffected by this doc except that `EmptyState.vue` (built
  here, §6) is now available for Phase 3 to reuse rather than rebuild.
- Overriding PrimeVue's own internal component icons (Dialog close, DataTable sort arrows, etc.) to Lucide,
  if full visual consistency down to that level is ever wanted — explicitly out of scope here (§4).
