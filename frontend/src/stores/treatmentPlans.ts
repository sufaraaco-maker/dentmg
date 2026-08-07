import { defineStore } from 'pinia'
import { reactive, ref } from 'vue'
import { treatmentPlanItemsApi, treatmentPlansApi } from '@/services/treatmentPlans'
import type {
  CreateTreatmentPlanItemPayload,
  CreateTreatmentPlanPayload,
  CreateTreatmentPlanRevisionPayload,
  TreatmentPlan,
  UpdateTreatmentPlanItemPayload,
  UpdateTreatmentPlanPayload,
} from '@/types/treatmentPlan'

interface PatientPageMeta {
  currentPage: number
  lastPage: number
  perPage: number
  total: number
}

/**
 * A single id-keyed cache serving both the Patient Treatment Plans tab's list (`plansForPatient`)
 * and the dedicated Plan Detail route's single-resource fetch (`fetchOne`), design doc §9/§11 —
 * closer to `stores/appointments.ts`'s `cache: Map` than `stores/dentalChartEntries.ts`'s
 * single-active-patient list, because this module (unlike Dental Chart) has a real
 * `GET /treatment-plans/{plan}` detail endpoint reachable independently of any patient's list ever
 * having been fetched (e.g. a direct/bookmarked navigation to Plan Detail, §11).
 *
 * Every mutation below — plan-level *and* item-level — upserts the response directly with no
 * follow-up re-fetch, because the backend deliberately returns the full updated `TreatmentPlan`
 * (items eager-loaded) from every mutation endpoint (design doc §9). This is the one place this
 * store is simpler than `appointments.ts`'s `fetchOne`-after-every-mutation workaround: that
 * pattern exists there only because of a documented backend gap (TECH_DEBT.md) this module's API
 * was deliberately designed not to repeat.
 *
 * Phase 2.1 (design doc §11/§14.4): the patient-scoped list is now paginated server-side. `cache`
 * can hold plans from more than one page (e.g. after visiting page 1 then page 2), so
 * `plansForPatient` can no longer safely derive its result by filtering the *whole* cache — it
 * would silently merge pages together. `patientPageIds` tracks the ordered id list for whichever
 * page was *most recently* fetched for that patient; `plansForPatient` reads only that.
 */
