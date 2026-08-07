import { api } from '@/lib/api'
import { rethrowTreatmentPlanError } from './errors'
import type {
  CreateTreatmentPlanPayload,
  CreateTreatmentPlanRevisionPayload,
  TreatmentPlan,
  TreatmentPlanStatus,
  UpdateTreatmentPlanPayload,
} from '@/types/treatmentPlan'

export interface PaginatedTreatmentPlans {
  data: TreatmentPlan[]
  meta: { current_page: number; last_page: number; per_page: number; total: number }
}

export const treatmentPlansApi = {
  // Paginated (Phase 2.1, design doc §11/§14.4) — a long-tenured patient's full plan history used
  // to load in one unbounded request; `page` defaults to 1 server-side when omitted.
  async list(patientId: string, page?: number): Promise<PaginatedTreatmentPlans> {
    const { data } = await api.get<PaginatedTreatmentPlans>(`/patients/${patientId}/treatment-plans`, {
      params: { page },
    })
    return data
  },

  /** Clinic-wide, paginated (mirrors `invoicesApi.listAll()`'s exact shape) — the Sidebar's
   *  Treatment Plans entry's destination, distinct from `list()` above which stays intentionally
   *  unpaginated and patient-scoped. */
  async listAll(
    params: { page?: number; search?: string; status?: TreatmentPlanStatus } = {},
  ): Promise<PaginatedTreatmentPlans> {
    const { data } = await api.get<PaginatedTreatmentPlans>('/treatment-plans', { params })
    return data
  },

  async create(patientId: string, payload: CreateTreatmentPlanPayload): Promise<TreatmentPlan> {
    try {
      const { data } = await api.post<TreatmentPlan>(`/patients/${patientId}/treatment-plans`, payload)
      return data
    } catch (error) {
      rethrowTreatmentPlanError(error)
    }
  },

  async get(id: string): Promise<TreatmentPlan> {
    const { data } = await api.get<TreatmentPlan>(`/treatment-plans/${id}`)
    return data
  },

  async update(id: string, payload: UpdateTreatmentPlanPayload): Promise<TreatmentPlan> {
    try {
      const { data } = await api.put<TreatmentPlan>(`/treatment-plans/${id}`, payload)
      return data
    } catch (error) {
      rethrowTreatmentPlanError(error)
    }
  },

  async present(id: string): Promise<TreatmentPlan> {
    try {
      const { data } = await api.post<TreatmentPlan>(`/treatment-plans/${id}/present`)
      return data
    } catch (error) {
      rethrowTreatmentPlanError(error)
    }
  },

  async accept(id: string): Promise<TreatmentPlan> {
    try {
      const { data } = await api.post<TreatmentPlan>(`/treatment-plans/${id}/accept`)
      return data
    } catch (error) {
      rethrowTreatmentPlanError(error)
    }
  },

  async reject(id: string): Promise<TreatmentPlan> {
    try {
      const { data } = await api.post<TreatmentPlan>(`/treatment-plans/${id}/reject`)
      return data
    } catch (error) {
      rethrowTreatmentPlanError(error)
    }
  },

  async start(id: string): Promise<TreatmentPlan> {
    try {
      const { data } = await api.post<TreatmentPlan>(`/treatment-plans/${id}/start`)
      return data
    } catch (error) {
      rethrowTreatmentPlanError(error)
    }
  },

  async complete(id: string): Promise<TreatmentPlan> {
    try {
      const { data } = await api.post<TreatmentPlan>(`/treatment-plans/${id}/complete`)
      return data
    } catch (error) {
      rethrowTreatmentPlanError(error)
    }
  },

  async cancel(id: string): Promise<TreatmentPlan> {
    try {
      const { data } = await api.post<TreatmentPlan>(`/treatment-plans/${id}/cancel`)
      return data
    } catch (error) {
      rethrowTreatmentPlanError(error)
    }
  },

  /** Creates a superseding revision plan (design doc §15 Q4) — returns the *new* draft plan. */
  async createRevision(id: string, payload: CreateTreatmentPlanRevisionPayload = {}): Promise<TreatmentPlan> {
    try {
      const { data } = await api.post<TreatmentPlan>(`/treatment-plans/${id}/revisions`, payload)
      return data
    } catch (error) {
      rethrowTreatmentPlanError(error)
    }
  },

  async remove(id: string): Promise<void> {
    await api.delete(`/treatment-plans/${id}`)
  },
}
