import { mount, flushPromises } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { createMemoryHistory, createRouter } from 'vue-router'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import PatientAppointmentsPanel from './PatientAppointmentsPanel.vue'
import { appointmentsApi } from '@/services/appointments'
import { useAuthStore } from '@/stores/auth'
import type { Appointment } from '@/types/appointment'

// Opening the real (unstubbed) AppointmentDialog inside these tests also mounts DentistSelect/
// AppointmentTypeSelect, which pull in these other service modules — matching AppointmentDialog's
// own test mock shape so those don't fail with unhandled rejections.
vi.mock('@/services/appointments', () => ({
  appointmentsApi: { list: vi.fn(), create: vi.fn(), get: vi.fn() },
  appointmentTypesApi: { list: vi.fn().mockResolvedValue([]) },
  providersApi: { listAll: vi.fn().mockResolvedValue([]) },
  isAppointmentConflictError: () => false,
}))

vi.mock('@/lib/api', () => ({
  api: { get: vi.fn().mockResolvedValue({ data: { data: [] } }) },
}))

const mockedApi = vi.mocked(appointmentsApi)

function makeRouter() {
  return createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/', name: 'dashboard', component: { template: '<div />' } },
      { path: '/patients/:id', name: 'patient-detail', component: { template: '<div />' } },
      { path: '/appointments/:id', name: 'appointment-detail', component: { template: '<div />' } },
    ],
  })
}

function makeAppointment(overrides: Partial<Appointment> = {}): Appointment {
  return {
    id: 'a1',
    patient_id: 'p1',
    dentist_id: 'd1',
    appointment_type_id: 't1',
    start_at: '2026-07-20T09:00:00+00:00',
    end_at: '2026-07-20T09:30:00+00:00',
    duration_minutes: 30,
    status: 'scheduled',
    reason: null,
    notes: null,
    cancellation_reason: null,
    cancelled_at: null,
    cancelled_by: null,
    checked_in_at: null,
    started_at: null,
    completed_at: null,
    no_show_at: null,
    reschedule_count: 0,
    created_at: '',
    updated_at: '',
    patient: { id: 'p1', patient_code: 'P-001', full_name: 'Jane Patient' },
    dentist: { id: 'd1', name: 'Dr. Smith' },
    ...overrides,
  }
}

async function mountPanel(patientId = 'p1') {
  const router = makeRouter()
  await router.push({ name: 'patient-detail', params: { id: patientId } })
  await router.isReady()
  const wrapper = mount(PatientAppointmentsPanel, {
    props: { patientId },
    global: { plugins: [router] },
  })
  await flushPromises()
  return wrapper
}

beforeEach(() => {
  setActivePinia(createPinia())
  vi.clearAllMocks()
  useAuthStore().user = { id: 'u1', name: 'Front Desk', email: 'fd@example.com', role: 'receptionist' }
})

describe('PatientAppointmentsPanel', () => {
  it("fetches an unfiltered range and shows only this patient's appointments", async () => {
    mockedApi.list.mockResolvedValue([
      makeAppointment({ id: 'a1', patient_id: 'p1' }),
      makeAppointment({
        id: 'a2',
        patient_id: 'other-patient',
        patient: { id: 'other-patient', patient_code: 'P-002', full_name: 'Someone Else' },
      }),
    ])
    const wrapper = await mountPanel('p1')

    // Never passes patient_id to the API — would corrupt the shared range cache (see the
    // component's own doc comment). Filtering happens client-side instead.
    expect(mockedApi.list).toHaveBeenCalledWith(
      expect.not.objectContaining({ patient_id: expect.anything() }),
    )
    expect(wrapper.text()).toContain('Jane Patient')
    expect(wrapper.text()).not.toContain('Someone Else')
  })

  it('shows the empty state when the patient has no appointments in range', async () => {
    mockedApi.list.mockResolvedValue([])
    const wrapper = await mountPanel()

    expect(wrapper.text()).toContain('No appointments for this patient yet.')
  })

  it('shows the New Appointment button for a role that can manage appointments', async () => {
    const wrapper = await mountPanel()
    expect(wrapper.text()).toContain('New Appointment')
  })

  it('hides the New Appointment button for a dentist', async () => {
    useAuthStore().user = { id: 'd1', name: 'Dr. Smith', email: 'd1@example.com', role: 'dentist' }
    mockedApi.list.mockResolvedValue([])
    const wrapper = await mountPanel()

    expect(wrapper.text()).not.toContain('New Appointment')
  })

  it('closes the dialog on save without an extra list() refetch (relies on the shared cache)', async () => {
    mockedApi.list.mockResolvedValue([])
    const wrapper = await mountPanel('p1')
    expect(mockedApi.list).toHaveBeenCalledTimes(1)

    // Opens the dialog via the New Appointment button (only button rendered while the list is
    // empty), same as a real user would, then simulates its save — the panel's own `patient_id`
    // filter reads from the same shared `appointments.ts` cache `create()` already upserts into,
    // so no second `list()` call should happen.
    await wrapper.find('button').trigger('click')
    const dialog = wrapper.findComponent({ name: 'AppointmentDialog' })
    expect(dialog.props('visible')).toBe(true)

    await dialog.vm.$emit('saved', makeAppointment())
    await flushPromises()

    expect(dialog.props('visible')).toBe(false)
    expect(mockedApi.list).toHaveBeenCalledTimes(1)
  })
})
