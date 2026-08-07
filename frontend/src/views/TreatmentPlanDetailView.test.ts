import { mount, flushPromises } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { createMemoryHistory, createRouter } from 'vue-router'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import TreatmentPlanDetailView from './TreatmentPlanDetailView.vue'
import { treatmentPlansApi } from '@/services/treatmentPlans'
import { dentalConditionsApi } from '@/services/dentalChart'
import { useTreatmentPlansStore } from '@/stores/treatmentPlans'
import { useAuthStore } from '@/stores/auth'
import type { TreatmentPlan, TreatmentPlanItem } from '@/types/treatmentPlan'

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
  treatmentPlanItemsApi: {
    create: vi.fn(),
    update: vi.fn(),
    complete: vi.fn(),
    cancel: vi.fn(),
    remove: vi.fn(),
  },
  isTreatmentPlanError: () => false,
  rethrowTreatmentPlanError: (error: unknown) => {
    throw error
  },
}))

vi.mock('@/services/dentalChart', () => ({
  dentalConditionsApi: { list: vi.fn(), create: vi.fn(), update: vi.fn(), remove: vi.fn() },
}))

const mockedPlansApi = vi.mocked(treatmentPlansApi)
const mockedConditionsApi = vi.mocked(dentalConditionsApi)

function makeItem(overrides: Partial<TreatmentPlanItem> = {}): TreatmentPlanItem {
  return {
    id: 'item-1',
    treatment_plan_id: 'plan-1',
    dental_condition_id: 'condition-1',
    procedure_name: 'Composite Filling',
    procedure_description: 'A tooth-colored resin filling.',
    diagnosis_entry_id: null,
    tooth_number: '16',
    surfaces: ['M'],
    quantity: 1,
    unit_cost: '150.00',
    estimated_cost: '150.00',
    phase: 1,
    sequence: null,
    status: 'planned',
    appointment_id: null,
    notes: null,
    completed_at: null,
    cancelled_at: null,
    created_at: '2026-07-20T09:00:00+00:00',
    updated_at: '2026-07-20T09:00:00+00:00',
    ...overrides,
  }
}

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
    estimated_cost: '150.00',
    dentist: { id: 'dentist-1', name: 'Dr. Layla Hassan' },
    created_by: { id: 'admin-1', name: 'Front Desk' },
    items: [makeItem()],
    ...overrides,
  }
}

function makePage(plans: TreatmentPlan[]) {
  return {
    data: plans,
    meta: { current_page: 1, last_page: 1, per_page: 15, total: plans.length },
  }
}

function makeRouter() {
  return createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/patients/:id', name: 'patient-detail', component: { template: '<div />' } },
      {
        path: '/patients/:id/treatment-plans/:planId',
        name: 'treatment-plan-detail',
        component: TreatmentPlanDetailView,
      },
    ],
  })
}

async function mountAt(planId: string, patientId = 'patient-1') {
  const router = makeRouter()
  router.push({ name: 'treatment-plan-detail', params: { id: patientId, planId } })
  await router.isReady()
  const wrapper = mount(TreatmentPlanDetailView, { global: { plugins: [router] } })
  await flushPromises()
  return wrapper
}

beforeEach(() => {
  setActivePinia(createPinia())
  vi.clearAllMocks()
  useAuthStore().user = { id: 'u1', name: 'Test User', email: 't@example.com', role: 'dentist' }
  mockedConditionsApi.list.mockResolvedValue([])
})

describe('TreatmentPlanDetailView — hydration', () => {
  it('hydrates from the store cache without an extra fetch when already loaded', async () => {
    mockedPlansApi.list.mockResolvedValue(makePage([makePlan()]))
    const store = useTreatmentPlansStore()
    await store.fetchForPatient('patient-1')

    const wrapper = await mountAt('plan-1')

    expect(mockedPlansApi.get).not.toHaveBeenCalled()
    expect(wrapper.text()).toContain('Option A')
  })

  it('fetches the plan when not already cached (direct/bookmarked navigation)', async () => {
    mockedPlansApi.get.mockResolvedValue(makePlan())

    const wrapper = await mountAt('plan-1')

    expect(mockedPlansApi.get).toHaveBeenCalledWith('plan-1')
    expect(wrapper.text()).toContain('Option A')
  })

  it('shows the not-found message when the plan fails to load', async () => {
    mockedPlansApi.get.mockRejectedValue({ response: { status: 404 } })

    const wrapper = await mountAt('missing')

    expect(wrapper.text()).toContain('Treatment plan not found')
  })
})

