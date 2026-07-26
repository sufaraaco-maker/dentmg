export type ClinicalNoteStatus = 'draft' | 'signed'

export const CLINICAL_NOTE_STATUSES: ClinicalNoteStatus[] = ['draft', 'signed']

export type ClinicalNoteType = 'progress' | 'consultation' | 'phone' | 'referral' | 'other'

export const CLINICAL_NOTE_TYPES: ClinicalNoteType[] = [
  'progress',
  'consultation',
  'phone',
  'referral',
  'other',
]

export interface ClinicalNoteUserSummary {
  id: string
  name: string
}

export interface ClinicalNoteAddendum {
  id: string
  clinical_note_id: string
  body: string
  created_at: string
  author?: ClinicalNoteUserSummary
}

export interface ClinicalNote {
  id: string
  patient_id: string
  appointment_id: string | null
  dentist_id: string
  note_type: ClinicalNoteType
  chief_complaint: string | null
  subjective: string | null
  objective: string | null
  assessment: string | null
  plan: string | null
  status: ClinicalNoteStatus
  signed_at: string | null
  signed_by_id: string | null
  created_by_id: string
  updated_by_id: string | null
  created_at: string
  updated_at: string
  dentist?: ClinicalNoteUserSummary
  signed_by?: ClinicalNoteUserSummary | null
  created_by?: ClinicalNoteUserSummary
  addendums?: ClinicalNoteAddendum[]
}

export interface CreateClinicalNotePayload {
  dentist_id: string
  appointment_id?: string | null
  note_type: ClinicalNoteType
  chief_complaint?: string | null
  subjective?: string | null
  objective?: string | null
  assessment?: string | null
  plan?: string | null
}

/**
 * Same content fields as Create, all optional — matches `UpdateClinicalNoteRequest`.
 * `dentist_id`/`patient_id`/`status`/`signed_at`/`signed_by_id` are never accepted (design doc §7/§8).
 */
export type UpdateClinicalNotePayload = Partial<Omit<CreateClinicalNotePayload, 'dentist_id'>>

export type ClinicalNoteErrorCode = 'clinical_note_locked' | 'invalid_clinical_note_operation'

/** The `{message, code}` shape every Clinical Note domain exception renders as (design doc §9/§12). */
export interface ClinicalNoteError {
  message: string
  code: ClinicalNoteErrorCode
}
