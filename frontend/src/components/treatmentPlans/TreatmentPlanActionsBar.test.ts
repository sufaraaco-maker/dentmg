import { mount, flushPromises } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import TreatmentPlanActionsBar from './TreatmentPlanActionsBar.vue'
import { treatmentPlansApi } from '@/services/treatmentPlans'
import { useAuthStore } from '@/stores/auth'
import type { TreatmentPlan } from '@/types/treatmentPlan'

vi.mock('primevue/useconfirm', () => ({ useConfirm: vi.fn() }))
// A real <Toast> is never mounted in an isolated component test, so `wrapper.text()` can never
// show a toast's content — mocked here so error-message assertions can check `toast.add`'s
// arguments directly instead (same reasoning as the `useConfirm` mock above).
vi.mock('primevue/usetoast', () => ({ useToast: vi.fn() }))

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

const mockedPlansApi = vi.mocked(treatmentPlansApi)

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
    created_at: '2026-07-22T09:00:00+00:00',
    updated_at: '2026-07-22T09:00:00+00:00',
    estimated_cost: '0.00',
    items: [],
    ...overrides,
  }
}

function actionLabels(wrapper: ReturnType<typeof mount>) {
  return wrapper.findAll('button').map((b) => b.text())
}

const toastAddMock = vi.fn()

beforeEach(() => {
  setActivePinia(createPinia())
  vi.clearAllMocks()
  vi.mocked(useConfirm).mockReturnValue({ require: vi.fn() } as unknown as ReturnType<typeof useConfirm>)
  vi.mocked(useToast).mockReturnValue({ add: toastAddMock } as unknown as ReturnType<typeof useToast>)
})

describe('TreatmentPlanActionsBar — visibility', () => {
  it('shows Present and Cancel for a draft plan (dentist)', () => {
    useAuthStore().user = { id: 'u1', name: 'Dr. Smith', email: 'd@example.com', role: 'dentist' }
    const wrapper = mount(TreatmentPlanActionsBar, { props: { plan: makePlan({ status: 'draft' }) } })

    expect(actionLabels(wrapper)).toEqual(expect.arrayContaining(['Present', 'Cancel Plan']))
  })

  it('shows Accept, Reject, and Cancel for a presented plan', () => {
    useAuthStore().user = { id: 'u1', name: 'Dr. Smith', email: 'd@example.com', role: 'dentist' }
    const wrapper = mount(TreatmentPlanActionsBar, { props: { plan: makePlan({ status: 'presented' }) } })

    expect(actionLabels(wrapper)).toEqual(expect.arrayContaining(['Accept', 'Reject', 'Cancel Plan']))
    expect(actionLabels(wrapper)).not.toContain('Present')
  })

  it('shows Start for an accepted plan and Complete for an in-progress plan', () => {
    useAuthStore().user = { id: 'u1', name: 'Dr. Smith', email: 'd@example.com', role: 'dentist' }
    expect(actionLabels(mount(TreatmentPlanActionsBar, { props: { plan: makePlan({ status: 'accepted' }) } }))).toEqual(
      expect.arrayContaining(['Start', 'Cancel Plan']),
    )
    expect(
      actionLabels(mount(TreatmentPlanActionsBar, { props: { plan: makePlan({ status: 'in_progress' }) } })),
    ).toEqual(expect.arrayContaining(['Complete', 'Cancel Plan']))
  })

  it('renders no actions for a terminal plan', () => {
    useAuthStore().user = { id: 'u1', name: 'Dr. Smith', email: 'd@example.com', role: 'dentist' }
    const wrapper = mount(TreatmentPlanActionsBar, { props: { plan: makePlan({ status: 'completed' }) } })

    expect(wrapper.findAll('button')).toHaveLength(0)
  })

  it('renders no actions for a receptionist regardless of status', () => {
    useAuthStore().user = { id: 'u1', name: 'Front Desk', email: 'f@example.com', role: 'receptionist' }
    const wrapper = mount(TreatmentPlanActionsBar, { props: { plan: makePlan({ status: 'draft' }) } })

    expect(wrapper.findAll('button')).toHaveLength(0)
  })
})

