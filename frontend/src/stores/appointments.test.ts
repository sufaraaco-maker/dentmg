import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { appointmentsApi } from '@/services/appointments'
import { useAppointmentsStore } from './appointments'
import { useCalendarStore } from './calendar'
import type { Appointment } from '@/types/appointment'

vi.mock('@/services/appointments', () => ({
  appointmentsApi: {
    list: vi.fn(),
    get: vi.fn(),
    create: vi.fn(),
    update: vi.fn(),
    confirm: vi.fn(),
    checkIn: vi.fn(),
    start: vi.fn(),
    complete: vi.fn(),
    cancel: vi.fn(),
    noShow: vi.fn(),
    remove: vi.fn(),
  },
}))

const mockedApi = vi.mocked(appointmentsApi)

function makeAppointment(overrides: Partial<Appointment> = {}): Appointment {
  return {
    id: overrides.id ?? 'apt-1',
    patient_id: 'patient-1',
    dentist_id: 'dentist-1',
    appointment_type_id: 'type-1',
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
    created_at: '2026-07-15T00:00:00+00:00',
    updated_at: '2026-07-15T00:00:00+00:00',
    ...overrides,
  }
}

beforeEach(() => {
  setActivePinia(createPinia())
  vi.clearAllMocks()
})

describe('useAppointmentsStore.fetchRange', () => {
  it('fetches and caches appointments for a range', async () => {
    mockedApi.list.mockResolvedValueOnce([makeAppointment()])
    const store = useAppointmentsStore()

    await store.fetchRange(new Date(2026, 6, 20), new Date(2026, 6, 26))

    expect(store.cache.get('apt-1')).toBeDefined()
    expect(mockedApi.list).toHaveBeenCalledTimes(1)
  })

  it('skips the network call when the range is already fully loaded', async () => {
    mockedApi.list.mockResolvedValue([makeAppointment()])
    const store = useAppointmentsStore()

    await store.fetchRange(new Date(2026, 6, 20), new Date(2026, 6, 26))
    await store.fetchRange(new Date(2026, 6, 21), new Date(2026, 6, 23)) // fully inside the first range

    expect(mockedApi.list).toHaveBeenCalledTimes(1)
  })

  it('refetches when force is true even if the range is already loaded', async () => {
    mockedApi.list.mockResolvedValue([makeAppointment()])
    const store = useAppointmentsStore()

    await store.fetchRange(new Date(2026, 6, 20), new Date(2026, 6, 26))
    await store.fetchRange(new Date(2026, 6, 20), new Date(2026, 6, 26), true)

    expect(mockedApi.list).toHaveBeenCalledTimes(2)
  })

  it('sets a translation-key error and clears loading on failure', async () => {
    mockedApi.list.mockRejectedValueOnce(new Error('network down'))
    const store = useAppointmentsStore()

    await store.fetchRange(new Date(2026, 6, 20), new Date(2026, 6, 26))

    expect(store.error).toBe('appointments.loadError')
    expect(store.loading).toBe(false)
  })
})

describe('useAppointmentsStore mutations (post-mutation rehydration)', () => {
  it('create() re-fetches the single appointment to hydrate nested relations', async () => {
    const created = makeAppointment({ id: 'apt-2' })
    const hydrated = makeAppointment({
      id: 'apt-2',
      patient: { id: 'p1', patient_code: 'P-00001', full_name: 'Jane Doe' },
    })
    mockedApi.create.mockResolvedValueOnce(created)
    mockedApi.get.mockResolvedValueOnce(hydrated)

    const store = useAppointmentsStore()
    const result = await store.create({
      patient_id: 'patient-1',
      dentist_id: 'dentist-1',
      appointment_type_id: 'type-1',
      start_at: '2026-07-20T09:00:00+00:00',
      duration_minutes: 30,
    })

    expect(mockedApi.get).toHaveBeenCalledWith('apt-2')
    expect(result.patient?.full_name).toBe('Jane Doe')
    expect(store.cache.get('apt-2')?.patient?.full_name).toBe('Jane Doe')
  })

  it('confirm() calls the transition endpoint then rehydrates', async () => {
    mockedApi.confirm.mockResolvedValueOnce(makeAppointment({ status: 'confirmed' }))
    mockedApi.get.mockResolvedValueOnce(makeAppointment({ status: 'confirmed' }))

    const store = useAppointmentsStore()
    const result = await store.confirm('apt-1')

    expect(mockedApi.confirm).toHaveBeenCalledWith('apt-1')
    expect(mockedApi.get).toHaveBeenCalledWith('apt-1')
    expect(result.status).toBe('confirmed')
  })

  it('a conflict error thrown by the service propagates through the store action unchanged', async () => {
    mockedApi.create.mockRejectedValueOnce({ message: 'Dentist double-booked', code: 'dentist_conflict' })
    const store = useAppointmentsStore()

    await expect(
      store.create({
        patient_id: 'patient-1',
        dentist_id: 'dentist-1',
        appointment_type_id: 'type-1',
        start_at: '2026-07-20T09:00:00+00:00',
        duration_minutes: 30,
      }),
    ).rejects.toEqual(expect.objectContaining({ code: 'dentist_conflict' }))
    expect(mockedApi.get).not.toHaveBeenCalled()
  })

  it('remove() deletes the appointment and evicts it from the cache', async () => {
    mockedApi.list.mockResolvedValueOnce([makeAppointment()])
    mockedApi.remove.mockResolvedValueOnce(undefined)

    const store = useAppointmentsStore()
    await store.fetchRange(new Date(2026, 6, 20), new Date(2026, 6, 26))
    expect(store.cache.has('apt-1')).toBe(true)

    await store.remove('apt-1')

    expect(store.cache.has('apt-1')).toBe(false)
  })
})

describe('useAppointmentsStore.filteredAppointments', () => {
  it('narrows cached appointments to the calendar store range and filters', async () => {
    mockedApi.list.mockResolvedValueOnce([
      makeAppointment({ id: 'in-range', start_at: '2026-07-15T09:00:00+00:00', dentist_id: 'dentist-1' }),
      makeAppointment({
        id: 'wrong-dentist',
        start_at: '2026-07-15T10:00:00+00:00',
        dentist_id: 'dentist-2',
      }),
      makeAppointment({ id: 'out-of-range', start_at: '2026-09-01T09:00:00+00:00', dentist_id: 'dentist-1' }),
    ])

    const calendar = useCalendarStore()
    calendar.currentDate = new Date(2026, 6, 15)
    calendar.setViewMode('timeGridWeek')
    calendar.setFilter('dentistIds', ['dentist-1'])

    const store = useAppointmentsStore()
    await store.fetchRange(calendar.currentRange.start, calendar.currentRange.end)

    const ids = store.filteredAppointments.map((a) => a.id)
    expect(ids).toEqual(['in-range'])
  })
})
