/** Mirrors `PatientActivityPolicy::CATEGORY_SUBJECT_MAP` (backend) exactly — keep both in sync. */
export type PatientActivityCategory =
  | 'appointments'
  | 'treatment_plans'
  | 'clinical_notes'
  | 'billing'
  | 'medical_history'
  | 'laboratory'
  | 'imaging'
  | 'documents'

/**
 * `PatientActivityResource`'s shape (Phase 2.6a) — never a nested `subject`; `summary` is
 * precomputed server-side at dispatch time, so the frontend never needs to know each
 * `subject_type`'s own fields to render a Timeline row.
 */
export interface PatientActivity {
  id: string
  patient_id: string
  event_type: string
  category: PatientActivityCategory
  subject_type: string
  subject_id: string
  actor_id: string | null
  summary: string
  metadata: Record<string, unknown> | null
  occurred_at: string
  actor: { id: string; name: string } | null
}

export interface PaginatedPatientActivities {
  data: PatientActivity[]
  meta: { current_page: number; last_page: number; per_page: number; total: number }
}
