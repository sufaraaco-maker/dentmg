import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { invoiceItemsApi, invoicesApi } from '@/services/invoices'
import { useInvoicesStore } from './invoices'
import type { Invoice } from '@/types/invoice'

vi.mock('@/services/invoices', () => ({
  invoicesApi: {
    list: vi.fn(),
    listIssued: vi.fn(),
    create: vi.fn(),
    get: vi.fn(),
    update: vi.fn(),
    issue: vi.fn(),
    void: vi.fn(),
    remove: vi.fn(),
    billableTreatmentPlanItems: vi.fn(),
  },
  invoiceItemsApi: {
    create: vi.fn(),
    update: vi.fn(),
    remove: vi.fn(),
  },
}))

const mockedInvoicesApi = vi.mocked(invoicesApi)
const mockedItemsApi = vi.mocked(invoiceItemsApi)

function makeInvoice(overrides: Partial<Invoice> = {}): Invoice {
  return {
    id: overrides.id ?? 'invoice-1',
    patient_id: 'patient-1',
    created_by_id: 'admin-1',
    sequence_number: null,
    invoice_number: null,
    currency_code: 'USD',
    status: 'draft',
    notes: null,
    issue_date: null,
    due_date: null,
    issued_at: null,
    voided_at: null,
    created_at: '2026-08-01T09:00:00+00:00',
    updated_at: '2026-08-01T09:00:00+00:00',
    items: [],
    ...overrides,
  }
}

function makePage(
  invoices: Invoice[],
  overrides: Partial<{ current_page: number; last_page: number; per_page: number; total: number }> = {},
) {
  return {
    data: invoices,
    meta: {
      current_page: overrides.current_page ?? 1,
      last_page: overrides.last_page ?? 1,
      per_page: overrides.per_page ?? 15,
      total: overrides.total ?? invoices.length,
    },
  }
}

beforeEach(() => {
  setActivePinia(createPinia())
  vi.clearAllMocks()
})

describe('useInvoicesStore.fetchForPatient', () => {
  it('fetches and caches a patient’s invoices', async () => {
    mockedInvoicesApi.list.mockResolvedValueOnce(makePage([makeInvoice()]))
    const store = useInvoicesStore()

    await store.fetchForPatient('patient-1')

    expect(store.invoicesForPatient('patient-1')).toHaveLength(1)
    expect(mockedInvoicesApi.list).toHaveBeenCalledWith('patient-1', 1)
  })

  it('skips the network call on a second fetch for the same patient and page', async () => {
    mockedInvoicesApi.list.mockResolvedValue(makePage([makeInvoice()]))
    const store = useInvoicesStore()

    await store.fetchForPatient('patient-1')
    await store.fetchForPatient('patient-1')

    expect(mockedInvoicesApi.list).toHaveBeenCalledTimes(1)
  })

  it('refetches when force is true', async () => {
    mockedInvoicesApi.list.mockResolvedValue(makePage([makeInvoice()]))
    const store = useInvoicesStore()

    await store.fetchForPatient('patient-1')
    await store.fetchForPatient('patient-1', 1, true)

    expect(mockedInvoicesApi.list).toHaveBeenCalledTimes(2)
  })

  it('sets a translation-key error on failure', async () => {
    mockedInvoicesApi.list.mockRejectedValueOnce(new Error('network error'))
    const store = useInvoicesStore()

    await store.fetchForPatient('patient-1')

    expect(store.error).toBe('invoices.loadError')
    expect(store.loading).toBe(false)
  })

  it('invoicesForPatient only returns the current page’s invoices, most recent first', async () => {
    mockedInvoicesApi.list.mockResolvedValueOnce(
      makePage([
        makeInvoice({ id: 'invoice-1', created_at: '2026-07-20T09:00:00+00:00' }),
        makeInvoice({ id: 'invoice-2', created_at: '2026-08-01T09:00:00+00:00' }),
      ]),
    )
    const store = useInvoicesStore()

    await store.fetchForPatient('patient-1')

    expect(store.invoicesForPatient('patient-1').map((invoice) => invoice.id)).toEqual([
      'invoice-2',
      'invoice-1',
    ])
  })

  it('pageMetaForPatient reflects the server pagination meta', async () => {
    mockedInvoicesApi.list.mockResolvedValueOnce(
      makePage([makeInvoice()], { current_page: 1, last_page: 2, per_page: 15, total: 20 }),
    )
    const store = useInvoicesStore()

    await store.fetchForPatient('patient-1')

    expect(store.pageMetaForPatient('patient-1')).toEqual({
      currentPage: 1,
      lastPage: 2,
      perPage: 15,
      total: 20,
    })
  })
})

