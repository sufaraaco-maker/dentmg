import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { dentalChartEntriesApi } from '@/services/dentalChart'
import { useDentalChartEntriesStore } from './dentalChartEntries'
import type { DentalChartEntry } from '@/types/dentalChart'

vi.mock('@/services/dentalChart', () => ({
  dentalChartEntriesApi: {
    list: vi.fn(),
    create: vi.fn(),
    update: vi.fn(),
    complete: vi.fn(),
    cancel: vi.fn(),
    remove: vi.fn(),
  },
}))

const mockedApi = vi.mocked(dentalChartEntriesApi)

function makeEntry(overrides: Partial<DentalChartEntry> = {}): DentalChartEntry {
  return {
    id: overrides.id ?? 'entry-1',
    patient_id: 'patient-1',
    tooth_number: '16',
    dentition_type: 'permanent',
    surfaces: null,
    status: 'active',
    notes: null,
    recorded_at: '2026-07-20T09:00:00+00:00',
    completed_at: null,
    cancelled_at: null,
    created_at: '2026-07-20T09:00:00+00:00',
    updated_at: '2026-07-20T09:00:00+00:00',
    ...overrides,
  }
}

beforeEach(() => {
  setActivePinia(createPinia())
  vi.clearAllMocks()
})

describe('useDentalChartEntriesStore.fetchForPatient', () => {
  it('fetches and caches a patient’s entries', async () => {
    mockedApi.list.mockResolvedValueOnce([makeEntry()])
    const store = useDentalChartEntriesStore()

    await store.fetchForPatient('patient-1')

    expect(store.entries).toHaveLength(1)
    expect(store.loaded).toBe(true)
    expect(mockedApi.list).toHaveBeenCalledTimes(1)
  })

  it('skips the network call on a second fetch for the same patient', async () => {
    mockedApi.list.mockResolvedValue([makeEntry()])
    const store = useDentalChartEntriesStore()

    await store.fetchForPatient('patient-1')
    await store.fetchForPatient('patient-1')

    expect(mockedApi.list).toHaveBeenCalledTimes(1)
  })

  it('refetches when switching to a different patient', async () => {
    mockedApi.list.mockResolvedValue([makeEntry()])
    const store = useDentalChartEntriesStore()

    await store.fetchForPatient('patient-1')
    await store.fetchForPatient('patient-2')

    expect(mockedApi.list).toHaveBeenCalledTimes(2)
  })

  it('refetches when force is true even for the same patient', async () => {
    mockedApi.list.mockResolvedValue([makeEntry()])
    const store = useDentalChartEntriesStore()

    await store.fetchForPatient('patient-1')
    await store.fetchForPatient('patient-1', true)

    expect(mockedApi.list).toHaveBeenCalledTimes(2)
  })

  it('sets a translation-key error on failure', async () => {
    mockedApi.list.mockRejectedValueOnce(new Error('network error'))
    const store = useDentalChartEntriesStore()

    await store.fetchForPatient('patient-1')

    expect(store.error).toBe('dentalChart.loadError')
    expect(store.loading).toBe(false)
  })
})

describe('useDentalChartEntriesStore mutations', () => {
  it('create re-fetches the patient list to rehydrate embedded relations', async () => {
    mockedApi.list.mockResolvedValueOnce([]).mockResolvedValueOnce([makeEntry()])
    mockedApi.create.mockResolvedValueOnce(makeEntry())

    const store = useDentalChartEntriesStore()
    await store.fetchForPatient('patient-1')
    await store.create('patient-1', {
      dental_condition_id: 'condition-1',
      dentist_id: 'dentist-1',
      tooth_number: '16',
      status: 'active',
    })

    expect(mockedApi.list).toHaveBeenCalledTimes(2)
    expect(store.entries).toHaveLength(1)
  })

  it('update re-fetches the currently-loaded patient after a successful write', async () => {
    mockedApi.list
      .mockResolvedValueOnce([makeEntry()])
      .mockResolvedValueOnce([makeEntry({ notes: 'Updated' })])
    mockedApi.update.mockResolvedValueOnce(makeEntry({ notes: 'Updated' }))

    const store = useDentalChartEntriesStore()
    await store.fetchForPatient('patient-1')
    await store.update('entry-1', { notes: 'Updated' })

    expect(mockedApi.list).toHaveBeenCalledTimes(2)
    expect(store.entries[0].notes).toBe('Updated')
  })

  it('complete re-fetches the patient list', async () => {
    mockedApi.list
      .mockResolvedValueOnce([makeEntry({ status: 'planned' })])
      .mockResolvedValueOnce([makeEntry({ status: 'completed' })])
    mockedApi.complete.mockResolvedValueOnce(makeEntry({ status: 'completed' }))

    const store = useDentalChartEntriesStore()
    await store.fetchForPatient('patient-1')
    await store.complete('entry-1')

    expect(store.entries[0].status).toBe('completed')
  })

  it('cancel re-fetches the patient list', async () => {
    mockedApi.list
      .mockResolvedValueOnce([makeEntry({ status: 'planned' })])
      .mockResolvedValueOnce([makeEntry({ status: 'cancelled' })])
    mockedApi.cancel.mockResolvedValueOnce(makeEntry({ status: 'cancelled' }))

    const store = useDentalChartEntriesStore()
    await store.fetchForPatient('patient-1')
    await store.cancel('entry-1')

    expect(store.entries[0].status).toBe('cancelled')
  })

  it('remove filters the deleted entry out of the cached list without a re-fetch', async () => {
    mockedApi.list.mockResolvedValueOnce([makeEntry()])
    mockedApi.remove.mockResolvedValueOnce(undefined)

    const store = useDentalChartEntriesStore()
    await store.fetchForPatient('patient-1')
    await store.remove('entry-1')

    expect(store.entries).toHaveLength(0)
    expect(mockedApi.list).toHaveBeenCalledTimes(1)
  })
})
