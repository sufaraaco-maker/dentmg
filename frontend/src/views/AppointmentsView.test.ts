import { mount, flushPromises } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { createMemoryHistory, createRouter } from 'vue-router'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import AppointmentsView from './AppointmentsView.vue'
import AppointmentCalendar from '@/components/appointments/AppointmentCalendar.vue'
import {
  appointmentsApi,
  providersApi,
  appointmentTypesApi,
  workingHoursApi,
  timeOffApi,
} from '@/services/appointments'
import type { Appointment } from '@/types/appointment'

vi.mock('@/services/appointments', () => ({
  appointmentsApi: { list: vi.fn(), get: vi.fn() },
  providersApi: { listAll: vi.fn() },
  appointmentTypesApi: { list: vi.fn() },
  workingHoursApi: { listForDentist: vi.fn() },
  timeOffApi: { listForDentist: vi.fn() },
}))

function makeAppointment(overrides: Partial<Appointment> = {}): Appointment {
  return {
    id: 'appt-1',
    patient_id: 'p1',
    dentist_id: 'd1',
    appointment_type_id: 't1',
    start_at: '2026-07-15T09:00:00',
    end_at: '2026-07-15T09:30:00',
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
    created_at: '2026-07-01T00:00:00',
    updated_at: '2026-07-01T00:00:00',
    patient: { id: 'p1', patient_code: 'P-0001', full_name: 'Jane Doe' },
    dentist: { id: 'd1', name: 'Dr. Smith' },
    ...overrides,
  }
}

function makeRouter() {
  return createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/appointments', name: 'appointments', component: AppointmentsView },
      { path: '/appointments/:id', name: 'appointment-detail', component: { template: '<div />' } },
    ],
  })
}

beforeEach(() => {
  setActivePinia(createPinia())
  vi.mocked(appointmentsApi.list).mockResolvedValue([makeAppointment()])
  vi.mocked(providersApi.listAll).mockResolvedValue([
    { id: 'd1', name: 'Dr. Smith', email: 'smith@example.com', role: 'dentist' },
  ])
  vi.mocked(appointmentTypesApi.list).mockResolvedValue([])
  vi.mocked(workingHoursApi.listForDentist).mockResolvedValue([])
  vi.mocked(timeOffApi.listForDentist).mockResolvedValue([])
})

async function mountView() {
  const router = makeRouter()
  await router.push('/appointments')
  await router.isReady()
  const wrapper = mount(AppointmentsView, { global: { plugins: [router] } })
  await flushPromises()
  return { wrapper, router }
}

// FullCalendar requires a real layout engine (row/column measurement) to place time-grid events,
// which jsdom doesn't provide — so these tests verify the wiring at the AppointmentCalendar prop
// boundary (this project's own mapping/store-integration code) rather than FullCalendar's internal
// DOM rendering, which the library's own test suite already covers. Real visual rendering is
// verified by hand against the dev stack, per this module's Testing Strategy (§16).
describe('AppointmentsView board', () => {
  it('fetches the current range on mount and maps the result into calendar events', async () => {
    const { wrapper } = await mountView()

    expect(appointmentsApi.list).toHaveBeenCalled()

    const calendar = wrapper.findComponent(AppointmentCalendar)
    const events = calendar.props('events')
    expect(events).toHaveLength(1)
    expect(events[0]).toMatchObject({
      id: 'appt-1',
      title: 'Jane Doe',
      extendedProps: { status: 'scheduled', dentistName: 'Dr. Smith' },
    })
  })

  it('navigates to the appointment detail route when a calendar event is clicked', async () => {
    const { wrapper, router } = await mountView()

    const calendar = wrapper.findComponent(AppointmentCalendar)
    await calendar.vm.$emit('event-click', 'appt-1')
    await flushPromises()

    expect(router.currentRoute.value.name).toBe('appointment-detail')
    expect(router.currentRoute.value.params.id).toBe('appt-1')
  })

  it('refetches the range when the calendar reports a range change', async () => {
    const { wrapper } = await mountView()
    vi.mocked(appointmentsApi.list).mockClear()

    const calendar = wrapper.findComponent(AppointmentCalendar)
    await calendar.vm.$emit('range-change', { start: new Date(2026, 7, 1), end: new Date(2026, 7, 31) })
    await flushPromises()

    expect(appointmentsApi.list).toHaveBeenCalledWith(
      expect.objectContaining({ date_from: '2026-08-01', date_to: '2026-08-31' }),
    )
  })
})
