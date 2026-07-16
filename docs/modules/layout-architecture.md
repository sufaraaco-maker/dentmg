# Application Shell / Layout Architecture

Status: **Implemented (2026-07-16).** Replaces the informal top-nav `DefaultLayout.vue` (added ad hoc, see
`ARCHITECTURE_REVIEW.md:92,168`) with a permanent, documented sidebar+header shell. This is the foundational
frontend shell every future module (Dental Chart, Treatment Plans, Billing, Reports, Settings) plugs into.

Scope: **Frontend only.** No backend, database, or API changes. No existing route paths/names changed.

## Implementation Notes (as-built)

Everything in this document was implemented as designed, with the two open questions resolved as follows:

- **Header search**: omitted entirely (not shipped even as a disabled input) — approved decision, to avoid
  UI that looks functional but does nothing. Will be added once a real search endpoint exists.
- **Route-guard fix**: implemented in this same pass. `router/index.ts` now declares `RouteMeta.roles?:
  UserRole[]` (module augmentation) and `router.beforeEach` redirects to a new `forbidden` route when the
  authenticated user's role isn't in `to.meta.roles`. The `users` route carries `meta: { roles: ['admin'] }`.
  This closes the gap described in §8 below — hiding a nav item was never a security boundary, and now isn't
  the only thing enforcing it.

One implementation-time decision not spelled out in the original design: on the **collapsed desktop rail**,
clicking a nav item that has children (only "Appointments" today) doesn't try to expand in place — there's no
room. It navigates directly to the group's own route (`AppSidebarItem.vue`'s `onParentClick`), the same
behavior as clicking "Calendar" in the expanded view.

Components delivered exactly as designed: `AppSidebar.vue`, `AppHeader.vue`, `AppSidebarItem.vue`,
`config/navigation.ts`, and a thin `DefaultLayout.vue`. `AppSidebar.vue` takes a `variant: 'desktop' |
'drawer'` prop and is mounted twice from `DefaultLayout.vue` — once docked, once inside a PrimeVue `Drawer` —
so the nav-item markup and role-filtering logic live in exactly one place. Sidebar collapse state
(`ui.sidebarCollapsed`) and mobile-drawer open state (`ui.mobileSidebarOpen`) both live in the existing
`stores/ui.ts`, following the same `localStorage` pattern as theme/locale.

This was also the project's first round of component-level tests (previously only Pinia stores/services had
tests). `src/test/setup.ts` now globally registers the PrimeVue plugin, the `Tooltip` directive, and a
`matchMedia` polyfill (jsdom doesn't implement it, and PrimeVue's `Select` needs it) — a one-time investment
that benefits every future component test in the project, not just this module's.

---

## 1. Goal / Purpose

Replace the current horizontal top-nav (`frontend/src/layouts/DefaultLayout.vue`) with a professional
medical-SaaS shell: a persistent, collapsible, icon-based **sidebar** plus a slim **header** carrying only
global actions. This is a foundational decision — every future module (Dental Chart, Treatment Plans,
Billing, Reports, Settings) plugs into this shell rather than each inventing its own navigation.

## 2. Full Workflow

**Desktop (≥ `lg` breakpoint, 1024px):**
- Sidebar is permanently docked (left in LTR, right in RTL), full-height, sits beside a scrollable content area.
- Expanded state: ~260px wide, icon + label per item.
- Collapsed state: ~72px wide, icon only, label available via tooltip on hover. A toggle button (chevron, in the sidebar header) switches between the two. State persists in `localStorage` (same pattern as theme/locale) so it survives reloads.
- Header spans the remaining width above the content area, sticky on scroll.

**Mobile / tablet (< `lg`):**
- Sidebar is hidden by default. Header shows a hamburger button (leftmost/start side).
- Tapping it opens the sidebar as a slide-in overlay drawer (from the start edge, i.e. mirrors for RTL) with a backdrop; tapping the backdrop, pressing Esc, or navigating closes it.
- No "collapsed icon rail" state on mobile — it's either fully open (drawer) or fully closed.

**Navigation behavior (both):**
- Active route highlighted in the sidebar (background + accent-colored text/icon, per `router-link-active`).
- A parent item with children (only "Appointments" today: Calendar / Types / Working Hours) expands in place to show its children when active or clicked; children route to existing `appointment-types` / `dentist-schedule` routes plus the main `appointments` calendar route — closing the long-standing gap where those two pages were only reachable via in-page links, never from top-level nav.
- Clicking a nav item on mobile also closes the drawer.

