import { defineStore } from 'pinia'
import { useLocalStorage } from '@vueuse/core'
import { DETAIL_ROUTES, type NavSection } from '@/config/navigation'

const FAVORITES_KEY = 'dentalsuite.sidebarFavorites'
const RECENTS_KEY = 'dentalsuite.sidebarRecents'
const COLLAPSED_SECTIONS_KEY = 'dentalsuite.sidebarCollapsedSections'
const MAX_RECENT_ITEMS = 5

export interface RecentItem {
  routeName: string
  /** Route params needed to rebuild the link (e.g. `{ id: '...' }`) — never contains query state. */
  params: Record<string, string>
  /** Either an i18n key (fallback, resolved with `t()` at render time) or a literal display name
   *  (e.g. a real patient/invoice name, once a detail view calls `updateLabel`) — `isLiteral`
   *  disambiguates so the fallback stays reactive to locale switches while a real name doesn't get
   *  mistakenly translated. */
  label: string
  isLiteral: boolean
  icon: string
  visitedAt: number
}

function sameEntry(a: Pick<RecentItem, 'routeName' | 'params'>, b: Pick<RecentItem, 'routeName' | 'params'>) {
  return a.routeName === b.routeName && JSON.stringify(a.params) === JSON.stringify(b.params)
}

/**
 * Favorites, Recent Items, and collapsed-section state for the redesigned Sidebar
 * (frontend-ux-redesign design doc §3 item 3/§5.1) — `localStorage`-only per the confirmed
 * "frontend-only, no cross-device sync" decision, backed by `@vueuse/core`'s `useLocalStorage` so
 * every consumer (Sidebar, Command Palette) shares one reactive source instead of drifting out of
 * sync the way independent `useLocalStorage()` calls would within the same tab.
 */
export const useSidebarPreferencesStore = defineStore('sidebarPreferences', () => {
  const favorites = useLocalStorage<string[]>(FAVORITES_KEY, [])
  const recentItems = useLocalStorage<RecentItem[]>(RECENTS_KEY, [])
  const collapsedSections = useLocalStorage<NavSection[]>(COLLAPSED_SECTIONS_KEY, [])

  function isFavorite(routeName: string): boolean {
    return favorites.value.includes(routeName)
  }

  function toggleFavorite(routeName: string) {
    favorites.value = isFavorite(routeName)
      ? favorites.value.filter((name) => name !== routeName)
      : [...favorites.value, routeName]
  }

  function isSectionCollapsed(section: NavSection): boolean {
    return collapsedSections.value.includes(section)
  }

  function toggleSection(section: NavSection) {
    collapsedSections.value = isSectionCollapsed(section)
      ? collapsedSections.value.filter((s) => s !== section)
      : [...collapsedSections.value, section]
  }

  function recordVisit(entry: {
    routeName: string
    params: Record<string, string>
    label: string
    icon: string
  }) {
    const withoutDuplicate = recentItems.value.filter((item) => !sameEntry(item, entry))
    recentItems.value = [{ ...entry, isLiteral: false, visitedAt: Date.now() }, ...withoutDuplicate].slice(
      0,
      MAX_RECENT_ITEMS,
    )
  }

  /** Upgrades a fallback (i18n-key) label to the record's real display name, once a detail view has
   *  it loaded — no extra fetch, no route change (design doc §5.1). No-op if the entry already
   *  scrolled out of the 5-item window. */
  function updateRecentLabel(routeName: string, params: Record<string, string>, label: string) {
    const match = recentItems.value.find((item) => sameEntry(item, { routeName, params }))
    if (match) {
      match.label = label
      match.isLiteral = true
    }
  }

  return {
    favorites,
    isFavorite,
    toggleFavorite,
    collapsedSections,
    isSectionCollapsed,
    toggleSection,
    recentItems,
    recordVisit,
    updateRecentLabel,
  }
})

/** Called from the router's `afterEach` (design doc §5.1) — builds the fallback entry for any
 *  route flagged `meta.recent: true`, using `DETAIL_ROUTES` for detail pages. Exported standalone
 *  (not a store method) since it needs the route object, not just primitives. */
export function buildRecentItemFromRoute(routeName: string, params: Record<string, string>) {
  const detail = DETAIL_ROUTES[routeName]
  if (!detail) return null

  return {
    routeName,
    params,
    label: detail.labelKey,
    icon: detail.icon,
  }
}
