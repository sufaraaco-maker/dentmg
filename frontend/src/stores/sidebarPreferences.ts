import { defineStore } from 'pinia'
import { useLocalStorage } from '@vueuse/core'
import type { NavSection } from '@/config/navigation'

const FAVORITES_KEY = 'dentalsuite.sidebarFavorites'
const COLLAPSED_SECTIONS_KEY = 'dentalsuite.sidebarCollapsedSections'

/**
 * Favorites and collapsed-section state for the redesigned Sidebar (frontend-ux-redesign design
 * doc §3 item 3/§5.1) — `localStorage`-only per the confirmed "frontend-only, no cross-device
 * sync" decision, backed by `@vueuse/core`'s `useLocalStorage` so every consumer (Sidebar, Command
 * Palette) shares one reactive source instead of drifting out of sync the way independent
 * `useLocalStorage()` calls would within the same tab.
 *
 * Recent Items (auto-tracked recently-visited records) previously lived here too — removed
 * entirely per frontend-visual-redesign-design.md §1.2, not just hidden.
 */
export const useSidebarPreferencesStore = defineStore('sidebarPreferences', () => {
  const favorites = useLocalStorage<string[]>(FAVORITES_KEY, [])
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

  return {
    favorites,
    isFavorite,
    toggleFavorite,
    collapsedSections,
    isSectionCollapsed,
    toggleSection,
  }
})
