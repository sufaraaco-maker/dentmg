import { beforeEach, describe, expect, it, vi } from 'vitest'
import { api } from '@/lib/api'
import {
  aiAssistantErrorCode,
  askDashboardInsight,
  askSmartSearch,
  draftClinicalNote,
  getReportNarrative,
  recordAiDecision,
  suggestTreatmentItems,
} from './aiAssistantApi'

vi.mock('@/lib/api', () => ({
  api: { post: vi.fn() },
}))

const mockedApi = vi.mocked(api)

beforeEach(() => {
  vi.clearAllMocks()
})

describe('askDashboardInsight', () => {
  it('posts the question and returns the answer', async () => {
    mockedApi.post.mockResolvedValue({ data: { answer: 'Up 12%.', interaction_id: 'log-1' } })

    const result = await askDashboardInsight('How did collections trend?')

    expect(mockedApi.post).toHaveBeenCalledWith('/ai-assistant/dashboard-insight', {
      question: 'How did collections trend?',
    })
    expect(result.answer).toBe('Up 12%.')
  })
})

describe('askSmartSearch', () => {
  it('posts the query', async () => {
    mockedApi.post.mockResolvedValue({ data: { answer: 'Found 3 patients.', interaction_id: 'log-2' } })

    await askSmartSearch('overdue patients')

    expect(mockedApi.post).toHaveBeenCalledWith('/ai-assistant/smart-search', { query: 'overdue patients' })
  })
})

describe('getReportNarrative', () => {
  it('sends the report type merged with non-empty params', async () => {
    mockedApi.post.mockResolvedValue({ data: { narrative: 'Production was steady.', interaction_id: 'log-3' } })

    await getReportNarrative('production', { date_from: '2026-01-01', date_to: '2026-01-31', dentist_id: null })

    expect(mockedApi.post).toHaveBeenCalledWith('/ai-assistant/report-narrative', {
      report_type: 'production',
      date_from: '2026-01-01',
      date_to: '2026-01-31',
    })
  })
})

describe('draftClinicalNote', () => {
  it('posts the shorthand to the note-scoped endpoint', async () => {
    mockedApi.post.mockResolvedValue({
      data: { subjective: 'S', objective: 'O', assessment: 'A', plan: 'P', interaction_id: 'log-4' },
    })

    await draftClinicalNote('note-1', 'pt c/o pain')

    expect(mockedApi.post).toHaveBeenCalledWith('/clinical-notes/note-1/ai-draft', { shorthand: 'pt c/o pain' })
  })
})

describe('suggestTreatmentItems', () => {
  it('posts to the plan-scoped endpoint with no body', async () => {
    mockedApi.post.mockResolvedValue({ data: { candidates: [], interaction_id: 'log-5' } })

    await suggestTreatmentItems('plan-1')

    expect(mockedApi.post).toHaveBeenCalledWith('/treatment-plans/plan-1/ai-suggestions')
  })
})

describe('recordAiDecision', () => {
  it('posts the accept decision and optional edited summary', async () => {
    mockedApi.post.mockResolvedValue({ data: { id: 'log-1', accepted: true } })

    await recordAiDecision('log-1', true, 'Edited before saving')

    expect(mockedApi.post).toHaveBeenCalledWith('/ai-assistant/interactions/log-1/decision', {
      accepted: true,
      response_summary: 'Edited before saving',
    })
  })
})

describe('aiAssistantErrorCode', () => {
  it('extracts the code from an axios-shaped error', () => {
    const err = { response: { status: 422, data: { code: 'ai_assistant_disabled' } } }

    expect(aiAssistantErrorCode(err)).toBe('ai_assistant_disabled')
  })

  it('returns null when no code is present', () => {
    expect(aiAssistantErrorCode(new Error('boom'))).toBeNull()
    expect(aiAssistantErrorCode(undefined)).toBeNull()
  })
})
