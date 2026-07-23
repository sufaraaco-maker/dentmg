import { describe, expect, it } from 'vitest'
import { isTreatmentPlanError, rethrowTreatmentPlanError } from './errors'

describe('isTreatmentPlanError', () => {
  it('recognizes a valid plan-transition-error shape', () => {
    expect(isTreatmentPlanError({ message: 'x', code: 'invalid_treatment_plan_status_transition' })).toBe(true)
  })

  it('recognizes a valid item-transition-error shape', () => {
    expect(
      isTreatmentPlanError({ message: 'x', code: 'invalid_treatment_plan_item_status_transition' }),
    ).toBe(true)
  })

  it('recognizes a valid locked-item error shape', () => {
    expect(isTreatmentPlanError({ message: 'x', code: 'treatment_plan_item_locked' })).toBe(true)
  })

  it('recognizes a valid open-items error shape', () => {
    expect(isTreatmentPlanError({ message: 'x', code: 'treatment_plan_has_open_items' })).toBe(true)
  })

  it('rejects an unrecognized code', () => {
    expect(isTreatmentPlanError({ message: 'x', code: 'something_else' })).toBe(false)
  })

  it('rejects plain validation-error shapes (no code field)', () => {
    expect(isTreatmentPlanError({ message: 'x', errors: { dentist_id: ['required'] } })).toBe(false)
  })

  it('rejects non-object input', () => {
    expect(isTreatmentPlanError(null)).toBe(false)
    expect(isTreatmentPlanError(undefined)).toBe(false)
    expect(isTreatmentPlanError('treatment_plan_item_locked')).toBe(false)
  })
})

describe('rethrowTreatmentPlanError', () => {
  it('rethrows the typed error when the response carries a recognized code', () => {
    const axiosError = {
      response: {
        data: {
          message: 'Cannot transition a treatment plan from "draft" to "accepted".',
          code: 'invalid_treatment_plan_status_transition',
        },
      },
    }

    expect(() => rethrowTreatmentPlanError(axiosError)).toThrowError(
      expect.objectContaining({ code: 'invalid_treatment_plan_status_transition' }),
    )
  })

  it('rethrows the original error unchanged for plain validation errors', () => {
    const axiosError = {
      response: { data: { message: 'Validation failed', errors: { dentist_id: ['required'] } } },
    }

    try {
      rethrowTreatmentPlanError(axiosError)
      expect.unreachable()
    } catch (thrown) {
      expect(thrown).toBe(axiosError)
    }
  })

  it('rethrows the original error unchanged when there is no response (network error)', () => {
    const networkError = new Error('Network Error')

    try {
      rethrowTreatmentPlanError(networkError)
      expect.unreachable()
    } catch (thrown) {
      expect(thrown).toBe(networkError)
    }
  })
})
