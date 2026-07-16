import { beforeEach, describe, expect, it, vi } from 'vitest'
import { api } from '@/lib/api'
import { appointmentsApi } from './appointmentsApi'
import type { Appointment } from '@/types/appointment'

vi.mock('@/lib/api', () => ({
  api: {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    delete: vi.fn(),
  },
}))

const mockedApi = vi.mocked(api)

function makeAppointment(overrides: Partial<Appointment> = {}): Appointment {
  return {
    id: 'apt-1',
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
  vi.clearAllMocks()
})

describe('appointmentsApi.list', () => {
  it('returns the bare array the endpoint sends (deliberately not paginated) and forwards params', async () => {
    const appointments = [makeAppointment()]
    // GET /api/appointments is not paginated (backend design doc §16/§19) — a {data: [...]}
    // envelope here was a real bug (found via manual browser verification against the real
    // backend, not caught by this test until it was corrected to match the actual contract).
    mockedApi.get.mockResolvedValueOnce({ data: appointments })

    const result = await appointmentsApi.list({ date_from: '2026-07-20', date_to: '2026-07-26' })

    expect(result).toEqual(appointments)
    expect(mockedApi.get).toHaveBeenCalledWith('/appointments', {
      params: { date_from: '2026-07-20', date_to: '2026-07-26' },
    })
  })
})

describe('appointmentsApi.create', () => {
  it('returns the created appointment on success', async () => {
    const created = makeAppointment()
    mockedApi.post.mockResolvedValueOnce({ data: created })

    const result = await appointmentsApi.create({
      patient_id: 'patient-1',
      dentist_id: 'dentist-1',
      appointment_type_id: 'type-1',
      start_at: '2026-07-20T09:00:00+00:00',
      duration_minutes: 30,
    })

    expect(result).toEqual(created)
  })

  it('rethrows a typed AppointmentConflictError on a 409 dentist conflict', async () => {
    mockedApi.post.mockRejectedValueOnce({
      response: { status: 409, data: { message: 'Dentist double-booked', code: 'dentist_conflict' } },
    })

    await expect(
      appointmentsApi.create({
        patient_id: 'patient-1',
        dentist_id: 'dentist-1',
        appointment_type_id: 'type-1',
        start_at: '2026-07-20T09:00:00+00:00',
        duration_minutes: 30,
      }),
    ).rejects.toEqual(expect.objectContaining({ code: 'dentist_conflict' }))
  })

  it('rethrows a plain 422 validation error unchanged', async () => {
    const validationError = { response: { status: 422, data: { errors: { start_at: ['required'] } } } }
    mockedApi.post.mockRejectedValueOnce(validationError)

    await expect(
      appointmentsApi.create({
        patient_id: 'patient-1',
        dentist_id: 'dentist-1',
        appointment_type_id: 'type-1',
        start_at: '',
        duration_minutes: 30,
      }),
    ).rejects.toBe(validationError)
  })
})

describe('appointmentsApi status transitions', () => {
  it.each(['confirm', 'checkIn', 'start', 'complete'] as const)(
    '%s posts to the right endpoint',
    async (fn) => {
      const updated = makeAppointment({ status: 'confirmed' })
      mockedApi.post.mockResolvedValueOnce({ data: updated })

      const result = await appointmentsApi[fn]('apt-1')

      expect(result).toEqual(updated)
      expect(mockedApi.post).toHaveBeenCalledTimes(1)
    },
  )

  it('cancel sends the cancellation reason payload', async () => {
    const cancelled = makeAppointment({ status: 'cancelled' })
    mockedApi.post.mockResolvedValueOnce({ data: cancelled })

    await appointmentsApi.cancel('apt-1', { cancellation_reason: 'Patient requested' })

    expect(mockedApi.post).toHaveBeenCalledWith('/appointments/apt-1/cancel', {
      cancellation_reason: 'Patient requested',
    })
  })

  it('noShow rethrows a typed conflict error when marked before start_at', async () => {
    mockedApi.post.mockRejectedValueOnce({
      response: {
        status: 422,
        data: {
          message: 'Too early to mark as no-show',
          code: 'early_no_show',
          overridable: true,
          override_field: 'override_early_no_show',
        },
      },
    })

    await expect(appointmentsApi.noShow('apt-1')).rejects.toEqual(
      expect.objectContaining({ code: 'early_no_show', overridable: true }),
    )
  })
})

describe('appointmentsApi.availableSlots', () => {
  it('returns the slots array', async () => {
    mockedApi.get.mockResolvedValueOnce({ data: { slots: ['2026-07-20T09:00:00+00:00'] } })

    const result = await appointmentsApi.availableSlots({
      dentist_id: 'dentist-1',
      date: '2026-07-20',
      duration_minutes: 30,
    })

    expect(result).toEqual(['2026-07-20T09:00:00+00:00'])
  })
})
