import { nextTick } from 'vue'
import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it } from 'vitest'
import { buildRecentItemFromRoute, useSidebarPreferencesStore } from './sidebarPreferences'

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

describe('useSidebarPreferencesStore recent items', () => {
  it('records a visit at the front of the list', () => {
    const store = useSidebarPreferencesStore()

    store.recordVisit({ routeName: 'patient-detail', params: { id: 'p1' }, label: 'nav.patients', icon: 'pi pi-user' })

    expect(store.recentItems).toHaveLength(1)
    expect(store.recentItems[0]?.routeName).toBe('patient-detail')
    expect(store.recentItems[0]?.isLiteral).toBe(false)
  })

  it('moves a re-visited entry back to the front instead of duplicating it', () => {
    const store = useSidebarPreferencesStore()
    store.recordVisit({ routeName: 'patient-detail', params: { id: 'p1' }, label: 'nav.patients', icon: 'pi pi-user' })
    store.recordVisit({ routeName: 'invoice-detail', params: { id: 'p2', invoiceId: 'i1' }, label: 'patients.tabs.invoices', icon: 'pi pi-wallet' })
    store.recordVisit({ routeName: 'patient-detail', params: { id: 'p1' }, label: 'nav.patients', icon: 'pi pi-user' })

    expect(store.recentItems).toHaveLength(2)
    expect(store.recentItems[0]?.routeName).toBe('patient-detail')
  })

  it('caps the list at 5 entries', () => {
    const store = useSidebarPreferencesStore()
    for (let i = 0; i < 7; i += 1) {
      store.recordVisit({ routeName: 'patient-detail', params: { id: `p${i}` }, label: 'nav.patients', icon: 'pi pi-user' })
    }

    expect(store.recentItems).toHaveLength(5)
    expect(store.recentItems[0]?.params.id).toBe('p6')
  })

  it('upgrades a fallback label to a real name without touching other entries', () => {
    const store = useSidebarPreferencesStore()
    store.recordVisit({ routeName: 'patient-detail', params: { id: 'p1' }, label: 'nav.patients', icon: 'pi pi-user' })

    store.updateRecentLabel('patient-detail', { id: 'p1' }, 'Jane Doe')

    expect(store.recentItems[0]?.label).toBe('Jane Doe')
    expect(store.recentItems[0]?.isLiteral).toBe(true)
  })

  it('is a no-op when updating a label for an entry that scrolled out of the window', () => {
    const store = useSidebarPreferencesStore()

    expect(() => store.updateRecentLabel('patient-detail', { id: 'nonexistent' }, 'Anyone')).not.toThrow()
  })
})

describe('buildRecentItemFromRoute', () => {
  it('resolves icon and fallback label for a known detail route', () => {
    const entry = buildRecentItemFromRoute('patient-detail', { id: 'p1' })

    expect(entry).toEqual({
      routeName: 'patient-detail',
      params: { id: 'p1' },
      label: 'nav.patients',
      icon: 'pi pi-user',
    })
  })

  it('returns null for a route with no DETAIL_ROUTES mapping', () => {
    expect(buildRecentItemFromRoute('dashboard', {})).toBeNull()
  })
})
