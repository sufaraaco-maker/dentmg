import type { AppointmentConflictError } from '@/types/appointment'

const CONFLICT_CODES: AppointmentConflictError['code'][] = [
  'dentist_conflict',
  'patient_conflict',
  'outside_working_hours',
  'early_no_show',
  'invalid_status_transition',
]

export function isAppointmentConflictError(data: unknown): data is AppointmentConflictError {
  if (typeof data !== 'object' || data === null || !('code' in data)) return false

  const code = (data as { code: unknown }).code

  return typeof code === 'string' && CONFLICT_CODES.includes(code as AppointmentConflictError['code'])
}

/**
 * Normalizes an Axios error from an Appointments endpoint: when the response carries the
 * 409/422 `code`/`overridable`/`override_field` shape (docs/modules/appointments-ui-design.md
 * §17), rethrows the typed AppointmentConflictError directly so callers can `catch` it and
 * check `.code`/`.overridable` without re-parsing the Axios envelope. Anything else (plain
 * 422 validation, 401/403/404/500/network) is rethrown unchanged for the caller's existing
 * generic handling (matching the PatientFormDialog convention).
 */
export function rethrowAppointmentError(error: unknown): never {
  const data = (error as { response?: { data?: unknown } })?.response?.data

  if (isAppointmentConflictError(data)) {
    throw data
  }

  throw error
}
