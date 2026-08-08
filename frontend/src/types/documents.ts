export type DocumentCategory =
  'consent_form' | 'insurance' | 'referral' | 'clinical_summary' | 'correspondence' | 'other'

export interface PatientDocument {
  id: string
  patient_id: string
  uploaded_by: string | null
  category: DocumentCategory
  title: string
  original_filename: string
  mime_type: string
  file_size: number
  notes: string | null
  file_url: string
  created_at: string
  updated_at: string
  uploaded_by_user?: { id: string; name: string } | null
}

export interface UploadDocumentPayload {
  file: File
  category: DocumentCategory
  title: string
  notes?: string | null
}

export interface UpdateDocumentPayload {
  category?: DocumentCategory
  title?: string
  notes?: string | null
}

export interface PatientDocumentFilters {
  category?: DocumentCategory | null
}

export interface PaginatedResponse<T> {
  data: T[]
  meta: { current_page: number; last_page: number; total: number; per_page: number }
}
