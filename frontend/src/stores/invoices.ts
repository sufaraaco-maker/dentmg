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

interface PatientPageMeta {
  currentPage: number
  lastPage: number
  perPage: number
  total: number
}

/**
 * A single id-keyed cache serving both the Patient Invoices tab's list (`invoicesForPatient`) and
 * the dedicated Invoice Detail route's single-resource fetch (`fetchOne`) — mirrors
 * `stores/treatmentPlans.ts` exactly, including its central premise: every mutation below —
 * invoice-level *and* item-level, add/edit/remove alike — upserts the response directly with no
 * follow-up re-fetch, because the backend deliberately returns the full updated `Invoice` (items
 * eager-loaded) from every mutation endpoint, including item removal (backend design doc §9, a
 * deliberate divergence from Treatment Plan Items' `204`).
 *
 * Phase 2.2 (TECH_DEBT.md's now-resolved entry): the patient-scoped list is now paginated
 * server-side, same `patientPageIds`/`patientPageMeta`/`loadedPatientPage` shape as
 * `treatmentPlans.ts` — `cache` can hold invoices from more than one page, so
 * `invoicesForPatient` reads only the most-recently-fetched page's id list, not the whole cache.
 */
export const useInvoicesStore = defineStore('invoices', () => {
  const cache = reactive(new Map<string, Invoice>())
  const patientPageIds = reactive(new Map<string, string[]>())
  const patientPageMeta = reactive(new Map<string, PatientPageMeta>())
  const loadedPatientPage = reactive(new Map<string, number>())
  const loading = ref(false)
  const error = ref<string | null>(null)

  function upsert(invoice: Invoice) {
    cache.set(invoice.id, invoice)
  }

  /** The most recently fetched page's invoices for one patient, most recently created first. */
  function invoicesForPatient(patientId: string): Invoice[] {
    const ids = patientPageIds.get(patientId) ?? []
    return ids
      .map((id) => cache.get(id))
      .filter((invoice): invoice is Invoice => !!invoice)
      .sort((a, b) => b.created_at.localeCompare(a.created_at))
  }

  /** Pagination metadata for the currently loaded page, for the panel's `Paginator`. */
  function pageMetaForPatient(patientId: string): PatientPageMeta {
    return patientPageMeta.get(patientId) ?? { currentPage: 1, lastPage: 1, perPage: 15, total: 0 }
  }

  async function fetchForPatient(patientId: string, page = 1, force = false): Promise<void> {
    if (loadedPatientPage.get(patientId) === page && !force) return

    loading.value = true
    error.value = null

    try {
      const result = await invoicesApi.list(patientId, page)
      result.data.forEach(upsert)
      patientPageIds.set(
        patientId,
        result.data.map((invoice) => invoice.id),
      )
      patientPageMeta.set(patientId, {
        currentPage: result.meta.current_page,
        lastPage: result.meta.last_page,
        perPage: result.meta.per_page,
        total: result.meta.total,
      })
      loadedPatientPage.set(patientId, page)
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

  /** A new invoice always sorts to the top (most recently created) — refreshes page 1 so it's
   *  immediately visible regardless of which page the patient's list happened to be on. */
  async function create(patientId: string, payload: CreateInvoicePayload = {}): Promise<Invoice> {
    const created = await invoicesApi.create(patientId, payload)
    upsert(created)
    await fetchForPatient(patientId, 1, true)
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
    patientPageIds.clear()
    patientPageMeta.clear()
    loadedPatientPage.clear()
    loading.value = false
    error.value = null
  }

  return {
    cache,
    loading,
    error,
    invoicesForPatient,
    pageMetaForPatient,
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
