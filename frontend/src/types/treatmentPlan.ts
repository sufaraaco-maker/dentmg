import type { DentalConditionCategory, DentalChartEntryStatus, ToothSurface } from './dentalChart'
import type { AppointmentStatus } from './appointment'

export type TreatmentPlanStatus =
  'draft' | 'presented' | 'accepted' | 'in_progress' | 'completed' | 'rejected' | 'cancelled'

export const TREATMENT_PLAN_STATUSES: TreatmentPlanStatus[] = [
  'draft',
  'presented',
  'accepted',
  'in_progress',
  'completed',
  'rejected',
  'cancelled',
]

/** Terminal statuses per the backend's `TreatmentPlanStatus::isTerminal()` (design doc §5). */
export const TERMINAL_TREATMENT_PLAN_STATUSES: TreatmentPlanStatus[] = ['completed', 'rejected', 'cancelled']

export type TreatmentPlanItemStatus = 'planned' | 'completed' | 'cancelled'

export const TREATMENT_PLAN_ITEM_STATUSES: TreatmentPlanItemStatus[] = ['planned', 'completed', 'cancelled']

export interface TreatmentPlanUserSummary {
  id: string
  name: string
}

/** The embedded `dental_condition` shape on a `TreatmentPlanItem` — not the full catalog record. */
export interface TreatmentPlanConditionSummary {
  id: string
  name: string
  category: DentalConditionCategory
}

/** The embedded `diagnosis_entry` shape — a read-only traceability reference (design doc §7). */
export interface TreatmentPlanDiagnosisEntrySummary {
  id: string
  tooth_number: string
  status: DentalChartEntryStatus
}

/** The embedded `appointment` shape — a read-only reference, set once the item is booked (§7). */
export interface TreatmentPlanAppointmentSummary {
  id: string
  start_at: string
  status: AppointmentStatus
}

export interface TreatmentPlanItem {
  id: string
  treatment_plan_id: string
  dental_condition_id: string
  /** Snapshot of the catalog procedure's name/description at add-time — frozen once the parent
   *  plan leaves `draft` (design doc §6/§8, Decision 2). Never read from `dental_condition` directly. */
  procedure_name: string
  procedure_description: string | null
  diagnosis_entry_id: string | null
  tooth_number: string | null
  surfaces: ToothSurface[] | null
  quantity: number
  /** Decimal cast — serialized as a string by the backend (e.g. `"150.00"`), not a number. */
  unit_cost: string
  /** `unit_cost * quantity`, computed server-side — never stored (design doc §6). */
  estimated_cost: string
  phase: number | null
  sequence: number | null
  status: TreatmentPlanItemStatus
  appointment_id: string | null
  notes: string | null
  completed_at: string | null
  cancelled_at: string | null
  created_at: string
  updated_at: string
  dental_condition?: TreatmentPlanConditionSummary
  diagnosis_entry?: TreatmentPlanDiagnosisEntrySummary | null
  appointment?: TreatmentPlanAppointmentSummary | null
  created_by?: TreatmentPlanUserSummary
  updated_by?: TreatmentPlanUserSummary | null
}

export interface TreatmentPlanSupersededBySummary {
  id: string
  title: string | null
  status: TreatmentPlanStatus
}

/** The embedded `patient` shape — only present on the clinic-wide list response (`listAll`), never
 *  on patient-scoped endpoints which already have the patient in route context. */
export interface TreatmentPlanPatientSummary {
  id: string
  first_name: string
  last_name: string
}

export interface TreatmentPlan {
  id: string
  patient_id: string
  dentist_id: string
  created_by_id: string
  title: string | null
  status: TreatmentPlanStatus
  notes: string | null
  presented_at: string | null
  accepted_at: string | null
  rejected_at: string | null
  started_at: string | null
  completed_at: string | null
  cancelled_at: string | null
  superseded_by_plan_id: string | null
  created_at: string
  updated_at: string
  /** Plan-level cost roll-up over non-cancelled items — computed server-side, never stored
   *  (design doc §6/§13). Present whenever `items` is loaded, which every API response is
   *  (index/show/every mutation response — design doc §9). */
  estimated_cost?: string
  dentist?: TreatmentPlanUserSummary
  created_by?: TreatmentPlanUserSummary
  superseded_by_plan?: TreatmentPlanSupersededBySummary | null
  items?: TreatmentPlanItem[]
  patient?: TreatmentPlanPatientSummary
}

export interface CreateTreatmentPlanPayload {
  dentist_id: string
  title?: string | null
  notes?: string | null
}

export type UpdateTreatmentPlanPayload = Partial<CreateTreatmentPlanPayload>

/** All fields optional — anything omitted falls back to the original plan's own value (§15 Q4). */
export interface CreateTreatmentPlanRevisionPayload {
  dentist_id?: string
  title?: string | null
  notes?: string | null
}

export interface CreateTreatmentPlanItemPayload {
  dental_condition_id: string
  diagnosis_entry_id?: string | null
  tooth_number?: string | null
  surfaces?: ToothSurface[]
  quantity?: number
  /** Sent as a number even though the response echoes it back as a decimal string (see
   *  `TreatmentPlanItem.unit_cost`) — nullable at the HTTP layer so it can default from the
   *  catalog's `default_cost` (design doc §6/§8). */
  unit_cost?: number | null
  phase?: number | null
  sequence?: number | null
  appointment_id?: string | null
  notes?: string | null
}

/**
 * Same shape as Create, all `sometimes` — matches `UpdateTreatmentPlanItemRequest`. Whether a
 * given field is actually still editable (offer-field freeze once the parent plan leaves `draft`,
 * §6/§8) is enforced server-side, not here.
 */
export type UpdateTreatmentPlanItemPayload = Partial<CreateTreatmentPlanItemPayload>

export type TreatmentPlanErrorCode =
  | 'invalid_treatment_plan_status_transition'
  | 'invalid_treatment_plan_item_status_transition'
  | 'treatment_plan_item_locked'
  | 'treatment_plan_has_open_items'

/** The `{message, code}` shape every Treatment Plan domain exception renders as (design doc §9/§12). */
export interface TreatmentPlanError {
  message: string
  code: TreatmentPlanErrorCode
}
