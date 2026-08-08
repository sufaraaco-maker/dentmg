import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import {
  deletePatientDocument,
  fetchPatientDocuments,
  updatePatientDocument,
  uploadDocument,
} from '@/services/documents'
import { usePatientDocumentsStore } from './patientDocuments'
import type { PatientDocument } from '@/types/documents'

vi.mock('@/services/documents', () => ({
  fetchPatientDocuments: vi.fn(),
  uploadDocument: vi.fn(),
  updatePatientDocument: vi.fn(),
  deletePatientDocument: vi.fn(),
}))

const mockedFetch = vi.mocked(fetchPatientDocuments)
const mockedUpload = vi.mocked(uploadDocument)
const mockedUpdate = vi.mocked(updatePatientDocument)
const mockedDelete = vi.mocked(deletePatientDocument)

function makeDocument(overrides: Partial<PatientDocument> = {}): PatientDocument {
  return {
    id: overrides.id ?? 'doc-1',
    patient_id: 'patient-1',
    uploaded_by: 'user-1',
    category: 'consent_form',
    title: 'Consent Form',
    original_filename: 'consent.pdf',
    mime_type: 'application/pdf',
    file_size: 12345,
    notes: null,
    file_url: '/api/documents/doc-1/file',
    created_at: '2026-08-08T09:00:00+00:00',
    updated_at: '2026-08-08T09:00:00+00:00',
    ...overrides,
  }
}

function makePage(
  documents: PatientDocument[],
  overrides: Partial<{ current_page: number; last_page: number; per_page: number; total: number }> = {},
) {
  return {
    data: documents,
    meta: {
      current_page: overrides.current_page ?? 1,
      last_page: overrides.last_page ?? 1,
      per_page: overrides.per_page ?? 15,
      total: overrides.total ?? documents.length,
    },
  }
}

beforeEach(() => {
  setActivePinia(createPinia())
  vi.clearAllMocks()
})

describe('usePatientDocumentsStore.fetchForPatient', () => {
  it('fetches and caches a patient’s documents', async () => {
    mockedFetch.mockResolvedValueOnce(makePage([makeDocument()]))
    const store = usePatientDocumentsStore()

    await store.fetchForPatient('patient-1')

    expect(store.documentsForPatient('patient-1')).toHaveLength(1)
    expect(mockedFetch).toHaveBeenCalledTimes(1)
    expect(mockedFetch).toHaveBeenCalledWith('patient-1', { category: null }, 1)
  })

  it('skips the network call on a second fetch for the same patient and page', async () => {
    mockedFetch.mockResolvedValue(makePage([makeDocument()]))
    const store = usePatientDocumentsStore()

    await store.fetchForPatient('patient-1')
    await store.fetchForPatient('patient-1')

    expect(mockedFetch).toHaveBeenCalledTimes(1)
  })

  it('refetches when force is true', async () => {
    mockedFetch.mockResolvedValue(makePage([makeDocument()]))
    const store = usePatientDocumentsStore()

    await store.fetchForPatient('patient-1')
    await store.fetchForPatient('patient-1', 1, null, true)

    expect(mockedFetch).toHaveBeenCalledTimes(2)
  })

  it('passes the category filter through to the API', async () => {
    mockedFetch.mockResolvedValueOnce(makePage([]))
    const store = usePatientDocumentsStore()

    await store.fetchForPatient('patient-1', 1, 'insurance')

    expect(mockedFetch).toHaveBeenCalledWith('patient-1', { category: 'insurance' }, 1)
  })

  it('sets a translation-key error and clears loading on failure, instead of rejecting', async () => {
    mockedFetch.mockRejectedValueOnce(new Error('network error'))
    const store = usePatientDocumentsStore()

    await store.fetchForPatient('patient-1')

    expect(store.error).toBe('documents.loadError')
    expect(store.loading).toBe(false)
  })
})

describe('usePatientDocumentsStore mutations', () => {
  it('upload upserts the new document and refreshes page 1 for that patient', async () => {
    mockedUpload.mockResolvedValueOnce(makeDocument({ id: 'doc-2' }))
    mockedFetch.mockResolvedValueOnce(
      makePage([makeDocument({ id: 'doc-2' }), makeDocument({ id: 'doc-1' })]),
    )
    const store = usePatientDocumentsStore()

    const created = await store.upload('patient-1', {
      file: new File(['x'], 'consent.pdf'),
      category: 'consent_form',
      title: 'Consent Form',
    })

    expect(created.id).toBe('doc-2')
    expect(mockedFetch).toHaveBeenCalledWith('patient-1', { category: null }, 1)
    expect(store.documentsForPatient('patient-1').map((d) => d.id)).toEqual(['doc-2', 'doc-1'])
  })

  it('update upserts the returned document into the cache', async () => {
    mockedFetch.mockResolvedValueOnce(makePage([makeDocument()]))
    const store = usePatientDocumentsStore()
    await store.fetchForPatient('patient-1')

    mockedUpdate.mockResolvedValueOnce(makeDocument({ title: 'Renamed' }))
    await store.update('doc-1', { title: 'Renamed' })

    expect(store.documentsForPatient('patient-1')[0].title).toBe('Renamed')
  })

  it('remove deletes and drops the document from the cache and page index', async () => {
    mockedFetch.mockResolvedValueOnce(makePage([makeDocument()]))
    const store = usePatientDocumentsStore()
    await store.fetchForPatient('patient-1')

    mockedDelete.mockResolvedValueOnce(undefined)
    await store.remove('doc-1')

    expect(mockedDelete).toHaveBeenCalledWith('doc-1')
    expect(store.cache.has('doc-1')).toBe(false)
    expect(store.documentsForPatient('patient-1')).toHaveLength(0)
  })

  it('upsert() writes directly into the cache without a network call', () => {
    const store = usePatientDocumentsStore()

    store.upsert(makeDocument({ id: 'doc-3', title: 'Direct' }))

    expect(store.cache.get('doc-3')?.title).toBe('Direct')
    expect(mockedFetch).not.toHaveBeenCalled()
  })
})

describe('usePatientDocumentsStore.$reset', () => {
  it('clears the cache and pagination state', async () => {
    mockedFetch.mockResolvedValueOnce(makePage([makeDocument()]))
    const store = usePatientDocumentsStore()
    await store.fetchForPatient('patient-1')

    store.$reset()

    expect(store.documentsForPatient('patient-1')).toHaveLength(0)
    expect(store.cache.size).toBe(0)
  })
})
