import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { dashboardApi } from '@/services/dashboard'
import { useDashboardStore } from './dashboard'
import type { DashboardFinancialSummary, DashboardSummary } from '@/types/dashboard'

vi.mock('@/services/dashboard', () => ({
  dashboardApi: { getSummary: vi.fn(), getFinancialSummary: vi.fn() },
}))

const mockedApi = vi.mocked(dashboardApi)

function makeSummary(overrides: Partial<DashboardSummary> = {}): DashboardSummary {
  return {
    total_patients: 10,
    today_appointments: 2,
    unscheduled_accepted_treatment: { count: 0, items: [] },
    ...overrides,
  }
}

function makeFinancialSummary(overrides: Partial<DashboardFinancialSummary> = {}): DashboardFinancialSummary {
  return {
    monthly_revenue: '1000.00',
    production_trend: { current: '1200.00', previous: '900.00', change_pct: 33.33 },
    collections_trend: { current: '1000.00', previous: '950.00', change_pct: 5.26 },
    ar_aging: {
      total: '300.00',
      buckets: {
        current: '100.00',
        '1_30': '100.00',
        '31_60': '50.00',
        '61_90': '30.00',
        '90_plus': '20.00',
      },
    },
    ...overrides,
  }
}

beforeEach(() => {
  setActivePinia(createPinia())
  vi.clearAllMocks()
})

describe('useDashboardStore.fetchSummary', () => {
  it('fetches and stores the operational summary', async () => {
    mockedApi.getSummary.mockResolvedValueOnce(makeSummary())
    const store = useDashboardStore()

    await store.fetchSummary()

    expect(store.summary).toEqual(makeSummary())
    expect(store.loading).toBe(false)
    expect(store.error).toBeNull()
  })

  it('sets a translation-key error on failure', async () => {
    mockedApi.getSummary.mockRejectedValueOnce(new Error('network error'))
    const store = useDashboardStore()

    await store.fetchSummary()

    expect(store.error).toBe('dashboard.loadError')
    expect(store.loading).toBe(false)
    expect(store.summary).toBeNull()
  })
})

describe('useDashboardStore.fetchFinancialSummary', () => {
  it('fetches and stores the financial summary independently of the operational one', async () => {
    mockedApi.getSummary.mockResolvedValueOnce(makeSummary())
    mockedApi.getFinancialSummary.mockResolvedValueOnce(makeFinancialSummary())
    const store = useDashboardStore()

    await store.fetchSummary()
    await store.fetchFinancialSummary()

    expect(store.summary).toEqual(makeSummary())
    expect(store.financialSummary).toEqual(makeFinancialSummary())
  })

  it('sets a translation-key error on failure, independent of the operational error', async () => {
    mockedApi.getFinancialSummary.mockRejectedValueOnce(new Error('forbidden'))
    const store = useDashboardStore()

    await store.fetchFinancialSummary()

    expect(store.financialError).toBe('dashboard.financialLoadError')
    expect(store.financialLoading).toBe(false)
    expect(store.error).toBeNull()
  })
})

describe('useDashboardStore.$reset', () => {
  it('clears both summaries and error/loading state', async () => {
    mockedApi.getSummary.mockResolvedValueOnce(makeSummary())
    mockedApi.getFinancialSummary.mockResolvedValueOnce(makeFinancialSummary())
    const store = useDashboardStore()
    await store.fetchSummary()
    await store.fetchFinancialSummary()

    store.$reset()

    expect(store.summary).toBeNull()
    expect(store.financialSummary).toBeNull()
    expect(store.error).toBeNull()
    expect(store.financialError).toBeNull()
  })
})
