import { defineStore } from 'pinia'
import { reactive, ref } from 'vue'
import { invoiceItemsApi, invoicesApi } from '@/services/invoices'
import type {
  CreateInvoiceItemPayload,
  CreateInvoicePayload,
  Invoice,
  UpdateInvoiceItemPayload,
  UpdateInvoicePayload,
} from '@/types/invoice'

/**
 * A single id-keyed cache serving both the Patient Invoices tab's list (`invoicesForPatient`) and
 * the dedicated Invoice Detail route's single-resource fetch (`fetchOne`) — mirrors
 * `stores/treatmentPlans.ts` exactly, including its central premise: every mutation below —
 * invoice-level *and* item-level, add/edit/remove alike — upserts the response directly with no
 * follow-up re-fetch, because the backend deliberately returns the full updated `Invoice` (items
 * eager-loaded) from every mutation endpoint, including item removal (backend design doc §9, a
 * deliberate divergence from Treatment Plan Items' `204`).
 */
export const useInvoicesStore = defineStore('invoices', () => {
  const cache = reactive(new Map<string, Invoice>())
  const loadedPatientIds = reactive(new Set<string>())
  const loading = ref(false)
  const error = ref<string | null>(null)

  function upsert(invoice: Invoice) {
    cache.set(invoice.id, invoice)
  }

  /** All cached invoices for one patient, most recently created first — backs the Invoice List tab. */
  function invoicesForPatient(patientId: string): Invoice[] {
    return Array.from(cache.values())
      .filter((invoice) => invoice.patient_id === patientId)
      .sort((a, b) => b.created_at.localeCompare(a.created_at))
  }

  async function fetchForPatient(patientId: string, force = false): Promise<void> {
    if (loadedPatientIds.has(patientId) && !force) return

    loading.value = true
    error.value = null

    try {
      const invoices = await invoicesApi.list(patientId)
      invoices.forEach(upsert)
      loadedPatientIds.add(patientId)
    } catch {
      error.value = 'invoices.loadError'
    } finally {
      loading.value = false
    }
  }

  async function fetchOne(id: string): Promise<Invoice> {
    const invoice = await invoicesApi.get(id)
    upsert(invoice)
    return invoice
  }

  async function create(patientId: string, payload: CreateInvoicePayload = {}): Promise<Invoice> {
    const created = await invoicesApi.create(patientId, payload)
    upsert(created)
    return created
  }

  async function update(id: string, payload: UpdateInvoicePayload): Promise<Invoice> {
    const updated = await invoicesApi.update(id, payload)
    upsert(updated)
    return updated
  }

  async function issue(id: string): Promise<Invoice> {
    const invoice = await invoicesApi.issue(id)
    upsert(invoice)
    return invoice
  }

  async function voidInvoice(id: string): Promise<Invoice> {
    const invoice = await invoicesApi.void(id)
    upsert(invoice)
    return invoice
  }

  async function remove(id: string): Promise<void> {
    await invoicesApi.remove(id)
    cache.delete(id)
  }

  async function addItem(invoiceId: string, payload: CreateInvoiceItemPayload): Promise<Invoice> {
    const invoice = await invoiceItemsApi.create(invoiceId, payload)
    upsert(invoice)
    return invoice
  }

  async function updateItem(itemId: string, payload: UpdateInvoiceItemPayload): Promise<Invoice> {
    const invoice = await invoiceItemsApi.update(itemId, payload)
    upsert(invoice)
    return invoice
  }

  async function removeItem(itemId: string): Promise<Invoice> {
    const invoice = await invoiceItemsApi.remove(itemId)
    upsert(invoice)
    return invoice
  }

  /** Not cached — always fresh (backend design doc §7: "already invoiced" is a live derived
   *  query), so a picker opened twice in the same session never offers an item invoiced in
   *  between. Routed through the store anyway, matching every other API access in this module. */
  async function billableTreatmentPlanItems(patientId: string) {
    return invoicesApi.billableTreatmentPlanItems(patientId)
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
    invoicesForPatient,
    fetchForPatient,
    fetchOne,
    create,
    update,
    issue,
    voidInvoice,
    remove,
    addItem,
    updateItem,
    removeItem,
    billableTreatmentPlanItems,
    $reset,
  }
})
