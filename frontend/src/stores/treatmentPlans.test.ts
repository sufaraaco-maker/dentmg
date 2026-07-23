import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { treatmentPlanItemsApi, treatmentPlansApi } from '@/services/treatmentPlans'
import { useTreatmentPlansStore } from './treatmentPlans'
import type { TreatmentPlan } from '@/types/treatmentPlan'

vi.mock('@/services/treatmentPlans', () => ({
  treatmentPlansApi: {
    list: vi.fn(),
    create: vi.fn(),
    get: vi.fn(),
    update: vi.fn(),
    present: vi.fn(),
    accept: vi.fn(),
    reject: vi.fn(),
    start: vi.fn(),
    complete: vi.fn(),
    cancel: vi.fn(),
    createRevision: vi.fn(),
    remove: vi.fn(),
  },
  treatmentPlanItemsApi: {
    create: vi.fn(),
    update: vi.fn(),
    complete: vi.fn(),
    cancel: vi.fn(),
    remove: vi.fn(),
  },
}))

const mockedPlansApi = vi.mocked(treatmentPlansApi)
const mockedItemsApi = vi.mocked(treatmentPlanItemsApi)

function makePlan(overrides: Partial<TreatmentPlan> = {}): TreatmentPlan {
  return {
    id: overrides.id ?? 'plan-1',
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
  setActivePinia(createPinia())
  vi.clearAllMocks()
})

describe('useTreatmentPlansStore.fetchForPatient', () => {
  it('fetches and caches a patient’s plans', async () => {
    mockedPlansApi.list.mockResolvedValueOnce([makePlan()])
    const store = useTreatmentPlansStore()

    await store.fetchForPatient('patient-1')

    expect(store.plansForPatient('patient-1')).toHaveLength(1)
    expect(mockedPlansApi.list).toHaveBeenCalledTimes(1)
  })

  it('skips the network call on a second fetch for the same patient', async () => {
    mockedPlansApi.list.mockResolvedValue([makePlan()])
    const store = useTreatmentPlansStore()

    await store.fetchForPatient('patient-1')
    await store.fetchForPatient('patient-1')

    expect(mockedPlansApi.list).toHaveBeenCalledTimes(1)
  })

  it('refetches when force is true', async () => {
    mockedPlansApi.list.mockResolvedValue([makePlan()])
    const store = useTreatmentPlansStore()

    await store.fetchForPatient('patient-1')
    await store.fetchForPatient('patient-1', true)

    expect(mockedPlansApi.list).toHaveBeenCalledTimes(2)
  })

  it('sets a translation-key error on failure', async () => {
    mockedPlansApi.list.mockRejectedValueOnce(new Error('network error'))
    const store = useTreatmentPlansStore()

    await store.fetchForPatient('patient-1')

    expect(store.error).toBe('treatmentPlans.loadError')
    expect(store.loading).toBe(false)
  })

  it('plansForPatient only returns that patient’s plans, most recent first', async () => {
    mockedPlansApi.list.mockImplementation(async (patientId: string) =>
      patientId === 'patient-1'
        ? [
            makePlan({ id: 'plan-1', created_at: '2026-07-20T09:00:00+00:00' }),
            makePlan({ id: 'plan-2', created_at: '2026-07-22T09:00:00+00:00' }),
          ]
        : [makePlan({ id: 'plan-3', patient_id: 'patient-2' })],
    )
    const store = useTreatmentPlansStore()

    await store.fetchForPatient('patient-1')
    await store.fetchForPatient('patient-2')

    const plans = store.plansForPatient('patient-1')
    expect(plans.map((p) => p.id)).toEqual(['plan-2', 'plan-1'])
  })
})

describe('useTreatmentPlansStore.fetchOne', () => {
  it('fetches and caches a single plan directly, independent of any patient list fetch', async () => {
    mockedPlansApi.get.mockResolvedValueOnce(makePlan({ id: 'plan-1' }))
    const store = useTreatmentPlansStore()

    const plan = await store.fetchOne('plan-1')

    expect(plan.id).toBe('plan-1')
    expect(store.cache.get('plan-1')).toEqual(plan)
    expect(mockedPlansApi.list).not.toHaveBeenCalled()
  })
})

