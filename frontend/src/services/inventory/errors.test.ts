import { describe, expect, it } from 'vitest'
import { isInventoryError, rethrowInventoryError } from './errors'

describe('isInventoryError', () => {
  it('recognizes a valid insufficient-stock error shape', () => {
    expect(isInventoryError({ message: 'x', code: 'inventory_insufficient_stock' })).toBe(true)
  })

  it('recognizes a valid invalid-purchase-order-operation error shape', () => {
    expect(isInventoryError({ message: 'x', code: 'invalid_purchase_order_operation' })).toBe(true)
  })

  it('rejects an unrecognized code', () => {
    expect(isInventoryError({ message: 'x', code: 'something_else' })).toBe(false)
  })

  it('rejects plain validation-error shapes (no code field)', () => {
    expect(isInventoryError({ message: 'x', errors: { quantity: ['required'] } })).toBe(false)
  })

  it('rejects non-object input', () => {
    expect(isInventoryError(null)).toBe(false)
    expect(isInventoryError(undefined)).toBe(false)
    expect(isInventoryError('inventory_insufficient_stock')).toBe(false)
  })
})

describe('rethrowInventoryError', () => {
  it('rethrows the typed error when the response carries a recognized code', () => {
    const axiosError = {
      response: {
        data: {
          message: 'This would exceed the ordered quantity.',
          code: 'invalid_purchase_order_operation',
        },
      },
    }

    expect(() => rethrowInventoryError(axiosError)).toThrowError(
      expect.objectContaining({ code: 'invalid_purchase_order_operation' }),
    )
  })

  it('rethrows the original error unchanged for plain validation errors', () => {
    const axiosError = {
      response: { data: { message: 'Validation failed', errors: { quantity: ['required'] } } },
    }

    try {
      rethrowInventoryError(axiosError)
      expect.unreachable()
    } catch (thrown) {
      expect(thrown).toBe(axiosError)
    }
  })

  it('rethrows the original error unchanged when there is no response (network error)', () => {
    const networkError = new Error('Network Error')

    try {
      rethrowInventoryError(networkError)
      expect.unreachable()
    } catch (thrown) {
      expect(thrown).toBe(networkError)
    }
  })
})
