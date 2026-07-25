import type { PaymentError } from '@/types/payment'

const ERROR_CODES: PaymentError['code'][] = ['invalid_payment_operation', 'payment_refund_exceeds_remaining_balance']

export function isPaymentError(data: unknown): data is PaymentError {
  if (typeof data !== 'object' || data === null || !('code' in data)) return false

  const code = (data as { code: unknown }).code

  return typeof code === 'string' && ERROR_CODES.includes(code as PaymentError['code'])
}

/**
 * Normalizes an Axios error from a Payment endpoint, mirroring `services/invoices/errors.ts`
 * exactly: rethrows the typed PaymentError when the response carries the 422 `{message, code}`
 * shape, so callers can `catch` it and check `.code` without re-parsing the Axios envelope.
 */
export function rethrowPaymentError(error: unknown): never {
  const data = (error as { response?: { data?: unknown } })?.response?.data

  if (isPaymentError(data)) {
    throw data
  }

  throw error
}
