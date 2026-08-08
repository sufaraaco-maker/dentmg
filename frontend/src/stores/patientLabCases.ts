import { defineStore } from 'pinia'
import { reactive, ref } from 'vue'
import { labCasesApi } from '@/services/laboratory'
import type { CreateLabCasePayload, LabCase, LabCaseStatus } from '@/types/laboratory'

interface PatientPageMeta {
  currentPage: number
  lastPage: number
  perPage: number
  total: number
}

/**
 * Patient Profile's Laboratory tab (Phase 2.4, patient-laboratory-redesign-design.md §4.4) — same
 * id-keyed cache + per-patient page-tracking shape as `stores/treatmentPlans.ts`, not a reuse of
 * `stores/labs.ts` (that store stays exactly what it is: the small `Lab` vendor-catalog dropdown
 * source) and not a copy of `LabCasesView.vue`'s "no store" direct-`api`-call pattern (that pattern
 * fits a flat, patient-agnostic clinic-wide list; a patient-scoped tab needs the same
 * cache/reactivity every other Patient Profile tab already has).
 *
 * Mutation actions (`create`/`send`/`receive`/`qualityCheck`/`cancel`) are thin wrappers around the
 * *existing*, unchanged Laboratory API endpoints — this store does not reimplement any lifecycle
 * logic, it just upserts each response into this patient-scoped cache so the tab reflects a status
 * change immediately, mirroring how `treatmentPlansStore.accept()`/`complete()` etc. already work.
 */
export const usePatientLabCasesStore = defineStore('patientLabCases', () => {
  const cache = reactive(new Map<string, LabCase>())
  const patientPageIds = reactive(new Map<string, string[]>())
  const patientPageMeta = reactive(new Map<string, PatientPageMeta>())
  const loadedPatientPage = reactive(new Map<string, number>())
  const loading = ref(false)
  const error = ref<string | null>(null)

  /** Exposed publicly (not just used internally) so `PatientLabCasesPanel.vue` can hand this the
   *  fresh case `LabCaseActionsBar.vue` emits on its own `updated` event — that component already
   *  calls the status-transition endpoints directly (design doc §4.5, reused as-is), so this store
   *  only needs to absorb its result into the patient-scoped cache, not duplicate the request. */
  function upsert(labCase: LabCase) {
    cache.set(labCase.id, labCase)
  }

  /** The most recently fetched page's cases for one patient, most recently created first. */
  function casesForPatient(patientId: string): LabCase[] {
    const ids = patientPageIds.get(patientId) ?? []
    return ids
      .map((id) => cache.get(id))
      .filter((labCase): labCase is LabCase => !!labCase)
      .sort((a, b) => b.created_at.localeCompare(a.created_at))
  }

  /** Pagination metadata for the currently loaded page, for the panel's `Paginator`. */
  function pageMetaForPatient(patientId: string): PatientPageMeta {
    return patientPageMeta.get(patientId) ?? { currentPage: 1, lastPage: 1, perPage: 15, total: 0 }
  }

  async function fetchForPatient(
    patientId: string,
    page = 1,
    status: LabCaseStatus | null = null,
    force = false,
  ): Promise<void> {
    if (loadedPatientPage.get(patientId) === page && !force) return

    loading.value = true
    error.value = null

    try {
      const result = await labCasesApi.list(patientId, page, status)
      result.data.forEach(upsert)
      patientPageIds.set(
        patientId,
        result.data.map((labCase) => labCase.id),
      )
      patientPageMeta.set(patientId, {
        currentPage: result.meta.current_page,
        lastPage: result.meta.last_page,
        perPage: result.meta.per_page,
        total: result.meta.total,
      })
      loadedPatientPage.set(patientId, page)
    } catch {
      error.value = 'laboratory.labCases.loadError'
    } finally {
      loading.value = false
    }
  }

  /** A new case always sorts to the top — refreshes page 1 so it's immediately visible regardless
   *  of which page the patient's list happened to be on. */
  async function create(patientId: string, payload: CreateLabCasePayload): Promise<LabCase> {
    const created = await labCasesApi.create(payload)
    upsert(created)
    await fetchForPatient(patientId, 1, null, true)
    return created
  }

  async function send(id: string): Promise<LabCase> {
    const labCase = await labCasesApi.send(id)
    upsert(labCase)
    return labCase
  }

  async function receive(id: string): Promise<LabCase> {
    const labCase = await labCasesApi.receive(id)
    upsert(labCase)
    return labCase
  }

  async function qualityCheck(id: string): Promise<LabCase> {
    const labCase = await labCasesApi.qualityCheck(id)
    upsert(labCase)
    return labCase
  }

  async function cancel(id: string): Promise<LabCase> {
    const labCase = await labCasesApi.cancel(id)
    upsert(labCase)
    return labCase
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
    casesForPatient,
    pageMetaForPatient,
    fetchForPatient,
    upsert,
    create,
    send,
    receive,
    qualityCheck,
    cancel,
    $reset,
  }
})
