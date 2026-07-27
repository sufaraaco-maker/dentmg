import type { InventoryError } from '@/types/inventory'

const ERROR_CODES: InventoryError['code'][] = ['inventory_insufficient_stock', 'invalid_purchase_order_operation']

export function isInventoryError(data: unknown): data is InventoryError {
  if (typeof data !== 'object' || data === null || !('code' in data)) return false

  const code = (data as { code: unknown }).code

  return typeof code === 'string' && ERROR_CODES.includes(code as InventoryError['code'])
}

/**
 * Normalizes an Axios error from an Inventory endpoint, mirroring `services/payments/errors.ts`
 * exactly: rethrows the typed InventoryError when the response carries the 422 `{message, code}`
 * shape, so callers can `catch` it and check `.code` without re-parsing the Axios envelope.
 */
export function rethrowInventoryError(error: unknown): never {
  const data = (error as { response?: { data?: unknown } })?.response?.data

  if (isInventoryError(data)) {
    throw data
  }

  throw error
}
