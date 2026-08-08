export type AllergySeverity = 'mild' | 'moderate' | 'severe'

export const ALLERGY_SEVERITIES: AllergySeverity[] = ['mild', 'moderate', 'severe']

export type MedicalConditionStatus = 'active' | 'resolved' | 'chronic'

export const MEDICAL_CONDITION_STATUSES: MedicalConditionStatus[] = ['active', 'resolved', 'chronic']

export interface MedicalHistoryUserSummary {
  id: string
  name: string
}

export interface PatientAllergy {
  id: string
  patient_id: string
  allergen: string
  severity: AllergySeverity | null
  reaction: string | null
  notes: string | null
  created_at: string
  updated_at: string
  created_by?: MedicalHistoryUserSummary | null
  updated_by?: MedicalHistoryUserSummary | null
}

export interface CreateAllergyPayload {
  allergen: string
  severity?: AllergySeverity | null
  reaction?: string | null
  notes?: string | null
}

export type UpdateAllergyPayload = Partial<CreateAllergyPayload>

export interface PatientMedicalCondition {
  id: string
  patient_id: string
  condition_name: string
  status: MedicalConditionStatus
  diagnosed_date: string | null
  notes: string | null
  created_at: string
  updated_at: string
  created_by?: MedicalHistoryUserSummary | null
  updated_by?: MedicalHistoryUserSummary | null
}

export interface CreateMedicalConditionPayload {
  condition_name: string
  status?: MedicalConditionStatus
  diagnosed_date?: string | null
  notes?: string | null
}

export type UpdateMedicalConditionPayload = Partial<CreateMedicalConditionPayload>

export interface PatientMedication {
  id: string
  patient_id: string
  medication_name: string
  dosage: string | null
  frequency: string | null
  is_current: boolean
  start_date: string | null
  end_date: string | null
  notes: string | null
  created_at: string
  updated_at: string
  created_by?: MedicalHistoryUserSummary | null
  updated_by?: MedicalHistoryUserSummary | null
}

export interface CreateMedicationPayload {
  medication_name: string
  dosage?: string | null
  frequency?: string | null
  is_current?: boolean
  start_date?: string | null
  end_date?: string | null
  notes?: string | null
}

export type UpdateMedicationPayload = Partial<CreateMedicationPayload>

export interface MedicalHistoryPageMeta {
  current_page: number
  last_page: number
  per_page: number
  total: number
}

export interface PaginatedMedicalHistory<T> {
  data: T[]
  meta: MedicalHistoryPageMeta
}
