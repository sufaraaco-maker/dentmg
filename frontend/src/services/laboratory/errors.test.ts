import { describe, expect, it } from 'vitest'
import { isLabCaseError, rethrowLabCaseError } from './errors'

describe('isLabCaseError', () => {
  it('recognizes a valid invalid-lab-case-operation error shape', () => {
    expect(isLabCaseError({ message: 'x', code: 'invalid_lab_case_operation' })).toBe(true)
  })

  it('rejects an unrecognized code', () => {
    expect(isLabCaseError({ message: 'x', code: 'something_else' })).toBe(false)
  })

  it('rejects plain validation-error shapes (no code field)', () => {
    expect(isLabCaseError({ message: 'x', errors: { lab_id: ['required'] } })).toBe(false)
  })

  it('rejects non-object input', () => {
    expect(isLabCaseError(null)).toBe(false)
    expect(isLabCaseError(undefined)).toBe(false)
    expect(isLabCaseError('invalid_lab_case_operation')).toBe(false)
  })
})

describe('rethrowLabCaseError', () => {
  it('rethrows the typed error when the response carries a recognized code', () => {
    const axiosError = {
      response: {
        data: {
          message: 'Cannot move a case from sent to sent.',
          code: 'invalid_lab_case_operation',
        },
      },
    }

    expect(() => rethrowLabCaseError(axiosError)).toThrowError(
      expect.objectContaining({ code: 'invalid_lab_case_operation' }),
    )
  })

  it('rethrows the original error unchanged for plain validation errors', () => {
    const axiosError = {
      response: { data: { message: 'Validation failed', errors: { lab_id: ['required'] } } },
    }

    try {
      rethrowLabCaseError(axiosError)
      expect.unreachable()
    } catch (thrown) {
      expect(thrown).toBe(axiosError)
    }
  })

  it('rethrows the original error unchanged when there is no response (network error)', () => {
    const networkError = new Error('Network Error')

    try {
      rethrowLabCaseError(networkError)
      expect.unreachable()
    } catch (thrown) {
      expect(thrown).toBe(networkError)
    }
  })
})
