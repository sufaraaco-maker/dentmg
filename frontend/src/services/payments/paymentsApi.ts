import { api } from '@/lib/api'
import { rethrowPaymentError } from './errors'
import type {
  ApplyPaymentPayload,
  Payment,
  RecordPaymentPayload,
  RefundPaymentPayload,
  UpdatePaymentPayload,
} from '@/types/payment'

export const paymentsApi = {
  // GET /api/patients/{patient}/payments is deliberately not paginated — a patient's lifetime
  // payment count stays small (backend design doc §9), same documented exception class as Invoices.
  async list(patientId: string): Promise<Payment[]> {
    const { data } = await api.get<Payment[]>(`/patients/${patientId}/payments`)
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
