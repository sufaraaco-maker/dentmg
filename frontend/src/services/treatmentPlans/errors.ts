import type { TreatmentPlanError } from '@/types/treatmentPlan'

const ERROR_CODES: TreatmentPlanError['code'][] = [
  'invalid_treatment_plan_status_transition',
  'invalid_treatment_plan_item_status_transition',
  'treatment_plan_item_locked',
  'treatment_plan_has_open_items',
]

export function isTreatmentPlanError(data: unknown): data is TreatmentPlanError {
  if (typeof data !== 'object' || data === null || !('code' in data)) return false

  const code = (data as { code: unknown }).code

  return typeof code === 'string' && ERROR_CODES.includes(code as TreatmentPlanError['code'])
}

/**
 * Normalizes an Axios error from a Treatment Plan(s Item) endpoint: when the response carries the
 * 422 `{message, code}` shape (`InvalidTreatmentPlan(Item)StatusTransitionException`,
 * `TreatmentPlanItemLockedException`, `TreatmentPlanHasOpenItemsException`), rethrows the typed
 * TreatmentPlanError directly so callers can `catch` it and check `.code` without re-parsing the
 * Axios envelope. Anything else (plain 422 validation, 401/403/404/500, network) is rethrown
 * unchanged, mirroring `services/dentalChart/errors.ts`.
 */
export function rethrowTreatmentPlanError(error: unknown): never {
  const data = (error as { response?: { data?: unknown } })?.response?.data

  if (isTreatmentPlanError(data)) {
    throw data
  }

  throw error
}
