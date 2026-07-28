import { mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { createMemoryHistory, createRouter } from 'vue-router'
import { describe, expect, it } from 'vitest'
import ReportsHomeView from './ReportsHomeView.vue'
import { useAuthStore } from '@/stores/auth'
import type { UserRole } from '@/types/user'

function makeRouter() {
  return createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/reports', name: 'reports', component: ReportsHomeView },
      { path: '/reports/production', name: 'report-production', component: { template: '<div />' } },
      { path: '/reports/collections', name: 'report-collections', component: { template: '<div />' } },
      { path: '/reports/ar-aging', name: 'report-ar-aging', component: { template: '<div />' } },
      { path: '/reports/appointments', name: 'report-appointments', component: { template: '<div />' } },
      {
        path: '/reports/treatment-plan-acceptance',
        name: 'report-treatment-plan-acceptance',
        component: { template: '<div />' },
      },
      { path: '/reports/new-patients', name: 'report-new-patients', component: { template: '<div />' } },
    ],
  })
}

async function mountAs(role: UserRole) {
  setActivePinia(createPinia())
  useAuthStore().user = { id: 'u1', name: 'Test User', email: 'u1@example.com', role }

  const router = makeRouter()
  router.push('/reports')
  await router.isReady()

  return mount(ReportsHomeView, { global: { plugins: [router] } })
}

describe('ReportsHomeView', () => {
  it('shows every report card for an admin, including financial ones', async () => {
    const wrapper = await mountAs('admin')

    expect(wrapper.text()).toContain('Production')
    expect(wrapper.text()).toContain('Collections')
    expect(wrapper.text()).toContain('A/R Aging')
    expect(wrapper.text()).toContain('Appointment Analytics')
    expect(wrapper.text()).toContain('Treatment Plan Acceptance')
    expect(wrapper.text()).toContain('New Patients')
  })

  it('hides financial report cards for a dentist', async () => {
    const wrapper = await mountAs('dentist')

    expect(wrapper.text()).not.toContain('Production')
    expect(wrapper.text()).not.toContain('Collections')
    expect(wrapper.text()).not.toContain('A/R Aging')
    expect(wrapper.text()).toContain('Appointment Analytics')
    expect(wrapper.text()).toContain('Treatment Plan Acceptance')
    expect(wrapper.text()).toContain('New Patients')
  })

  it('hides financial report cards for a receptionist', async () => {
    const wrapper = await mountAs('receptionist')

    expect(wrapper.text()).not.toContain('Production')
    expect(wrapper.text()).toContain('New Patients')
  })
})
