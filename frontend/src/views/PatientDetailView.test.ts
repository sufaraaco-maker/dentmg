import { mount, flushPromises } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { createMemoryHistory, createRouter } from 'vue-router'
import { describe, expect, it, vi } from 'vitest'
import DataTable from 'primevue/datatable'
import PatientDetailView from './PatientDetailView.vue'
import PatientAppointmentsPanel from '@/components/appointments/PatientAppointmentsPanel.vue'
import PatientDentalChartPanel from '@/components/dental-chart/PatientDentalChartPanel.vue'
import { useAuthStore } from '@/stores/auth'
import type { Patient } from '@/types/patient'
import type { UserRole } from '@/types/user'

const mockedApiGet = vi.fn()

vi.mock('@/lib/api', () => ({
  api: {
    get: (...args: unknown[]) => mockedApiGet(...args),
    delete: vi.fn(),
  },
}))

function makePatient(overrides: Partial<Patient> = {}): Patient {
  return {
    id: 'patient-1',
    patient_code: 'P-00001',
    first_name: 'Jane',
    last_name: 'Doe',
    full_name: 'Jane Doe',
    date_of_birth: '1990-01-01',
    gender: 'female',
    blood_type: null,
    national_id: null,
    phone: '555-0100',
    email: null,
    address: null,
    emergency_contact_name: null,
    emergency_contact_phone: null,
    allergies: null,
    medical_history: null,
    insurance_provider: null,
    insurance_number: null,
    notes: null,
    created_at: '2026-07-01T00:00:00+00:00',
    updated_at: '2026-07-01T00:00:00+00:00',
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
      { path: '/patients', name: 'patients', component: { template: '<div />' } },
      { path: '/patients/:id', name: 'patient-detail', component: PatientDetailView },
    ],
  })
}

async function mountView(role: UserRole) {
  setActivePinia(createPinia())
  setRole(role)
  mockedApiGet.mockReset()
  mockedApiGet.mockImplementation((url: string) => {
    if (url.endsWith('/audit-logs')) return Promise.resolve({ data: { data: [] } })
    return Promise.resolve({ data: makePatient() })
  })

  const router = makeRouter()
  router.push('/patients/patient-1')
  await router.isReady()

  const wrapper = mount(PatientDetailView, {
    global: {
      plugins: [router],
      stubs: { PatientAppointmentsPanel: true, PatientDentalChartPanel: true },
    },
  })
  await flushPromises()
  return wrapper
}

function clickTab(wrapper: Awaited<ReturnType<typeof mountView>>, label: string) {
  const tab = wrapper.findAll('[role="tab"]').find((n) => n.text() === label)
  return tab?.trigger('click')
}

describe('PatientDetailView — tabs', () => {
  it('shows the Overview tab content (demographics) by default', async () => {
    const wrapper = await mountView('admin')
    expect(wrapper.text()).toContain('Demographics')
    expect(wrapper.text()).toContain('555-0100')
  })

  it('renders PatientAppointmentsPanel, prefilled with the current patient, in the Appointments tab', async () => {
    const wrapper = await mountView('admin')
    await clickTab(wrapper, 'Appointments')
    await flushPromises()

    const panel = wrapper.findComponent(PatientAppointmentsPanel)
    expect(panel.exists()).toBe(true)
    expect(panel.props('patientId')).toBe('patient-1')
    expect(panel.props('patient')?.id).toBe('patient-1')
  })

  it('renders PatientDentalChartPanel, scoped to the current patient, in the Dental Chart tab', async () => {
    const wrapper = await mountView('admin')
    await clickTab(wrapper, 'Dental Chart')
    await flushPromises()

    const panel = wrapper.findComponent(PatientDentalChartPanel)
    expect(panel.exists()).toBe(true)
    expect(panel.props('patientId')).toBe('patient-1')
  })
})

describe('PatientDetailView — permissions (unchanged by the tab refactor)', () => {
  it('shows the audit history section to an admin', async () => {
    const wrapper = await mountView('admin')
    expect(wrapper.findComponent(DataTable).exists()).toBe(true)
  })

  it('hides the audit history section from a dentist', async () => {
    const wrapper = await mountView('dentist')
    expect(wrapper.findComponent(DataTable).exists()).toBe(false)
  })

  it('shows Edit and Delete to an admin', async () => {
    const wrapper = await mountView('admin')
    expect(wrapper.findAll('button').some((b) => b.text() === 'Edit')).toBe(true)
    expect(wrapper.findAll('button').some((b) => b.text() === 'Delete')).toBe(true)
  })

  it('shows Edit but not Delete to a receptionist', async () => {
    const wrapper = await mountView('receptionist')
    expect(wrapper.findAll('button').some((b) => b.text() === 'Edit')).toBe(true)
    expect(wrapper.findAll('button').some((b) => b.text() === 'Delete')).toBe(false)
  })

  it('shows neither Edit nor Delete to a dentist', async () => {
    const wrapper = await mountView('dentist')
    expect(wrapper.findAll('button').some((b) => b.text() === 'Edit')).toBe(false)
    expect(wrapper.findAll('button').some((b) => b.text() === 'Delete')).toBe(false)
  })
})

describe('PatientDetailView — Clinical Notes tab visibility (design doc §10/§15 Decision D)', () => {
  it('shows the Clinical Notes tab to an admin', async () => {
    const wrapper = await mountView('admin')
    expect(wrapper.findAll('[role="tab"]').some((n) => n.text() === 'Clinical Notes')).toBe(true)
  })

  it('shows the Clinical Notes tab to a dentist', async () => {
    const wrapper = await mountView('dentist')
    expect(wrapper.findAll('[role="tab"]').some((n) => n.text() === 'Clinical Notes')).toBe(true)
  })

  it('hides the Clinical Notes tab from a receptionist entirely — not just its write actions', async () => {
    const wrapper = await mountView('receptionist')
    expect(wrapper.findAll('[role="tab"]').some((n) => n.text() === 'Clinical Notes')).toBe(false)
  })
})
