import { mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { createMemoryHistory, createRouter, type Router } from 'vue-router'
import { beforeEach, describe, expect, it } from 'vitest'
import AppSidebar from './AppSidebar.vue'
import { useAuthStore } from '@/stores/auth'
import { useUiStore } from '@/stores/ui'
import type { AuthUser, UserRole } from '@/types/user'

function makeUser(role: UserRole): AuthUser {
  return { id: 'u1', name: 'Test User', email: 'test@example.com', role }
}

function makeRouter(): Router {
  return createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/', name: 'dashboard', component: { template: '<div />' } },
      { path: '/patients', name: 'patients', component: { template: '<div />' } },
      { path: '/appointments', name: 'appointments', component: { template: '<div />' } },
      { path: '/appointments/types', name: 'appointment-types', component: { template: '<div />' } },
      { path: '/appointments/schedule', name: 'dentist-schedule', component: { template: '<div />' } },
      { path: '/users', name: 'users', component: { template: '<div />' } },
      { path: '/settings', name: 'settings', component: { template: '<div />' } },
      // Billing is a real `RouterLink` now (no longer `comingSoon`) — `useLink` resolves it
      // eagerly on render, so every test in this file needs a matching route or mounting itself
      // throws, not just the tests that specifically assert on Billing.
      { path: '/invoices', name: 'invoices', component: { template: '<div />' } },
    ],
  })
}

beforeEach(() => {
  setActivePinia(createPinia())
  // `sidebarPreferences` is backed by real `localStorage` (`@vueuse/core`'s `useLocalStorage`),
  // which jsdom does not reset between `it()` blocks the way a fresh Pinia instance resets
  // in-memory state — without this, a favorite/recent toggled in one test would leak into the next.
  localStorage.clear()
})

async function mountSidebar(role: UserRole, variant: 'desktop' | 'drawer' = 'desktop') {
  const router = makeRouter()
  await router.push('/')
  await router.isReady()

  const auth = useAuthStore()
  auth.user = makeUser(role)

  const wrapper = mount(AppSidebar, {
    props: { variant },
    global: { plugins: [router] },
  })
  return { wrapper, router }
}

describe('AppSidebar role-based visibility', () => {
  it('shows the Users item to an admin', async () => {
    const { wrapper } = await mountSidebar('admin')
    expect(wrapper.text()).toContain('Users')
  })

  it('hides the Users item from a dentist', async () => {
    const { wrapper } = await mountSidebar('dentist')
    expect(wrapper.text()).not.toContain('Users')
  })

  it('hides the Users item from a receptionist', async () => {
    const { wrapper } = await mountSidebar('receptionist')
    expect(wrapper.text()).not.toContain('Users')
  })

  it('always shows always-on items regardless of role', async () => {
    const { wrapper } = await mountSidebar('dentist')
    expect(wrapper.text()).toContain('Dashboard')
    expect(wrapper.text()).toContain('Patients')
    expect(wrapper.text()).toContain('Appointments')
  })

  it('renders unbuilt modules as visible "Soon" items, not fake links', async () => {
    const { wrapper } = await mountSidebar('admin')
    expect(wrapper.text()).toContain('Dental Chart')
    expect(wrapper.text()).toContain('Treatment Plans')
    expect(wrapper.findAll('.pi-clipboard').length).toBeGreaterThan(0)
    // Coming-soon items render no navigable target for their own row.
    const links = wrapper.findAll('a').map((a) => a.text())
    expect(links.some((text) => text.includes('Treatment Plans'))).toBe(false)
  })

  it('renders Billing as a real navigable link, not a "Soon" placeholder', async () => {
    const { wrapper } = await mountSidebar('admin')
    const links = wrapper.findAll('a').map((a) => a.text())
    expect(links.some((text) => text.includes('Billing'))).toBe(true)
  })
})

describe('AppSidebar sections', () => {
  it('groups items under collapsible section headers', async () => {
    const { wrapper } = await mountSidebar('admin')
    expect(wrapper.text()).toContain('Operations')
    expect(wrapper.text()).toContain('Admin')
  })

  it('collapses a section when its header is clicked, hiding its items', async () => {
    const { wrapper } = await mountSidebar('admin')
    expect(wrapper.text()).toContain('Billing')

    const headers = wrapper.findAll('button').filter((b) => b.text() === 'Operations')
    await headers[0]?.trigger('click')

    expect(wrapper.text()).not.toContain('Billing')
  })
})

describe('AppSidebar favorites', () => {
  it('shows a Favorites group once an item is favorited', async () => {
    const { wrapper } = await mountSidebar('admin')
    expect(wrapper.text()).not.toContain('Favorites')

    const favoriteButtons = wrapper
      .findAll('button[aria-label]')
      .filter((b) => b.attributes('aria-label') === 'Add to favorites')
    await favoriteButtons[0]?.trigger('click')

    expect(wrapper.text()).toContain('Favorites')
  })
})

describe('AppSidebar recent items', () => {
  it('shows a Recent group once a visit is recorded', async () => {
    const { wrapper } = await mountSidebar('admin')
    expect(wrapper.text()).not.toContain('Recent')

    const { useSidebarPreferencesStore } = await import('@/stores/sidebarPreferences')
    useSidebarPreferencesStore().recordVisit({
      routeName: 'patients',
      params: {},
      label: 'nav.patients',
      icon: 'pi pi-users',
    })
    await wrapper.vm.$nextTick()

    expect(wrapper.text()).toContain('Recent')
  })
})

describe('AppSidebar mobile drawer host', () => {
  it('closes the mobile drawer in the ui store when a nav item is clicked', async () => {
    const { wrapper } = await mountSidebar('admin', 'drawer')
    const ui = useUiStore()
    ui.openMobileSidebar()
    expect(ui.mobileSidebarOpen).toBe(true)

    await wrapper.get('a').trigger('click')
    expect(ui.mobileSidebarOpen).toBe(false)
  })

  it('never renders the icon-only collapsed rail in the drawer variant', async () => {
    const { wrapper } = await mountSidebar('admin', 'drawer')
    const ui = useUiStore()
    ui.sidebarCollapsed = true
    await wrapper.vm.$nextTick()
    expect(wrapper.text()).toContain('Dashboard')
  })
})
