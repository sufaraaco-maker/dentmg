import { mount, flushPromises } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { createMemoryHistory, createRouter } from 'vue-router'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import UpcomingAppointmentsWidget from './UpcomingAppointmentsWidget.vue'
import { appointmentsApi } from '@/services/appointments'
import { useAuthStore } from '@/stores/auth'
import type { Appointment } from '@/types/appointment'

vi.mock('@/services/appointments', () => ({
  appointmentsApi: { list: vi.fn() },
  isAppointmentConflictError: () => false,
}))

const mockedApi = vi.mocked(appointmentsApi)

function makeRouter() {
  return createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/', name: 'dashboard', component: { template: '<div />' } },
      { path: '/appointments/:id', name: 'appointment-detail', component: { template: '<div />' } },
    ],
  })
}

// `parseServerDateTime` reads a timestamp's digits via UTC getters and treats them AS the
// clinic's local wall-clock time (lib/date.ts) — format local Date components directly with a
// `+00:00` suffix, not `.toISOString()`, which would re-express through real UTC.
function toServerTimestamp(d: Date): string {
  const pad = (n: number) => String(n).padStart(2, '0')
  return (
    `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}` +
    `T${pad(d.getHours())}:${pad(d.getMinutes())}:${pad(d.getSeconds())}+00:00`
  )
}

function isoDaysFromNow(days: number): string {
  const d = new Date()
  d.setDate(d.getDate() + days)
  d.setHours(10, 0, 0, 0)
  return toServerTimestamp(d)
}

function makeAppointment(overrides: Partial<Appointment> = {}): Appointment {
  return {
    id: 'a1',
    patient_id: 'p1',
    dentist_id: 'd1',
    appointment_type_id: 't1',
    start_at: isoDaysFromNow(2),
    end_at: isoDaysFromNow(2),
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

async function mountWidget(scope: 'own' | 'all' = 'all') {
  const router = makeRouter()
  await router.push({ name: 'dashboard' })
  await router.isReady()
  const wrapper = mount(UpcomingAppointmentsWidget, { props: { scope }, global: { plugins: [router] } })
  await flushPromises()
  return wrapper
}

beforeEach(() => {
  setActivePinia(createPinia())
  vi.clearAllMocks()
  useAuthStore().user = { id: 'd1', name: 'Front Desk', email: 'fd@example.com', role: 'receptionist' }
})

describe('UpcomingAppointmentsWidget', () => {
  it('fetches the upcoming window and renders the appointments', async () => {
    mockedApi.list.mockResolvedValue([makeAppointment()])
    const wrapper = await mountWidget()

    expect(mockedApi.list).toHaveBeenCalledTimes(1)
    expect(wrapper.text()).toContain('Jane Patient')
  })

  it('shows the empty state with a New Appointment quick action', async () => {
    mockedApi.list.mockResolvedValue([])
    const wrapper = await mountWidget()

    expect(wrapper.text()).toContain('No upcoming appointments.')
    await wrapper.find('button').trigger('click')
    expect(wrapper.emitted('new-appointment')).toHaveLength(1)
  })

  it('excludes cancelled and no-show appointments', async () => {
    mockedApi.list.mockResolvedValue([
      makeAppointment({ id: 'a1', status: 'cancelled', patient: { id: 'p1', patient_code: 'P-1', full_name: 'Cancelled Patient' } }),
      makeAppointment({ id: 'a2', status: 'no_show', patient: { id: 'p2', patient_code: 'P-2', full_name: 'NoShow Patient' } }),
      makeAppointment({ id: 'a3', status: 'confirmed', patient: { id: 'p3', patient_code: 'P-3', full_name: 'Confirmed Patient' } }),
    ])
    const wrapper = await mountWidget()

    expect(wrapper.text()).not.toContain('Cancelled Patient')
    expect(wrapper.text()).not.toContain('NoShow Patient')
    expect(wrapper.text()).toContain('Confirmed Patient')
  })

  it('filters to only the dentist\'s own appointments for scope=own', async () => {
    useAuthStore().user = { id: 'd1', name: 'Dr. Smith', email: 'd1@example.com', role: 'dentist' }
    mockedApi.list.mockResolvedValue([
      makeAppointment({ id: 'a1', dentist_id: 'd1', patient: { id: 'p1', patient_code: 'P-1', full_name: 'Own Patient' } }),
      makeAppointment({ id: 'a2', dentist_id: 'other', patient: { id: 'p2', patient_code: 'P-2', full_name: 'Other Patient' } }),
    ])
    const wrapper = await mountWidget('own')

    expect(wrapper.text()).toContain('Own Patient')
    expect(wrapper.text()).not.toContain('Other Patient')
  })

  it('caps the list at 5 rows', async () => {
    mockedApi.list.mockResolvedValue(
      Array.from({ length: 8 }, (_, i) =>
        makeAppointment({
          id: `a${i}`,
          start_at: isoDaysFromNow(1 + i * 0.1),
          patient: { id: `p${i}`, patient_code: `P-${i}`, full_name: `Patient ${i}` },
        }),
      ),
    )
    const wrapper = await mountWidget()

    expect(wrapper.findAllComponents({ name: 'AppointmentCard' })).toHaveLength(5)
  })
})
