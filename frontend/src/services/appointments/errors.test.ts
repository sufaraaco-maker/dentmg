import { describe, expect, it } from 'vitest'
import { isAppointmentConflictError, rethrowAppointmentError } from './errors'

describe('isAppointmentConflictError', () => {
  it('recognizes a valid conflict error shape', () => {
    expect(isAppointmentConflictError({ message: 'x', code: 'dentist_conflict' })).toBe(true)
    expect(
      isAppointmentConflictError({
        message: 'x',
        code: 'patient_conflict',
        overridable: true,
        override_field: 'override_patient_conflict',
      }),
    ).toBe(true)
  })

  it('rejects an unrecognized code', () => {
    expect(isAppointmentConflictError({ message: 'x', code: 'something_else' })).toBe(false)
  })

  it('rejects plain validation-error shapes (no code field)', () => {
    expect(isAppointmentConflictError({ message: 'x', errors: { start_at: ['required'] } })).toBe(false)
  })

  it('rejects non-object input', () => {
    expect(isAppointmentConflictError(null)).toBe(false)
    expect(isAppointmentConflictError(undefined)).toBe(false)
    expect(isAppointmentConflictError('dentist_conflict')).toBe(false)
  })
})

describe('rethrowAppointmentError', () => {
  it('rethrows the typed conflict error when the response carries a recognized code', () => {
    const axiosError = {
      response: { data: { message: 'Dentist is double-booked', code: 'dentist_conflict' } },
    }

    expect(() => rethrowAppointmentError(axiosError)).toThrowError(
      expect.objectContaining({ code: 'dentist_conflict' }),
    )
  })

  it('rethrows the original error unchanged for plain validation errors', () => {
    const axiosError = {
      response: { data: { message: 'Validation failed', errors: { start_at: ['required'] } } },
    }

    try {
      rethrowAppointmentError(axiosError)
      expect.unreachable()
    } catch (thrown) {
      expect(thrown).toBe(axiosError)
    }
  })

  it('rethrows the original error unchanged when there is no response (network error)', () => {
    const networkError = new Error('Network Error')

    try {
      rethrowAppointmentError(networkError)
      expect.unreachable()
    } catch (thrown) {
      expect(thrown).toBe(networkError)
    }
  })
})
