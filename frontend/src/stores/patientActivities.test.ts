import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { patientActivitiesApi } from '@/services/patientActivities'
import { usePatientActivitiesStore } from './patientActivities'
import type { PatientActivity } from '@/types/patientActivity'

vi.mock('@/services/patientActivities', () => ({
  patientActivitiesApi: { list: vi.fn() },
}))

const mockedList = vi.mocked(patientActivitiesApi.list)

function makeActivity(overrides: Partial<PatientActivity> = {}): PatientActivity {
  return {
    id: overrides.id ?? 'activity-1',
    patient_id: 'patient-1',
    event_type: 'invoice.issued',
    category: 'billing',
    subject_type: 'Invoice',
    subject_id: 'invoice-1',
    actor_id: 'user-1',
    summary: 'Invoice #INV-1 issued',
    metadata: null,
    occurred_at: '2026-08-09T09:00:00+00:00',
    actor: { id: 'user-1', name: 'Dr. Smith' },
    ...overrides,
  }
}

function makePage(
  activities: PatientActivity[],
  overrides: Partial<{ current_page: number; last_page: number; per_page: number; total: number }> = {},
) {
  return {
    data: activities,
    meta: {
      current_page: overrides.current_page ?? 1,
      last_page: overrides.last_page ?? 1,
      per_page: overrides.per_page ?? 15,
      total: overrides.total ?? activities.length,
    },
  }
}

beforeEach(() => {
  setActivePinia(createPinia())
  vi.clearAllMocks()
})

describe('usePatientActivitiesStore.fetchNextPage', () => {
  it('fetches page 1 and caches it for that query', async () => {
    mockedList.mockResolvedValueOnce(makePage([makeActivity()]))
    const store = usePatientActivitiesStore()

    await store.fetchNextPage('patient-1', null, 15)

    expect(store.activitiesForQuery('patient-1', null, 15)).toHaveLength(1)
    expect(mockedList).toHaveBeenCalledWith('patient-1', 1, null, 15)
  })

  it('appends the next page instead of replacing the accumulated list ("Load more")', async () => {
    mockedList.mockResolvedValueOnce(
      makePage([makeActivity({ id: 'a1' })], { current_page: 1, last_page: 2 }),
    )
    const store = usePatientActivitiesStore()
    await store.fetchNextPage('patient-1', null, 15)

    mockedList.mockResolvedValueOnce(
      makePage([makeActivity({ id: 'a2' })], { current_page: 2, last_page: 2 }),
    )
    await store.fetchNextPage('patient-1', null, 15)

    expect(mockedList).toHaveBeenLastCalledWith('patient-1', 2, null, 15)
    expect(store.activitiesForQuery('patient-1', null, 15).map((a) => a.id)).toEqual(['a1', 'a2'])
  })

  it('is a no-op once the last page has already been fetched', async () => {
    mockedList.mockResolvedValueOnce(makePage([makeActivity()], { current_page: 1, last_page: 1 }))
    const store = usePatientActivitiesStore()
    await store.fetchNextPage('patient-1', null, 15)

    await store.fetchNextPage('patient-1', null, 15)

    expect(mockedList).toHaveBeenCalledTimes(1)
  })

  it('keeps different categories for the same patient in separate queries', async () => {
    mockedList.mockResolvedValueOnce(makePage([makeActivity({ id: 'billing-1', category: 'billing' })]))
    mockedList.mockResolvedValueOnce(makePage([makeActivity({ id: 'lab-1', category: 'laboratory' })]))
    const store = usePatientActivitiesStore()

    await store.fetchNextPage('patient-1', 'billing', 15)
    await store.fetchNextPage('patient-1', 'laboratory', 15)

    expect(store.activitiesForQuery('patient-1', 'billing', 15).map((a) => a.id)).toEqual(['billing-1'])
    expect(store.activitiesForQuery('patient-1', 'laboratory', 15).map((a) => a.id)).toEqual(['lab-1'])
  })

  it('keeps different page sizes for the same patient/category in separate queries', async () => {
    mockedList.mockResolvedValueOnce(makePage([makeActivity({ id: 'a1' }), makeActivity({ id: 'a2' })]))
    mockedList.mockResolvedValueOnce(makePage([makeActivity({ id: 'a1' })]))
    const store = usePatientActivitiesStore()

    await store.fetchNextPage('patient-1', null, 15)
    await store.fetchNextPage('patient-1', null, 5)

    expect(store.activitiesForQuery('patient-1', null, 15)).toHaveLength(2)
    expect(store.activitiesForQuery('patient-1', null, 5)).toHaveLength(1)
  })

  it('sets a translation-key error and clears loading on failure, instead of rejecting', async () => {
    mockedList.mockRejectedValueOnce(new Error('network error'))
    const store = usePatientActivitiesStore()

    await store.fetchNextPage('patient-1', null, 15)

    expect(store.errorForQuery('patient-1', null, 15)).toBe('patients.timelinePanel.loadError')
    expect(store.isLoadingQuery('patient-1', null, 15)).toBe(false)
  })
})

describe('usePatientActivitiesStore.$reset', () => {
  it('clears every query’s cached pages', async () => {
    mockedList.mockResolvedValueOnce(makePage([makeActivity()]))
    const store = usePatientActivitiesStore()
    await store.fetchNextPage('patient-1', null, 15)

    store.$reset()

    expect(store.activitiesForQuery('patient-1', null, 15)).toHaveLength(0)
  })
})
