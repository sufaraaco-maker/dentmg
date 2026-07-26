import { api } from '@/lib/api'
import { rethrowClinicalNoteError } from './errors'
import type { ClinicalNote, CreateClinicalNotePayload, UpdateClinicalNotePayload } from '@/types/clinicalNote'

export const clinicalNotesApi = {
  // GET /api/patients/{patient}/clinical-notes is deliberately not paginated — a patient's
  // lifetime note count is naturally small, same documented exception class as
  // treatment-plans/payments/dental-chart-entries.
  async list(patientId: string): Promise<ClinicalNote[]> {
    const { data } = await api.get<ClinicalNote[]>(`/patients/${patientId}/clinical-notes`)
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