describe('useInvoicesStore mutations', () => {
  it('create upserts the created invoice and refreshes page 1 so it is immediately visible', async () => {
    mockedInvoicesApi.create.mockResolvedValueOnce(makeInvoice())
    mockedInvoicesApi.list.mockResolvedValueOnce(makePage([makeInvoice()]))
    const store = useInvoicesStore()

    await store.create('patient-1')

    expect(store.cache.get('invoice-1')).toBeDefined()
    expect(mockedInvoicesApi.list).toHaveBeenCalledWith('patient-1', 1)
    expect(store.invoicesForPatient('patient-1')).toHaveLength(1)
  })

  it('update upserts the response directly', async () => {
    mockedInvoicesApi.update.mockResolvedValueOnce(makeInvoice({ notes: 'Updated' }))
    const store = useInvoicesStore()

    await store.update('invoice-1', { notes: 'Updated' })

    expect(store.cache.get('invoice-1')?.notes).toBe('Updated')
  })

  it('issue upserts the response directly', async () => {
    mockedInvoicesApi.issue.mockResolvedValueOnce(makeInvoice({ status: 'issued' }))
    const store = useInvoicesStore()

    await store.issue('invoice-1')

    expect(store.cache.get('invoice-1')?.status).toBe('issued')
  })

  it('voidInvoice upserts the response directly', async () => {
    mockedInvoicesApi.void.mockResolvedValueOnce(makeInvoice({ status: 'void' }))
    const store = useInvoicesStore()

    await store.voidInvoice('invoice-1')

    expect(store.cache.get('invoice-1')?.status).toBe('void')
  })

  it('remove deletes the invoice from the cache', async () => {
    mockedInvoicesApi.get.mockResolvedValueOnce(makeInvoice())
    mockedInvoicesApi.remove.mockResolvedValueOnce(undefined)
    const store = useInvoicesStore()

    await store.fetchOne('invoice-1')
    await store.remove('invoice-1')

    expect(store.cache.has('invoice-1')).toBe(false)
  })

  it('addItem/updateItem/removeItem all upsert the returned parent invoice', async () => {
    mockedItemsApi.create.mockResolvedValueOnce(makeInvoice({ id: 'invoice-1' }))
    mockedItemsApi.update.mockResolvedValueOnce(makeInvoice({ id: 'invoice-1' }))
    mockedItemsApi.remove.mockResolvedValueOnce(makeInvoice({ id: 'invoice-1' }))
    const store = useInvoicesStore()

    await store.addItem('invoice-1', { kind: 'charge', description: 'Filling', unit_amount: 100 })
    await store.updateItem('item-1', { unit_amount: 120 })
    await store.removeItem('item-1')

    expect(mockedItemsApi.create).toHaveBeenCalledWith('invoice-1', {
      kind: 'charge',
      description: 'Filling',
      unit_amount: 100,
    })
    expect(mockedItemsApi.update).toHaveBeenCalledWith('item-1', { unit_amount: 120 })
    expect(mockedItemsApi.remove).toHaveBeenCalledWith('item-1')
  })
})

describe('useInvoicesStore.$reset', () => {
  it('clears the cache and loaded-patient tracking', async () => {
    mockedInvoicesApi.list.mockResolvedValueOnce(makePage([makeInvoice()]))
    const store = useInvoicesStore()
    await store.fetchForPatient('patient-1')

    store.$reset()

    expect(store.cache.size).toBe(0)
    expect(store.invoicesForPatient('patient-1')).toHaveLength(0)
  })
})
