import { describe, expect, it } from 'vitest'
import { isDentalChartEntryError, rethrowDentalChartEntryError } from './errors'

describe('isDentalChartEntryError', () => {
  it('recognizes a valid transition-error shape', () => {
    expect(isDentalChartEntryError({ message: 'x', code: 'invalid_status_transition' })).toBe(true)
  })

  it('recognizes a valid locked-entry error shape', () => {
    expect(isDentalChartEntryError({ message: 'x', code: 'dental_chart_entry_locked' })).toBe(true)
  })

  it('rejects an unrecognized code', () => {
    expect(isDentalChartEntryError({ message: 'x', code: 'something_else' })).toBe(false)
  })

  it('rejects plain validation-error shapes (no code field)', () => {
    expect(isDentalChartEntryError({ message: 'x', errors: { tooth_number: ['required'] } })).toBe(false)
  })

  it('rejects non-object input', () => {
    expect(isDentalChartEntryError(null)).toBe(false)
    expect(isDentalChartEntryError(undefined)).toBe(false)
    expect(isDentalChartEntryError('invalid_status_transition')).toBe(false)
  })
})

describe('rethrowDentalChartEntryError', () => {
  it('rethrows the typed error when the response carries a recognized code', () => {
    const axiosError = {
      response: {
        data: {
          message: 'Cannot transition from "active" to "completed".',
          code: 'invalid_status_transition',
        },
      },
    }

    expect(() => rethrowDentalChartEntryError(axiosError)).toThrowError(
      expect.objectContaining({ code: 'invalid_status_transition' }),
    )
  })

  it('rethrows the original error unchanged for plain validation errors', () => {
    const axiosError = {
      response: { data: { message: 'Validation failed', errors: { tooth_number: ['required'] } } },
    }

    try {
      rethrowDentalChartEntryError(axiosError)
      expect.unreachable()
    } catch (thrown) {
      expect(thrown).toBe(axiosError)
    }
  })

  it('rethrows the original error unchanged when there is no response (network error)', () => {
    const networkError = new Error('Network Error')

    try {
      rethrowDentalChartEntryError(networkError)
      expect.unreachable()
    } catch (thrown) {
      expect(thrown).toBe(networkError)
    }
  })
})
