export interface Lab {
  id: string
  name: string
  contact_name: string | null
  phone: string | null
  email: string | null
  address: string | null
  default_turnaround_days: number | null
  notes: string | null
  is_active: boolean
  created_at: string
  updated_at: string
}

export interface CreateLabPayload {
  name: string
  contact_name?: string | null
  phone?: string | null
  email?: string | null
  address?: string | null
  default_turnaround_days?: number | null
  notes?: string | null
  is_active?: boolean
}

export type UpdateLabPayload = Partial<CreateLabPayload>

export type LabCaseStatus = 'draft' | 'sent' | 'received' | 'quality_checked' | 'cancelled'

export interface LabCaseSummaryRef {
  id: string
  name: string
}

export interface LabCase {
  id: string
  sequence_number: number
  case_number: string
  patient_id: string
  lab_id: string
  dentist_id: string | null
  treatment_plan_item_id: string | null
  appointment_id: string | null
  tooth_numbers: string[] | null
  case_type: string | null
  shade: string | null
  material: string | null
  instructions: string | null
  fee: number | null
  tracking_number: string | null
  status: LabCaseStatus
  sent_at: string | null
  due_at: string | null
  received_at: string | null
  quality_checked_at: string | null
  cancelled_at: string | null
  created_at: string
  updated_at: string
  patient?: { id: string; patient_code: string; full_name: string }
  lab?: LabCaseSummaryRef
  dentist?: LabCaseSummaryRef | null
}

export interface CreateLabCasePayload {
  patient_id: string
  lab_id: string
  dentist_id?: string | null
  treatment_plan_item_id?: string | null
  appointment_id?: string | null
  tooth_numbers?: string[] | null
  case_type?: string | null
  shade?: string | null
  material?: string | null
  instructions?: string | null
  fee?: number | null
  tracking_number?: string | null
}

export type UpdateLabCasePayload = Omit<CreateLabCasePayload, 'patient_id'>

export type LabCaseErrorCode = 'invalid_lab_case_operation'

/** The `{message, code}` shape every Laboratory domain exception renders as (backend design doc §4). */
export interface LabCaseError {
  message: string
  code: LabCaseErrorCode
}
