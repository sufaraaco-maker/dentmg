import { api } from '@/lib/api'
import type { PaymentMethod } from '@/types/payment'
import type {
  ArAgingReport,
  AppointmentAnalyticsReport,
  CollectionsReport,
  NewPatientsReport,
  ProductionReport,
  TreatmentPlanAcceptanceReport,
} from '@/types/reports'

export interface DateRangeParams {
  date_from: string
  date_to: string
}

export interface DentistFilterParams extends DateRangeParams {
  dentist_id?: string | null
}

export function getProductionReport(params: DentistFilterParams) {
  return api.get<ProductionReport>('/reports/production', { params }).then((r) => r.data)
}

export function getCollectionsReport(params: DateRangeParams & { method?: PaymentMethod | null }) {
  return api.get<CollectionsReport>('/reports/collections', { params }).then((r) => r.data)
}

export function getArAgingReport() {
  return api.get<ArAgingReport>('/reports/ar-aging').then((r) => r.data)
}

export function getAppointmentAnalyticsReport(params: DentistFilterParams) {
  return api.get<AppointmentAnalyticsReport>('/reports/appointments', { params }).then((r) => r.data)
}

export function getTreatmentPlanAcceptanceReport(params: DentistFilterParams) {
  return api
    .get<TreatmentPlanAcceptanceReport>('/reports/treatment-plan-acceptance', { params })
    .then((r) => r.data)
}

export function getNewPatientsReport(params: DateRangeParams) {
  return api.get<NewPatientsReport>('/reports/new-patients', { params }).then((r) => r.data)
}

/**
 * Streams a report's CSV export (`?format=csv` on the same endpoint) as a blob and triggers a
 * browser download — no new dependency, mirrors this codebase's "browser-native export" precedent
 * (Laboratory's printable slip) applied to CSV (design doc §6/§8 decision 3).
 */
export async function downloadReportCsv(
  path: string,
  params: Record<string, string | null | undefined>,
  filename: string,
): Promise<void> {
  const response = await api.get<Blob>(path, {
    params: { ...params, format: 'csv' },
    responseType: 'blob',
  })

  const url = window.URL.createObjectURL(response.data)
  const link = document.createElement('a')
  link.href = url
  link.download = filename
  document.body.appendChild(link)
  link.click()
  link.remove()
  window.URL.revokeObjectURL(url)
}
