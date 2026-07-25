import { api } from '@/lib/api'
import { rethrowTreatmentPlanError } from './errors'
import type {
  CreateTreatmentPlanItemPayload,
  TreatmentPlan,
  UpdateTreatmentPlanItemPayload,
} from '@/types/treatmentPlan'

/**
 * Every method here returns the full parent `TreatmentPlan` (items eager-loaded), not the item
 * alone — matching the backend's deliberate design (docs/modules/treatment-plans-design.md §9):
 * an item change moves the plan's derived cost roll-up, so returning the plan lets callers
 * re-hydrate from one response instead of a second `GET /treatment-plans/{plan}` round-trip.
 */
export const treatmentPlanItemsApi = {
  async create(planId: string, payload: CreateTreatmentPlanItemPayload): Promise<TreatmentPlan> {
    try {
      const { data } = await api.post<TreatmentPlan>(`/treatment-plans/${planId}/items`, payload)
      return data
    } catch (error) {
      rethrowTreatmentPlanError(error)
    }
  },

  async update(itemId: string, payload: UpdateTreatmentPlanItemPayload): Promise<TreatmentPlan> {
    try {
      const { data } = await api.put<TreatmentPlan>(`/treatment-plan-items/${itemId}`, payload)
      return data
    } catch (error) {
      rethrowTreatmentPlanError(error)
    }
  },

  async complete(itemId: string): Promise<TreatmentPlan> {
    try {
      const { data } = await api.post<TreatmentPlan>(`/treatment-plan-items/${itemId}/complete`)
      return data
    } catch (error) {
      rethrowTreatmentPlanError(error)
    }
  },

  async cancel(itemId: string): Promise<TreatmentPlan> {
    try {
      const { data } = await api.post<TreatmentPlan>(`/treatment-plan-items/${itemId}/cancel`)
      return data
    } catch (error) {
      rethrowTreatmentPlanError(error)
    }
  },

  /** Soft delete — admin-only (design doc §8/§10); unlike every other item mutation, there is no
   *  plan left to return once the item itself is gone, so this stays a plain `204`. */
  async remove(itemId: string): Promise<void> {
    await api.delete(`/treatment-plan-items/${itemId}`)
  },
}
