import { mount, flushPromises } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { createMemoryHistory, createRouter } from 'vue-router'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import PatientTreatmentPlansPanel from './PatientTreatmentPlansPanel.vue'
import CreateTreatmentPlanDialog from './CreateTreatmentPlanDialog.vue'
import { treatmentPlansApi } from '@/services/treatmentPlans'
import { providersApi } from '@/services/appointments'
import { useAuthStore } from '@/stores/auth'
import type { TreatmentPlan } from '@/types/treatmentPlan'
import type { UserRole } from '@/types/user'

vi.mock('@/services/treatmentPlans', () => ({
  treatmentPlansApi: {
    list: vi.fn(),
    create: vi.fn(),
    get: vi.fn(),
    update: vi.fn(),
    present: vi.fn(),
    accept: vi.fn(),
    reject: vi.fn(),
    start: vi.fn(),
    complete: vi.fn(),
    cancel: vi.fn(),
    createRevision: vi.fn(),
    remove: vi.fn(),
  },
  treatmentPlanItemsApi: { create: vi.fn(), update: vi.fn(), complete: vi.fn(), cancel: vi.fn(), remove: vi.fn() },
  isTreatmentPlanError: () => false,
  rethrowTreatmentPlanError: (error: unknown) => {
    throw error
  },
}))

// CreateTreatmentPlanDialog mounts DentistSelect, which pulls in the providers store.
vi.mock('@/services/appointments', () => ({ providersApi: { listAll: vi.fn() } }))

const mockedPlansApi = vi.mocked(treatmentPlansApi)
const mockedProvidersApi = vi.mocked(providersApi)

function makePlan(overrides: Partial<TreatmentPlan> = {}): TreatmentPlan {
  return {
    id: 'plan-1',
    patient_id: 'patient-1',
    dentist_id: 'dentist-1',
    created_by_id: 'admin-1',
    title: 'Option A',
    status: 'draft',
    notes: null,
    presented_at: null,
    accepted_at: null,
    rejected_at: null,
    started_at: null,
    completed_at: null,
    cancelled_at: null,
    superseded_by_plan_id: null,
    created_at: '2026-07-20T09:00:00+00:00',
    updated_at: '2026-07-20T09:00:00+00:00',
    estimated_cost: '0.00',
    dentist: { id: 'dentist-1', name: 'Dr. Layla Hassan' },
    items: [],
    ...overrides,
  }
}

function setRole(role: UserRole) {
  const auth = useAuthStore()
  auth.user = { id: 'u1', name: 'Test User', email: 't@example.com', role }
}

function makeRouter() {
  return createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/patients/:id', name: 'patient-detail', component: { template: '<div />' } },
      {
        path: '/patients/:id/treatment-plans/:planId',
        name: 'treatment-plan-detail',
        component: { template: '<div />' },
      },
    ],
  })
}

async function mountPanel(patientId = 'patient-1') {
  const router = makeRouter()
  await router.push({ name: 'patient-detail', params: { id: patientId } })
  await router.isReady()
  const wrapper = mount(PatientTreatmentPlansPanel, {
    props: { patientId },
    global: { plugins: [router] },
  })
  await flushPromises()
  return { wrapper, router }
}

beforeEach(() => {
  setActivePinia(createPinia())
  vi.clearAllMocks()
  mockedProvidersApi.listAll.mockResolvedValue([])
  mockedPlansApi.list.mockResolvedValue([])
})

describe('PatientTreatmentPlansPanel — data loading', () => {
  it("fetches this patient's plans on mount", async () => {
    setRole('dentist')
    await mountPanel('patient-1')

    expect(mockedPlansApi.list).toHaveBeenCalledWith('patient-1')
  })

  it('re-fetches when patientId changes', async () => {
    setRole('dentist')
    const { wrapper } = await mountPanel('patient-1')
    await wrapper.setProps({ patientId: 'patient-2' })
    await flushPromises()

    expect(mockedPlansApi.list).toHaveBeenCalledWith('patient-2')
  })

  it('shows the empty state when the patient has no plans', async () => {
    setRole('dentist')
    const { wrapper } = await mountPanel('patient-1')

    expect(wrapper.text()).toContain('No treatment plans for this patient yet.')
  })

  it('renders the list table once plans are loaded', async () => {
    setRole('dentist')
    mockedPlansApi.list.mockResolvedValue([makePlan()])
    const { wrapper } = await mountPanel('patient-1')

    expect(wrapper.text()).toContain('Option A')
  })
})

describe('PatientTreatmentPlansPanel — permissions', () => {
  it('shows the New Treatment Plan button for a dentist', async () => {
    setRole('dentist')
    const { wrapper } = await mountPanel('patient-1')

    expect(wrapper.text()).toContain('New Treatment Plan')
  })

  it('shows the New Treatment Plan button for an admin', async () => {
    setRole('admin')
    const { wrapper } = await mountPanel('patient-1')

    expect(wrapper.text()).toContain('New Treatment Plan')
  })

  it('hides the New Treatment Plan button for a receptionist', async () => {
    setRole('receptionist')
    const { wrapper } = await mountPanel('patient-1')

    expect(wrapper.text()).not.toContain('New Treatment Plan')
  })
})

describe('PatientTreatmentPlansPanel — dialog wiring', () => {
  it('opens the create dialog from the New Treatment Plan button', async () => {
    setRole('dentist')
    const { wrapper } = await mountPanel('patient-1')

    await wrapper.find('button').trigger('click')

    const dialog = wrapper.findComponent(CreateTreatmentPlanDialog)
    expect(dialog.props('visible')).toBe(true)
    expect(dialog.props('patientId')).toBe('patient-1')
  })

  it('closes the dialog on save without an extra list() refetch (relies on the shared cache)', async () => {
    setRole('dentist')
    const { wrapper } = await mountPanel('patient-1')
    expect(mockedPlansApi.list).toHaveBeenCalledTimes(1)

    await wrapper.find('button').trigger('click')
    const dialog = wrapper.findComponent(CreateTreatmentPlanDialog)
    expect(dialog.props('visible')).toBe(true)

    await dialog.vm.$emit('saved', makePlan())
    await flushPromises()

    expect(dialog.props('visible')).toBe(false)
    expect(mockedPlansApi.list).toHaveBeenCalledTimes(1)
  })
})

describe('PatientTreatmentPlansPanel — navigation', () => {
  it('navigates to the Plan Detail route when a row is clicked', async () => {
    setRole('dentist')
    mockedPlansApi.list.mockResolvedValue([makePlan()])
    const { wrapper, router } = await mountPanel('patient-1')
    const push = vi.spyOn(router, 'push')

    await wrapper.find('tbody tr').trigger('click')

    expect(push).toHaveBeenCalledWith({
      name: 'treatment-plan-detail',
      params: { id: 'patient-1', planId: 'plan-1' },
    })
  })
})
