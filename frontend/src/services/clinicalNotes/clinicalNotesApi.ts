import { api } from '@/lib/api'
import { rethrowClinicalNoteError } from './errors'
import type { ClinicalNote, CreateClinicalNotePayload, UpdateClinicalNotePayload } from '@/types/clinicalNote'

export interface PaginatedClinicalNotes {
  data: ClinicalNote[]
  meta: { current_page: number; last_page: number; per_page: number; total: number }
}

export const clinicalNotesApi = {
  // Paginated (Phase 2.1, design doc §11/§14.4) — a long-tenured patient's full note history used
  // to load in one unbounded request; `page` defaults to 1 server-side when omitted.
  async list(patientId: string, page?: number): Promise<PaginatedClinicalNotes> {
    const { data } = await api.get<PaginatedClinicalNotes>(`/patients/${patientId}/clinical-notes`, {
      params: { page },
    })
    return data
  },

  async create(patientId: string, payload: CreateClinicalNotePayload): Promise<ClinicalNote> {
    try {
      const { data } = await api.post<ClinicalNote>(`/patients/${patientId}/clinical-notes`, payload)
      return data
    } catch (error) {
      rethrowClinicalNoteError(error)
    }
  },

  async get(id: string): Promise<ClinicalNote> {
    const { data } = await api.get<ClinicalNote>(`/clinical-notes/${id}`)
    return data
  },

  async update(id: string, payload: UpdateClinicalNotePayload): Promise<ClinicalNote> {
    try {
      const { data } = await api.put<ClinicalNote>(`/clinical-notes/${id}`, payload)
      return data
    } catch (error) {
      rethrowClinicalNoteError(error)
    }
  },

  /** Idempotent server-side (design doc §8) — safe to call even if already signed. */
  async sign(id: string): Promise<ClinicalNote> {
    try {
      const { data } = await api.post<ClinicalNote>(`/clinical-notes/${id}/sign`)
      return data
    } catch (error) {
      rethrowClinicalNoteError(error)
    }
  },

  /** Returns the full parent note with `addendums` refreshed — no separate addendum fetch. */
  async addAddendum(id: string, body: string): Promise<ClinicalNote> {
    try {
      const { data } = await api.post<ClinicalNote>(`/clinical-notes/${id}/addendums`, { body })
      return data
    } catch (error) {
      rethrowClinicalNoteError(error)
    }
  },

  async remove(id: string): Promise<void> {
    await api.delete(`/clinical-notes/${id}`)
  },
}
