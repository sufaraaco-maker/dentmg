import { beforeEach, describe, expect, it, vi } from 'vitest'
import { api } from '@/lib/api'
import { treatmentPlansApi } from './treatmentPlansApi'
import type { TreatmentPlan } from '@/types/treatmentPlan'

vi.mock('@/lib/api', () => ({
  api: { get: vi.fn(), post: vi.fn(), put: vi.fn(), delete: vi.fn() },
}))

const mockedApi = vi.mocked(api)

function makePlan(overrides: Partial<TreatmentPlan> = {}): TreatmentPlan {
  return {
    id: 'plan-1',
    patient_id: 'patient-1',
    dentist_id: 'dentist-1',
    created_by_id: 'admin-1',
    title: 'Option A',
    status: 'draft',
    notes: null,
    presented_at: null,
    accepted_at: null,
    rejected_at: null,
    started_at: null,
    completed_at: null,
    cancelled_at: null,
    superseded_by_plan_id: null,
    created_at: '2026-07-22T09:00:00+00:00',
    updated_at: '2026-07-22T09:00:00+00:00',
    estimated_cost: '0.00',
    items: [],
    ...overrides,
  }
}

beforeEach(() => {
  vi.clearAllMocks()
})

describe('treatmentPlansApi.list', () => {
  it('returns the bare array (deliberately not paginated)', async () => {
    const plans = [makePlan()]
    mockedApi.get.mockResolvedValueOnce({ data: plans })

    const result = await treatmentPlansApi.list('patient-1')

    expect(result).toEqual(plans)
    expect(mockedApi.get).toHaveBeenCalledWith('/patients/patient-1/treatment-plans')
  })
})

describe('treatmentPlansApi.create', () => {
  it('posts to the patient-nested route', async () => {
    const created = makePlan()
    mockedApi.post.mockResolvedValueOnce({ data: created })

    const result = await treatmentPlansApi.create('patient-1', { dentist_id: 'dentist-1', title: 'Option A' })

    expect(result).toEqual(created)
    expect(mockedApi.post).toHaveBeenCalledWith('/patients/patient-1/treatment-plans', {
      dentist_id: 'dentist-1',
      title: 'Option A',
    })
  })

  it('rethrows a typed error for a 422 with a recognized code', async () => {
    mockedApi.post.mockRejectedValueOnce({
      response: { data: { message: 'locked', code: 'treatment_plan_item_locked' } },
    })

    await expect(treatmentPlansApi.create('patient-1', { dentist_id: 'dentist-1' })).rejects.toEqual(
      expect.objectContaining({ code: 'treatment_plan_item_locked' }),
    )
  })
})

describe('treatmentPlansApi.get', () => {
  it('fetches the single-resource route', async () => {
    const plan = makePlan()
    mockedApi.get.mockResolvedValueOnce({ data: plan })

    const result = await treatmentPlansApi.get('plan-1')

    expect(result).toEqual(plan)
    expect(mockedApi.get).toHaveBeenCalledWith('/treatment-plans/plan-1')
  })
})

describe('treatmentPlansApi.update', () => {
  it('puts to the plan-level route', async () => {
    const updated = makePlan({ title: 'New title' })
    mockedApi.put.mockResolvedValueOnce({ data: updated })

    const result = await treatmentPlansApi.update('plan-1', { title: 'New title' })

    expect(result).toEqual(updated)
    expect(mockedApi.put).toHaveBeenCalledWith('/treatment-plans/plan-1', { title: 'New title' })
  })
})

describe('treatmentPlansApi status transitions', () => {
  const cases: [keyof typeof treatmentPlansApi, string][] = [
    ['present', '/treatment-plans/plan-1/present'],
    ['accept', '/treatment-plans/plan-1/accept'],
    ['reject', '/treatment-plans/plan-1/reject'],
    ['start', '/treatment-plans/plan-1/start'],
    ['complete', '/treatment-plans/plan-1/complete'],
    ['cancel', '/treatment-plans/plan-1/cancel'],
  ]

  it.each(cases)('%s() posts to the correct transition endpoint', async (method, url) => {
    const plan = makePlan()
    mockedApi.post.mockResolvedValueOnce({ data: plan })

    const action = treatmentPlansApi[method] as (id: string) => Promise<TreatmentPlan>
    const result = await action('plan-1')

    expect(result).toEqual(plan)
    expect(mockedApi.post).toHaveBeenCalledWith(url)
  })

  it('rethrows a typed error when a transition is invalid', async () => {
    mockedApi.post.mockRejectedValueOnce({
      response: { data: { message: 'invalid', code: 'invalid_treatment_plan_status_transition' } },
    })

    await expect(treatmentPlansApi.present('plan-1')).rejects.toEqual(
      expect.objectContaining({ code: 'invalid_treatment_plan_status_transition' }),
    )
  })
})

describe('treatmentPlansApi.createRevision', () => {
  it('posts to the revisions route and returns the new plan', async () => {
    const revision = makePlan({ id: 'plan-2', title: 'Option A — Revised', status: 'draft' })
    mockedApi.post.mockResolvedValueOnce({ data: revision })

    const result = await treatmentPlansApi.createRevision('plan-1', { title: 'Option A — Revised' })

    expect(result).toEqual(revision)
    expect(mockedApi.post).toHaveBeenCalledWith('/treatment-plans/plan-1/revisions', {
      title: 'Option A — Revised',
    })
  })

  it('defaults to an empty payload', async () => {
    mockedApi.post.mockResolvedValueOnce({ data: makePlan() })

    await treatmentPlansApi.createRevision('plan-1')

    expect(mockedApi.post).toHaveBeenCalledWith('/treatment-plans/plan-1/revisions', {})
  })
})

describe('treatmentPlansApi.remove', () => {
  it('deletes the correct id', async () => {
    mockedApi.delete.mockResolvedValueOnce({ data: undefined })

    await treatmentPlansApi.remove('plan-1')

    expect(mockedApi.delete).toHaveBeenCalledWith('/treatment-plans/plan-1')
  })
})