describe('TreatmentPlanActionsBar — non-destructive actions run immediately', () => {
  it('presents a draft plan without a confirmation dialog', async () => {
    useAuthStore().user = { id: 'u1', name: 'Dr. Smith', email: 'd@example.com', role: 'dentist' }
    mockedPlansApi.present.mockResolvedValue(makePlan({ status: 'presented' }))

    const wrapper = mount(TreatmentPlanActionsBar, { props: { plan: makePlan({ status: 'draft' }) } })
    await wrapper.findAll('button').find((b) => b.text() === 'Present')!.trigger('click')
    await flushPromises()

    expect(mockedPlansApi.present).toHaveBeenCalledWith('plan-1')
    const requireMock = vi.mocked(useConfirm)().require
    expect(requireMock).not.toHaveBeenCalled()
  })
})

describe('TreatmentPlanActionsBar — destructive actions require confirmation', () => {
  it('only cancels the plan once the confirm dialog is accepted', async () => {
    useAuthStore().user = { id: 'u1', name: 'Dr. Smith', email: 'd@example.com', role: 'dentist' }
    const requireMock = vi.fn()
    vi.mocked(useConfirm).mockReturnValue({ require: requireMock } as unknown as ReturnType<typeof useConfirm>)
    mockedPlansApi.cancel.mockResolvedValue(makePlan({ status: 'cancelled' }))

    const wrapper = mount(TreatmentPlanActionsBar, { props: { plan: makePlan({ status: 'draft' }) } })
    await wrapper.findAll('button').find((b) => b.text() === 'Cancel Plan')!.trigger('click')

    expect(requireMock).toHaveBeenCalledTimes(1)
    expect(mockedPlansApi.cancel).not.toHaveBeenCalled()

    await requireMock.mock.calls[0][0].accept()
    await flushPromises()

    expect(mockedPlansApi.cancel).toHaveBeenCalledWith('plan-1')
  })
})

describe('TreatmentPlanActionsBar — error handling', () => {
  it('shows a specific message when completing is blocked by still-planned items', async () => {
    useAuthStore().user = { id: 'u1', name: 'Dr. Smith', email: 'd@example.com', role: 'dentist' }
    // treatmentPlansApi is mocked directly here (bypassing the real rethrowTreatmentPlanError), so
    // the rejection must already be in the post-rethrow {message, code} shape isTreatmentPlanError
    // checks for — not the raw axios {response: {data: {...}}} envelope.
    mockedPlansApi.complete.mockRejectedValue({
      message: 'Open items remain.',
      code: 'treatment_plan_has_open_items',
    })

    const wrapper = mount(TreatmentPlanActionsBar, { props: { plan: makePlan({ status: 'in_progress' }) } })
    await wrapper.findAll('button').find((b) => b.text() === 'Complete')!.trigger('click')
    await flushPromises()

    expect(toastAddMock).toHaveBeenCalledWith(
      expect.objectContaining({ severity: 'error', summary: expect.stringContaining('Complete or cancel every planned item') }),
    )
  })

  it('resyncs the plan from the backend on a stale-transition 422', async () => {
    useAuthStore().user = { id: 'u1', name: 'Dr. Smith', email: 'd@example.com', role: 'dentist' }
    mockedPlansApi.present.mockRejectedValue({
      message: 'Invalid transition.',
      code: 'invalid_treatment_plan_status_transition',
    })
    mockedPlansApi.get.mockResolvedValue(makePlan({ status: 'presented' }))

    const wrapper = mount(TreatmentPlanActionsBar, { props: { plan: makePlan({ status: 'draft' }) } })
    await wrapper.findAll('button').find((b) => b.text() === 'Present')!.trigger('click')
    await flushPromises()

    expect(mockedPlansApi.get).toHaveBeenCalledWith('plan-1')
  })
})
