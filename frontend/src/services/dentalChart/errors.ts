import type { DentalChartEntryError } from '@/types/dentalChart'

const ERROR_CODES: DentalChartEntryError['code'][] = [
  'invalid_status_transition',
  'dental_chart_entry_locked',
]

export function isDentalChartEntryError(data: unknown): data is DentalChartEntryError {
  if (typeof data !== 'object' || data === null || !('code' in data)) return false

  const code = (data as { code: unknown }).code

  return typeof code === 'string' && ERROR_CODES.includes(code as DentalChartEntryError['code'])
}

/**
 * Normalizes an Axios error from a Dental Chart Entry endpoint: when the response carries the
 * 422 `{message, code}` shape (`InvalidStatusTransitionException`/`EntryLockedException`),
 * rethrows the typed DentalChartEntryError directly so callers can `catch` it and check `.code`
 * without re-parsing the Axios envelope. Anything else (plain 422 validation, 401/403/404/500,
 * network) is rethrown unchanged, mirroring `services/appointments/errors.ts`.
 */
export function rethrowDentalChartEntryError(error: unknown): never {
  const data = (error as { response?: { data?: unknown } })?.response?.data

  if (isDentalChartEntryError(data)) {
    throw data
  }

  throw error
}
