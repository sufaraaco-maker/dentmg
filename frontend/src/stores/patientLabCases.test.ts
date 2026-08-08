import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { labCasesApi } from '@/services/laboratory'
import { usePatientLabCasesStore } from './patientLabCases'
import type { LabCase } from '@/types/laboratory'

vi.mock('@/services/laboratory', () => ({
  labCasesApi: {
    list: vi.fn(),
    create: vi.fn(),
    send: vi.fn(),
    receive: vi.fn(),
    qualityCheck: vi.fn(),
    cancel: vi.fn(),
  },
}))

const mockedApi = vi.mocked(labCasesApi)

function makeCase(overrides: Partial<LabCase> = {}): LabCase {
  return {
    id: overrides.id ?? 'case-1',
    sequence_number: 1,
    case_number: 'LC-000001',
    patient_id: 'patient-1',
    lab_id: 'lab-1',
    dentist_id: null,
    treatment_plan_item_id: null,
    appointment_id: null,
    tooth_numbers: null,
    case_type: null,
    shade: null,
    material: null,
    instructions: null,
    fee: null,
    tracking_number: null,
    status: 'draft',
    sent_at: null,
    due_at: null,
    received_at: null,
    quality_checked_at: null,
    cancelled_at: null,
    created_at: '2026-08-08T09:00:00+00:00',
    updated_at: '2026-08-08T09:00:00+00:00',
    ...overrides,
  }
}

function makePage(
  cases: LabCase[],
  overrides: Partial<{ current_page: number; last_page: number; per_page: number; total: number }> = {},
) {
  return {
    data: cases,
    meta: {
      current_page: overrides.current_page ?? 1,
      last_page: overrides.last_page ?? 1,
      per_page: overrides.per_page ?? 15,
      total: overrides.total ?? cases.length,
    },
  }
}

beforeEach(() => {
  setActivePinia(createPinia())
  vi.clearAllMocks()
})

describe('usePatientLabCasesStore.fetchForPatient', () => {
  it('fetches and caches a patient’s lab cases', async () => {
    mockedApi.list.mockResolvedValueOnce(makePage([makeCase()]))
    const store = usePatientLabCasesStore()

    await store.fetchForPatient('patient-1')

    expect(store.casesForPatient('patient-1')).toHaveLength(1)
    expect(mockedApi.list).toHaveBeenCalledTimes(1)
    expect(mockedApi.list).toHaveBeenCalledWith('patient-1', 1, null)
  })

  it('skips the network call on a second fetch for the same patient and page', async () => {
    mockedApi.list.mockResolvedValue(makePage([makeCase()]))
    const store = usePatientLabCasesStore()

    await store.fetchForPatient('patient-1')
    await store.fetchForPatient('patient-1')

    expect(mockedApi.list).toHaveBeenCalledTimes(1)
  })

  it('refetches when force is true', async () => {
    mockedApi.list.mockResolvedValue(makePage([makeCase()]))
    const store = usePatientLabCasesStore()

    await store.fetchForPatient('patient-1')
    await store.fetchForPatient('patient-1', 1, null, true)

    expect(mockedApi.list).toHaveBeenCalledTimes(2)
  })

  it('fetches again when a different page is requested', async () => {
    mockedApi.list.mockResolvedValueOnce(makePage([makeCase()], { current_page: 1, last_page: 2, total: 16 }))
    mockedApi.list.mockResolvedValueOnce(
      makePage([makeCase({ id: 'case-2' })], { current_page: 2, last_page: 2, total: 16 }),
    )
    const store = usePatientLabCasesStore()

    await store.fetchForPatient('patient-1')
    await store.fetchForPatient('patient-1', 2)

    expect(mockedApi.list).toHaveBeenCalledTimes(2)
    expect(store.casesForPatient('patient-1').map((c) => c.id)).toEqual(['case-2'])
  })

  it('passes the status filter through to the API', async () => {
    mockedApi.list.mockResolvedValueOnce(makePage([]))
    const store = usePatientLabCasesStore()

    await store.fetchForPatient('patient-1', 1, 'sent')

    expect(mockedApi.list).toHaveBeenCalledWith('patient-1', 1, 'sent')
  })

  it('sets a translation-key error and clears loading on failure, instead of rejecting', async () => {
    mockedApi.list.mockRejectedValueOnce(new Error('network error'))
    const store = usePatientLabCasesStore()

    await store.fetchForPatient('patient-1')

    expect(store.error).toBe('laboratory.labCases.loadError')
    expect(store.loading).toBe(false)
  })
})

describe('usePatientLabCasesStore mutations', () => {
  it('create upserts the new case and refreshes page 1 for that patient', async () => {
    mockedApi.create.mockResolvedValueOnce(makeCase({ id: 'case-2' }))
    mockedApi.list.mockResolvedValueOnce(makePage([makeCase({ id: 'case-2' }), makeCase({ id: 'case-1' })]))
    const store = usePatientLabCasesStore()

    const created = await store.create('patient-1', { patient_id: 'patient-1', lab_id: 'lab-1' })

    expect(created.id).toBe('case-2')
    expect(mockedApi.list).toHaveBeenCalledWith('patient-1', 1, null)
    expect(store.casesForPatient('patient-1').map((c) => c.id)).toEqual(['case-2', 'case-1'])
  })

  it('send/receive/qualityCheck/cancel each upsert the returned case into the cache', async () => {
    mockedApi.list.mockResolvedValueOnce(makePage([makeCase()]))
    const store = usePatientLabCasesStore()
    await store.fetchForPatient('patient-1')

    mockedApi.send.mockResolvedValueOnce(makeCase({ status: 'sent' }))
    await store.send('case-1')
    expect(store.casesForPatient('patient-1')[0].status).toBe('sent')

    mockedApi.receive.mockResolvedValueOnce(makeCase({ status: 'received' }))
    await store.receive('case-1')
    expect(store.casesForPatient('patient-1')[0].status).toBe('received')

    mockedApi.qualityCheck.mockResolvedValueOnce(makeCase({ status: 'quality_checked' }))
    await store.qualityCheck('case-1')
    expect(store.casesForPatient('patient-1')[0].status).toBe('quality_checked')
  })

  it('cancel upserts the cancelled case into the cache', async () => {
    mockedApi.list.mockResolvedValueOnce(makePage([makeCase()]))
    const store = usePatientLabCasesStore()
    await store.fetchForPatient('patient-1')

    mockedApi.cancel.mockResolvedValueOnce(makeCase({ status: 'cancelled' }))
    await store.cancel('case-1')

    expect(store.casesForPatient('patient-1')[0].status).toBe('cancelled')
  })

  it('upsert() writes directly into the cache without a network call', () => {
    const store = usePatientLabCasesStore()

    store.upsert(makeCase({ id: 'case-3', status: 'sent' }))

    expect(store.cache.get('case-3')?.status).toBe('sent')
    expect(mockedApi.list).not.toHaveBeenCalled()
  })
})

describe('usePatientLabCasesStore.$reset', () => {
  it('clears the cache and pagination state', async () => {
    mockedApi.list.mockResolvedValueOnce(makePage([makeCase()]))
    const store = usePatientLabCasesStore()
    await store.fetchForPatient('patient-1')

    store.$reset()

    expect(store.casesForPatient('patient-1')).toHaveLength(0)
    expect(store.cache.size).toBe(0)
  })
})
