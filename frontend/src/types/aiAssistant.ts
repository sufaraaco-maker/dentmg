import type { ToothSurface } from '@/types/dentalChart'

export type AiInteractionFeature =
  'dashboard_insight' | 'smart_search' | 'report_narrative' | 'clinical_note_draft' | 'treatment_suggestion'

export interface AiQaResponse {
  answer: string
  interaction_id: string
}

export interface AiReportNarrativeResponse {
  narrative: string
  interaction_id: string
}

export interface AiClinicalNoteDraft {
  subjective: string
  objective: string
  assessment: string
  plan: string
  interaction_id: string
}

export interface AiTreatmentSuggestionCandidate {
  dental_condition_id: string
  tooth_number: string | null
  surfaces: ToothSurface[] | null
  rationale: string
}

export interface AiTreatmentSuggestionsResponse {
  candidates: AiTreatmentSuggestionCandidate[]
  interaction_id: string
}

export interface AiInteractionLog {
  id: string
  user_id: string
  feature: AiInteractionFeature
  patient_id: string | null
  prompt_summary: string
  response_summary: string
  accepted: boolean | null
  model: string
  created_at: string
}

/** Error shape every AiAssistant* exception's render() method returns — see backend design §5/§6. */
export interface AiAssistantErrorCode {
  message: string
  code:
    | 'ai_assistant_disabled'
    | 'ai_assistant_phi_not_acknowledged'
    | 'ai_assistant_unavailable'
    | 'clinical_note_locked'
    | 'treatment_plan_item_locked'
}
