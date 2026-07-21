export type DentalConditionCategory = 'finding' | 'procedure'

export const DENTAL_CONDITION_CATEGORIES: DentalConditionCategory[] = ['finding', 'procedure']

export type DentalChartEntryStatus = 'existing' | 'active' | 'planned' | 'completed' | 'cancelled'

export const DENTAL_CHART_ENTRY_STATUSES: DentalChartEntryStatus[] = [
  'existing',
  'active',
  'planned',
  'completed',
  'cancelled',
]

/** A brand-new entry is never created pre-cancelled — `cancelled` is only reachable via `/cancel`. */
export type CreatableDentalChartEntryStatus = Exclude<DentalChartEntryStatus, 'cancelled'>

/** The fixed FDI tooth-surface code set (backend `Rule::in(['M','D','F','L','O','I'])`). */
export type ToothSurface = 'M' | 'D' | 'F' | 'L' | 'O' | 'I'

export const TOOTH_SURFACES: ToothSurface[] = ['M', 'D', 'F', 'L', 'O', 'I']

export interface DentalCondition {
  id: string
  name: string
  category: DentalConditionCategory
  applies_to_surface: boolean
  default_color: string
  icon_key: string | null
  is_active: boolean
  sort_order: number
  created_at: string
  updated_at: string
}

export interface CreateDentalConditionPayload {
  name: string
  category: DentalConditionCategory
  applies_to_surface: boolean
  default_color: string
  icon_key?: string | null
  is_active?: boolean
  sort_order?: number | null
}

export type UpdateDentalConditionPayload = Partial<CreateDentalConditionPayload>

/** The embedded `dental_condition` shape on a `DentalChartEntry` — not the full catalog record. */
export interface DentalChartEntryConditionSummary {
  id: string
  name: string
  category: DentalConditionCategory
  default_color: string
  icon_key: string | null
}

export interface DentalChartEntryUserSummary {
  id: string
  name: string
}

export interface DentalChartEntry {
  id: string
  patient_id: string
  tooth_number: string
  /** Derived by the backend from `tooth_number`, never stored — see `App\Support\ToothChart`. */
  dentition_type: 'permanent' | 'primary'
  surfaces: ToothSurface[] | null
  status: DentalChartEntryStatus
  notes: string | null
  recorded_at: string
  completed_at: string | null
  cancelled_at: string | null
  created_at: string
  updated_at: string
  /** Present on `index` responses; omitted (not `null`) on write-action responses — see TECH_DEBT.md. */
  dental_condition?: DentalChartEntryConditionSummary
  dentist?: DentalChartEntryUserSummary
  created_by?: DentalChartEntryUserSummary
  updated_by?: DentalChartEntryUserSummary | null
}

export interface CreateDentalChartEntryPayload {
  dental_condition_id: string
  dentist_id: string
  tooth_number: string
  surfaces?: ToothSurface[]
  status: CreatableDentalChartEntryStatus
  notes?: string | null
  recorded_at?: string | null
}

export interface UpdateDentalChartEntryPayload {
  dental_condition_id?: string
  dentist_id?: string
  tooth_number?: string
  surfaces?: ToothSurface[]
  notes?: string | null
  recorded_at?: string
}

export interface IndexDentalChartEntryParams {
  status?: DentalChartEntryStatus
  tooth_number?: string
  category?: DentalConditionCategory
}

export type DentalChartEntryErrorCode = 'invalid_status_transition' | 'dental_chart_entry_locked'

/** The `{message, code}` shape `InvalidStatusTransitionException`/`EntryLockedException` render as. */
export interface DentalChartEntryError {
  message: string
  code: DentalChartEntryErrorCode
}
