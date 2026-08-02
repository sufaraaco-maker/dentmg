import type { Component } from 'vue'
import {
  AlertCircle,
  BarChart3,
  Building2,
  Calendar,
  ClipboardList,
  Clock,
  Home,
  LineChart,
  Network,
  Package,
  Palette,
  Send,
  Settings,
  Tags,
  Truck,
  User,
  UserPlus,
  Users,
  Wallet,
} from 'lucide-vue-next'
import type { UserRole } from '@/types/user'

/** Section grouping keys (frontend-ux-redesign design doc §5.1) — order here is render order. */
export const NAV_SECTIONS = ['clinical', 'operations', 'insights', 'admin'] as const
export type NavSection = (typeof NAV_SECTIONS)[number]

export interface NavItem {
  /** i18n key resolved against the current locale */
  labelKey: string
  /** Lucide icon component (frontend-visual-redesign-design.md §4/§5) — rendered via
   *  `<component :is="icon" />`, replacing the previous PrimeIcons class-string convention. */
  icon: Component
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
    icon: Home,
    routeName: 'dashboard',
  },
  {
    labelKey: 'nav.patients',
    icon: Users,
    routeName: 'patients',
  },
  {
    labelKey: 'nav.appointments',
    icon: Calendar,
    routeName: 'appointments',
    section: 'clinical',
    children: [
      {
        labelKey: 'appointments.nav.board',
        icon: Calendar,
        routeName: 'appointments',
      },
      {
        labelKey: 'appointments.nav.types',
        icon: Tags,
        routeName: 'appointment-types',
      },
      {
        labelKey: 'appointments.nav.schedule',
        icon: Clock,
        routeName: 'dentist-schedule',
      },
    ],
  },
  {
    labelKey: 'nav.dentalChart',
    icon: Network,
    section: 'clinical',
    // No overview route of its own yet — the chart itself lives on PatientDetailView's Dental
    // Chart tab (implementation plan §2.3), not a dedicated screen. The catalog admin screen is
    // real, so this is a real expandable group, not a `comingSoon` placeholder.
    children: [
      {
        labelKey: 'dentalChart.nav.conditions',
        icon: Palette,
        routeName: 'dental-conditions',
        roles: ['admin'],
      },
    ],
  },
  {
    // Was `comingSoon: true` despite Treatment Plan CRUD/routes already working end-to-end
    // (patient-scoped) — the gap was purely a missing clinic-wide index, now built (same fix as
    // the Billing entry below, `GET /treatment-plans`).
    labelKey: 'nav.treatmentPlans',
    icon: ClipboardList,
    routeName: 'treatment-plans',
    section: 'clinical',
  },
  {
    labelKey: 'nav.inventory',
    icon: Package,
    routeName: 'supplies',
    section: 'operations',
    children: [
      {
        labelKey: 'inventory.nav.supplies',
        icon: Package,
        routeName: 'supplies',
      },
      {
        labelKey: 'inventory.nav.purchaseOrders',
        icon: Truck,
        routeName: 'purchase-orders',
      },
      {
        labelKey: 'inventory.nav.suppliers',
        icon: Building2,
        routeName: 'suppliers',
        roles: ['admin'],
      },
      {
        labelKey: 'inventory.nav.categories',
        icon: Tags,
        routeName: 'supply-categories',
        roles: ['admin'],
      },
    ],
  },
  {
    labelKey: 'nav.laboratory',
    icon: Send,
    routeName: 'lab-cases',
    section: 'operations',
    children: [
      {
        labelKey: 'laboratory.nav.labCases',
        icon: Send,
        routeName: 'lab-cases',
      },
      {
        labelKey: 'laboratory.nav.labs',
        icon: Building2,
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
    icon: Wallet,
    routeName: 'invoices',
    section: 'operations',
  },
  {
    labelKey: 'nav.reports',
    icon: BarChart3,
    routeName: 'reports',
    section: 'insights',
    children: [
      {
        labelKey: 'reports.nav.production',
        icon: LineChart,
        routeName: 'report-production',
        roles: ['admin'],
      },
      {
        labelKey: 'reports.nav.collections',
        icon: Wallet,
        routeName: 'report-collections',
        roles: ['admin'],
      },
      {
        labelKey: 'reports.nav.arAging',
        icon: AlertCircle,
        routeName: 'report-ar-aging',
        roles: ['admin'],
      },
      {
        labelKey: 'reports.nav.appointments',
        icon: Calendar,
        routeName: 'report-appointments',
      },
      {
        labelKey: 'reports.nav.treatmentPlanAcceptance',
        icon: ClipboardList,
        routeName: 'report-treatment-plan-acceptance',
      },
      {
        labelKey: 'reports.nav.newPatients',
        icon: UserPlus,
        routeName: 'report-new-patients',
      },
    ],
  },
  {
    labelKey: 'nav.users',
    icon: User,
    routeName: 'users',
    roles: ['admin'],
    section: 'admin',
  },
  {
    labelKey: 'nav.settings',
    icon: Settings,
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
 * in a list, never from the nav) — used by `useBreadcrumbs` to resolve a parent/fallback label for
 * a detail page (design doc §5.2). Previously also fed the Recent Items tracker (`icon` field) —
 * that feature is removed (frontend-visual-redesign-design.md §1.2), so no `icon` field here.
 */
export const DETAIL_ROUTES: Record<string, { labelKey: string; parentRouteName: string }> = {
  'patient-detail': { labelKey: 'nav.patients', parentRouteName: 'patients' },
  'treatment-plan-detail': { labelKey: 'patients.tabs.treatmentPlans', parentRouteName: 'patients' },
  'clinical-note-detail': { labelKey: 'patients.tabs.clinicalNotes', parentRouteName: 'patients' },
  'invoice-detail': { labelKey: 'patients.tabs.invoices', parentRouteName: 'patients' },
  'appointment-detail': { labelKey: 'nav.appointments', parentRouteName: 'appointments' },
  'supply-detail': { labelKey: 'inventory.nav.supplies', parentRouteName: 'supplies' },
  'purchase-order-detail': { labelKey: 'inventory.nav.purchaseOrders', parentRouteName: 'purchase-orders' },
  'lab-case-detail': { labelKey: 'laboratory.nav.labCases', parentRouteName: 'lab-cases' },
}
