import type { UserRole } from '@/types/user'

/** Section grouping keys (frontend-ux-redesign design doc §5.1) — order here is render order. */
export const NAV_SECTIONS = ['clinical', 'operations', 'insights', 'admin'] as const
export type NavSection = (typeof NAV_SECTIONS)[number]

export interface NavItem {
  /** i18n key resolved against the current locale */
  labelKey: string
  /** PrimeIcons class, e.g. 'pi pi-home' */
  icon: string
  /** Named route to link to. Omitted for "coming soon" items with no module yet. */
  routeName?: string
  /** Roles allowed to see this item. Omitted = visible to every authenticated role. */
  roles?: UserRole[]
  /** True when the module isn't built yet — rendered visible but disabled, no click/route. */
  comingSoon?: boolean
  /** Collapsible group this item renders under. Omitted = one of the two pinned top items
   *  (Dashboard/Patients) that always render flat, above every section (design doc §5.1). */
  section?: NavSection
  children?: NavItem[]
}

/**
 * Single source of truth for the app sidebar. Adding a real module later is a two-line change:
 * add its route, then flip its entry here from `comingSoon: true` to a real `routeName`.
 */
export const navigation: NavItem[] = [
  {
    labelKey: 'nav.dashboard',
    icon: 'pi pi-home',
    routeName: 'dashboard',
  },
  {
    labelKey: 'nav.patients',
    icon: 'pi pi-users',
    routeName: 'patients',
  },
  {
    labelKey: 'nav.appointments',
    icon: 'pi pi-calendar',
    routeName: 'appointments',
    section: 'clinical',
    children: [
      {
        labelKey: 'appointments.nav.board',
        icon: 'pi pi-calendar',
        routeName: 'appointments',
      },
      {
        labelKey: 'appointments.nav.types',
        icon: 'pi pi-tags',
        routeName: 'appointment-types',
      },
      {
        labelKey: 'appointments.nav.schedule',
        icon: 'pi pi-clock',
        routeName: 'dentist-schedule',
      },
    ],
  },
  {
    labelKey: 'nav.dentalChart',
    icon: 'pi pi-sitemap',
    section: 'clinical',
    // No overview route of its own yet — the chart itself lives on PatientDetailView's Dental
    // Chart tab (implementation plan §2.3), not a dedicated screen. The catalog admin screen is
    // real, so this is a real expandable group, not a `comingSoon` placeholder.
    children: [
      {
        labelKey: 'dentalChart.nav.conditions',
        icon: 'pi pi-palette',
        routeName: 'dental-conditions',
        roles: ['admin'],
      },
    ],
  },
  {
    labelKey: 'nav.treatmentPlans',
    icon: 'pi pi-clipboard',
    section: 'clinical',
    comingSoon: true,
  },
  {
    labelKey: 'nav.inventory',
    icon: 'pi pi-box',
    routeName: 'supplies',
    section: 'operations',
    children: [
      {
        labelKey: 'inventory.nav.supplies',
        icon: 'pi pi-box',
        routeName: 'supplies',
      },
      {
        labelKey: 'inventory.nav.purchaseOrders',
        icon: 'pi pi-truck',
        routeName: 'purchase-orders',
      },
      {
        labelKey: 'inventory.nav.suppliers',
        icon: 'pi pi-building',
        routeName: 'suppliers',
        roles: ['admin'],
      },
      {
        labelKey: 'inventory.nav.categories',
        icon: 'pi pi-tags',
        routeName: 'supply-categories',
        roles: ['admin'],
      },
    ],
  },
  {
    labelKey: 'nav.laboratory',
    icon: 'pi pi-send',
    routeName: 'lab-cases',
    section: 'operations',
    children: [
      {
        labelKey: 'laboratory.nav.labCases',
        icon: 'pi pi-send',
        routeName: 'lab-cases',
      },
      {
        labelKey: 'laboratory.nav.labs',
        icon: 'pi pi-building',
        routeName: 'labs',
        roles: ['admin'],
      },
    ],
  },
  {
    // Was `comingSoon: true` despite Invoice CRUD/routes already working end-to-end
    // (patient-scoped) — the gap was purely a missing clinic-wide index, now built
    // (frontend-ux-redesign design doc §5.1/§11, `GET /invoices`).
    labelKey: 'nav.billing',
    icon: 'pi pi-wallet',
    routeName: 'invoices',
    section: 'operations',
  },
  {
    labelKey: 'nav.reports',
    icon: 'pi pi-chart-bar',
    routeName: 'reports',
    section: 'insights',
    children: [
      {
        labelKey: 'reports.nav.production',
        icon: 'pi pi-chart-line',
        routeName: 'report-production',
        roles: ['admin'],
      },
      {
        labelKey: 'reports.nav.collections',
        icon: 'pi pi-wallet',
        routeName: 'report-collections',
        roles: ['admin'],
      },
      {
        labelKey: 'reports.nav.arAging',
        icon: 'pi pi-exclamation-circle',
        routeName: 'report-ar-aging',
        roles: ['admin'],
      },
      {
        labelKey: 'reports.nav.appointments',
        icon: 'pi pi-calendar',
        routeName: 'report-appointments',
      },
      {
        labelKey: 'reports.nav.treatmentPlanAcceptance',
        icon: 'pi pi-clipboard',
        routeName: 'report-treatment-plan-acceptance',
      },
      {
        labelKey: 'reports.nav.newPatients',
        icon: 'pi pi-user-plus',
        routeName: 'report-new-patients',
      },
    ],
  },
  {
    labelKey: 'nav.users',
    icon: 'pi pi-user',
    routeName: 'users',
    roles: ['admin'],
    section: 'admin',
  },
  {
    labelKey: 'nav.settings',
    icon: 'pi pi-cog',
    routeName: 'settings',
    // Every child is admin-only, so this is gated at the top level itself rather than a
    // mixed-visibility group (design doc §7) — unlike Reports, which has some non-admin children.
    roles: ['admin'],
    section: 'admin',
  },
]

