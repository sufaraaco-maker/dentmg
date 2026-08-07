import { beforeEach, describe, expect, it, vi } from 'vitest'
import { api } from '@/lib/api'
import { clinicalNotesApi } from './clinicalNotesApi'
import type { ClinicalNote } from '@/types/clinicalNote'

vi.mock('@/lib/api', () => ({
  api: { get: vi.fn(), post: vi.fn(), put: vi.fn(), delete: vi.fn() },
}))

const mockedApi = vi.mocked(api)

function makeNote(overrides: Partial<ClinicalNote> = {}): ClinicalNote {
  return {
    id: 'note-1',
    patient_id: 'patient-1',
    appointment_id: null,
    dentist_id: 'dentist-1',
    note_type: 'progress',
    chief_complaint: 'Sensitivity on lower left molar.',
    subjective: 'Patient reports mild pain when chewing.',
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
  vi.clearAllMocks()
})

describe('clinicalNotesApi.list', () => {
  it('returns the paginated response shape', async () => {
    const page = {
      data: [makeNote()],
      meta: { current_page: 1, last_page: 1, per_page: 15, total: 1 },
    }
    mockedApi.get.mockResolvedValueOnce({ data: page })

    const result = await clinicalNotesApi.list('patient-1')

    expect(result).toEqual(page)
    expect(mockedApi.get).toHaveBeenCalledWith('/patients/patient-1/clinical-notes', {
      params: { page: undefined },
    })
  })

  it('passes the requested page through', async () => {
    const page = {
      data: [],
      meta: { current_page: 2, last_page: 2, per_page: 15, total: 16 },
    }
    mockedApi.get.mockResolvedValueOnce({ data: page })

    await clinicalNotesApi.list('patient-1', 2)

    expect(mockedApi.get).toHaveBeenCalledWith('/patients/patient-1/clinical-notes', {
      params: { page: 2 },
    })
  })
})

describe('clinicalNotesApi.create', () => {
  it('posts to the patient-nested route', async () => {
    const created = makeNote()
    mockedApi.post.mockResolvedValueOnce({ data: created })

    const result = await clinicalNotesApi.create('patient-1', {
      dentist_id: 'dentist-1',
      note_type: 'progress',
    })

    expect(result).toEqual(created)
    expect(mockedApi.post).toHaveBeenCalledWith('/patients/patient-1/clinical-notes', {
      dentist_id: 'dentist-1',
      note_type: 'progress',
    })
  })

  it('rethrows a typed error for a 422 with a recognized code', async () => {
    mockedApi.post.mockRejectedValueOnce({
      response: { data: { message: 'invalid', code: 'invalid_clinical_note_operation' } },
    })

    await expect(
      clinicalNotesApi.create('patient-1', { dentist_id: 'dentist-1', note_type: 'progress' }),
    ).rejects.toEqual(expect.objectContaining({ code: 'invalid_clinical_note_operation' }))
  })
})

describe('clinicalNotesApi.get', () => {
  it('fetches the single-resource route', async () => {
    const note = makeNote()
    mockedApi.get.mockResolvedValueOnce({ data: note })

    const result = await clinicalNotesApi.get('note-1')

    expect(result).toEqual(note)
    expect(mockedApi.get).toHaveBeenCalledWith('/clinical-notes/note-1')
  })
})

describe('clinicalNotesApi.update', () => {
  it('puts to the note-level route', async () => {
    const updated = makeNote({ subjective: 'Updated' })
    mockedApi.put.mockResolvedValueOnce({ data: updated })

    const result = await clinicalNotesApi.update('note-1', { subjective: 'Updated' })

    expect(result).toEqual(updated)
    expect(mockedApi.put).toHaveBeenCalledWith('/clinical-notes/note-1', { subjective: 'Updated' })
  })

  it('rethrows a typed locked error', async () => {
    mockedApi.put.mockRejectedValueOnce({
      response: { data: { message: 'locked', code: 'clinical_note_locked' } },
    })

    await expect(clinicalNotesApi.update('note-1', { subjective: 'x' })).rejects.toEqual(
      expect.objectContaining({ code: 'clinical_note_locked' }),
    )
  })
})

describe('clinicalNotesApi.sign', () => {
  it('posts to the sign endpoint', async () => {
    const signed = makeNote({
      status: 'signed',
      signed_at: '2026-07-26T10:00:00+00:00',
      signed_by_id: 'dentist-1',
    })
    mockedApi.post.mockResolvedValueOnce({ data: signed })

    const result = await clinicalNotesApi.sign('note-1')

    expect(result).toEqual(signed)
    expect(mockedApi.post).toHaveBeenCalledWith('/clinical-notes/note-1/sign')
  })

  it('rethrows a typed error when signing a blank note', async () => {
    mockedApi.post.mockRejectedValueOnce({
      response: { data: { message: 'blank', code: 'invalid_clinical_note_operation' } },
    })

    await expect(clinicalNotesApi.sign('note-1')).rejects.toEqual(
      expect.objectContaining({ code: 'invalid_clinical_note_operation' }),
    )
  })
})

describe('clinicalNotesApi.addAddendum', () => {
  it('posts the body to the addendums endpoint and returns the refreshed note', async () => {
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
    mockedApi.post.mockResolvedValueOnce({ data: withAddendum })

    const result = await clinicalNotesApi.addAddendum('note-1', 'Follow-up.')

    expect(result).toEqual(withAddendum)
    expect(mockedApi.post).toHaveBeenCalledWith('/clinical-notes/note-1/addendums', { body: 'Follow-up.' })
  })

  it('rethrows a typed error when adding an addendum to a draft note', async () => {
    mockedApi.post.mockRejectedValueOnce({
      response: { data: { message: 'too early', code: 'invalid_clinical_note_operation' } },
    })

    await expect(clinicalNotesApi.addAddendum('note-1', 'x')).rejects.toEqual(
      expect.objectContaining({ code: 'invalid_clinical_note_operation' }),
    )
  })
})

describe('clinicalNotesApi.remove', () => {
  it('deletes the correct id', async () => {
    mockedApi.delete.mockResolvedValueOnce({ data: undefined })

    await clinicalNotesApi.remove('note-1')

    expect(mockedApi.delete).toHaveBeenCalledWith('/clinical-notes/note-1')
  })
})
