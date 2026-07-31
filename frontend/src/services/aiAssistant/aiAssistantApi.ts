import { api } from '@/lib/api'
import type {
  AiClinicalNoteDraft,
  AiInteractionLog,
  AiQaResponse,
  AiReportNarrativeResponse,
  AiTreatmentSuggestionsResponse,
} from '@/types/aiAssistant'

export function askDashboardInsight(question: string) {
  return api.post<AiQaResponse>('/ai-assistant/dashboard-insight', { question }).then((r) => r.data)
}

export function askSmartSearch(query: string) {
  return api.post<AiQaResponse>('/ai-assistant/smart-search', { query }).then((r) => r.data)
}

export function getReportNarrative(reportType: string, params: Record<string, string | null | undefined>) {
  const payload = Object.fromEntries(Object.entries(params).filter(([, v]) => v != null && v !== ''))

  return api
    .post<AiReportNarrativeResponse>('/ai-assistant/report-narrative', {
      report_type: reportType,
      ...payload,
    })
    .then((r) => r.data)
}

export function draftClinicalNote(clinicalNoteId: string, shorthand: string) {
  return api
    .post<AiClinicalNoteDraft>(`/clinical-notes/${clinicalNoteId}/ai-draft`, { shorthand })
    .then((r) => r.data)
}

export function suggestTreatmentItems(treatmentPlanId: string) {
  return api
    .post<AiTreatmentSuggestionsResponse>(`/treatment-plans/${treatmentPlanId}/ai-suggestions`)
    .then((r) => r.data)
}

export function recordAiDecision(interactionId: string, accepted: boolean, responseSummary?: string) {
  return api
    .post<AiInteractionLog>(`/ai-assistant/interactions/${interactionId}/decision`, {
      accepted,
      response_summary: responseSummary,
    })
    .then((r) => r.data)
}

export interface AiAssistantApiError {
  response?: {
    status?: number
    data?: { code?: string; message?: string; errors?: Record<string, string[]> }
  }
}

export function aiAssistantErrorCode(err: unknown): string | null {
  return (err as AiAssistantApiError)?.response?.data?.code ?? null
}