export const useTreatmentPlansStore = defineStore('treatmentPlans', () => {
  const cache = reactive(new Map<string, TreatmentPlan>())
  const patientPageIds = reactive(new Map<string, string[]>())
  const patientPageMeta = reactive(new Map<string, PatientPageMeta>())
  const loadedPatientPage = reactive(new Map<string, number>())
  const loading = ref(false)
  const error = ref<string | null>(null)

  function upsert(plan: TreatmentPlan) {
    cache.set(plan.id, plan)
  }

  /** The most recently fetched page's plans for one patient, most recently created first. */
  function plansForPatient(patientId: string): TreatmentPlan[] {
    const ids = patientPageIds.get(patientId) ?? []
    return ids
      .map((id) => cache.get(id))
      .filter((plan): plan is TreatmentPlan => !!plan)
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
      const result = await treatmentPlansApi.list(patientId, page)
      result.data.forEach(upsert)
      patientPageIds.set(
        patientId,
        result.data.map((plan) => plan.id),
      )
      patientPageMeta.set(patientId, {
        currentPage: result.meta.current_page,
        lastPage: result.meta.last_page,
        perPage: result.meta.per_page,
        total: result.meta.total,
      })
      loadedPatientPage.set(patientId, page)
    } catch {
      error.value = 'treatmentPlans.loadError'
    } finally {
      loading.value = false
    }
  }

  async function fetchOne(id: string): Promise<TreatmentPlan> {
    const plan = await treatmentPlansApi.get(id)
    upsert(plan)
    return plan
  }

  /** A new plan always sorts to the top (most recently created) — refreshes page 1 so it's
   *  immediately visible regardless of which page the patient's list happened to be on. */
  async function create(patientId: string, payload: CreateTreatmentPlanPayload): Promise<TreatmentPlan> {
    const created = await treatmentPlansApi.create(patientId, payload)
    upsert(created)
    await fetchForPatient(patientId, 1, true)
    return created
  }

  async function update(id: string, payload: UpdateTreatmentPlanPayload): Promise<TreatmentPlan> {
    const updated = await treatmentPlansApi.update(id, payload)
    upsert(updated)
    return updated
  }

  async function present(id: string): Promise<TreatmentPlan> {
    const plan = await treatmentPlansApi.present(id)
    upsert(plan)
    return plan
  }

  /** Auto-rejects sibling `presented` plans server-side (§5/§15 Q3) — refreshes the currently
   *  loaded page of this patient's list so those siblings' now-stale cached statuses are
   *  corrected too, not just this plan. */
  async function accept(id: string): Promise<TreatmentPlan> {
    const plan = await treatmentPlansApi.accept(id)
    upsert(plan)
    await fetchForPatient(plan.patient_id, loadedPatientPage.get(plan.patient_id) ?? 1, true)
    return plan
  }

  async function reject(id: string): Promise<TreatmentPlan> {
    const plan = await treatmentPlansApi.reject(id)
    upsert(plan)
    return plan
  }

  async function start(id: string): Promise<TreatmentPlan> {
    const plan = await treatmentPlansApi.start(id)
    upsert(plan)
    return plan
  }

  async function complete(id: string): Promise<TreatmentPlan> {
    const plan = await treatmentPlansApi.complete(id)
    upsert(plan)
    return plan
  }

  /** Cascades to cancel every still-`planned` item server-side (§5/§8) — already reflected in the
   *  returned plan's own `items`, no extra fetch needed. */
  async function cancel(id: string): Promise<TreatmentPlan> {
    const plan = await treatmentPlansApi.cancel(id)
    upsert(plan)
    return plan
  }

  /** Creates a superseding revision (§15 Q4) and refreshes the original plan so its now-set
   *  `superseded_by_plan_id` (the "Replaced by Plan #N" link, §11) is reflected in the cache too. */
  async function createRevision(
    id: string,
    payload: CreateTreatmentPlanRevisionPayload = {},
  ): Promise<TreatmentPlan> {
    const revision = await treatmentPlansApi.createRevision(id, payload)
    upsert(revision)
    await fetchOne(id)
    return revision
  }

  async function remove(id: string): Promise<void> {
    await treatmentPlansApi.remove(id)
    cache.delete(id)
  }

  async function addItem(planId: string, payload: CreateTreatmentPlanItemPayload): Promise<TreatmentPlan> {
    const plan = await treatmentPlanItemsApi.create(planId, payload)
    upsert(plan)
    return plan
  }

  async function updateItem(itemId: string, payload: UpdateTreatmentPlanItemPayload): Promise<TreatmentPlan> {
    const plan = await treatmentPlanItemsApi.update(itemId, payload)
    upsert(plan)
    return plan
  }

  async function completeItem(itemId: string): Promise<TreatmentPlan> {
    const plan = await treatmentPlanItemsApi.complete(itemId)
    upsert(plan)
    return plan
  }

  async function cancelItem(itemId: string): Promise<TreatmentPlan> {
    const plan = await treatmentPlanItemsApi.cancel(itemId)
    upsert(plan)
    return plan
  }

  /** Item delete returns `204` (no plan payload, unlike every other item mutation — design doc
   *  §9), so the parent plan is explicitly re-fetched to pick up the updated `items`/`estimated_cost`. */
  async function removeItem(itemId: string, planId: string): Promise<void> {
    await treatmentPlanItemsApi.remove(itemId)
    await fetchOne(planId)
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
    plansForPatient,
    pageMetaForPatient,
    fetchForPatient,
    fetchOne,
    create,
    update,
    present,
    accept,
    reject,
    start,
    complete,
    cancel,
    createRevision,
    remove,
    addItem,
    updateItem,
    completeItem,
    cancelItem,
    removeItem,
    $reset,
  }
})
