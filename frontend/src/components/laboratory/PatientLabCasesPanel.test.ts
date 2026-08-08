import { mount, flushPromises } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { createMemoryHistory, createRouter } from 'vue-router'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import Select from 'primevue/select'
import PatientLabCasesPanel from './PatientLabCasesPanel.vue'
import CreateLabCaseDialog from './CreateLabCaseDialog.vue'
import { api } from '@/lib/api'
import { labCasesApi } from '@/services/laboratory'
import { providersApi } from '@/services/appointments'
import { useAuthStore } from '@/stores/auth'
import type { LabCase } from '@/types/laboratory'
import type { UserRole } from '@/types/user'

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

// CreateLabCaseDialog (always mounted, just hidden) renders DentistSelect, which pulls in the
// providers store — same requirement as PatientTreatmentPlansPanel.test.ts's identical mock.
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
    case_type: 'Crown',
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
    lab: { id: 'lab-1', name: 'Precision Dental Lab' },
    ...overrides,
  }
}

function makePage(cases: LabCase[]) {
  return {
    data: cases,
    meta: { current_page: 1, last_page: 1, per_page: 15, total: cases.length },
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
      { path: '/lab-cases/:id', name: 'lab-case-detail', component: { template: '<div />' } },
    ],
  })
}

async function mountPanel(patientId = 'patient-1') {
  const router = makeRouter()
  await router.push({ name: 'patient-detail', params: { id: patientId } })
  await router.isReady()
  const wrapper = mount(PatientLabCasesPanel, {
    props: { patientId },
    global: { plugins: [router] },
  })
  await flushPromises()
  return { wrapper, router }
}

beforeEach(() => {
  setActivePinia(createPinia())
  vi.clearAllMocks()
  mockedApi.get.mockResolvedValue({ data: [] }) // useLabsStore.fetchAll(), pulled in by CreateLabCaseDialog
  mockedLabCasesApi.list.mockResolvedValue(makePage([]))
  mockedProvidersApi.listAll.mockResolvedValue([])
})

describe('PatientLabCasesPanel — data loading', () => {
  it("fetches this patient's lab cases (page 1) on mount", async () => {
    setRole('dentist')
    await mountPanel('patient-1')

    expect(mockedLabCasesApi.list).toHaveBeenCalledWith('patient-1', 1, null)
  })

  it('re-fetches when patientId changes', async () => {
    setRole('dentist')
    const { wrapper } = await mountPanel('patient-1')
    await wrapper.setProps({ patientId: 'patient-2' })
    await flushPromises()

    expect(mockedLabCasesApi.list).toHaveBeenCalledWith('patient-2', 1, null)
  })

  it('shows the empty state when the patient has no lab cases', async () => {
    setRole('dentist')
    const { wrapper } = await mountPanel('patient-1')

    expect(wrapper.text()).toContain('No lab cases for this patient yet.')
  })

  it('renders the case list once cases are loaded', async () => {
    setRole('dentist')
    mockedLabCasesApi.list.mockResolvedValue(makePage([makeCase()]))
    const { wrapper } = await mountPanel('patient-1')

    expect(wrapper.text()).toContain('LC-000001')
    expect(wrapper.text()).toContain('Precision Dental Lab')
  })

  it('refetches page 1 when the status filter changes', async () => {
    setRole('dentist')
    const { wrapper } = await mountPanel('patient-1')
    expect(mockedLabCasesApi.list).toHaveBeenCalledTimes(1)

    await wrapper.findComponent(Select).vm.$emit('update:modelValue', 'sent')
    await flushPromises()

    expect(mockedLabCasesApi.list).toHaveBeenCalledWith('patient-1', 1, 'sent')
  })
})

describe('PatientLabCasesPanel — permissions', () => {
  it('shows the New Lab Case button for a dentist', async () => {
    setRole('dentist')
    const { wrapper } = await mountPanel('patient-1')

    expect(wrapper.text()).toContain('New Lab Case')
  })

  it('shows the New Lab Case button for an admin', async () => {
    setRole('admin')
    const { wrapper } = await mountPanel('patient-1')

    expect(wrapper.text()).toContain('New Lab Case')
  })

  it('hides the New Lab Case button for a receptionist (matches LabCasePolicy::create)', async () => {
    setRole('receptionist')
    const { wrapper } = await mountPanel('patient-1')

    expect(wrapper.text()).not.toContain('New Lab Case')
  })
})

describe('PatientLabCasesPanel — dialog wiring', () => {
  it('opens the create dialog pre-filled with the current patient (no patient search)', async () => {
    setRole('dentist')
    const { wrapper } = await mountPanel('patient-1')

    await wrapper.find('button').trigger('click')

    const dialog = wrapper.findComponent(CreateLabCaseDialog)
    expect(dialog.props('visible')).toBe(true)
    expect(dialog.props('patientId')).toBe('patient-1')
  })

  it('closes the dialog and refreshes the list once a case is created', async () => {
    setRole('dentist')
    const { wrapper } = await mountPanel('patient-1')
    expect(mockedLabCasesApi.list).toHaveBeenCalledTimes(1)

    await wrapper.find('button').trigger('click')
    const dialog = wrapper.findComponent(CreateLabCaseDialog)

    await dialog.vm.$emit('created', makeCase())
    await flushPromises()

    expect(dialog.props('visible')).toBe(false)
  })
})

describe('PatientLabCasesPanel — navigation', () => {
  it('navigates to the Lab Case Detail route when a case row is clicked', async () => {
    setRole('dentist')
    mockedLabCasesApi.list.mockResolvedValue(makePage([makeCase()]))
    const { wrapper, router } = await mountPanel('patient-1')
    const push = vi.spyOn(router, 'push')

    await wrapper.find('button.text-start').trigger('click')

    expect(push).toHaveBeenCalledWith({ name: 'lab-case-detail', params: { id: 'case-1' } })
  })
})
