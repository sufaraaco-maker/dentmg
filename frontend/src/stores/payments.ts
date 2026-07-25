import { defineStore } from 'pinia'
import { reactive, ref } from 'vue'
import { paymentsApi } from '@/services/payments'
import type {
  ApplyPaymentPayload,
  Payment,
  RecordPaymentPayload,
  RefundPaymentPayload,
  UpdatePaymentPayload,
} from '@/types/payment'

/**
 * A single id-keyed cache serving both the Patient Payments tab's list and the per-invoice
 * Payments panel (filtered client-side by `invoice_id`, mirroring `invoicesStore`'s identical
 * precedent for treatment-plan-item billability) — no `GET /api/invoices/{invoice}/payments`
 * exists (backend design doc §9).
 */
export const usePaymentsStore = defineStore('payments', () => {
  const cache = reactive(new Map<string, Payment>())
  const loadedPatientIds = reactive(new Set<string>())
  const loading = ref(false)
  const error = ref<string | null>(null)

  function upsert(payment: Payment) {
    cache.set(payment.id, payment)
  }

  /** All cached payments for one patient, most recently received first. */
  function paymentsForPatient(patientId: string): Payment[] {
    return Array.from(cache.values())
      .filter((payment) => payment.patient_id === patientId)
      .sort((a, b) => b.received_at.localeCompare(a.received_at) || b.created_at.localeCompare(a.created_at))
  }

  /** The subset of a patient's cached payments applied to one specific invoice — backs the Invoice
   *  Detail Payments panel. */
  function paymentsForInvoice(invoiceId: string): Payment[] {
    return Array.from(cache.values())
      .filter((payment) => payment.invoice_id === invoiceId)
      .sort((a, b) => b.received_at.localeCompare(a.received_at) || b.created_at.localeCompare(a.created_at))
  }

  async function fetchForPatient(patientId: string, force = false): Promise<void> {
    if (loadedPatientIds.has(patientId) && !force) return

    loading.value = true
    error.value = null

    try {
      const payments = await paymentsApi.list(patientId)
      payments.forEach(upsert)
      loadedPatientIds.add(patientId)
    } catch {
      error.value = 'payments.loadError'
    } finally {
      loading.value = false
    }
  }

  async function fetchOne(id: string): Promise<Payment> {
    const payment = await paymentsApi.get(id)
    upsert(payment)
    return payment
  }

  async function record(patientId: string, payload: RecordPaymentPayload): Promise<Payment> {
    const payment = await paymentsApi.record(patientId, payload)
    upsert(payment)
    return payment
  }

  async function update(id: string, payload: UpdatePaymentPayload): Promise<Payment> {
    const payment = await paymentsApi.update(id, payload)
    upsert(payment)
    return payment
  }

  async function apply(id: string, payload: ApplyPaymentPayload): Promise<Payment> {
    const payment = await paymentsApi.apply(id, payload)
    upsert(payment)
    return payment
  }

  async function refund(id: string, payload: RefundPaymentPayload): Promise<Payment> {
    const refundRow = await paymentsApi.refund(id, payload)
    upsert(refundRow)
    // The original's own `remaining_refundable_amount` changed — resync it too rather than leaving
    // a stale cached copy (no follow-up GET needed, the backend already knows the new value; we
    // just haven't fetched it yet).
    await fetchOne(id)
    return refundRow
  }

  async function remove(id: string): Promise<void> {
    await paymentsApi.remove(id)
    cache.delete(id)
  }

  function $reset() {
    cache.clear()
    loadedPatientIds.clear()
    loading.value = false
    error.value = null
  }

  return {
    cache,
    loading,
    error,
    paymentsForPatient,
    paymentsForInvoice,
    fetchForPatient,
    fetchOne,
    record,
    update,
    apply,
    refund,
    remove,
    $reset,
  }
})
