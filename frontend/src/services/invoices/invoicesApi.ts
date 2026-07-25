import { api } from '@/lib/api'
import { rethrowInvoiceError } from './errors'
import type { CreateInvoicePayload, Invoice, UpdateInvoicePayload } from '@/types/invoice'
import type { TreatmentPlanItem } from '@/types/treatmentPlan'

export const invoicesApi = {
  // GET /api/patients/{patient}/invoices is deliberately not paginated — a patient's lifetime
  // invoice count is naturally small (design doc §9/§13), same documented exception class as
  // Treatment Plans/Dental Chart/Patient audit logs.
  async list(patientId: string): Promise<Invoice[]> {
    const { data } = await api.get<Invoice[]>(`/patients/${patientId}/invoices`)
    return data
  },

  async create(patientId: string, payload: CreateInvoicePayload = {}): Promise<Invoice> {
    try {
      const { data } = await api.post<Invoice>(`/patients/${patientId}/invoices`, payload)
      return data
    } catch (error) {
      rethrowInvoiceError(error)
    }
  },

  async get(id: string): Promise<Invoice> {
    const { data } = await api.get<Invoice>(`/invoices/${id}`)
    return data
  },

  async update(id: string, payload: UpdateInvoicePayload): Promise<Invoice> {
    try {
      const { data } = await api.put<Invoice>(`/invoices/${id}`, payload)
      return data
    } catch (error) {
      rethrowInvoiceError(error)
    }
  },

  async issue(id: string): Promise<Invoice> {
    try {
      const { data } = await api.post<Invoice>(`/invoices/${id}/issue`)
      return data
    } catch (error) {
      rethrowInvoiceError(error)
    }
  },

  async void(id: string): Promise<Invoice> {
    try {
      const { data } = await api.post<Invoice>(`/invoices/${id}/void`)
      return data
    } catch (error) {
      rethrowInvoiceError(error)
    }
  },

  async remove(id: string): Promise<void> {
    await api.delete(`/invoices/${id}`)
  },

  /** The "not yet invoiced, completed" picker source for the "Add from Treatment Plan" dialog
   *  (backend design doc §3 step 1/§9) — a derived, read-only query, not a new stored concept
   *  (§7). Returns the same `TreatmentPlanItem` shape the Treatment Plans module already types,
   *  since the backend echoes the identical `TreatmentPlanItemResource`. */
  async billableTreatmentPlanItems(patientId: string): Promise<TreatmentPlanItem[]> {
    const { data } = await api.get<TreatmentPlanItem[]>(
      `/patients/${patientId}/treatment-plan-items/billable`,
    )
    return data
  },
}
