import { beforeEach, describe, expect, it, vi } from 'vitest'
import { api } from '@/lib/api'
import {
  downloadReportCsv,
  getAppointmentAnalyticsReport,
  getArAgingReport,
  getCollectionsReport,
  getNewPatientsReport,
  getProductionReport,
  getTreatmentPlanAcceptanceReport,
} from './index'

vi.mock('@/lib/api', () => ({
  api: { get: vi.fn() },
}))

const mockedApi = vi.mocked(api)

beforeEach(() => {
  vi.clearAllMocks()
})

describe('report getters', () => {
  it('production passes date range and dentist filter', async () => {
    mockedApi.get.mockResolvedValue({ data: { summary: { total: '0.00', by_dentist: [] }, rows: [] } })

    await getProductionReport({ date_from: '2026-06-01', date_to: '2026-06-30', dentist_id: 'dentist-1' })

    expect(mockedApi.get).toHaveBeenCalledWith('/reports/production', {
      params: { date_from: '2026-06-01', date_to: '2026-06-30', dentist_id: 'dentist-1' },
    })
  })

  it('collections passes an optional payment method filter', async () => {
    mockedApi.get.mockResolvedValue({ data: { summary: { total: '0.00', by_method: [] }, rows: [] } })

    await getCollectionsReport({ date_from: '2026-06-01', date_to: '2026-06-30', method: 'cash' })

    expect(mockedApi.get).toHaveBeenCalledWith('/reports/collections', {
      params: { date_from: '2026-06-01', date_to: '2026-06-30', method: 'cash' },
    })
  })

  it('ar-aging takes no params — a point-in-time snapshot', async () => {
    mockedApi.get.mockResolvedValue({ data: { summary: { total: '0.00', buckets: {} }, rows: [] } })

    await getArAgingReport()

    expect(mockedApi.get).toHaveBeenCalledWith('/reports/ar-aging')
  })

  it('appointment analytics passes date range and dentist filter', async () => {
    mockedApi.get.mockResolvedValue({
      data: { summary: { total: 0, by_status: [], no_show_rate: 0, cancellation_rate: 0 }, rows: [] },
    })

    await getAppointmentAnalyticsReport({ date_from: '2026-06-01', date_to: '2026-06-30', dentist_id: null })

    expect(mockedApi.get).toHaveBeenCalledWith('/reports/appointments', {
      params: { date_from: '2026-06-01', date_to: '2026-06-30', dentist_id: null },
    })
  })

  it('treatment plan acceptance passes date range and dentist filter', async () => {
    mockedApi.get.mockResolvedValue({
      data: {
        summary: { presented: 0, accepted: 0, rejected: 0, acceptance_rate: 0, accepted_value: '0.00' },
        rows: [],
      },
    })

    await getTreatmentPlanAcceptanceReport({ date_from: '2026-06-01', date_to: '2026-06-30' })

    expect(mockedApi.get).toHaveBeenCalledWith('/reports/treatment-plan-acceptance', {
      params: { date_from: '2026-06-01', date_to: '2026-06-30' },
    })
  })

  it('new patients passes only the date range', async () => {
    mockedApi.get.mockResolvedValue({ data: { summary: { total: 0, by_month: [] }, rows: [] } })

    await getNewPatientsReport({ date_from: '2026-06-01', date_to: '2026-06-30' })

    expect(mockedApi.get).toHaveBeenCalledWith('/reports/new-patients', {
      params: { date_from: '2026-06-01', date_to: '2026-06-30' },
    })
  })
})

describe('downloadReportCsv', () => {
  it('requests the same endpoint with format=csv as a blob and triggers a browser download', async () => {
    const blob = new Blob(['a,b\n1,2'], { type: 'text/csv' })
    mockedApi.get.mockResolvedValue({ data: blob })

    const createObjectURL = vi.fn().mockReturnValue('blob:mock-url')
    const revokeObjectURL = vi.fn()
    vi.stubGlobal('URL', { ...URL, createObjectURL, revokeObjectURL })

    const clickSpy = vi.spyOn(HTMLAnchorElement.prototype, 'click').mockImplementation(() => {})
    const appendSpy = vi.spyOn(document.body, 'appendChild')

    await downloadReportCsv(
      '/reports/collections',
      { date_from: '2026-06-01', date_to: '2026-06-30' },
      'collections.csv',
    )

    expect(mockedApi.get).toHaveBeenCalledWith('/reports/collections', {
      params: { date_from: '2026-06-01', date_to: '2026-06-30', format: 'csv' },
      responseType: 'blob',
    })
    expect(createObjectURL).toHaveBeenCalledWith(blob)
    expect(revokeObjectURL).toHaveBeenCalledWith('blob:mock-url')
    expect(appendSpy).toHaveBeenCalled()
    expect(clickSpy).toHaveBeenCalled()

    appendSpy.mockRestore()
    clickSpy.mockRestore()
    vi.unstubAllGlobals()
  })
})
