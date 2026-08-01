import { nextTick } from 'vue'
import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it } from 'vitest'
import { useSidebarPreferencesStore } from './sidebarPreferences'

beforeEach(() => {
  setActivePinia(createPinia())
  localStorage.clear()
})

describe('useSidebarPreferencesStore favorites', () => {
  it('toggles a route name in and out of favorites', () => {
    const store = useSidebarPreferencesStore()
    expect(store.isFavorite('patients')).toBe(false)

    store.toggleFavorite('patients')
    expect(store.isFavorite('patients')).toBe(true)
    expect(store.favorites).toContain('patients')

    store.toggleFavorite('patients')
    expect(store.isFavorite('patients')).toBe(false)
  })

  it('persists favorites to localStorage', async () => {
    useSidebarPreferencesStore().toggleFavorite('reports')
    // `@vueuse/core`'s `useLocalStorage` writes via a `flush: 'pre'` watcher, not synchronously
    // on assignment — the write lands on the next tick, not before this line returns.
    await nextTick()

    expect(JSON.parse(localStorage.getItem('dentalsuite.sidebarFavorites') ?? '[]')).toEqual(['reports'])
  })
})

describe('useSidebarPreferencesStore collapsed sections', () => {
  it('toggles a section between collapsed and expanded', () => {
    const store = useSidebarPreferencesStore()
    expect(store.isSectionCollapsed('operations')).toBe(false)

    store.toggleSection('operations')
    expect(store.isSectionCollapsed('operations')).toBe(true)

    store.toggleSection('operations')
    expect(store.isSectionCollapsed('operations')).toBe(false)
  })
})
