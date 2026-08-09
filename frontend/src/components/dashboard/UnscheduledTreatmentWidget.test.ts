import { mount } from '@vue/test-utils'
import { createMemoryHistory, createRouter, type Router } from 'vue-router'
import { describe, expect, it, vi } from 'vitest'
import UnscheduledTreatmentWidget from './UnscheduledTreatmentWidget.vue'
import type { DashboardSummary } from '@/types/dashboard'

function makeData(
  overrides: Partial<DashboardSummary['unscheduled_accepted_treatment']> = {},
): DashboardSummary['unscheduled_accepted_treatment'] {
  return {
    count: 1,
    items: [
      {
        patient: 'Layla Hassan',
        patient_id: 'patient-1',
        treatment_plan_id: 'plan-1',
        item_description: 'Root Canal Treatment',
        accepted_at: '2026-08-01T10:00:00+00:00',
      },
    ],
    ...overrides,
  }
}

function makeRouter(): Router {
  return createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/patients/:id', name: 'patient-detail', component: { template: '<div />' } },
      { path: '/', name: 'dashboard', component: { template: '<div />' } },
    ],
  })
}

async function mountWidget(data: DashboardSummary['unscheduled_accepted_treatment'] | null, loading = false) {
  const router = makeRouter()
  await router.push({ name: 'dashboard' })
  await router.isReady()
  const wrapper = mount(UnscheduledTreatmentWidget, {
    props: { data, loading },
    global: { plugins: [router] },
  })
  return { wrapper, router }
}

describe('UnscheduledTreatmentWidget — loading', () => {
  it('shows skeletons while loading with no data yet', async () => {
    const { wrapper } = await mountWidget(null, true)

    expect(wrapper.findComponent({ name: 'Skeleton' }).exists()).toBe(true)
  })
})

describe('UnscheduledTreatmentWidget — empty state', () => {
  it('renders the empty state when count is 0', async () => {
    const { wrapper } = await mountWidget(makeData({ count: 0, items: [] }))

    expect(wrapper.text()).toContain('Nothing outstanding')
  })
})

describe('UnscheduledTreatmentWidget — rendering', () => {
  it('renders each item with patient name and procedure', async () => {
    const { wrapper } = await mountWidget(makeData())

    expect(wrapper.text()).toContain('Layla Hassan')
    expect(wrapper.text()).toContain('Root Canal Treatment')
  })

  it('navigates to the treatment plans tab when "View plan" is clicked', async () => {
    const { wrapper, router } = await mountWidget(makeData())
    const pushSpy = vi.spyOn(router, 'push')

    await wrapper.find('button').trigger('click')

    expect(pushSpy).toHaveBeenCalledWith({
      name: 'patient-detail',
      params: { id: 'patient-1' },
      query: { tab: 'treatmentPlans' },
    })
  })
})
