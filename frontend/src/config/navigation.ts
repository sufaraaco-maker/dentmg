import type { UserRole } from '@/types/user'

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
    comingSoon: true,
  },
  {
    labelKey: 'nav.billing',
    icon: 'pi pi-wallet',
    comingSoon: true,
  },
  {
    labelKey: 'nav.reports',
    icon: 'pi pi-chart-bar',
    comingSoon: true,
  },
  {
    labelKey: 'nav.users',
    icon: 'pi pi-user',
    routeName: 'users',
    roles: ['admin'],
  },
  {
    labelKey: 'nav.settings',
    icon: 'pi pi-cog',
    comingSoon: true,
  },
]
