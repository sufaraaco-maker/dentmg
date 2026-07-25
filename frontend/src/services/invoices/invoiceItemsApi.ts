import { api } from '@/lib/api'
import { rethrowInvoiceError } from './errors'
import type { CreateInvoiceItemPayload, Invoice, UpdateInvoiceItemPayload } from '@/types/invoice'

/**
 * Every method here returns the full parent `Invoice` (items eager-loaded) — including `remove`, a
 * deliberate divergence from Treatment Plan Items' `204` (backend design doc §9): every item
 * change moves the invoice's derived total, so returning the invoice lets callers re-hydrate from
 * one response instead of a second `GET /invoices/{invoice}` round-trip.
 */
export const invoiceItemsApi = {
  async create(invoiceId: string, payload: CreateInvoiceItemPayload): Promise<Invoice> {
    try {
      const { data } = await api.post<Invoice>(`/invoices/${invoiceId}/items`, payload)
      return data
    } catch (error) {
      rethrowInvoiceError(error)
    }
  },

  async update(itemId: string, payload: UpdateInvoiceItemPayload): Promise<Invoice> {
    try {
      const { data } = await api.put<Invoice>(`/invoice-items/${itemId}`, payload)
      return data
    } catch (error) {
      rethrowInvoiceError(error)
    }
  },

  async remove(itemId: string): Promise<Invoice> {
    try {
      const { data } = await api.delete<Invoice>(`/invoice-items/${itemId}`)
      return data
    } catch (error) {
      rethrowInvoiceError(error)
    }
  },
}