/**
 * Depth-first search across top-level items and one level of children (matches
 * `AppSidebarItem.vue`'s own nesting depth). Used by the Command Palette (every reachable route
 * becomes a "Go to X" entry) and `useBreadcrumbs` (trail from section root down to the current
 * item) — one traversal, two call sites, per the frontend-ux-redesign design doc §5.2/§5.3.
 */
export function findNavTrailByRouteName(routeName: string): NavItem[] | undefined {
  for (const item of navigation) {
    if (item.routeName === routeName) return [item]
    if (item.children) {
      const child = item.children.find((c) => c.routeName === routeName)
      if (child) return [item, child]
    }
  }
  return undefined
}

/** Every leaf item with a real route — flattened once for the Command Palette's action list. */
export function flattenNavItems(): NavItem[] {
  return navigation.flatMap((item) => (item.children?.length ? item.children : [item]))
}

/**
 * Record-detail routes that have no sidebar entry of their own (they're reached by clicking a row
 * in a list, never from the nav) — used by both `useBreadcrumbs` and the Recent Items tracker to
 * resolve an icon/parent/fallback label for a detail page (design doc §5.1/§5.4/§11).
 */
export const DETAIL_ROUTES: Record<string, { icon: string; labelKey: string; parentRouteName: string }> = {
  'patient-detail': { icon: 'pi pi-user', labelKey: 'nav.patients', parentRouteName: 'patients' },
  'treatment-plan-detail': {
    icon: 'pi pi-clipboard',
    labelKey: 'patients.tabs.treatmentPlans',
    parentRouteName: 'patients',
  },
  'clinical-note-detail': {
    icon: 'pi pi-file',
    labelKey: 'patients.tabs.clinicalNotes',
    parentRouteName: 'patients',
  },
  'invoice-detail': { icon: 'pi pi-wallet', labelKey: 'patients.tabs.invoices', parentRouteName: 'patients' },
  'appointment-detail': {
    icon: 'pi pi-calendar',
    labelKey: 'nav.appointments',
    parentRouteName: 'appointments',
  },
  'supply-detail': { icon: 'pi pi-box', labelKey: 'inventory.nav.supplies', parentRouteName: 'supplies' },
  'purchase-order-detail': {
    icon: 'pi pi-truck',
    labelKey: 'inventory.nav.purchaseOrders',
    parentRouteName: 'purchase-orders',
  },
  'lab-case-detail': {
    icon: 'pi pi-send',
    labelKey: 'laboratory.nav.labCases',
    parentRouteName: 'lab-cases',
  },
}
