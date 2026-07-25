import { mount, flushPromises } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import InputText from 'primevue/inputtext'
import Textarea from 'primevue/textarea'
import CreateTreatmentPlanDialog from './CreateTreatmentPlanDialog.vue'
import DentistSelect from '@/components/appointments/DentistSelect.vue'
import { treatmentPlansApi } from '@/services/treatmentPlans'
import { providersApi } from '@/services/appointments'
import type { TreatmentPlan } from '@/types/treatmentPlan'

vi.mock('@/services/treatmentPlans', async () => {
  const actual =
    await vi.importActual<typeof import('@/services/treatmentPlans')>('@/services/treatmentPlans')
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
    treatmentPlanItemsApi: {
      create: vi.fn(),
      update: vi.fn(),
      complete: vi.fn(),
      cancel: vi.fn(),
      remove: vi.fn(),
    },
    isTreatmentPlanError: actual.isTreatmentPlanError,
    rethrowTreatmentPlanError: actual.rethrowTreatmentPlanError,
  }
})

vi.mock('@/services/appointments', () => ({ providersApi: { listAll: vi.fn() } }))

const mockedPlansApi = vi.mocked(treatmentPlansApi)
const mockedProvidersApi = vi.mocked(providersApi)

function makePlan(overrides: Partial<TreatmentPlan> = {}): TreatmentPlan {
  return {
    id: 'plan-1',
    patient_id: 'patient-1',
    dentist_id: 'dentist-1',
    created_by_id: 'admin-1',
    title: null,
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

beforeEach(() => {
  setActivePinia(createPinia())
  vi.clearAllMocks()
  mockedProvidersApi.listAll.mockResolvedValue([])
})

describe('CreateTreatmentPlanDialog — validation', () => {
  it('requires a dentist before submitting', async () => {
    const wrapper = mount(CreateTreatmentPlanDialog, { props: { visible: true, patientId: 'patient-1' } })
    await flushPromises()

    await wrapper.find('form').trigger('submit.prevent')

    expect(wrapper.text()).toContain('This field is required')
    expect(mockedPlansApi.create).not.toHaveBeenCalled()
  })
})

describe('CreateTreatmentPlanDialog — create', () => {
  it('creates a draft plan with the entered fields', async () => {
    mockedPlansApi.create.mockResolvedValue(makePlan({ title: 'Option A' }))
    const wrapper = mount(CreateTreatmentPlanDialog, { props: { visible: true, patientId: 'patient-1' } })
    await flushPromises()

    await wrapper.findComponent(DentistSelect).vm.$emit('update:modelValue', 'dentist-1')
    await wrapper.findComponent(InputText).vm.$emit('update:modelValue', 'Option A')
    await wrapper.findComponent(Textarea).vm.$emit('update:modelValue', 'Some notes')
    await flushPromises()

    await wrapper.find('form').trigger('submit.prevent')
    await flushPromises()

    expect(mockedPlansApi.create).toHaveBeenCalledWith('patient-1', {
      dentist_id: 'dentist-1',
      title: 'Option A',
      notes: 'Some notes',
    })
    expect(wrapper.emitted('saved')).toBeTruthy()
    expect(wrapper.emitted('update:visible')?.[0]).toEqual([false])
  })

  it('sends null for optional fields left blank', async () => {
    mockedPlansApi.create.mockResolvedValue(makePlan())
    const wrapper = mount(CreateTreatmentPlanDialog, { props: { visible: true, patientId: 'patient-1' } })
    await flushPromises()

    await wrapper.findComponent(DentistSelect).vm.$emit('update:modelValue', 'dentist-1')
    await flushPromises()

    await wrapper.find('form').trigger('submit.prevent')
    await flushPromises()

    expect(mockedPlansApi.create).toHaveBeenCalledWith('patient-1', {
      dentist_id: 'dentist-1',
      title: null,
      notes: null,
    })
  })

  it('resets the form each time the dialog re-opens', async () => {
    const wrapper = mount(CreateTreatmentPlanDialog, {
      props: { visible: true, patientId: 'patient-1' },
    })
    await flushPromises()

    await wrapper.findComponent(InputText).vm.$emit('update:modelValue', 'Leftover title')
    await wrapper.setProps({ visible: false })
    await wrapper.setProps({ visible: true })
    await flushPromises()

    expect(wrapper.findComponent(InputText).props('modelValue')).toBe('')
  })
})

describe('CreateTreatmentPlanDialog — error handling', () => {
  it('surfaces 422 field errors from the API', async () => {
    mockedPlansApi.create.mockRejectedValue({
      response: { status: 422, data: { errors: { dentist_id: ['The selected dentist is invalid.'] } } },
    })
    const wrapper = mount(CreateTreatmentPlanDialog, { props: { visible: true, patientId: 'patient-1' } })
    await flushPromises()

    await wrapper.findComponent(DentistSelect).vm.$emit('update:modelValue', 'dentist-1')
    await flushPromises()

    await wrapper.find('form').trigger('submit.prevent')
    await flushPromises()

    expect(wrapper.text()).toContain('The selected dentist is invalid.')
    expect(wrapper.emitted('saved')).toBeFalsy()
  })
})
