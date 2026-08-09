import { api } from '@/lib/api'
import type { DashboardFinancialSummary, DashboardSummary } from '@/types/dashboard'

export const dashboardApi = {
  async getSummary(): Promise<DashboardSummary> {
    const { data } = await api.get<DashboardSummary>('/dashboard/summary')
    return data
  },

  async getFinancialSummary(): Promise<DashboardFinancialSummary> {
    const { data } = await api.get<DashboardFinancialSummary>('/dashboard/financial-summary')
    return data
  },
}
