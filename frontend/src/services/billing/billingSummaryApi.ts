import { api } from '@/lib/api'
import type { BillingSummary } from '@/types/billing'

export const billingSummaryApi = {
  async get(patientId: string): Promise<BillingSummary> {
    const { data } = await api.get<BillingSummary>(`/patients/${patientId}/billing-summary`)
    return data
  },
}
