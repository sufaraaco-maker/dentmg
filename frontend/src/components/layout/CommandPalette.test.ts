import { mount, flushPromises } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { createMemoryHistory, createRouter, type Router } from 'vue-router'
import { beforeEach, describe, expect, it } from 'vitest'
import CommandPalette from './CommandPalette.vue'
import { useAuthStore } from '@/stores/auth'
import { useCommandPaletteStore } from '@/stores/commandPalette'
import type { UserRole } from '@/types/user'

function makeRouter(): Router {
  return createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/', name: 'dashboard', component: { template: '<div />' } },
      { path: '/patients', name: 'patients', component: { template: '<div />' } },
      { path: '/users', name: 'users', component: { template: '<div />' } },
    ],
  })
}

async function mountPalette(role: UserRole) {
  const router = makeRouter()
  await router.push('/')
  await router.isReady()

  const auth = useAuthStore()
  auth.user = { id: 'u1', name: 'Test User', email: 'u1@example.com', role }

  const commandPalette = useCommandPaletteStore()
  commandPalette.show()

  const wrapper = mount(CommandPalette, { global: { plugins: [router] } })
  await flushPromises()
  return { wrapper, router, commandPalette }
}

beforeEach(() => {
  setActivePinia(createPinia())
  localStorage.clear()
})

describe('CommandPalette', () => {
  it('lists role-visible navigation actions when opened', async () => {
    const { wrapper } = await mountPalette('admin')
    expect(wrapper.text()).toContain('Go to Users')
  })

  it('hides actions restricted to a role the current user does not have', async () => {
    const { wrapper } = await mountPalette('dentist')
    expect(wrapper.text()).not.toContain('Go to Users')
  })

  it('filters the action list as the user types', async () => {
    const { wrapper } = await mountPalette('admin')
    const input = wrapper.get('input')

    await input.setValue('Patients')

    expect(wrapper.text()).toContain('Go to Patients')
    expect(wrapper.text()).not.toContain('Go to Users')
  })

  it('shows an empty state when nothing matches', async () => {
    const { wrapper } = await mountPalette('admin')
    await wrapper.get('input').setValue('xyz-no-match')

    expect(wrapper.text()).toContain('No results found')
  })

  it('navigates and closes the palette on Enter', async () => {
    const { wrapper, router, commandPalette } = await mountPalette('admin')
    const input = wrapper.get('input')

    await input.setValue('Patients')
    await input.trigger('keydown', { key: 'Enter' })
    await flushPromises()

    expect(router.currentRoute.value.name).toBe('patients')
    expect(commandPalette.open).toBe(false)
  })

  it('moves the active selection with arrow keys', async () => {
    const { wrapper } = await mountPalette('admin')
    const input = wrapper.get('input')

    await input.trigger('keydown', { key: 'ArrowDown' })
    const options = wrapper.findAll('[role="option"]')
    expect(options[1]?.attributes('aria-selected')).toBe('true')
  })

  it('offers a "New Patient" quick action to admin/receptionist but not dentist', async () => {
    // Exact-text match on each option row, not a substring check on the whole palette — "New
    // Patient" (this quick action) is a substring of "Go to New Patients" (the unrelated New
    // Patients report, visible to every role), so a loose `.toContain` assertion here would pass
    // even if this quick action were never rendered at all.
    const hasNewPatientAction = (wrapper: { findAll: (selector: string) => { text: () => string }[] }) =>
      wrapper.findAll('[role="option"]').some((option) => option.text() === 'New Patient')

    const admin = await mountPalette('admin')
    expect(hasNewPatientAction(admin.wrapper)).toBe(true)

    const dentist = await mountPalette('dentist')
    expect(hasNewPatientAction(dentist.wrapper)).toBe(false)
  })
})
