import { beforeEach, describe, expect, it, vi } from 'vitest'
import { api } from '@/lib/api'
import { treatmentPlanItemsApi } from './treatmentPlanItemsApi'
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
    estimated_cost: '150.00',
    items: [],
    ...overrides,
  }
}

beforeEach(() => {
  vi.clearAllMocks()
})

describe('treatmentPlanItemsApi.create', () => {
  it('posts to the plan-nested route and returns the full parent plan', async () => {
    const plan = makePlan()
    mockedApi.post.mockResolvedValueOnce({ data: plan })

    const result = await treatmentPlanItemsApi.create('plan-1', {
      dental_condition_id: 'condition-1',
      tooth_number: '16',
    })

    expect(result).toEqual(plan)
    expect(mockedApi.post).toHaveBeenCalledWith('/treatment-plans/plan-1/items', {
      dental_condition_id: 'condition-1',
      tooth_number: '16',
    })
  })

  it('rethrows a typed error when the plan is no longer draft', async () => {
    mockedApi.post.mockRejectedValueOnce({
      response: { data: { message: 'locked', code: 'treatment_plan_item_locked' } },
    })

    await expect(
      treatmentPlanItemsApi.create('plan-1', { dental_condition_id: 'condition-1' }),
    ).rejects.toEqual(expect.objectContaining({ code: 'treatment_plan_item_locked' }))
  })
})

describe('treatmentPlanItemsApi.update', () => {
  it('puts to the item-level route (not nested under the plan) and returns the parent plan', async () => {
    const plan = makePlan()
    mockedApi.put.mockResolvedValueOnce({ data: plan })

    const result = await treatmentPlanItemsApi.update('item-1', { notes: 'Scheduled for next Tuesday' })

    expect(result).toEqual(plan)
    expect(mockedApi.put).toHaveBeenCalledWith('/treatment-plan-items/item-1', {
      notes: 'Scheduled for next Tuesday',
    })
  })
})

describe('treatmentPlanItemsApi.complete / cancel', () => {
  it('complete() posts to the item /complete transition endpoint', async () => {
    const plan = makePlan()
    mockedApi.post.mockResolvedValueOnce({ data: plan })

    const result = await treatmentPlanItemsApi.complete('item-1')

    expect(result).toEqual(plan)
    expect(mockedApi.post).toHaveBeenCalledWith('/treatment-plan-items/item-1/complete')
  })

  it('cancel() posts to the item /cancel transition endpoint', async () => {
    const plan = makePlan()
    mockedApi.post.mockResolvedValueOnce({ data: plan })

    const result = await treatmentPlanItemsApi.cancel('item-1')

    expect(result).toEqual(plan)
    expect(mockedApi.post).toHaveBeenCalledWith('/treatment-plan-items/item-1/cancel')
  })

  it('rethrows a typed error when an item transition is invalid', async () => {
    mockedApi.post.mockRejectedValueOnce({
      response: { data: { message: 'invalid', code: 'invalid_treatment_plan_item_status_transition' } },
    })

    await expect(treatmentPlanItemsApi.complete('item-1')).rejects.toEqual(
      expect.objectContaining({ code: 'invalid_treatment_plan_item_status_transition' }),
    )
  })
})

describe('treatmentPlanItemsApi.remove', () => {
  it('deletes the correct item id', async () => {
    mockedApi.delete.mockResolvedValueOnce({ data: undefined })

    await treatmentPlanItemsApi.remove('item-1')

    expect(mockedApi.delete).toHaveBeenCalledWith('/treatment-plan-items/item-1')
  })
})
