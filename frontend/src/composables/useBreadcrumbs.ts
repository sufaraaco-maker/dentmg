import { computed } from 'vue'
import { useRoute } from 'vue-router'
import { DETAIL_ROUTES, findNavTrailByRouteName } from '@/config/navigation'

export interface BreadcrumbEntry {
  labelKey: string
  /** Omitted on the trail's last entry (the current page never links to itself). */
  routeName?: string
}

/** Routes with neither a sidebar entry nor a dynamic `:id` param (so `DETAIL_ROUTES` doesn't fit)
 *  — My Account, the Notification Center's full page (reached from the header bell, deliberately
 *  not a sidebar entry), and the three Settings sub-pages, each pointing at its existing title key. */
const STANDALONE_ROUTES: Record<string, { labelKey: string; parentRouteName?: string }> = {
  account: { labelKey: 'nav.myAccount' },
  notifications: { labelKey: 'notifications.title' },
  'settings-practice': { labelKey: 'settings.nav.practice', parentRouteName: 'settings' },
  'settings-billing': { labelKey: 'settings.nav.billing', parentRouteName: 'settings' },
  'settings-ai-assistant': { labelKey: 'settings.nav.aiAssistant', parentRouteName: 'settings' },
}

/**
 * Derives the Header's breadcrumb trail from the current route (frontend-ux-redesign design doc
 * §5.2) — reuses `config/navigation.ts` as the single source of truth rather than duplicating
 * per-route breadcrumb metadata across `router/index.ts`. Detail pages that aren't in the sidebar
 * (`patient-detail`, `invoice-detail`, etc.) resolve via `DETAIL_ROUTES`' declared parent instead.
 */
export function useBreadcrumbs() {
  const route = useRoute()

  return computed<BreadcrumbEntry[]>(() => {
    const name = route.name as string | undefined
    if (!name) return []

    const detail = DETAIL_ROUTES[name]
    if (detail) {
      const parentTrail = findNavTrailByRouteName(detail.parentRouteName) ?? []
      return [
        ...parentTrail.map((item) => ({ labelKey: item.labelKey, routeName: item.routeName })),
        { labelKey: detail.labelKey },
      ]
    }

    const trail = findNavTrailByRouteName(name)
    if (trail) {
      return trail.map((item, index) => ({
        labelKey: item.labelKey,
        routeName: index === trail.length - 1 ? undefined : item.routeName,
      }))
    }

    const standalone = STANDALONE_ROUTES[name]
    if (standalone) {
      const parentTrail = standalone.parentRouteName
        ? (findNavTrailByRouteName(standalone.parentRouteName) ?? [])
        : []
      return [
        ...parentTrail.map((item) => ({ labelKey: item.labelKey, routeName: item.routeName })),
        { labelKey: standalone.labelKey },
      ]
    }

    // Truly unmapped routes (`forbidden`, `not-found`) already carry their own large heading —
    // no breadcrumb trail is worth forcing here.
    return []
  })
}
