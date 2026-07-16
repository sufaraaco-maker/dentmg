import { flushPromises, mount } from '@vue/test-utils'
import { createMemoryHistory, createRouter, type Router } from 'vue-router'
import { describe, expect, it } from 'vitest'
import AppSidebarItem from './AppSidebarItem.vue'
import type { NavItem } from '@/config/navigation'

function makeRouter(): Router {
  return createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/', name: 'dashboard', component: { template: '<div />' } },
      { path: '/appointments', name: 'appointments', component: { template: '<div />' } },
      { path: '/appointments/types', name: 'appointment-types', component: { template: '<div />' } },
      { path: '/appointments/schedule', name: 'dentist-schedule', component: { template: '<div />' } },
    ],
  })
}

async function mountItem(item: NavItem, options: { collapsed?: boolean; path?: string } = {}) {
  const router = makeRouter()
  await router.push(options.path ?? '/')
  await router.isReady()
  const wrapper = mount(AppSidebarItem, {
    props: { item, collapsed: options.collapsed ?? false },
    global: { plugins: [router] },
  })
  return { wrapper, router }
}

const dashboardItem: NavItem = { labelKey: 'nav.dashboard', icon: 'pi pi-home', routeName: 'dashboard' }

const comingSoonItem: NavItem = { labelKey: 'nav.billing', icon: 'pi pi-wallet', comingSoon: true }

const appointmentsItem: NavItem = {
  labelKey: 'nav.appointments',
  icon: 'pi pi-calendar',
  routeName: 'appointments',
  children: [
    { labelKey: 'appointments.nav.board', icon: 'pi pi-calendar', routeName: 'appointments' },
    { labelKey: 'appointments.nav.types', icon: 'pi pi-tags', routeName: 'appointment-types' },
  ],
}

describe('AppSidebarItem', () => {
  it('renders a router link for a plain item and labels it with the i18n text', async () => {
    const { wrapper } = await mountItem(dashboardItem)
    const link = wrapper.get('a')
    expect(link.text()).toContain('Dashboard')
    expect(link.attributes('href')).toBe('/')
  })

  it('applies the active styling when the current route matches', async () => {
    const { wrapper } = await mountItem(dashboardItem, { path: '/' })
    expect(wrapper.get('a').classes()).toContain('text-primary')
  })

  it('renders coming-soon items as disabled, with a badge and no link target', async () => {
    const { wrapper } = await mountItem(comingSoonItem)
    expect(wrapper.find('a').exists()).toBe(false)
    expect(wrapper.find('button').exists()).toBe(false)
    const row = wrapper.get('[aria-disabled="true"]')
    expect(row.text()).toContain('Billing')
    expect(row.text()).toContain('Soon')
  })

  it('expands to show its children when the parent row is clicked', async () => {
    const { wrapper } = await mountItem(appointmentsItem, { path: '/' })
    expect(wrapper.findAll('a')).toHaveLength(0)
    await wrapper.get('button').trigger('click')
    const links = wrapper.findAll('a')
    expect(links).toHaveLength(2)
    expect(links[0]?.text()).toContain('Calendar')
    expect(links[1]?.text()).toContain('Appointment Types')
  })

  it('auto-expands when a child route is already active', async () => {
    const { wrapper } = await mountItem(appointmentsItem, { path: '/appointments/types' })
    expect(wrapper.findAll('a')).toHaveLength(2)
  })

  it('navigates to the group route instead of expanding when the rail is collapsed', async () => {
    const { wrapper, router } = await mountItem(appointmentsItem, { collapsed: true, path: '/' })
    await wrapper.get('button').trigger('click')
    await flushPromises()
    expect(wrapper.emitted('navigate')).toBeTruthy()
    expect(router.currentRoute.value.name).toBe('appointments')
    expect(wrapper.findAll('a')).toHaveLength(0)
  })
})
