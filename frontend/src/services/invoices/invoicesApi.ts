import { api } from '@/lib/api'
import { rethrowInvoiceError } from './errors'
import type { CreateInvoicePayload, Invoice, InvoiceStatus, UpdateInvoicePayload } from '@/types/invoice'
import type { TreatmentPlanItem } from '@/types/treatmentPlan'

export interface PaginatedInvoices {
  data: Invoice[]
  meta: { current_page: number; last_page: number; per_page: number; total: number }
}

/** Distinct from `PaginatedInvoices` above (that one's `listAll()`'s clinic-wide shape) — this is
 *  `list()`'s patient-scoped paginated envelope, same field names but a different concern. */
export interface PaginatedInvoicesForPatient {
  data: Invoice[]
  meta: { current_page: number; last_page: number; per_page: number; total: number }
}

export const invoicesApi = {
  // Paginated (Phase 2.2, design doc §11/§14.4 — deferred from Phase 2.1, see TECH_DEBT.md's
  // now-resolved entry): 15/page, same convention as every other patient-scoped list endpoint.
  async list(patientId: string, page?: number): Promise<PaginatedInvoicesForPatient> {
    const { data } = await api.get<PaginatedInvoicesForPatient>(`/patients/${patientId}/invoices`, {
      params: { page },
    })
    return data
  },

  /** Every `issued` invoice for a patient, regardless of pagination — backs
   *  `ApplyPaymentDialog.vue`'s invoice picker (TECH_DEBT.md, Phase 2.2), which needs the full set
   *  of issued invoices, not just whatever's on the currently-viewed page. Uses the backend's
   *  `?status=` filter, which serves a higher per_page for filtered requests specifically for this
   *  use case — "issued invoices for one patient" is a naturally small, bounded set (drafts/voided
   *  excluded), not an unbounded fetch. */
  async listIssued(patientId: string): Promise<Invoice[]> {
    const { data } = await api.get<PaginatedInvoicesForPatient>(`/patients/${patientId}/invoices`, {
      params: { status: 'issued' },
    })
    return data.data
  },

  /** Clinic-wide, paginated (frontend-ux-redesign design doc §5.1/§11) — the Billing sidebar
   *  entry's destination, distinct from `list()` above which stays intentionally unpaginated and
   *  patient-scoped. */
  async listAll(
    params: { page?: number; search?: string; status?: InvoiceStatus } = {},
  ): Promise<PaginatedInvoices> {
    const { data } = await api.get<PaginatedInvoices>('/invoices', { params })
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
