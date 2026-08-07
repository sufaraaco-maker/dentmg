import { defineStore } from 'pinia'
import { reactive, ref } from 'vue'
import { patientsApi } from '@/services/patients/patientsApi'
import type { CreatePatientPayload, Patient, PatientAuditLog, UpdatePatientPayload } from '@/types/patient'

/**
 * Patient-level state only (Phase 2.1 design review decision) — this store owns the Patients
 * list, a single-patient cache, and admin audit-log history. It is deliberately not a
 * replacement for the domain stores (`treatmentPlans.ts`, `invoices.ts`, `clinicalNotes.ts`,
 * etc.) that already own their own patient-scoped data via `fetchForPatient` — this store closes
 * the one architectural gap Patients itself had (every sibling module already has a Pinia store;
 * `PatientsView.vue`/`PatientDetailView.vue` called `@/lib/api` inline instead).
 */
export const usePatientsStore = defineStore('patients', () => {
  const cache = reactive(new Map<string, Patient>())
  const auditLogs = reactive(new Map<string, PatientAuditLog[]>())

  const listItems = ref<Patient[]>([])
  const listMeta = reactive({ currentPage: 1, lastPage: 1, perPage: 15, total: 0 })
  const listLoading = ref(false)
  const listError = ref<string | null>(null)

  const auditLoading = ref(false)

  function upsert(patient: Patient) {
    cache.set(patient.id, patient)
  }

  /** Backs the Patients list view's server-paginated, debounced-search `DataTable`. */
  async function fetchList(params: { search?: string; page?: number } = {}): Promise<void> {
    listLoading.value = true
    listError.value = null

    try {
      const result = await patientsApi.list(params)
      listItems.value = result.data
      listMeta.currentPage = result.meta.current_page
      listMeta.lastPage = result.meta.last_page
      listMeta.perPage = result.meta.per_page
      listMeta.total = result.meta.total
      result.data.forEach(upsert)
    } catch {
      listError.value = 'patients.loadError'
    } finally {
      listLoading.value = false
    }
  }

  /** Always fetches fresh (no cache-first check) — matches every sibling store's `fetchOne`
   *  convention (`treatmentPlans.ts`/`invoices.ts`/etc.), since a direct/bookmarked navigation to
   *  a patient's detail route should never show stale data. */
  async function fetchOne(id: string): Promise<Patient> {
    const patient = await patientsApi.get(id)
    upsert(patient)
    return patient
  }

  async function create(payload: CreatePatientPayload): Promise<Patient> {
    const patient = await patientsApi.create(payload)
    upsert(patient)
    return patient
  }

  async function update(id: string, payload: UpdatePatientPayload): Promise<Patient> {
    const patient = await patientsApi.update(id, payload)
    upsert(patient)
    return patient
  }

  async function remove(id: string): Promise<void> {
    await patientsApi.remove(id)
    cache.delete(id)
    auditLogs.delete(id)
  }

  /** Admin-only (server-enforced via `PatientPolicy::viewAuditLogs`) — callers should still gate
   *  the call itself on `auth.isAdmin` first, matching `PatientDetailView.vue`'s existing check,
   *  so a non-admin never issues the request at all (defense in depth, not the only gate). */
  async function fetchAuditLogs(id: string): Promise<void> {
    auditLoading.value = true
    try {
      auditLogs.set(id, await patientsApi.auditLogs(id))
    } finally {
      auditLoading.value = false
    }
  }

  function auditLogsForPatient(id: string): PatientAuditLog[] {
    return auditLogs.get(id) ?? []
  }

  function $reset() {
    cache.clear()
    auditLogs.clear()
    listItems.value = []
    listMeta.currentPage = 1
    listMeta.lastPage = 1
    listMeta.perPage = 15
    listMeta.total = 0
    listLoading.value = false
    listError.value = null
    auditLoading.value = false
  }

  return {
    cache,
    listItems,
    listMeta,
    listLoading,
    listError,
    auditLoading,
    fetchList,
    fetchOne,
    create,
    update,
    remove,
    fetchAuditLogs,
    auditLogsForPatient,
    $reset,
  }
})
