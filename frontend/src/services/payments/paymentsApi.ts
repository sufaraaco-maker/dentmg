import { api } from '@/lib/api'
import { rethrowPaymentError } from './errors'
import type {
  ApplyPaymentPayload,
  Payment,
  RecordPaymentPayload,
  RefundPaymentPayload,
  UpdatePaymentPayload,
} from '@/types/payment'

export interface PaginatedPayments {
  data: Payment[]
  meta: { current_page: number; last_page: number; per_page: number; total: number }
}

export const paymentsApi = {
  // Paginated (Phase 2.2, design doc §11/§14.4 — deferred from Phase 2.1, see TECH_DEBT.md's
  // now-resolved entry): 15/page, same convention as `invoicesApi.list()`.
  async list(patientId: string, page?: number): Promise<PaginatedPayments> {
    const { data } = await api.get<PaginatedPayments>(`/patients/${patientId}/payments`, {
      params: { page },
    })
    return data
  },

  /** Payments applied to one specific invoice (TECH_DEBT.md, Phase 2.2) — backs
   *  `InvoicePaymentsPanel.vue` directly, replacing its former client-side filter of the full
   *  patient-level payment list. */
  async listForInvoice(invoiceId: string, page?: number): Promise<PaginatedPayments> {
    const { data } = await api.get<PaginatedPayments>(`/invoices/${invoiceId}/payments`, {
      params: { page },
    })
    return data
  },

  async record(patientId: string, payload: RecordPaymentPayload): Promise<Payment> {
    try {
      const { data } = await api.post<Payment>(`/patients/${patientId}/payments`, payload)
      return data
    } catch (error) {
      rethrowPaymentError(error)
    }
  },

  async get(id: string): Promise<Payment> {
    const { data } = await api.get<Payment>(`/payments/${id}`)
    return data
  },

  async update(id: string, payload: UpdatePaymentPayload): Promise<Payment> {
    try {
      const { data } = await api.put<Payment>(`/payments/${id}`, payload)
      return data
    } catch (error) {
      rethrowPaymentError(error)
    }
  },

  async apply(id: string, payload: ApplyPaymentPayload): Promise<Payment> {
    try {
      const { data } = await api.post<Payment>(`/payments/${id}/apply`, payload)
      return data
    } catch (error) {
      rethrowPaymentError(error)
    }
  },

  async refund(id: string, payload: RefundPaymentPayload): Promise<Payment> {
    try {
      const { data } = await api.post<Payment>(`/payments/${id}/refund`, payload)
      return data
    } catch (error) {
      rethrowPaymentError(error)
    }
  },

  async remove(id: string): Promise<void> {
    await api.delete(`/payments/${id}`)
  },
}