describe('useTreatmentPlansStore plan mutations', () => {
  it('create upserts the created plan with no extra fetch', async () => {
    mockedPlansApi.create.mockResolvedValueOnce(makePlan())
    const store = useTreatmentPlansStore()

    await store.create('patient-1', { dentist_id: 'dentist-1', title: 'Option A' })

    expect(store.cache.get('plan-1')?.title).toBe('Option A')
    expect(mockedPlansApi.list).not.toHaveBeenCalled()
  })

  it('update upserts the response directly', async () => {
    mockedPlansApi.update.mockResolvedValueOnce(makePlan({ title: 'New title' }))
    const store = useTreatmentPlansStore()

    await store.update('plan-1', { title: 'New title' })

    expect(store.cache.get('plan-1')?.title).toBe('New title')
  })

  it('present upserts the response directly with no extra fetch', async () => {
    mockedPlansApi.present.mockResolvedValueOnce(makePlan({ status: 'presented' }))
    const store = useTreatmentPlansStore()

    await store.present('plan-1')

    expect(store.cache.get('plan-1')?.status).toBe('presented')
    expect(mockedPlansApi.list).not.toHaveBeenCalled()
  })

  it('accept upserts the response and refreshes the patient list to correct auto-rejected siblings', async () => {
    mockedPlansApi.accept.mockResolvedValueOnce(makePlan({ status: 'accepted' }))
    mockedPlansApi.list.mockResolvedValueOnce([
      makePlan({ status: 'accepted' }),
      makePlan({ id: 'plan-2', status: 'rejected' }),
    ])
    const store = useTreatmentPlansStore()

    await store.accept('plan-1')

    expect(store.cache.get('plan-1')?.status).toBe('accepted')
    expect(store.cache.get('plan-2')?.status).toBe('rejected')
    expect(mockedPlansApi.list).toHaveBeenCalledWith('patient-1')
  })

  it('cancel upserts the response (its own items already reflect the server-side cascade)', async () => {
    mockedPlansApi.cancel.mockResolvedValueOnce(
      makePlan({ status: 'cancelled', items: [{ status: 'cancelled' } as never] }),
    )
    const store = useTreatmentPlansStore()

    const plan = await store.cancel('plan-1')

    expect(plan.status).toBe('cancelled')
    expect(mockedPlansApi.list).not.toHaveBeenCalled()
  })

  it('createRevision upserts the new plan and refreshes the original to pick up superseded_by_plan_id', async () => {
    mockedPlansApi.createRevision.mockResolvedValueOnce(makePlan({ id: 'plan-2', status: 'draft' }))
    mockedPlansApi.get.mockResolvedValueOnce(makePlan({ id: 'plan-1', status: 'rejected', superseded_by_plan_id: 'plan-2' }))
    const store = useTreatmentPlansStore()

    const revision = await store.createRevision('plan-1', { title: 'Revised' })

    expect(revision.id).toBe('plan-2')
    expect(store.cache.get('plan-1')?.superseded_by_plan_id).toBe('plan-2')
    expect(mockedPlansApi.get).toHaveBeenCalledWith('plan-1')
  })

  it('remove deletes the plan from the cache', async () => {
    mockedPlansApi.get.mockResolvedValueOnce(makePlan())
    mockedPlansApi.remove.mockResolvedValueOnce(undefined)
    const store = useTreatmentPlansStore()

    await store.fetchOne('plan-1')
    await store.remove('plan-1')

    expect(store.cache.has('plan-1')).toBe(false)
  })
})

describe('useTreatmentPlansStore item mutations', () => {
  it('addItem upserts the full returned parent plan', async () => {
    mockedItemsApi.create.mockResolvedValueOnce(makePlan({ items: [{ id: 'item-1' } as never] }))
    const store = useTreatmentPlansStore()

    const plan = await store.addItem('plan-1', { dental_condition_id: 'condition-1' })

    expect(plan.items).toHaveLength(1)
    expect(store.cache.get('plan-1')?.items).toHaveLength(1)
  })

  it('updateItem/completeItem/cancelItem all upsert the returned parent plan', async () => {
    mockedItemsApi.update.mockResolvedValueOnce(makePlan({ id: 'plan-1' }))
    mockedItemsApi.complete.mockResolvedValueOnce(makePlan({ id: 'plan-1' }))
    mockedItemsApi.cancel.mockResolvedValueOnce(makePlan({ id: 'plan-1' }))
    const store = useTreatmentPlansStore()

    await store.updateItem('item-1', { notes: 'x' })
    await store.completeItem('item-1')
    await store.cancelItem('item-1')

    expect(mockedItemsApi.update).toHaveBeenCalledWith('item-1', { notes: 'x' })
    expect(mockedItemsApi.complete).toHaveBeenCalledWith('item-1')
    expect(mockedItemsApi.cancel).toHaveBeenCalledWith('item-1')
  })

  it('removeItem deletes the item then re-fetches the parent plan (destroy returns no payload)', async () => {
    mockedItemsApi.remove.mockResolvedValueOnce(undefined)
    mockedPlansApi.get.mockResolvedValueOnce(makePlan({ items: [] }))
    const store = useTreatmentPlansStore()

    await store.removeItem('item-1', 'plan-1')

    expect(mockedItemsApi.remove).toHaveBeenCalledWith('item-1')
    expect(mockedPlansApi.get).toHaveBeenCalledWith('plan-1')
    expect(store.cache.get('plan-1')?.items).toEqual([])
  })
})

describe('useTreatmentPlansStore.$reset', () => {
  it('clears the cache and loaded-patient tracking', async () => {
    mockedPlansApi.list.mockResolvedValueOnce([makePlan()])
    const store = useTreatmentPlansStore()
    await store.fetchForPatient('patient-1')

    store.$reset()

    expect(store.cache.size).toBe(0)
    expect(store.plansForPatient('patient-1')).toHaveLength(0)
  })
})
