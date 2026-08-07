export type PatientGender = 'male' | 'female'

export const PATIENT_GENDERS: PatientGender[] = ['male', 'female']

export type BloodType = 'A+' | 'A-' | 'B+' | 'B-' | 'AB+' | 'AB-' | 'O+' | 'O-'

export const BLOOD_TYPES: BloodType[] = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-']

export interface Patient {
  id: string
  patient_code: string
  first_name: string
  last_name: string
  full_name: string
  date_of_birth: string
  gender: PatientGender
  phone: string
  email: string | null
  address: string | null
  national_id: string | null
  emergency_contact_name: string | null
  emergency_contact_phone: string | null
  blood_type: BloodType | null
  allergies: string | null
  medical_history: string | null
  insurance_provider: string | null
  insurance_number: string | null
  notes: string | null
  created_at: string
  updated_at: string
}

export interface PatientAuditLog {
  id: string
  action: 'created' | 'updated' | 'deleted'
  changes: Record<string, unknown> | null
  user: { id: string; name: string } | null
  created_at: string
}

export interface PatientPayload {
  first_name: string
  last_name: string
  date_of_birth: string | null
  gender: PatientGender
  phone: string
  email: string | null
  address: string | null
  national_id: string | null
  emergency_contact_name: string | null
  emergency_contact_phone: string | null
  blood_type: BloodType | null
  allergies: string | null
  medical_history: string | null
  insurance_provider: string | null
  insurance_number: string | null
  notes: string | null
}

export type CreatePatientPayload = PatientPayload
export type UpdatePatientPayload = PatientPayload
