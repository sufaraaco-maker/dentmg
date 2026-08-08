import { api } from '@/lib/api'
import { rethrowLabCaseError } from './errors'
import type { CreateLabCasePayload, LabCase, LabCaseStatus } from '@/types/laboratory'

export interface PaginatedLabCases {
  data: LabCase[]
  meta: { current_page: number; last_page: number; per_page: number; total: number }
}

/**
 * Patient Profile's Laboratory tab (Phase 2.4, patient-laboratory-redesign-design.md §4.4) — the
 * patient-scoped counterpart to `LabCasesView.vue`'s direct-`api`-call flat list. This module keeps
 * the existing standalone Lab Cases pages (`LabsView.vue`/`LabCasesView.vue`/`LabCaseDetailView.vue`)
 * and their own direct `api` calls untouched; this file backs only the new patient-scoped store.
 */
export const labCasesApi = {
  async list(patientId: string, page?: number, status?: LabCaseStatus | null): Promise<PaginatedLabCases> {
    const { data } = await api.get<PaginatedLabCases>(`/patients/${patientId}/lab-cases`, {
      params: { page, status: status || undefined },
    })
    return data
  },

  async create(payload: CreateLabCasePayload): Promise<LabCase> {
    try {
      const { data } = await api.post<LabCase>('/lab-cases', payload)
      return data
    } catch (error) {
      rethrowLabCaseError(error)
    }
  },

  async send(id: string): Promise<LabCase> {
    try {
      const { data } = await api.post<LabCase>(`/lab-cases/${id}/send`)
      return data
    } catch (error) {
      rethrowLabCaseError(error)
    }
  },

  async receive(id: string): Promise<LabCase> {
    try {
      const { data } = await api.post<LabCase>(`/lab-cases/${id}/receive`)
      return data
    } catch (error) {
      rethrowLabCaseError(error)
    }
  },

  async qualityCheck(id: string): Promise<LabCase> {
    try {
      const { data } = await api.post<LabCase>(`/lab-cases/${id}/quality-check`)
      return data
    } catch (error) {
      rethrowLabCaseError(error)
    }
  },

  async cancel(id: string): Promise<LabCase> {
    try {
      const { data } = await api.post<LabCase>(`/lab-cases/${id}/cancel`)
      return data
    } catch (error) {
      rethrowLabCaseError(error)
    }
  },
}
