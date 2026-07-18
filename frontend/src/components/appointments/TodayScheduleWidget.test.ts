import { mount, flushPromises } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { createMemoryHistory, createRouter } from 'vue-router'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import TodayScheduleWidget from './TodayScheduleWidget.vue'
import { appointmentsApi } from '@/services/appointments'
import { useAuthStore } from '@/stores/auth'
import type { Appointment } from '@/types/appointment'

vi.mock('@/services/appointments', () => ({
  appointmentsApi: { list: vi.fn(), checkIn: vi.fn(), get: vi.fn() },
  isAppointmentConflictError: () => false,
}))

const mockedApi = vi.mocked(appointmentsApi)

// Bypasses StatusActionButton's own PrimeVue confirm-dialog UX (already covered by its own test
// file) so this test can focus on TodayScheduleWidget's own logic: which single row gets the
// button, and that clicking it calls the store action.
const StatusActionButtonStub = {
  props: ['action', 'requiresReason', 'loading'],
  emits: ['confirmed'],
  template: '<button :data-action="action" @click="$emit(\'confirmed\')">Check In</button>',
}

function makeRouter() {
  return createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/', name: 'dashboard', component: { template: '<div />' } },
      { path: '/appointments/:id', name: 'appointment-detail', component: { template: '<div />' } },
    ],
  })
}

// `parseServerDateTime` (lib/date.ts) reads a timestamp's digits via UTC getters and treats them
// AS the clinic's local wall-clock time — so these helpers must format a Date's own *local*
// components with a `+00:00` suffix, not `.toISOString()` (which would re-express through real
// UTC and shift the digits on any non-UTC test-runner timezone).
function toServerTimestamp(d: Date): string {
  const pad = (n: number) => String(n).padStart(2, '0')
  return (
    `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}` +
    `T${pad(d.getHours())}:${pad(d.getMinutes())}:${pad(d.getSeconds())}+00:00`
  )
}

function isoAtTodayOffset(hours: number): string {
  const d = new Date()
  d.setHours(hours, 0, 0, 0)
  return toServerTimestamp(d)
}

/** Guaranteed to stay within "today" (clamped to 00:00) regardless of what time the suite runs. */
function isoMinutesAgo(minutes: number): string {
  const startOfDay = new Date()
  startOfDay.setHours(0, 0, 0, 0)
  const target = new Date(Date.now() - minutes * 60_000)
  const d = target < startOfDay ? startOfDay : target
  return toServerTimestamp(d)
}

function makeAppointment(overrides: Partial<Appointment> = {}): Appointment {
  return {
    id: 'a1',
    patient_id: 'p1',
    dentist_id: 'd1',
    appointment_type_id: 't1',
    start_at: isoAtTodayOffset(9),
    end_at: isoAtTodayOffset(10),
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
  const wrapper = mount(TodayScheduleWidget, {
    props: { scope },
    global: { plugins: [router], stubs: { StatusActionButton: StatusActionButtonStub } },
  })
  await flushPromises()
  return wrapper
}

beforeEach(() => {
  setActivePinia(createPinia())
  vi.clearAllMocks()
  useAuthStore().user = { id: 'd1', name: 'Front Desk', email: 'fd@example.com', role: 'receptionist' }
})

describe('TodayScheduleWidget', () => {
  it('fetches today and renders the appointments', async () => {
    mockedApi.list.mockResolvedValue([makeAppointment()])
    const wrapper = await mountWidget()

    expect(mockedApi.list).toHaveBeenCalledTimes(1)
    expect(wrapper.text()).toContain('Jane Patient')
  })

  it('shows the empty state with a New Appointment quick action', async () => {
    mockedApi.list.mockResolvedValue([])
    const wrapper = await mountWidget()

    expect(wrapper.text()).toContain('No appointments scheduled today.')
    await wrapper.find('button').trigger('click')
    expect(wrapper.emitted('new-appointment')).toHaveLength(1)
  })

  it('shows a Waiting tag for a checked-in appointment still not started, scope=all', async () => {
    mockedApi.list.mockResolvedValue([
      makeAppointment({ status: 'checked_in', checked_in_at: isoAtTodayOffset(9) }),
    ])
    const wrapper = await mountWidget('all')

    expect(wrapper.text()).toContain('Waiting')
  })

  it('shows a Late tag for a scheduled appointment well past its start time, scope=all', async () => {
    mockedApi.list.mockResolvedValue([makeAppointment({ status: 'scheduled', start_at: isoMinutesAgo(30) })])
    const wrapper = await mountWidget('all')

    expect(wrapper.text()).toContain('Late')
  })

  it('does not show Waiting/Late/Check-In framing for scope=own (dentist view)', async () => {
    useAuthStore().user = { id: 'd1', name: 'Dr. Smith', email: 'd1@example.com', role: 'dentist' }
    mockedApi.list.mockResolvedValue([makeAppointment({ status: 'checked_in', dentist_id: 'd1' })])
    const wrapper = await mountWidget('own')

    expect(wrapper.text()).not.toContain('Waiting')
    expect(wrapper.find('button[data-action="checkIn"]').exists()).toBe(false)
  })

  it("filters to only the dentist's own appointments for scope=own", async () => {
    useAuthStore().user = { id: 'd1', name: 'Dr. Smith', email: 'd1@example.com', role: 'dentist' }
    mockedApi.list.mockResolvedValue([
      makeAppointment({
        id: 'a1',
        dentist_id: 'd1',
        patient: { id: 'p1', patient_code: 'P-1', full_name: 'Own Patient' },
      }),
      makeAppointment({
        id: 'a2',
        dentist_id: 'other',
        patient: { id: 'p2', patient_code: 'P-2', full_name: 'Other Patient' },
      }),
    ])
    const wrapper = await mountWidget('own')

    expect(wrapper.text()).toContain('Own Patient')
    expect(wrapper.text()).not.toContain('Other Patient')
  })

  it('calls checkIn for the next scheduled appointment when confirmed', async () => {
    mockedApi.list.mockResolvedValue([makeAppointment({ status: 'scheduled' })])
    mockedApi.checkIn.mockResolvedValue(undefined)
    mockedApi.get.mockResolvedValue(makeAppointment({ status: 'checked_in' }))

    const wrapper = await mountWidget('all')
    await wrapper.find('button[data-action="checkIn"]').trigger('click')
    await flushPromises()

    expect(mockedApi.checkIn).toHaveBeenCalledWith('a1')
  })
})
