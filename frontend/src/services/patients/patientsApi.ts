import { api } from '@/lib/api'
import type { CreatePatientPayload, Patient, PatientAuditLog, UpdatePatientPayload } from '@/types/patient'

export interface PaginatedPatients {
  data: Patient[]
  meta: { current_page: number; last_page: number; per_page: number; total: number }
}

interface PaginatedPatientAuditLogs {
  data: PatientAuditLog[]
}

export const patientsApi = {
  async list(params: { search?: string; page?: number } = {}): Promise<PaginatedPatients> {
    const { data } = await api.get<PaginatedPatients>('/patients', {
      params: { search: params.search || undefined, page: params.page },
    })
    return data
  },

  async get(id: string): Promise<Patient> {
    const { data } = await api.get<Patient>(`/patients/${id}`)
    return data
  },

  async create(payload: CreatePatientPayload): Promise<Patient> {
    const { data } = await api.post<Patient>('/patients', payload)
    return data
  },

  async update(id: string, payload: UpdatePatientPayload): Promise<Patient> {
    const { data } = await api.put<Patient>(`/patients/${id}`, payload)
    return data
  },

  async remove(id: string): Promise<void> {
    await api.delete(`/patients/${id}`)
  },

  // Admin-only server-side (PatientPolicy::viewAuditLogs) — page 1 only, matching the Overview
  // tab's existing scope (a short recent-changes list, not a full paginated history browser).
  async auditLogs(id: string): Promise<PatientAuditLog[]> {
    const { data } = await api.get<PaginatedPatientAuditLogs>(`/patients/${id}/audit-logs`)
    return data.data
  },
}