describe('TreatmentPlanDetailView — overview', () => {
  it('renders plan metadata, status, and cost summary', async () => {
    mockedPlansApi.get.mockResolvedValue(
      makePlan({ status: 'presented', presented_at: '2026-07-21T10:00:00+00:00' }),
    )

    const wrapper = await mountAt('plan-1')

    expect(wrapper.text()).toContain('Dr. Layla Hassan')
    expect(wrapper.text()).toContain('Front Desk')
    expect(wrapper.text()).toContain('Presented')
    expect(wrapper.text()).toContain('150.00')
    expect(wrapper.text()).toContain('Estimate only')
  })
})

describe('TreatmentPlanDetailView — revision relationship', () => {
  it('shows a superseded-by banner when the plan has been replaced', async () => {
    mockedPlansApi.get.mockResolvedValue(
      makePlan({
        status: 'rejected',
        superseded_by_plan_id: 'plan-2',
        superseded_by_plan: { id: 'plan-2', title: 'Option B', status: 'draft' },
      }),
    )

    const wrapper = await mountAt('plan-1')

    expect(wrapper.text()).toContain('replaced by "Option B"')
  })

  it('shows a supersedes banner when a cached predecessor points at this plan', async () => {
    mockedPlansApi.list.mockResolvedValue(
      makePage([
        makePlan({ id: 'plan-1', title: 'Option A (original)', superseded_by_plan_id: 'plan-2' }),
        makePlan({ id: 'plan-2', title: 'Option A (revised)', superseded_by_plan_id: null }),
      ]),
    )
    const store = useTreatmentPlansStore()
    await store.fetchForPatient('patient-1')

    const wrapper = await mountAt('plan-2')

    expect(wrapper.text()).toContain('replaces "Option A (original)"')
  })
})

describe('TreatmentPlanDetailView — items', () => {
  it('renders the item table with procedure, tooth, quantity, cost, and status', async () => {
    mockedPlansApi.get.mockResolvedValue(makePlan())

    const wrapper = await mountAt('plan-1')

    expect(wrapper.text()).toContain('Composite Filling')
    expect(wrapper.text()).toContain('16')
    expect(wrapper.text()).toContain('150.00')
    expect(wrapper.text()).toContain('Planned')
  })

  it('shows the empty state when the plan has no items', async () => {
    mockedPlansApi.get.mockResolvedValue(makePlan({ items: [] }))

    const wrapper = await mountAt('plan-1')

    expect(wrapper.text()).toContain('No items in this plan yet.')
  })

  it('shows a linked-diagnosis indicator for an item that references one', async () => {
    mockedPlansApi.get.mockResolvedValue(
      makePlan({
        items: [
          makeItem({
            diagnosis_entry_id: 'entry-1',
            diagnosis_entry: { id: 'entry-1', tooth_number: '16', status: 'active' },
          }),
        ],
      }),
    )

    const wrapper = await mountAt('plan-1')

    expect(wrapper.text()).toContain('Diagnosis: Tooth 16')
  })
})

describe('TreatmentPlanDetailView — status actions', () => {
  it('shows the Present action for a draft plan (dentist) and lets it run', async () => {
    mockedPlansApi.get.mockResolvedValue(makePlan({ status: 'draft' }))
    mockedPlansApi.present.mockResolvedValue(makePlan({ status: 'presented' }))

    const wrapper = await mountAt('plan-1')
    expect(wrapper.findAll('button').some((b) => b.text() === 'Present')).toBe(true)

    await wrapper
      .findAll('button')
      .find((b) => b.text() === 'Present')!
      .trigger('click')
    await flushPromises()

    expect(mockedPlansApi.present).toHaveBeenCalledWith('plan-1')
  })

  it('renders no status-transition controls for a receptionist', async () => {
    useAuthStore().user = { id: 'u1', name: 'Front Desk', email: 'fd@example.com', role: 'receptionist' }
    mockedPlansApi.get.mockResolvedValue(makePlan({ status: 'draft' }))

    const wrapper = await mountAt('plan-1')

    expect(
      wrapper.findAll('button').every((b) => !/present|accept|reject|start|complete|cancel/i.test(b.text())),
    ).toBe(true)
  })
})

describe('TreatmentPlanDetailView — item management', () => {
  it('shows an Add Item button only while the plan is a draft', async () => {
    mockedPlansApi.get.mockResolvedValue(makePlan({ status: 'draft' }))
    const draftWrapper = await mountAt('plan-1')
    expect(draftWrapper.text()).toContain('Add Item')

    mockedPlansApi.get.mockResolvedValue(makePlan({ id: 'plan-2', status: 'presented' }))
    const presentedWrapper = await mountAt('plan-2')
    expect(presentedWrapper.text()).not.toContain('Add Item')
  })

  it('opens the item dialog in edit mode from the table row', async () => {
    mockedPlansApi.get.mockResolvedValue(makePlan())

    const wrapper = await mountAt('plan-1')
    await wrapper.find('button[aria-label="Edit"]').trigger('click')
    await flushPromises()

    expect(wrapper.text()).toContain('Edit Item')
  })
})
