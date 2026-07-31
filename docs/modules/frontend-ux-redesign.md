# Frontend UX & Navigation Redesign — Design Document (Awaiting Approval)

**Status: Design drafted 2026-07-31, awaiting user approval before implementation begins.** This is
not a new business module — it is a cross-cutting initiative across the entire existing frontend,
started once every module on the original roadmap (`PROJECT_CONTEXT.md`'s Main Modules list) reached
Production Ready ✅. Explicit user framing: focus entirely on **Frontend** quality — navigation,
polish, performance, accessibility — not new backend features. Four architecture forks were already
resolved with the user before this doc was written (see §3).

## 0. Competitive Research

The user named the benchmark explicitly: Linear, Notion, Stripe (Dashboard), Vercel — not dental-EMR
competitors this time (contrast with the standing [[product_philosophy_competitive_benchmark]]
directive, which targets Open Dental/Dentrix/CareStack for *feature* parity; this initiative targets
general modern-SaaS *interaction* quality instead). Patterns common to all four that are directly
actionable here, based on well-established, publicly documented conventions of these products:

- **Command palette as a first-class citizen** (`Ctrl+K`/`Cmd+K`), not a hidden power-user feature —
  discoverable via a visible search affordance in the header that hints at the shortcut.
- **Collapsible, icon-first sidebar** with a persistent collapse toggle, section grouping, and a
  clear distinction between "pinned/favorite" and "browse all" navigation.
- **Dense, keyboard-navigable data tables** — every list is arrow-key/`Enter` navigable, bulk
  selection has a persistent contextual toolbar (not a modal), and column state (width, order,
  visibility) persists per-user.
- **Skeleton screens over spinners** — layout-shape-matching placeholders, never a centered spinner
  for anything above ~150ms.
- **Restrained motion** — 120–200ms ease-out transitions on state changes (hover, expand/collapse,
  route transitions), no decorative animation; motion communicates state, not delight-for-its-own-sake.
- **Breadcrumbs + page-level actions in the header**, not buried in page body — the header is a
  consistent "where am I / what can I do here" anchor across every screen.
- **High information density with generous line-height** — small type (13–14px body) but never
  cramped, achieved via consistent 4px/8px spacing rhythm rather than large padding.

These principles inform every phase below; they are not a separate deliverable.

## 1. Goal / Purpose

Bring DentalSuite's navigation shell, dashboard, and data-table experience to a standard consistent
with modern SaaS products, without:
- Regressing any of the 16 Production-Ready modules already built on the current shell.
- Introducing new backend scope (per explicit user direction — this is a frontend-only initiative).
- Sacrificing the performance, accessibility (WCAG AA), RTL/i18n (ar/en/tr), and dark-mode support
  already correctly implemented in the current shell (see §2 — a lot of this is *already right* and
  must be preserved, not rebuilt).

## 2. Current State Audit (baseline — see full detail in session research, summarized here)

What already exists and works, confirmed by reading the actual code (not assumed):
- `AppSidebar.vue`/`AppSidebarItem.vue`: role-filtered nav from a typed config (`config/navigation.ts`),
  working desktop collapse/expand (persisted), mobile `Drawer`, RTL-safe active-state styling
  (logical `border-s`), auto-expand-on-active-child. **Gap**: one nesting level only, no
  favorites/recents, no section grouping beyond flat top-level items, Billing shows as `comingSoon`
  despite having working routes underneath it (a real pre-existing inconsistency to fix as part of
  this pass, not a new bug).
- `AppHeader.vue`: hamburger (mobile), notification bell (**inert — always "No notifications yet"**,
  already tracked in `TECH_DEBT.md`), locale switch, dark-mode toggle, avatar menu. **Gap**: no
  breadcrumbs, no global search/command palette, notification bell has no real backend to connect to
  (stays inert — building a real notification system is out of scope, see §10).
- Theming: Tailwind v4 CSS-first + `tailwindcss-primeui` bridging PrimeVue's Aura preset; dark mode via
  a `.dark` class + `useUiStore`, fully wired; RTL via a real `dir` attribute switch + logical Tailwind
  utilities throughout + a dedicated Arabic variable font. **This is a strong foundation — the redesign
  extends this token system, it does not replace it** (§3 decision: evolve Aura, not a bespoke system).
- Tables: ~20 list views, all hand-rolled directly against PrimeVue `DataTable`, with a copy-pasted
  300ms debounce-search pattern and no shared wrapper. No sticky headers, no column resize/reorder, no
  bulk actions, no shared empty-state component. This is the single highest-leverage, highest-blast-
  radius piece of the whole initiative (§6).
- Dashboard: stat cards + two widget rows + `AiQuestionBox`, no charting library installed, no
  dedicated Pinia store.
- Keyboard shortcuts: exactly one working scoped system today, on the Appointments calendar board
  (`useCalendarKeyboardShortcuts.ts`) — proves the guard patterns needed (ignore when typing, ignore
  when a dialog is open) already exist and should be generalized, not reinvented.
- No `@vueuse/core`, no animation library, no command-palette library, no headless table library, no
  chart library are currently installed.

## 3. Architecture Decisions (confirmed with user, 2026-07-31)

1. **Add `@vueuse/core`.** Small, tree-shakeable, the de facto standard Vue composable utility
   library. Used for `useMagicKeys`/`onKeyStroke` (shortcuts + command palette), `onClickOutside`
   (dropdowns/palette), `useLocalStorage` (sidebar prefs, favorites/recents, table column state),
   `useMediaQuery` (responsive logic instead of ad hoc `window.innerWidth` checks). The command
   palette UI itself is hand-built on top (no `cmdk`-equivalent Vue library is mature enough to adopt
   blindly) — it's a `Dialog` + filtered list, well within scope to build directly.
2. **Build a shared `AppDataTable.vue` wrapper around PrimeVue's own `DataTable`.** PrimeVue already
   supports `resizableColumns`, `reorderableColumns`, and a `v-model:selection` bulk-selection model
   natively — the gap is that no view uses them and every view reimplements pagination/search/empty-
   state/loading by hand. One wrapper component absorbs all of that once; existing views migrate to it
   incrementally (§6). No new table library.
3. **Favorites & Recent Items persist to `localStorage` only**, via `@vueuse/core`'s
   `useLocalStorage`, scoped per browser — consistent with the "frontend-only" framing of this
   initiative. Explicitly **not** synced across devices/sessions; if that's wanted later, it's a small,
   separate backend addition (a `user_preferences` JSON column or similar), not part of this pass.
4. **Evolve the existing PrimeVue Aura theme/token layer**, not a bespoke design system. Concretely:
   tighten the `@theme` block in `style.css` (currently only fonts) to add an explicit spacing/motion
   scale used consistently by the new components; extend the existing `definePreset` radius override
   with matching shadow/elevation tokens for cards and popovers. Lower risk, faster, and keeps the
   ~130 already-styled `.vue` files visually consistent with the new pieces instead of half-migrated.
5. **Dashboard charts — confirmed, add `chart.js` + `vue-chartjs`** (~200KB combined, gzip ~60KB, the
   most widely used and lightest full-featured option; no dependency on a heavier ecosystem like D3).
   Scope stays to simple sparklines/bar/line cards on the Dashboard and optionally the Reports views —
   not a general charting platform.

## 4. Phased Scope & Sequencing

Per this project's standing "one module at a time" development strategy, this initiative is split into
four sequential phases, each run through the full standard workflow (Design → Implementation → Tests →
CI → Documentation → PR) independently, so `main` stays releasable between phases and each phase gets
its own CI-confirmed Production Ready checkpoint rather than one enormous PR:

| Phase | Scope | Depends on |
|---|---|---|
| **1. Navigation Shell** | Sidebar redesign (grouping, favorites, recents, deeper nesting), Header redesign (breadcrumbs, global search entry point), Command Palette (`Ctrl+K`), app-wide keyboard shortcuts foundation | `@vueuse/core` addition |
| **2. Dashboard** | Smart stat cards, quick actions, chart/indicator cards, layout refresh | Phase 1 (header/shortcuts patterns reused), `chart.js`+`vue-chartjs` |
| **3. Data Tables System** | `AppDataTable.vue` shared wrapper (sticky header, column resize/reorder + persisted state, bulk actions, shared `EmptyState.vue`/skeleton loading), rollout across all ~20 list views | Phase 1 (bulk-action toolbar reuses shortcut/animation primitives) |
| **4. Cross-cutting polish** | Motion/micro-interaction audit, full responsive audit (desktop/tablet/mobile) on every screen touched, WCAG AA accessibility pass (focus order, ARIA, contrast), keyboard-shortcut help overlay covering all of Phases 1–3 | Phases 1–3 complete |

Each phase below is specified to the depth needed for implementation; exact component-by-component
detail for later phases may be refined slightly at that phase's own kickoff if Phase 1/2 reveal better
patterns — normal for a project run one module at a time.

## 5. Phase 1 — Navigation Shell

### 5.1 Sidebar

- **Section grouping**: extend `config/navigation.ts`'s `NavItem[]` with an optional `section` key
  (e.g. `"clinical"`, `"operations"`, `"admin"`) rendered as a collapsible group header with a
  chevron, state persisted per-user via `useLocalStorage`. Existing flat items default to an
  "unsectioned" top group so no route/behavior regresses.
- **Favorites**: a star toggle on hover/focus for any leaf nav item (not sections); favorited items
  render pinned at the top of the sidebar above the grouped sections, in a dedicated "Favorites"
  block that only renders when non-empty. Stored as an array of route names in `localStorage`.
- **Recent Items**: last 5 visited *record* pages (patient detail, invoice detail, treatment plan,
  etc. — not list pages), tracked via a `router.afterEach` hook, shown in a "Recent" block. Each entry
  stores `{ routeName, params, label, icon, timestamp }`; label resolved from the record's own display
  name where cheaply available (e.g. patient name already in the route's loaded data) — falling back
  to a generic label rather than an extra API call if not.
- **Deeper nesting**: generalize `AppSidebarItem.vue` to recurse (currently hard-limited to one level,
  a known, deliberate gap per `TECH_DEBT.md`) — needed for the section-grouping structure above even
  if no *route* nesting goes beyond 2 levels in V1.
- **Fix the Billing `comingSoon` inconsistency** found during the audit (§2) — Billing has working
  routes; the sidebar entry should point at a real index, not stay a placeholder. **Confirmed**: build
  a minimal clinic-wide Invoice index/list view (over the existing `Invoice` model/API — no new
  backend) as part of Phase 1, and point the sidebar's Billing entry at it instead of `comingSoon`.

### 5.2 Header

- **Breadcrumbs**: derived from `route.meta` (add an optional `breadcrumb` field, falling back to the
  matched nav item's label) — rendered left-aligned, replacing the current bare mobile-only title.
- **Global search entry point**: a visible, always-present search input/button in the header
  (`⌘K`/`Ctrl+K` hint shown as a kbd-styled badge) that opens the Command Palette — the discoverable
  affordance the competitive research calls out, not a hidden shortcut only.
- Notification bell, locale switch, theme toggle, avatar menu are kept as-is structurally (still
  inert notifications — real backend notifications are out of scope, §10) but restyled to match the
  refreshed token set.

### 5.3 Command Palette (`Ctrl+K` / `Cmd+K`)

- Global `Dialog`-based overlay, opened via the header button or the shortcut (`useMagicKeys`),
  closed on `Escape`/backdrop click/selection.
- Fuzzy-filters a static, role-filtered action list built from the same `config/navigation.ts` (every
  reachable route becomes a "Go to X" entry) plus a small set of hand-registered quick actions ("New
  Patient", "New Appointment" — reusing the exact dialogs the Dashboard/list views already open, not
  new logic).
- Arrow-key navigation + `Enter` to select, matching the "keyboard-navigable" competitive pattern.
- Explicitly **not** a global data search (e.g. searching patient names) in Phase 1 — that would need
  a new debounced backend query and cross-cuts into scope the user asked to keep frontend-only for
  this pass. Flagged as a natural Phase 5 candidate if wanted later, not committed here.

### 5.4 App-wide keyboard shortcuts foundation

- Generalize the guard logic already proven in `useCalendarKeyboardShortcuts.ts` (ignore while typing,
  ignore while a dialog is open) into a shared composable (`useAppShortcuts.ts`), registering:
  `Ctrl+K` (command palette), `?` (shortcuts help — see Phase 4), `G` then `D`/`P`/`A` etc.
  (go-to-section chords, Linear-style) reusing the same nav config.
- The existing calendar-specific shortcuts are untouched — this is additive, app-level, and must not
  conflict with any single-view shortcut already registered (guarded by the "ignore if a dialog is
  open" rule, same as today).

## 6. Phase 2 — Dashboard

- Restyle the existing 3 stat cards with the refreshed token set (§3.4) and add 1–2 sparkline/trend
  indicators per card via `chart.js`/`vue-chartjs` (e.g. a 7-day patient-count trend) where the
  `dashboard/summary` endpoint already returns enough history, or a simple up/down delta badge where
  it doesn't (no new backend endpoint — use what exists; if meaningful history isn't available from
  current data, ship the delta-badge version, not a fabricated chart).
- Add a **Quick Actions** row (New Patient, New Appointment, New Invoice, New Lab Case — reusing
  existing dialogs) as first-class buttons, not buried in a menu.
- Keep `TodayScheduleWidget`/`UpcomingAppointmentsWidget`/`LowStockWidget`/`DueLabCasesWidget`/
  `AiQuestionBox` — restyle to the new token set, fix the current 2-of-3-column dashboard-grid gap
  noted in the audit (§2) so the widget grid fills evenly.

## 7. Phase 3 — Data Tables System

- `AppDataTable.vue`: a single wrapper component absorbing the repeated pattern (server-paginated
  lazy fetch, 300ms debounced search, loading state, `#empty` slot) plus **new** capabilities PrimeVue
  already supports but no view currently uses: `resizableColumns`, `reorderableColumns` (state
  persisted to `localStorage` per table via `useLocalStorage`, keyed by route name), sticky header
  (PrimeVue's `scrollable`/`scrollHeight` props), and `v-model:selection` wired to a persistent bulk-
  action toolbar (appears above the table when ≥1 row selected, not a modal).
- **Bulk actions scope**: only enabled where a safe, already-existing single-item backend action can
  be looped client-side without a new bulk endpoint (e.g. bulk "mark as read"-style toggles) — per the
  frontend-only framing, this phase does **not** add new backend bulk endpoints. Where a real bulk
  operation would need one (e.g. bulk delete with a single transaction), the UI ships the selection
  toolbar but that specific action is deferred/logged rather than built against a client-side loop
  that could partially fail — a decision to confirm per-table at implementation time, not blanket-
  applied.
- Shared `EmptyState.vue` (icon + message + optional action button) and a `SkeletonRows.vue` (matches
  `DataTable` row shape, replacing the current bare `:loading` spinner) — both usable outside tables
  too (e.g. detail-view panels), not table-only components.
- **Rollout**: migrate all ~20 existing list views to `AppDataTable.vue` incrementally, view by view,
  each verified individually (existing Vitest + Playwright coverage per view must stay green) — not a
  single mechanical find-replace, since each view's current column set/actions differ.

## 8. Phase 4 — Cross-cutting Polish

- **Motion audit**: apply the 120–200ms ease-out standard (§0) consistently — sidebar section
  expand/collapse, command palette open/close, bulk-action toolbar enter/exit, dropdown/popover
  transitions. Respect the already-correct global `prefers-reduced-motion` handling in `style.css` —
  every new transition must be covered by it, not just the pre-existing ones.
- **Responsive audit**: every screen touched in Phases 1–3, re-verified at desktop/tablet/mobile
  breakpoints (the existing `lg:` 1024px shell breakpoint stays authoritative; tables in particular
  need a real mobile story — e.g. card-list fallback below `sm:` — since dense multi-column tables
  don't work on a phone screen today either).
- **Accessibility (WCAG AA) pass**: focus-visible states on every new interactive element, correct
  ARIA roles on the command palette (`role="dialog"`, `aria-modal`) and sidebar groups
  (`aria-expanded`), color-contrast check on the refreshed token set in both light and dark themes,
  full keyboard-only walkthrough of sidebar → header → command palette → a migrated table.
- **Keyboard shortcuts help overlay**: extend the existing `?`-triggered pattern
  (`KeyboardShortcutsHelp.vue`, currently calendar-only) into a global version covering every shortcut
  registered in Phase 1, reachable from anywhere, not just the Appointments board.

## 9. Non-Functional Requirements

- **Performance**: no regression to current bundle size beyond the two additions in §3
  (`@vueuse/core`, `chart.js`+`vue-chartjs`, both tree-shaken/lazy-loaded where possible — charts only
  loaded on the Dashboard route, not globally). Lighthouse/perceived-load spot-check on Dashboard and
  one migrated table view before/after each phase.
- **Accessibility**: WCAG AA is a hard bar for every new component (§8), not deferred to a final pass
  only — Phases 1–3 each include their own basic keyboard/contrast check; Phase 4 is the full audit.
- **i18n/RTL**: every new string ships in all three locales (`ar`/`en`/`tr`) per existing convention;
  every new layout uses logical Tailwind properties (`ps-`/`pe-`/`border-s-`/`text-start`) exactly as
  the current sidebar/header already do — no hardcoded `ml-`/`mr-`/`text-left` introduced.
- **Testing**: existing Vitest + Playwright coverage for every touched view must stay green; each new
  shared component (`AppDataTable.vue`, `EmptyState.vue`, command palette, shortcuts composable) gets
  its own unit tests; Phase 1's command palette and Phase 3's bulk-action flow each get a new
  permanent Playwright E2E spec, matching the project's established pattern of one E2E suite per major
  new interaction surface.
- **No regressions**: every one of the 16 Production-Ready modules' own E2E suites must stay green
  across all four phases — this initiative touches shared shell/table code with broad blast radius by
  design (§2), so full-suite CI runs (not just the touched view's own tests) gate every phase's PR.

## 10. Explicitly Out of Scope

- Any new backend endpoint, table, or business logic — this initiative is frontend-only per explicit
  user direction. Where a feature would require one (real notifications backend, cross-device
  favorites sync, true bulk-delete transactions), it's noted inline above and deferred, not silently
  dropped.
- A real notification system (bell stays inert — connecting it needs a backend event/notification
  model, already tracked separately in `TECH_DEBT.md`).
- Global full-text data search (patients/invoices/etc.) inside the command palette — navigation/action
  search only in this pass (§5.3).
- Replacing PrimeVue or Tailwind with a different UI/CSS framework.
- Multi-branch/multi-tenant UI — out of scope for the whole product in V1, unrelated to this
  initiative (see [[policy_saas_multitenant_readiness]] — this redesign must still not paint the app
  into a single-tenant-only corner, but that's already satisfied by staying purely presentational).
- A native mobile app — "mobile-first responsive web" only, per the existing
  [[policy_pwa_mobile_first]] standing directive, already in force for every module.

## 11. Decisions (all confirmed, 2026-07-31)

All open items are resolved — see §3 (architecture forks) and §5.1 (Billing sidebar fix). Nothing left
pending; this document is ready for implementation to begin on Phase 1 pending final user sign-off.
