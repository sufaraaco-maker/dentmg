import type { LabCaseError } from '@/types/laboratory'

const ERROR_CODES: LabCaseError['code'][] = ['invalid_lab_case_operation']

export function isLabCaseError(data: unknown): data is LabCaseError {
  if (typeof data !== 'object' || data === null || !('code' in data)) return false

  const code = (data as { code: unknown }).code

  return typeof code === 'string' && ERROR_CODES.includes(code as LabCaseError['code'])
}

/**
 * Normalizes an Axios error from a Laboratory endpoint, mirroring `services/inventory/errors.ts`
 * exactly: rethrows the typed LabCaseError when the response carries the 422 `{message, code}`
 * shape, so callers can `catch` it and check `.code` without re-parsing the Axios envelope.
 */
export function rethrowLabCaseError(error: unknown): never {
  const data = (error as { response?: { data?: unknown } })?.response?.data

  if (isLabCaseError(data)) {
    throw data
  }

  throw error
}
