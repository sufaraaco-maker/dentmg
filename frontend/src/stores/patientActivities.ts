import { defineStore } from 'pinia'
import { reactive } from 'vue'
import { patientActivitiesApi } from '@/services/patientActivities'
import type { PatientActivity, PatientActivityCategory } from '@/types/patientActivity'

interface QueryPageMeta {
  currentPage: number
  lastPage: number
  perPage: number
  total: number
}

/**
 * Unlike every other patient-scoped store (`patientLabCases.ts`/`patientDocuments.ts`), which cache
 * one page per patient because only one panel instance reads them at a time, `ActivityTimeline.vue`
 * is mounted up to three times simultaneously on the same patient (Overview preview, Billing's
 * Payment History embed, and the Timeline tab itself — `PatientDetailView.vue`'s `Tabs` isn't
 * `lazy`, so PrimeVue's `TabPanel` keeps every tab's content mounted via `v-show`, not `v-if`),
 * each wanting a different category/page-size slice. Caching keyed by patient id alone would let
 * one instance's fetch silently overwrite another's. Keying by patientId+category+perPage isolates
 * each call site's own accumulated pages.
 */
function queryKey(patientId: string, category: PatientActivityCategory | null, perPage: number): string {
  return `${patientId}::${category ?? 'all'}::${perPage}`
}

export const usePatientActivitiesStore = defineStore('patientActivities', () => {
  const cache = reactive(new Map<string, PatientActivity>())
  const queryIds = reactive(new Map<string, string[]>())
  const queryMeta = reactive(new Map<string, QueryPageMeta>())
  const queryLoading = reactive(new Map<string, boolean>())
  const queryError = reactive(new Map<string, string | null>())

  function activitiesForQuery(
    patientId: string,
    category: PatientActivityCategory | null,
    perPage: number,
  ): PatientActivity[] {
    const ids = queryIds.get(queryKey(patientId, category, perPage)) ?? []
    return ids.map((id) => cache.get(id)).filter((activity): activity is PatientActivity => !!activity)
  }

  function pageMetaForQuery(
    patientId: string,
    category: PatientActivityCategory | null,
    perPage: number,
  ): QueryPageMeta {
    return (
      queryMeta.get(queryKey(patientId, category, perPage)) ?? {
        currentPage: 0,
        lastPage: 1,
        perPage,
        total: 0,
      }
    )
  }

  function isLoadingQuery(
    patientId: string,
    category: PatientActivityCategory | null,
    perPage: number,
  ): boolean {
    return queryLoading.get(queryKey(patientId, category, perPage)) ?? false
  }

  function errorForQuery(
    patientId: string,
    category: PatientActivityCategory | null,
    perPage: number,
  ): string | null {
    return queryError.get(queryKey(patientId, category, perPage)) ?? null
  }

  /**
   * Appends the next page's rows to this query's accumulated list — "Load more" (design doc §13),
   * not `Paginator`-style page-jumping, so unlike `patientDocuments.ts`'s `fetchForPatient` this
   * grows `queryIds` rather than replacing it. A no-op once `lastPage` is reached.
   */
  async function fetchNextPage(
    patientId: string,
    category: PatientActivityCategory | null = null,
    perPage = 15,
  ): Promise<void> {
    const key = queryKey(patientId, category, perPage)
    const meta = queryMeta.get(key)
    const nextPage = (meta?.currentPage ?? 0) + 1
    if (meta && nextPage > meta.lastPage) return

    queryLoading.set(key, true)
    queryError.set(key, null)

    try {
      const result = await patientActivitiesApi.list(patientId, nextPage, category, perPage)
      result.data.forEach((activity) => cache.set(activity.id, activity))
      queryIds.set(key, [...(queryIds.get(key) ?? []), ...result.data.map((activity) => activity.id)])
      queryMeta.set(key, {
        currentPage: result.meta.current_page,
        lastPage: result.meta.last_page,
        perPage: result.meta.per_page,
        total: result.meta.total,
      })
    } catch {
      queryError.set(key, 'patients.timelinePanel.loadError')
    } finally {
      queryLoading.set(key, false)
    }
  }

  function $reset() {
    cache.clear()
    queryIds.clear()
    queryMeta.clear()
    queryLoading.clear()
    queryError.clear()
  }

  return {
    activitiesForQuery,
    pageMetaForQuery,
    isLoadingQuery,
    errorForQuery,
    fetchNextPage,
    $reset,
  }
})
