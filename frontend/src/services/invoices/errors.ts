import type { InvoiceError } from '@/types/invoice'

const ERROR_CODES: InvoiceError['code'][] = [
  'invalid_invoice_status_transition',
  'invoice_item_locked',
  'invalid_invoice_item',
]

export function isInvoiceError(data: unknown): data is InvoiceError {
  if (typeof data !== 'object' || data === null || !('code' in data)) return false

  const code = (data as { code: unknown }).code

  return typeof code === 'string' && ERROR_CODES.includes(code as InvoiceError['code'])
}

/**
 * Normalizes an Axios error from an Invoice(Item) endpoint: when the response carries the 422
 * `{message, code}` shape (`InvalidInvoiceStatusTransitionException`, `InvoiceItemLockedException`,
 * `InvalidInvoiceItemException`), rethrows the typed InvoiceError directly so callers can `catch`
 * it and check `.code` without re-parsing the Axios envelope. Anything else (plain 422 validation,
 * 401/403/404/500, network) is rethrown unchanged, mirroring `services/treatmentPlans/errors.ts`.
 */
export function rethrowInvoiceError(error: unknown): never {
  const data = (error as { response?: { data?: unknown } })?.response?.data

  if (isInvoiceError(data)) {
    throw data
  }

  throw error
}
