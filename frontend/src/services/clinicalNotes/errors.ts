import type { ClinicalNoteError } from '@/types/clinicalNote'

const ERROR_CODES: ClinicalNoteError['code'][] = ['clinical_note_locked', 'invalid_clinical_note_operation']

export function isClinicalNoteError(data: unknown): data is ClinicalNoteError {
  if (typeof data !== 'object' || data === null || !('code' in data)) return false

  const code = (data as { code: unknown }).code

  return typeof code === 'string' && ERROR_CODES.includes(code as ClinicalNoteError['code'])
}

/**
 * Normalizes an Axios error from a Clinical Note endpoint, mirroring
 * `services/treatmentPlans/errors.ts`/`services/payments/errors.ts` exactly: rethrows the typed
 * ClinicalNoteError when the response carries the 422 `{message, code}` shape, so callers can
 * `catch` it and check `.code` without re-parsing the Axios envelope.
 */
export function rethrowClinicalNoteError(error: unknown): never {
  const data = (error as { response?: { data?: unknown } })?.response?.data

  if (isClinicalNoteError(data)) {
    throw data
  }

  throw error
}
