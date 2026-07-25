import { api } from '@/lib/api'
import { rethrowTreatmentPlanError } from './errors'
import type {
  CreateTreatmentPlanPayload,
  CreateTreatmentPlanRevisionPayload,
  TreatmentPlan,
  UpdateTreatmentPlanPayload,
} from '@/types/treatmentPlan'

export const treatmentPlansApi = {
  // GET /api/patients/{patient}/treatment-plans is deliberately not paginated — a patient's
  // lifetime plan count is naturally small (design doc §9/§13), same documented exception class
  // as dental-chart-entries/patient-audit-logs.
  async list(patientId: string): Promise<TreatmentPlan[]> {
    const { data } = await api.get<TreatmentPlan[]>(`/patients/${patientId}/treatment-plans`)
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
