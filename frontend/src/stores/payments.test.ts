import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { paymentsApi } from '@/services/payments'
import { usePaymentsStore } from './payments'
import type { Payment } from '@/types/payment'

vi.mock('@/services/payments', () => ({
  paymentsApi: {
    list: vi.fn(),
    record: vi.fn(),
    get: vi.fn(),
    update: vi.fn(),
    apply: vi.fn(),
    refund: vi.fn(),
    remove: vi.fn(),
  },
}))

const mockedApi = vi.mocked(paymentsApi)

function makePayment(overrides: Partial<Payment> = {}): Payment {
  return {
    id: overrides.id ?? 'payment-1',
    patient_id: 'patient-1',
    invoice_id: null,
    refunded_payment_id: null,
    is_refund: false,
    remaining_refundable_amount: '100.00',
    method: 'cash',
    amount: '100.00',
    currency_code: 'USD',
    reference: null,
    notes: null,
    received_at: '2026-07-20',
    created_at: '2026-07-20T09:00:00+00:00',
    updated_at: '2026-07-20T09:00:00+00:00',
    ...overrides,
  }
}

beforeEach(() => {
  setActivePinia(createPinia())
  vi.clearAllMocks()
})

describe('usePaymentsStore.fetchForPatient', () => {
  it('fetches and caches a patient’s payments', async () => {
    mockedApi.list.mockResolvedValueOnce([makePayment()])
    const store = usePaymentsStore()

    await store.fetchForPatient('patient-1')

    expect(store.paymentsForPatient('patient-1')).toHaveLength(1)
    expect(mockedApi.list).toHaveBeenCalledTimes(1)
  })

  it('skips the network call on a second fetch for the same patient', async () => {
    mockedApi.list.mockResolvedValue([makePayment()])
    const store = usePaymentsStore()

    await store.fetchForPatient('patient-1')
    await store.fetchForPatient('patient-1')

    expect(mockedApi.list).toHaveBeenCalledTimes(1)
  })

  it('refetches when force is true', async () => {
    mockedApi.list.mockResolvedValue([makePayment()])
    const store = usePaymentsStore()

    await store.fetchForPatient('patient-1')
    await store.fetchForPatient('patient-1', true)

    expect(mockedApi.list).toHaveBeenCalledTimes(2)
  })

  it('sets a translation-key error on failure', async () => {
    mockedApi.list.mockRejectedValueOnce(new Error('network error'))
    const store = usePaymentsStore()

    await store.fetchForPatient('patient-1')

    expect(store.error).toBe('payments.loadError')
    expect(store.loading).toBe(false)
  })
})

describe('usePaymentsStore.paymentsForPatient / paymentsForInvoice', () => {
  it('paymentsForPatient only returns that patient’s payments, most recently received first', async () => {
    mockedApi.list.mockImplementation(async (patientId: string) =>
      patientId === 'patient-1'
        ? [
            makePayment({ id: 'payment-1', received_at: '2026-07-01' }),
            makePayment({ id: 'payment-2', received_at: '2026-07-20' }),
          ]
        : [makePayment({ id: 'payment-3', patient_id: 'patient-2' })],
    )
    const store = usePaymentsStore()

    await store.fetchForPatient('patient-1')
    await store.fetchForPatient('patient-2')

    expect(store.paymentsForPatient('patient-1').map((p) => p.id)).toEqual(['payment-2', 'payment-1'])
  })

  it('paymentsForInvoice filters by invoice_id only', async () => {
    mockedApi.list.mockResolvedValueOnce([
      makePayment({ id: 'payment-1', invoice_id: 'invoice-1' }),
      makePayment({ id: 'payment-2', invoice_id: null }),
    ])
    const store = usePaymentsStore()

    await store.fetchForPatient('patient-1')

    expect(store.paymentsForInvoice('invoice-1').map((p) => p.id)).toEqual(['payment-1'])
  })
})

describe('usePaymentsStore mutations', () => {
  it('record upserts the created payment with no extra fetch', async () => {
    mockedApi.record.mockResolvedValueOnce(makePayment())
    const store = usePaymentsStore()

    await store.record('patient-1', { method: 'cash', amount: 100 })

    expect(store.cache.get('payment-1')?.amount).toBe('100.00')
    expect(mockedApi.list).not.toHaveBeenCalled()
  })

  it('update upserts the response directly', async () => {
    mockedApi.update.mockResolvedValueOnce(makePayment({ reference: 'REF-1' }))
    const store = usePaymentsStore()

    await store.update('payment-1', { reference: 'REF-1' })

    expect(store.cache.get('payment-1')?.reference).toBe('REF-1')
  })

  it('apply upserts the response directly', async () => {
    mockedApi.apply.mockResolvedValueOnce(makePayment({ invoice_id: 'invoice-1' }))
    const store = usePaymentsStore()

    await store.apply('payment-1', { invoice_id: 'invoice-1' })

    expect(store.cache.get('payment-1')?.invoice_id).toBe('invoice-1')
  })

  it('refund upserts the new refund row and re-fetches the original to pick up the new remaining balance', async () => {
    mockedApi.refund.mockResolvedValueOnce(
      makePayment({ id: 'payment-2', amount: '-40.00', refunded_payment_id: 'payment-1', is_refund: true }),
    )
    mockedApi.get.mockResolvedValueOnce(makePayment({ id: 'payment-1', remaining_refundable_amount: '60.00' }))
    const store = usePaymentsStore()

    const refund = await store.refund('payment-1', { amount: 40 })

    expect(refund.id).toBe('payment-2')
    expect(store.cache.get('payment-2')?.amount).toBe('-40.00')
    expect(mockedApi.get).toHaveBeenCalledWith('payment-1')
    expect(store.cache.get('payment-1')?.remaining_refundable_amount).toBe('60.00')
  })

  it('remove deletes the payment from the cache', async () => {
    mockedApi.get.mockResolvedValueOnce(makePayment())
    mockedApi.remove.mockResolvedValueOnce(undefined)
    const store = usePaymentsStore()

    await store.fetchOne('payment-1')
    await store.remove('payment-1')

    expect(store.cache.has('payment-1')).toBe(false)
  })
})

describe('usePaymentsStore.$reset', () => {
  it('clears the cache and loaded-patient tracking', async () => {
    mockedApi.list.mockResolvedValueOnce([makePayment()])
    const store = usePaymentsStore()
    await store.fetchForPatient('patient-1')

    store.$reset()

    expect(store.cache.size).toBe(0)
    expect(store.paymentsForPatient('patient-1')).toHaveLength(0)
  })
})
