import { defineStore } from 'pinia'
import { reactive, ref } from 'vue'
import { billingSummaryApi } from '@/services/billing'
import type { BillingSummary } from '@/types/billing'

/**
 * Backs the Billing tab's Outstanding Balance hero + summary row (`BillingSummaryCard.vue`,
 * Phase 2.2). One aggregate object per patient, not a list — same `fetchForPatient(patientId,
 * force)` + `xForPatient(patientId)` shape every other store follows (design doc §14.2), just with
 * a plain id-keyed cache instead of pagination bookkeeping, since there's nothing to paginate here.
 */
export const useBillingSummaryStore = defineStore('billingSummary', () => {
  const cache = reactive(new Map<string, BillingSummary>())
  const loadedPatientIds = reactive(new Set<string>())
  const loading = ref(false)
  const error = ref<string | null>(null)

  function summaryForPatient(patientId: string): BillingSummary | null {
    return cache.get(patientId) ?? null
  }

  async function fetchForPatient(patientId: string, force = false): Promise<void> {
    if (loadedPatientIds.has(patientId) && !force) return

    loading.value = true
    error.value = null

    try {
      const summary = await billingSummaryApi.get(patientId)
      cache.set(patientId, summary)
      loadedPatientIds.add(patientId)
    } catch {
      error.value = 'patients.billingPanel.loadError'
    } finally {
      loading.value = false
    }
  }

  function $reset() {
    cache.clear()
    loadedPatientIds.clear()
    loading.value = false
    error.value = null
  }

  return { cache, loading, error, summaryForPatient, fetchForPatient, $reset }
})
