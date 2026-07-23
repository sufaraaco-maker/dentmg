import { mount, flushPromises } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { useConfirm } from 'primevue/useconfirm'
import TreatmentPlanItemsTable from './TreatmentPlanItemsTable.vue'
import { treatmentPlanItemsApi, treatmentPlansApi } from '@/services/treatmentPlans'
import { useAuthStore } from '@/stores/auth'
import type { TreatmentPlanItem } from '@/types/treatmentPlan'

vi.mock('primevue/useconfirm', () => ({ useConfirm: vi.fn() }))

vi.mock('@/services/treatmentPlans', async () => {
  const actual = await vi.importActual<typeof import('@/services/treatmentPlans')>('@/services/treatmentPlans')
  return {
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
    isTreatmentPlanError: actual.isTreatmentPlanError,
    rethrowTreatmentPlanError: actual.rethrowTreatmentPlanError,
  }
})

const mockedItemsApi = vi.mocked(treatmentPlanItemsApi)
const mockedPlansApi = vi.mocked(treatmentPlansApi)

function makeItem(overrides: Partial<TreatmentPlanItem> = {}): TreatmentPlanItem {
  return {
    id: 'item-1',
    treatment_plan_id: 'plan-1',
    dental_condition_id: 'condition-1',
    procedure_name: 'Composite Filling',
    procedure_description: 'A tooth-colored resin filling.',
    diagnosis_entry_id: null,
    tooth_number: '16',
    surfaces: ['M', 'O'],
    quantity: 2,
    unit_cost: '150.00',
    estimated_cost: '300.00',
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

function setRole(role: 'admin' | 'dentist' | 'receptionist') {
  const auth = useAuthStore()
  auth.user = { id: 'u1', name: 'Test User', email: 't@example.com', role }
}

beforeEach(() => {
  setActivePinia(createPinia())
  vi.clearAllMocks()
  vi.mocked(useConfirm).mockReturnValue({ require: vi.fn() } as unknown as ReturnType<typeof useConfirm>)
})

describe('TreatmentPlanItemsTable — rendering', () => {
  it('renders procedure, tooth, surfaces, quantity, unit cost, subtotal, and status', () => {
    const wrapper = mount(TreatmentPlanItemsTable, { props: { items: [makeItem()] } })

    expect(wrapper.text()).toContain('Composite Filling')
    expect(wrapper.text()).toContain('A tooth-colored resin filling.')
    expect(wrapper.text()).toContain('16')
    expect(wrapper.text()).toContain('2')
    expect(wrapper.text()).toContain('150.00')
    expect(wrapper.text()).toContain('300.00')
    expect(wrapper.text()).toContain('Planned')
  })

  it('falls back to a dash when tooth/surfaces are absent (a non-tooth-specific procedure)', () => {
    const wrapper = mount(TreatmentPlanItemsTable, {
      props: { items: [makeItem({ tooth_number: null, surfaces: null })] },
    })

    expect(wrapper.text()).toContain('—')
  })

  it('shows a diagnosis-link indicator when the item references a diagnosis entry', () => {
    const wrapper = mount(TreatmentPlanItemsTable, {
      props: {
        items: [
          makeItem({
            diagnosis_entry_id: 'entry-1',
            diagnosis_entry: { id: 'entry-1', tooth_number: '16', status: 'active' },
          }),
        ],
      },
    })

    expect(wrapper.text()).toContain('Diagnosis: Tooth 16');
  })

  it('shows an appointment indicator when the item is linked to one', () => {
    const wrapper = mount(TreatmentPlanItemsTable, {
      props: {
        items: [
          makeItem({
            appointment_id: 'appt-1',
            appointment: { id: 'appt-1', start_at: '2026-08-01T09:00:00+00:00', status: 'scheduled' },
          }),
        ],
      },
    })

    expect(wrapper.text()).toContain('Scheduled')
  })

  it('shows the localized empty state when there are no items', () => {
    const wrapper = mount(TreatmentPlanItemsTable, { props: { items: [] } })
    expect(wrapper.text()).toContain('No items in this plan yet.')
  })
})

describe('TreatmentPlanItemsTable — row action visibility', () => {
  it('shows edit/complete/cancel but not delete for a dentist on a planned item', () => {
    setRole('dentist')
    const wrapper = mount(TreatmentPlanItemsTable, { props: { items: [makeItem({ status: 'planned' })] } })

    expect(wrapper.find('button[aria-label="Edit"]').exists()).toBe(true)
    expect(wrapper.find('button[aria-label="Complete Item"]').exists()).toBe(true)
    expect(wrapper.find('button[aria-label="Cancel Item"]').exists()).toBe(true)
    expect(wrapper.find('button[aria-label="Delete"]').exists()).toBe(false)
  })

  it('shows delete for an admin', () => {
    setRole('admin')
    const wrapper = mount(TreatmentPlanItemsTable, { props: { items: [makeItem({ status: 'planned' })] } })

    expect(wrapper.find('button[aria-label="Delete"]').exists()).toBe(true)
  })

  it('shows no write actions for a receptionist', () => {
    setRole('receptionist')
    const wrapper = mount(TreatmentPlanItemsTable, { props: { items: [makeItem({ status: 'planned' })] } })

    expect(wrapper.findAll('button')).toHaveLength(0)
  })

  it('hides edit/complete/cancel for a terminal item', () => {
    setRole('admin')
    const wrapper = mount(TreatmentPlanItemsTable, { props: { items: [makeItem({ status: 'completed' })] } })

    expect(wrapper.find('button[aria-label="Edit"]').exists()).toBe(false)
    expect(wrapper.find('button[aria-label="Complete Item"]').exists()).toBe(false)
    expect(wrapper.find('button[aria-label="Cancel Item"]').exists()).toBe(false)
    expect(wrapper.find('button[aria-label="Delete"]').exists()).toBe(true)
  })
})

describe('TreatmentPlanItemsTable — row actions', () => {
  it('emits edit-item when the edit button is clicked', async () => {
    setRole('dentist')
    const item = makeItem({ status: 'planned' })
    const wrapper = mount(TreatmentPlanItemsTable, { props: { items: [item] } })

    await wrapper.find('button[aria-label="Edit"]').trigger('click')

    expect(wrapper.emitted('edit-item')?.[0]).toEqual([item])
  })

  it('completes a planned item', async () => {
    setRole('dentist')
    mockedItemsApi.complete.mockResolvedValue({} as never)
    const wrapper = mount(TreatmentPlanItemsTable, { props: { items: [makeItem({ status: 'planned' })] } })

    await wrapper.find('button[aria-label="Complete Item"]').trigger('click')
    await flushPromises()

    expect(mockedItemsApi.complete).toHaveBeenCalledWith('item-1')
  })

  it('cancels a planned item', async () => {
    setRole('dentist')
    mockedItemsApi.cancel.mockResolvedValue({} as never)
    const wrapper = mount(TreatmentPlanItemsTable, { props: { items: [makeItem({ status: 'planned' })] } })

    await wrapper.find('button[aria-label="Cancel Item"]').trigger('click')
    await flushPromises()

    expect(mockedItemsApi.cancel).toHaveBeenCalledWith('item-1')
  })

  it('deletes an item once the confirm popup is accepted', async () => {
    setRole('admin')
    const requireMock = vi.fn()
    vi.mocked(useConfirm).mockReturnValue({ require: requireMock } as unknown as ReturnType<typeof useConfirm>)
    mockedItemsApi.remove.mockResolvedValue(undefined)
    mockedPlansApi.get.mockResolvedValue({ id: 'plan-1', items: [] } as never)

    const wrapper = mount(TreatmentPlanItemsTable, { props: { items: [makeItem()] } })
    await wrapper.find('button[aria-label="Delete"]').trigger('click')

    expect(requireMock).toHaveBeenCalledTimes(1)
    await requireMock.mock.calls[0][0].accept()
    await flushPromises()

    expect(mockedItemsApi.remove).toHaveBeenCalledWith('item-1')
  })
})
