import { describe, expect, it } from 'vitest'
import { isClinicalNoteError, rethrowClinicalNoteError } from './errors'

describe('isClinicalNoteError', () => {
  it('recognizes a valid locked-note error shape', () => {
    expect(isClinicalNoteError({ message: 'x', code: 'clinical_note_locked' })).toBe(true)
  })

  it('recognizes a valid invalid-operation error shape', () => {
    expect(isClinicalNoteError({ message: 'x', code: 'invalid_clinical_note_operation' })).toBe(true)
  })

  it('rejects an unrecognized code', () => {
    expect(isClinicalNoteError({ message: 'x', code: 'something_else' })).toBe(false)
  })

  it('rejects plain validation-error shapes (no code field)', () => {
    expect(isClinicalNoteError({ message: 'x', errors: { note_type: ['required'] } })).toBe(false)
  })

  it('rejects non-object input', () => {
    expect(isClinicalNoteError(null)).toBe(false)
    expect(isClinicalNoteError(undefined)).toBe(false)
    expect(isClinicalNoteError('clinical_note_locked')).toBe(false)
  })
})

describe('rethrowClinicalNoteError', () => {
  it('rethrows the typed error when the response carries a recognized code', () => {
    const axiosError = {
      response: {
        data: {
          message: 'This clinical note is signed and can no longer be edited. Add an addendum instead.',
          code: 'clinical_note_locked',
        },
      },
    }

    expect(() => rethrowClinicalNoteError(axiosError)).toThrowError(
      expect.objectContaining({ code: 'clinical_note_locked' }),
    )
  })

  it('rethrows the original error unchanged for plain validation errors', () => {
    const axiosError = {
      response: { data: { message: 'Validation failed', errors: { note_type: ['required'] } } },
    }

    try {
      rethrowClinicalNoteError(axiosError)
      expect.unreachable()
    } catch (thrown) {
      expect(thrown).toBe(axiosError)
    }
  })

  it('rethrows the original error unchanged when there is no response (network error)', () => {
    const networkError = new Error('Network Error')

    try {
      rethrowClinicalNoteError(networkError)
      expect.unreachable()
    } catch (thrown) {
      expect(thrown).toBe(networkError)
    }
  })
})
