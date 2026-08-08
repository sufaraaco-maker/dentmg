import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { billingSummaryApi } from '@/services/billing'
import { useBillingSummaryStore } from './billingSummary'
import type { BillingSummary } from '@/types/billing'

vi.mock('@/services/billing', () => ({
  billingSummaryApi: { get: vi.fn() },
}))

const mockedApi = vi.mocked(billingSummaryApi)

function makeSummary(overrides: Partial<BillingSummary> = {}): BillingSummary {
  return {
    total_invoiced: '100.00',
    total_paid: '100.00',
    invoice_count: 1,
    last_payment_date: '2026-08-01',
    outstanding_balance: '0.00',
    status: 'paid',
    currency_code: 'USD',
    ...overrides,
  }
}

beforeEach(() => {
  setActivePinia(createPinia())
  vi.clearAllMocks()
})

describe('useBillingSummaryStore.fetchForPatient', () => {
  it('fetches and caches a patient’s billing summary', async () => {
    mockedApi.get.mockResolvedValueOnce(makeSummary())
    const store = useBillingSummaryStore()

    await store.fetchForPatient('patient-1')

    expect(store.summaryForPatient('patient-1')).toEqual(makeSummary())
    expect(mockedApi.get).toHaveBeenCalledWith('patient-1')
  })

  it('skips the network call on a second fetch for the same patient', async () => {
    mockedApi.get.mockResolvedValue(makeSummary())
    const store = useBillingSummaryStore()

    await store.fetchForPatient('patient-1')
    await store.fetchForPatient('patient-1')

    expect(mockedApi.get).toHaveBeenCalledTimes(1)
  })

  it('refetches when force is true', async () => {
    mockedApi.get.mockResolvedValue(makeSummary())
    const store = useBillingSummaryStore()

    await store.fetchForPatient('patient-1')
    await store.fetchForPatient('patient-1', true)

    expect(mockedApi.get).toHaveBeenCalledTimes(2)
  })

  it('sets a translation-key error on failure', async () => {
    mockedApi.get.mockRejectedValueOnce(new Error('network error'))
    const store = useBillingSummaryStore()

    await store.fetchForPatient('patient-1')

    expect(store.error).toBe('patients.billingPanel.loadError')
    expect(store.loading).toBe(false)
  })

  it('summaryForPatient returns null before any fetch', () => {
    const store = useBillingSummaryStore()

    expect(store.summaryForPatient('patient-1')).toBeNull()
  })
})

describe('useBillingSummaryStore.$reset', () => {
  it('clears the cache and loaded-patient tracking', async () => {
    mockedApi.get.mockResolvedValueOnce(makeSummary())
    const store = useBillingSummaryStore()
    await store.fetchForPatient('patient-1')

    store.$reset()

    expect(store.summaryForPatient('patient-1')).toBeNull()
  })
})
