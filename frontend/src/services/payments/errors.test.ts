import { describe, expect, it } from 'vitest'
import { isPaymentError, rethrowPaymentError } from './errors'

describe('isPaymentError', () => {
  it('recognizes a valid invalid-operation error shape', () => {
    expect(isPaymentError({ message: 'x', code: 'invalid_payment_operation' })).toBe(true)
  })

  it('recognizes a valid refund-exceeds-balance error shape', () => {
    expect(isPaymentError({ message: 'x', code: 'payment_refund_exceeds_remaining_balance' })).toBe(true)
  })

  it('rejects an unrecognized code', () => {
    expect(isPaymentError({ message: 'x', code: 'something_else' })).toBe(false)
  })

  it('rejects plain validation-error shapes (no code field)', () => {
    expect(isPaymentError({ message: 'x', errors: { amount: ['required'] } })).toBe(false)
  })

  it('rejects non-object input', () => {
    expect(isPaymentError(null)).toBe(false)
    expect(isPaymentError(undefined)).toBe(false)
    expect(isPaymentError('invalid_payment_operation')).toBe(false)
  })
})

describe('rethrowPaymentError', () => {
  it('rethrows the typed error when the response carries a recognized code', () => {
    const axiosError = {
      response: {
        data: {
          message: "The refund amount exceeds this payment's remaining refundable balance of 20.00.",
          code: 'payment_refund_exceeds_remaining_balance',
        },
      },
    }

    expect(() => rethrowPaymentError(axiosError)).toThrowError(
      expect.objectContaining({ code: 'payment_refund_exceeds_remaining_balance' }),
    )
  })

  it('rethrows the original error unchanged for plain validation errors', () => {
    const axiosError = {
      response: { data: { message: 'Validation failed', errors: { amount: ['required'] } } },
    }

    try {
      rethrowPaymentError(axiosError)
      expect.unreachable()
    } catch (thrown) {
      expect(thrown).toBe(axiosError)
    }
  })

  it('rethrows the original error unchanged when there is no response (network error)', () => {
    const networkError = new Error('Network Error')

    try {
      rethrowPaymentError(networkError)
      expect.unreachable()
    } catch (thrown) {
      expect(thrown).toBe(networkError)
    }
  })
})
