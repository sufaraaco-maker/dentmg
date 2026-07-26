import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { clinicalNotesApi } from '@/services/clinicalNotes'
import { useClinicalNotesStore } from './clinicalNotes'
import type { ClinicalNote } from '@/types/clinicalNote'

vi.mock('@/services/clinicalNotes', () => ({
  clinicalNotesApi: {
    list: vi.fn(),
    create: vi.fn(),
    get: vi.fn(),
    update: vi.fn(),
    sign: vi.fn(),
    addAddendum: vi.fn(),
    remove: vi.fn(),
  },
}))

const mockedApi = vi.mocked(clinicalNotesApi)

function makeNote(overrides: Partial<ClinicalNote> = {}): ClinicalNote {
  return {
    id: overrides.id ?? 'note-1',
    patient_id: 'patient-1',
    appointment_id: null,
    dentist_id: 'dentist-1',
    note_type: 'progress',
    chief_complaint: 'Sensitivity on lower left molar.',
    subjective: null,
    objective: null,
    assessment: null,
    plan: null,
    status: 'draft',
    signed_at: null,
    signed_by_id: null,
    created_by_id: 'dentist-1',
    updated_by_id: null,
    created_at: '2026-07-26T09:00:00+00:00',
    updated_at: '2026-07-26T09:00:00+00:00',
    addendums: [],
    ...overrides,
  }
}

beforeEach(() => {
  setActivePinia(createPinia())
  vi.clearAllMocks()
})

describe('useClinicalNotesStore.fetchForPatient', () => {
  it('fetches and caches a patient’s notes', async () => {
    mockedApi.list.mockResolvedValueOnce([makeNote()])
    const store = useClinicalNotesStore()

    await store.fetchForPatient('patient-1')

    expect(store.notesForPatient('patient-1')).toHaveLength(1)
    expect(mockedApi.list).toHaveBeenCalledTimes(1)
  })

  it('skips the network call on a second fetch for the same patient', async () => {
    mockedApi.list.mockResolvedValue([makeNote()])
    const store = useClinicalNotesStore()

    await store.fetchForPatient('patient-1')
    await store.fetchForPatient('patient-1')

    expect(mockedApi.list).toHaveBeenCalledTimes(1)
  })

  it('forces a re-fetch when force is true', async () => {
    mockedApi.list.mockResolvedValue([makeNote()])
    const store = useClinicalNotesStore()

    await store.fetchForPatient('patient-1')
    await store.fetchForPatient('patient-1', true)

    expect(mockedApi.list).toHaveBeenCalledTimes(2)
  })

  it('sets a translation-key error on failure', async () => {
    mockedApi.list.mockRejectedValueOnce(new Error('network error'))
    const store = useClinicalNotesStore()

    await store.fetchForPatient('patient-1')

    expect(store.error).toBe('clinicalNotes.loadError')
  })

  it('excludes notes belonging to a different patient', async () => {
    mockedApi.list.mockResolvedValueOnce([makeNote({ id: 'note-1', patient_id: 'patient-1' })])
    const store = useClinicalNotesStore()
    await store.fetchForPatient('patient-1')
    store.cache.set('note-2', makeNote({ id: 'note-2', patient_id: 'patient-2' }))

    expect(store.notesForPatient('patient-1')).toHaveLength(1)
  })
})

describe('useClinicalNotesStore.fetchOne', () => {
  it('fetches a single note and upserts it', async () => {
    const note = makeNote()
    mockedApi.get.mockResolvedValueOnce(note)
    const store = useClinicalNotesStore()

    const result = await store.fetchOne('note-1')

    expect(result).toEqual(note)
    expect(store.cache.get('note-1')).toEqual(note)
  })
})

describe('useClinicalNotesStore.create', () => {
  it('creates a draft note and caches it', async () => {
    const created = makeNote()
    mockedApi.create.mockResolvedValueOnce(created)
    const store = useClinicalNotesStore()

    const result = await store.create('patient-1', { dentist_id: 'dentist-1', note_type: 'progress' })

    expect(result).toEqual(created)
    expect(store.cache.get('note-1')).toEqual(created)
  })
})

describe('useClinicalNotesStore.update', () => {
  it('updates a draft note and re-caches the result', async () => {
    const updated = makeNote({ subjective: 'New subjective' })
    mockedApi.update.mockResolvedValueOnce(updated)
    const store = useClinicalNotesStore()

    const result = await store.update('note-1', { subjective: 'New subjective' })

    expect(result.subjective).toBe('New subjective')
    expect(store.cache.get('note-1')?.subjective).toBe('New subjective')
  })
})

describe('useClinicalNotesStore.sign', () => {
  it('signs a note and re-caches the signed result', async () => {
    const signed = makeNote({ status: 'signed', signed_at: '2026-07-26T10:00:00+00:00' })
    mockedApi.sign.mockResolvedValueOnce(signed)
    const store = useClinicalNotesStore()

    const result = await store.sign('note-1')

    expect(result.status).toBe('signed')
    expect(store.cache.get('note-1')?.status).toBe('signed')
  })
})

describe('useClinicalNotesStore.addAddendum', () => {
  it('adds an addendum and re-caches the note with the addendum included', async () => {
    const withAddendum = makeNote({
      status: 'signed',
      addendums: [
        {
          id: 'addendum-1',
          clinical_note_id: 'note-1',
          body: 'Follow-up.',
          created_at: '2026-07-26T11:00:00+00:00',
        },
      ],
    })
    mockedApi.addAddendum.mockResolvedValueOnce(withAddendum)
    const store = useClinicalNotesStore()

    const result = await store.addAddendum('note-1', 'Follow-up.')

    expect(result.addendums).toHaveLength(1)
    expect(store.cache.get('note-1')?.addendums).toHaveLength(1)
  })
})

describe('useClinicalNotesStore.remove', () => {
  it('deletes the note and evicts it from the cache', async () => {
    mockedApi.remove.mockResolvedValueOnce(undefined)
    const store = useClinicalNotesStore()
    store.cache.set('note-1', makeNote())

    await store.remove('note-1')

    expect(store.cache.has('note-1')).toBe(false)
  })
})

describe('useClinicalNotesStore.$reset', () => {
  it('clears the cache, loaded patients, and error state', async () => {
    mockedApi.list.mockResolvedValueOnce([makeNote()])
    const store = useClinicalNotesStore()
    await store.fetchForPatient('patient-1')

    store.$reset()

    expect(store.cache.size).toBe(0)
    expect(store.notesForPatient('patient-1')).toHaveLength(0)
    expect(store.error).toBeNull()
  })
})