## 3. Business Rules — Navigation Visibility

Per the approved scope decision: **all 9 items are shown to every authenticated user.** Items backed by a
real module are clickable; items with no module yet are visibly present but disabled (greyed out, a "Soon"
badge, no click/focus action, `aria-disabled="true"`), so the sidebar communicates the product's full
information architecture without any placeholder routes or fake pages.

| Sidebar item | Route today | State |
|---|---|---|
| Dashboard | `dashboard` | Active |
| Patients | `patients` | Active |
| Appointments (+ Calendar / Types / Working Hours children) | `appointments`, `appointment-types`, `dentist-schedule` | Active |
| Dental Chart | — | Disabled / "Soon" |
| Treatment Plans | — | Disabled / "Soon" |
| Billing | — | Disabled / "Soon" |
| Reports | — | Disabled / "Soon" |
| Users | `users` | Active — **admin only** (matches existing `auth.isAdmin` gating already inside `UsersView.vue`) |
| Settings | — | Disabled / "Soon" |

Role-based hiding is applied now only where a real permission rule already exists in the codebase (Users →
admin-only). I'm deliberately **not** guessing role rules for unbuilt modules (e.g. "should Billing be
hidden from dentists?") — that's a business-rule decision that belongs to each module's own design phase.
Once a "Soon" module is actually implemented, its row gets a real route + whatever role rule that module's
design doc specifies.

The header's **Search** and **Notifications** have the same problem one level down: there is no global
search endpoint and no notification system in the backend today. Proposal: ship both as visible, inert UI
—Search is a disabled input (or omitted entirely, see open question below); Notifications is a bell icon
with no badge and an empty-state popover ("No notifications yet") — rather than wiring either to fake data.

## 4. Database Design / 5. Table Relationships / 6. API Design

**Not applicable.** Pure frontend shell change; no migrations, models, or endpoints are added or modified.

## 7. UI/UX Design

**New components** (`frontend/src/components/layout/`):
- `AppSidebar.vue` — renders the nav tree from a config array; used both docked (desktop) and inside a
  `Drawer` (mobile) — one component, two hosts, so nav markup/logic is never duplicated.
- `AppHeader.vue` — hamburger (mobile only) · search · notifications bell · locale `Select` (existing,
  moved) · theme toggle (existing, moved) · user avatar/menu (name, role, logout).
- `AppSidebarItem.vue` — single nav row (icon, label, active state, disabled state, optional children).

**Refactored:**
- `DefaultLayout.vue` becomes the shell orchestrator only: renders `AppSidebar` + `AppHeader` + `<main>`, owns no nav markup itself.

**New config:**
- `frontend/src/config/navigation.ts` — single source of truth:
  ```ts
  interface NavItem {
    labelKey: string          // i18n key
    icon: string               // PrimeIcons class, e.g. 'pi pi-home'
    routeName?: string         // omitted for disabled/"soon" items
    roles?: Role[]              // omitted = visible to all
    children?: NavItem[]
  }
  ```

