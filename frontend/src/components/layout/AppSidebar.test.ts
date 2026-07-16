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
    ],
  })
}

beforeEach(() => {
  setActivePinia(createPinia())
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
    expect(wrapper.text()).toContain('Billing')
    expect(wrapper.findAll('.pi-wallet').length).toBeGreaterThan(0)
    // Coming-soon items render no navigable target for their own row.
    const links = wrapper.findAll('a').map((a) => a.text())
    expect(links.some((text) => text.includes('Billing'))).toBe(false)
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
