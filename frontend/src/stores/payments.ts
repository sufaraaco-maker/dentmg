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

interface PatientPageMeta {
  currentPage: number
  lastPage: number
  perPage: number
  total: number
}

/**
 * A single id-keyed cache serving the Patient Payments tab's list and the per-invoice Payments
 * panel — mirrors `stores/invoices.ts`'s paginated shape (Phase 2.2, TECH_DEBT.md's now-resolved
 * entry).
 *
 * A patient's payments and one invoice's payments are different pagination axes, so they get
 * separate page-tracking Maps (`patientPageIds`/... vs `invoicePageIds`/...) rather than sharing
 * one — folding them together would let one axis's "most recently fetched page" silently stomp
 * the other's. Both slices share the single `cache` Map, though: a `Payment` row is a `Payment`
 * row regardless of which list fetched it, same as `fetchOne` already relies on.
 */
export const usePaymentsStore = defineStore('payments', () => {
  const cache = reactive(new Map<string, Payment>())
  const patientPageIds = reactive(new Map<string, string[]>())
  const patientPageMeta = reactive(new Map<string, PatientPageMeta>())
  const loadedPatientPage = reactive(new Map<string, number>())
  const invoicePageIds = reactive(new Map<string, string[]>())
  const invoicePageMeta = reactive(new Map<string, PatientPageMeta>())
  const loadedInvoicePage = reactive(new Map<string, number>())
  const loading = ref(false)
  const error = ref<string | null>(null)

  function upsert(payment: Payment) {
    cache.set(payment.id, payment)
  }

  /** The most recently fetched page's payments for one patient, most recently received first. */
  function paymentsForPatient(patientId: string): Payment[] {
    const ids = patientPageIds.get(patientId) ?? []
    return ids
      .map((id) => cache.get(id))
      .filter((payment): payment is Payment => !!payment)
      .sort((a, b) => b.received_at.localeCompare(a.received_at) || b.created_at.localeCompare(a.created_at))
  }

  /** Pagination metadata for the currently loaded patient page, for the panel's `Paginator`. */
  function pageMetaForPatient(patientId: string): PatientPageMeta {
    return patientPageMeta.get(patientId) ?? { currentPage: 1, lastPage: 1, perPage: 15, total: 0 }
  }

  async function fetchForPatient(patientId: string, page = 1, force = false): Promise<void> {
    if (loadedPatientPage.get(patientId) === page && !force) return

    loading.value = true
    error.value = null

    try {
      const result = await paymentsApi.list(patientId, page)
      result.data.forEach(upsert)
      patientPageIds.set(
        patientId,
        result.data.map((payment) => payment.id),
      )
      patientPageMeta.set(patientId, {
        currentPage: result.meta.current_page,
        lastPage: result.meta.last_page,
        perPage: result.meta.per_page,
        total: result.meta.total,
      })
      loadedPatientPage.set(patientId, page)
    } catch {
      error.value = 'payments.loadError'
    } finally {
      loading.value = false
    }
  }

  /** The most recently fetched page's payments for one invoice — backs the Invoice Detail
   *  Payments panel (TECH_DEBT.md, Phase 2.2: replaces the former client-side filter of the full
   *  patient-level payment list). */
  function paymentsForInvoice(invoiceId: string): Payment[] {
    const ids = invoicePageIds.get(invoiceId) ?? []
    return ids
      .map((id) => cache.get(id))
      .filter((payment): payment is Payment => !!payment)
      .sort((a, b) => b.received_at.localeCompare(a.received_at) || b.created_at.localeCompare(a.created_at))
  }

  /** Pagination metadata for the currently loaded invoice page, for the panel's `Paginator`. */
  function pageMetaForInvoice(invoiceId: string): PatientPageMeta {
    return invoicePageMeta.get(invoiceId) ?? { currentPage: 1, lastPage: 1, perPage: 15, total: 0 }
  }

  async function fetchForInvoice(invoiceId: string, page = 1, force = false): Promise<void> {
    if (loadedInvoicePage.get(invoiceId) === page && !force) return

    loading.value = true
    error.value = null

    try {
      const result = await paymentsApi.listForInvoice(invoiceId, page)
      result.data.forEach(upsert)
      invoicePageIds.set(
        invoiceId,
        result.data.map((payment) => payment.id),
      )
      invoicePageMeta.set(invoiceId, {
        currentPage: result.meta.current_page,
        lastPage: result.meta.last_page,
        perPage: result.meta.per_page,
        total: result.meta.total,
      })
      loadedInvoicePage.set(invoiceId, page)
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

  /** A new payment always sorts to the top — refreshes page 1 of the patient's list so it's
   *  immediately visible regardless of which page was last loaded (mirrors
   *  `invoicesStore.create()`'s identical precedent). Also refreshes the target invoice's own
   *  page 1 when the payment was recorded directly against one, so `InvoicePaymentsPanel.vue`
   *  (driven by `paymentsForInvoice`, a separate pagination axis from the patient list) reflects
   *  it immediately too, not just after its own next fresh mount. */
  async function record(patientId: string, payload: RecordPaymentPayload): Promise<Payment> {
    const payment = await paymentsApi.record(patientId, payload)
    upsert(payment)
    await fetchForPatient(patientId, 1, true)
    if (payload.invoice_id) await fetchForInvoice(payload.invoice_id, 1, true)
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
    // just haven't fetched it yet). Also refresh page 1 of the patient's list, and of the original
    // payment's invoice (if any — a refund row inherits its `invoice_id`), so the new refund row is
    // spliced into both views immediately, same reasoning as `record()` above.
    const original = await fetchOne(id)
    await fetchForPatient(original.patient_id, 1, true)
    if (original.invoice_id) await fetchForInvoice(original.invoice_id, 1, true)
    return refundRow
  }

  async function remove(id: string): Promise<void> {
    await paymentsApi.remove(id)
    cache.delete(id)
  }

  function $reset() {
    cache.clear()
    patientPageIds.clear()
    patientPageMeta.clear()
    loadedPatientPage.clear()
    invoicePageIds.clear()
    invoicePageMeta.clear()
    loadedInvoicePage.clear()
    loading.value = false
    error.value = null
  }

  return {
    cache,
    loading,
    error,
    paymentsForPatient,
    pageMetaForPatient,
    paymentsForInvoice,
    pageMetaForInvoice,
    fetchForPatient,
    fetchForInvoice,
    fetchOne,
    record,
    update,
    apply,
    refund,
    remove,
    $reset,
  }
})