**Library choices** (all already installed, nothing new added — per `PROJECT_CONTEXT.md`'s "never introduce
unnecessary packages"):
- PrimeVue `Drawer` (v4's renamed `Sidebar`) for the mobile slide-over — free focus-trap, Esc-to-close, backdrop.
- PrimeVue `Menu` (or `Popover` + a plain list) for the user-profile dropdown.
- PrimeIcons (`pi pi-*`) for every nav/header icon — already the project's only icon set.
- Everything else (the sidebar itself, collapse rail, active-state styling) is a plain Tailwind component, consistent with how `DefaultLayout.vue` is hand-rolled today — a full PrimeVue `PanelMenu`/`Menubar` would fight the collapse-to-icon-rail requirement more than it'd help.

**Visual language:** reuse existing tokens exactly — `surface-*` scale, `text-primary`, `.dark` class
toggle — so the new shell looks native to the app rather than introducing a second design language.

## 8. Permissions

Covered in §3. Mechanism: `AppSidebar.vue` filters `navigation.ts` entries against
`auth.user?.role` (reusing the existing flat 3-role enum — no new permission-string system, per
`docs/modules/roles-permissions.md`).

**Gap noticed while doing this** (pre-existing, not introduced by this change): the router's
`beforeEach` guard only checks `requiresAuth`/`guestOnly` today — nothing stops a non-admin from
navigating straight to `/users` by URL even though the nav already hides it for them (`UsersView.vue`
happens to also gate its own buttons, but the route itself is unguarded). Since this task adds the first
real per-item permission concept to the nav, I'd like to close that gap in the same pass: add an optional
`meta.roles?: Role[]` to the `users` route and a matching check in `router.beforeEach` (redirect to
`dashboard` if the authenticated user's role isn't in the list). Low risk, additive only, and it makes the
sidebar's permission story actually enforced rather than cosmetic. Flagging this as part of the design for
approval rather than silently adding it.

## 9. Validation Rules

Not applicable (no forms/inputs beyond the existing locale `Select`, which is unchanged).

## 10. Security Considerations

- Nav-item visibility/disabling is a **UX convenience, not a security boundary** — it must never be the
  only thing standing between a role and a page. See the route-guard gap and proposed fix in §8.
- Disabled "Soon" items render with no `href`/route target at all (not a link to a 404 or a disabled
  route) — nothing for a curious user to probe.
- No new data leaves or enters the client; no new API calls are introduced by this change.

## 11. Performance Considerations

- No new dependencies; PrimeIcons font and PrimeVue `Drawer`/`Menu` are already in the current bundle.
- Collapse/expand and drawer open/close are pure CSS transitions (width/transform), not JS-animated — cheap and consistent with existing dark-mode/locale toggle patterns.
- The shell is not code-split (it's the root chrome for every authenticated route already), so there's no new lazy-loading concern.

## 12. Scalability Considerations

- Adding a real module later (e.g. Billing) is a two-line change: add its route, flip its `navigation.ts` entry from `disabled` to a real `routeName` (+ `roles` if the module's own design doc calls for restricting it). No shell changes needed.
- `navigation.ts` is the one place a future "clinic/branch switcher" or per-module badge (e.g. unread count) would hook in, without touching `AppSidebar.vue`'s rendering logic.
- Notifications is scaffolded as inert UI now specifically so a future real notification service only needs to fill in data, not redesign the header.

## 13. Trade-offs / Architectural Decisions

| Decision | Chosen | Alternative considered | Why |
|---|---|---|---|
| Sidebar implementation | Hand-rolled Tailwind component | PrimeVue `PanelMenu`/`Menubar` | Full control over the icon-rail collapse animation and RTL mirroring; PanelMenu's accordion model doesn't map cleanly to "collapse to icons" |
| Mobile overlay | PrimeVue `Drawer` | Hand-rolled `<Teleport>` + backdrop | Free a11y (focus trap, Esc, backdrop click) instead of reimplementing it |
| Nav source of truth | Config array (`navigation.ts`) | Nav markup hardcoded per-item (status quo) | Enables permission filtering, disabled state, and icons without N near-duplicate `RouterLink` blocks |
| Collapsed-state persistence | `localStorage`, like theme/locale | Session-only (resets each load) | Matches existing UX convention in `ui.ts`; users expect their sidebar state to stick |
| Unbuilt-module rows | Visible + disabled ("Soon") | Hidden until built / stub placeholder routes | Per your explicit choice — communicates full IA now with zero placeholder-page or fake-route overhead |

## 14. Potential Risks

- **RTL regression (highest risk):** Arabic is the app's **default locale** already. Any physical Tailwind utility (`ml-`, `mr-`, `left-`, `right-`) used in the new components instead of logical ones (`ms-`, `me-`, `start-`, `end-`) will visibly break on first load for most users, not just an edge case. Mitigation: build and manually verify every new component in `ar` locale before calling this done, not just `en`.
- **Dialog/overlay stacking:** existing `Toast`, `ConfirmDialog`, and form dialogs (`PatientFormDialog.vue`) must still render above the mobile drawer. Mitigation: verify z-index ordering manually across a few existing dialogs after the drawer is in place.
- **New route guard (§8 fix):** must not lock out a role from a page it currently (correctly) reaches. Mitigation: manually test all three roles against all routes after adding `meta.roles`.

## 15. Future Improvements (explicitly out of scope now)

- `meta.title`/`meta.icon` on routes to drive the header's page title / breadcrumb from route meta instead of being implied by the active nav item — nice-to-have, deferred to keep this change scoped to the shell itself.
- Real global search and real notifications, once their respective backends exist.
- Per-item badges (e.g. "3 pending appointments" on the Appointments item).
- Multi-branch switcher in the header, once multi-branch UI is in scope (backend already models `multi branch` per `PROJECT_CONTEXT.md`, but no branch-switching UI exists yet).

---

## Resolved decisions

Both open questions from the design phase were resolved by explicit user approval before implementation —
see "Implementation Notes" above for the final calls (search omitted; route-guard fix implemented in this
pass).
