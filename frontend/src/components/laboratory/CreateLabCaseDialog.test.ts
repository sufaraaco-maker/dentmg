import { mount, flushPromises } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import Select from 'primevue/select'
import CreateLabCaseDialog from './CreateLabCaseDialog.vue'
import PatientSearchSelect from '@/components/appointments/PatientSearchSelect.vue'
import { api } from '@/lib/api'
import { labCasesApi } from '@/services/laboratory'
import { providersApi } from '@/services/appointments'
import type { LabCase } from '@/types/laboratory'

vi.mock('@/lib/api', () => ({
  api: { get: vi.fn(), post: vi.fn(), put: vi.fn(), delete: vi.fn() },
}))

vi.mock('@/services/laboratory', () => ({
  labCasesApi: {
    list: vi.fn(),
    create: vi.fn(),
    send: vi.fn(),
    receive: vi.fn(),
    qualityCheck: vi.fn(),
    cancel: vi.fn(),
  },
  isLabCaseError: () => false,
  rethrowLabCaseError: (error: unknown) => {
    throw error
  },
}))

// DentistSelect (always rendered) pulls in the providers store.
vi.mock('@/services/appointments', () => ({ providersApi: { listAll: vi.fn() } }))

const mockedApi = vi.mocked(api)
const mockedLabCasesApi = vi.mocked(labCasesApi)
const mockedProvidersApi = vi.mocked(providersApi)

function makeCase(overrides: Partial<LabCase> = {}): LabCase {
  return {
    id: 'case-1',
    sequence_number: 1,
    case_number: 'LC-000001',
    patient_id: 'patient-1',
    lab_id: 'lab-1',
    dentist_id: null,
    treatment_plan_item_id: null,
    appointment_id: null,
    tooth_numbers: null,
    case_type: null,
    shade: null,
    material: null,
    instructions: null,
    fee: null,
    tracking_number: null,
    status: 'draft',
    sent_at: null,
    due_at: null,
    received_at: null,
    quality_checked_at: null,
    cancelled_at: null,
    created_at: '2026-08-08T09:00:00+00:00',
    updated_at: '2026-08-08T09:00:00+00:00',
    ...overrides,
  }
}

beforeEach(() => {
  setActivePinia(createPinia())
  vi.clearAllMocks()
  // useLabsStore.fetchAll(), pulled in by the dialog's Lab <Select> options.
  mockedApi.get.mockResolvedValue({ data: [{ id: 'lab-1', name: 'Precision Dental Lab', is_active: true }] })
  mockedProvidersApi.listAll.mockResolvedValue([])
})

describe('CreateLabCaseDialog — patientId prop (Phase 2.4, design doc §7 decision 1)', () => {
  it('skips PatientSearchSelect and pre-fills patient_id when opened with a fixed patientId', async () => {
    const wrapper = mount(CreateLabCaseDialog, { props: { visible: true, patientId: 'patient-1' } })
    await flushPromises()

    expect(wrapper.findComponent(PatientSearchSelect).exists()).toBe(false)
  })

  it('renders PatientSearchSelect when no patientId is given (standalone Lab Cases page flow)', async () => {
    const wrapper = mount(CreateLabCaseDialog, { props: { visible: true } })
    await flushPromises()

    expect(wrapper.findComponent(PatientSearchSelect).exists()).toBe(true)
  })

  it('routes the create through patientLabCases store, not the raw endpoint, when patientId is set', async () => {
    mockedLabCasesApi.create.mockResolvedValueOnce(makeCase())
    mockedLabCasesApi.list.mockResolvedValueOnce({
      data: [makeCase()],
      meta: { current_page: 1, last_page: 1, per_page: 15, total: 1 },
    })

    const wrapper = mount(CreateLabCaseDialog, { props: { visible: true, patientId: 'patient-1' } })
    await flushPromises()

    await wrapper.findComponent(Select).vm.$emit('update:modelValue', 'lab-1')
    await flushPromises()

    await wrapper.find('form').trigger('submit.prevent')
    await flushPromises()

    expect(mockedLabCasesApi.create).toHaveBeenCalledWith(
      expect.objectContaining({ patient_id: 'patient-1', lab_id: 'lab-1' }),
    )
    expect(mockedApi.post).not.toHaveBeenCalled()
    expect(wrapper.emitted('created')).toBeTruthy()
    expect(wrapper.emitted('update:visible')?.[0]).toEqual([false])
  })
})

describe('CreateLabCaseDialog — standalone flow (no patientId, unchanged)', () => {
  it('still calls the raw /lab-cases endpoint directly, not the patient-scoped store', async () => {
    mockedApi.post.mockResolvedValueOnce({ data: makeCase() })
    const wrapper = mount(CreateLabCaseDialog, { props: { visible: true } })
    await flushPromises()

    await wrapper.findComponent(PatientSearchSelect).vm.$emit('update:modelValue', 'patient-1')
    await wrapper.findComponent(Select).vm.$emit('update:modelValue', 'lab-1')
    await flushPromises()

    await wrapper.find('form').trigger('submit.prevent')
    await flushPromises()

    expect(mockedApi.post).toHaveBeenCalledWith(
      '/lab-cases',
      expect.objectContaining({ patient_id: 'patient-1', lab_id: 'lab-1' }),
    )
    expect(mockedLabCasesApi.create).not.toHaveBeenCalled()
  })
})
