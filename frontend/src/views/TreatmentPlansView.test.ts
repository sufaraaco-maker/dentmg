import { mount, flushPromises } from '@vue/test-utils'
import { createMemoryHistory, createRouter, type Router } from 'vue-router'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import TreatmentPlansView from './TreatmentPlansView.vue'
import { treatmentPlansApi } from '@/services/treatmentPlans'
import type { TreatmentPlan } from '@/types/treatmentPlan'

vi.mock('@/services/treatmentPlans', () => ({
  treatmentPlansApi: { listAll: vi.fn() },
}))

const mockedApi = vi.mocked(treatmentPlansApi)

function makePlan(overrides: Partial<TreatmentPlan> = {}): TreatmentPlan {
  return {
    id: 'tp-1',
    patient_id: 'p1',
    dentist_id: 'u2',
    created_by_id: 'u1',
    title: 'Option A — Full Mouth Implants',
    status: 'accepted',
    notes: null,
    presented_at: '2026-07-01T00:00:00+00:00',
    accepted_at: '2026-07-02T00:00:00+00:00',
    rejected_at: null,
    started_at: null,
    completed_at: null,
    cancelled_at: null,
    superseded_by_plan_id: null,
    created_at: '2026-07-01T00:00:00+00:00',
    updated_at: '2026-07-01T00:00:00+00:00',
    estimated_cost: '1500.00',
    patient: { id: 'p1', first_name: 'Jane', last_name: 'Doe' },
    ...overrides,
  }
}

function makeRouter(): Router {
  return createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/treatment-plans', name: 'treatment-plans', component: TreatmentPlansView },
      {
        path: '/patients/:id/treatment-plans/:planId',
        name: 'treatment-plan-detail',
        component: { template: '<div />' },
      },
    ],
  })
}

async function mountView() {
  const router = makeRouter()
  await router.push('/treatment-plans')
  await router.isReady()
  const wrapper = mount(TreatmentPlansView, { global: { plugins: [router] } })
  await flushPromises()
  return { wrapper, router }
}

beforeEach(() => {
  vi.clearAllMocks()
})

describe('TreatmentPlansView', () => {
  it('fetches and renders the clinic-wide treatment plan list, including the patient name', async () => {
    mockedApi.listAll.mockResolvedValue({
      data: [makePlan()],
      meta: { current_page: 1, last_page: 1, per_page: 15, total: 1 },
    })

    const { wrapper } = await mountView()

    expect(mockedApi.listAll).toHaveBeenCalled()
    expect(wrapper.text()).toContain('Jane Doe')
    expect(wrapper.text()).toContain('Option A — Full Mouth Implants')
  })

  it('re-fetches with a status filter when the status Select changes', async () => {
    mockedApi.listAll.mockResolvedValue({
      data: [],
      meta: { current_page: 1, last_page: 1, per_page: 15, total: 0 },
    })
    const { wrapper } = await mountView()
    mockedApi.listAll.mockClear()

    // Directly exercise the component's own status ref via its Select v-model rather than
    // simulating a full PrimeVue Select click sequence (its overlay is teleported/portal-based
    // and brittle to drive in jsdom) — this asserts the same re-fetch wiring `watch(status, ...)`
    // provides.
    await wrapper.findComponent({ name: 'Select' }).vm.$emit('update:modelValue', 'accepted')
    await flushPromises()

    expect(mockedApi.listAll).toHaveBeenCalledWith(expect.objectContaining({ status: 'accepted' }))
  })

  it('navigates to the patient-scoped treatment plan detail route on row click', async () => {
    mockedApi.listAll.mockResolvedValue({
      data: [makePlan()],
      meta: { current_page: 1, last_page: 1, per_page: 15, total: 1 },
    })
    const { wrapper, router } = await mountView()

    await wrapper.get('tbody tr').trigger('click')
    await flushPromises()

    expect(router.currentRoute.value.name).toBe('treatment-plan-detail')
    expect(router.currentRoute.value.params).toEqual({ id: 'p1', planId: 'tp-1' })
  })

  it('shows the empty state when no treatment plans match', async () => {
    mockedApi.listAll.mockResolvedValue({
      data: [],
      meta: { current_page: 1, last_page: 1, per_page: 15, total: 0 },
    })

    const { wrapper } = await mountView()

    expect(wrapper.text()).toContain('No treatment plans found')
  })
})
