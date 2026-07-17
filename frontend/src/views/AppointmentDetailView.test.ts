import { mount, flushPromises } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { createMemoryHistory, createRouter } from 'vue-router'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import AppointmentDetailView from './AppointmentDetailView.vue'
import { appointmentsApi, providersApi, appointmentTypesApi } from '@/services/appointments'
import { useAuthStore } from '@/stores/auth'
import type { Appointment } from '@/types/appointment'

vi.mock('@/services/appointments', () => ({
  appointmentsApi: { get: vi.fn(), update: vi.fn() },
  providersApi: { listAll: vi.fn() },
  appointmentTypesApi: { list: vi.fn() },
  isAppointmentConflictError: () => false,
}))

vi.mock('@/lib/api', () => ({
  api: { get: vi.fn().mockResolvedValue({ data: { data: [] } }) },
}))

function makeAppointment(overrides: Partial<Appointment> = {}): Appointment {
  return {
    id: 'appt-1',
    patient_id: 'p1',
    dentist_id: 'd1',
    appointment_type_id: 't1',
    start_at: '2026-07-20T09:00:00+00:00',
    end_at: '2026-07-20T09:30:00+00:00',
    duration_minutes: 30,
    status: 'scheduled',
    reason: 'Checkup',
    notes: null,
    cancellation_reason: null,
    cancelled_at: null,
    cancelled_by: null,
    checked_in_at: null,
    started_at: null,
    completed_at: null,
    no_show_at: null,
    reschedule_count: 0,
    created_at: '2026-07-15T00:00:00+00:00',
    updated_at: '2026-07-15T00:00:00+00:00',
    patient: { id: 'p1', patient_code: 'PT-0001', full_name: 'Jane Doe' },
    dentist: { id: 'd1', name: 'Dr. Smith' },
    appointment_type: {
      id: 't1',
      name: 'Cleaning',
      default_duration_minutes: 30,
      color: '#0ea5e9',
      is_active: true,
      created_at: '',
      updated_at: '',
    },
    ...overrides,
  }
}

function makeRouter() {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/appointments', name: 'appointments', component: { template: '<div />' } },
      { path: '/appointments/:id', name: 'appointment-detail', component: AppointmentDetailView },
      { path: '/patients/:id', name: 'patient-detail', component: { template: '<div />' } },
    ],
  })
  return router
}

beforeEach(() => {
  setActivePinia(createPinia())
  vi.clearAllMocks()
  vi.mocked(providersApi.listAll).mockResolvedValue([])
  vi.mocked(appointmentTypesApi.list).mockResolvedValue([])
})

async function mountView(
  appointment: Appointment,
  role: 'admin' | 'dentist' | 'receptionist' = 'receptionist',
) {
  useAuthStore().user = { id: 'u1', name: 'Test User', email: 'u1@example.com', role }
  vi.mocked(appointmentsApi.get).mockResolvedValue(appointment)

  const router = makeRouter()
  router.push({ name: 'appointment-detail', params: { id: appointment.id } })
  await router.isReady()

  const wrapper = mount(AppointmentDetailView, { global: { plugins: [router] } })
  await flushPromises()
  return wrapper
}

describe('AppointmentDetailView', () => {
  it('fetches the appointment and renders its summary, timeline, patient, and actions', async () => {
    const wrapper = await mountView(makeAppointment())

    expect(appointmentsApi.get).toHaveBeenCalledWith('appt-1')
    expect(wrapper.text()).toContain('Cleaning')
    expect(wrapper.text()).toContain('Jane Doe')
    expect(wrapper.text()).toContain('Timeline')
    expect(wrapper.text()).toContain('View full patient record')
    expect(wrapper.text()).toContain('Confirm')
  })

  it('shows the not-found message when the appointment fails to load', async () => {
    useAuthStore().user = { id: 'u1', name: 'Test', email: 'u1@example.com', role: 'admin' }
    vi.mocked(appointmentsApi.get).mockRejectedValue({ response: { status: 404 } })

    const router = makeRouter()
    router.push({ name: 'appointment-detail', params: { id: 'missing' } })
    await router.isReady()

    const wrapper = mount(AppointmentDetailView, { global: { plugins: [router] } })
    await flushPromises()

    expect(wrapper.text()).toContain('Appointment not found')
  })

  it('shows Edit for a receptionist and hides it for a dentist not assigned to the appointment', async () => {
    const withReceptionist = await mountView(makeAppointment(), 'receptionist')
    expect(withReceptionist.text()).toContain('Edit')
  })

  it('renders the audit-history placeholder only for admins', async () => {
    const asAdmin = await mountView(makeAppointment(), 'admin')
    expect(asAdmin.text()).toContain('Audit History')
  })

  it('does not render the audit-history placeholder for a receptionist', async () => {
    const asReceptionist = await mountView(makeAppointment(), 'receptionist')
    expect(asReceptionist.text()).not.toContain('Audit History')
  })

  it('renders the four always-on future-module placeholders', async () => {
    const wrapper = await mountView(makeAppointment())
    expect(wrapper.text()).toContain('Treatment Plan')
    expect(wrapper.text()).toContain('Invoices')
    expect(wrapper.text()).toContain('Clinical Notes')
    expect(wrapper.text()).toContain('Attachments')
  })
})
