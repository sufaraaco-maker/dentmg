import { defineStore } from 'pinia'
import { ref } from 'vue'
import { dashboardApi } from '@/services/dashboard'
import type { DashboardFinancialSummary, DashboardSummary } from '@/types/dashboard'

/**
 * Backs `DashboardView.vue` (Dashboard 2.0). Two independent fetches, not one, matching the
 * backend's operational/financial split (design doc §1): `fetchFinancialSummary` is only ever
 * called `if (auth.canViewFinancials)` by the view — skipping the network call entirely for a role
 * that would get a `403`, not just hiding the result.
 */
export const useDashboardStore = defineStore('dashboard', () => {
  const summary = ref<DashboardSummary | null>(null)
  const loading = ref(false)
  const error = ref<string | null>(null)

  const financialSummary = ref<DashboardFinancialSummary | null>(null)
  const financialLoading = ref(false)
  const financialError = ref<string | null>(null)

  async function fetchSummary(): Promise<void> {
    loading.value = true
    error.value = null

    try {
      summary.value = await dashboardApi.getSummary()
    } catch {
      error.value = 'dashboard.loadError'
    } finally {
      loading.value = false
    }
  }

  async function fetchFinancialSummary(): Promise<void> {
    financialLoading.value = true
    financialError.value = null

    try {
      financialSummary.value = await dashboardApi.getFinancialSummary()
    } catch {
      financialError.value = 'dashboard.financialLoadError'
    } finally {
      financialLoading.value = false
    }
  }

  function $reset() {
    summary.value = null
    loading.value = false
    error.value = null
    financialSummary.value = null
    financialLoading.value = false
    financialError.value = null
  }

  return {
    summary,
    loading,
    error,
    financialSummary,
    financialLoading,
    financialError,
    fetchSummary,
    fetchFinancialSummary,
    $reset,
  }
})
