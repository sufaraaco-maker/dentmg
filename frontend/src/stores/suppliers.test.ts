import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { api } from '@/lib/api'
import { useSuppliersStore } from './suppliers'
import type { Supplier } from '@/types/inventory'

vi.mock('@/lib/api', () => ({
  api: { get: vi.fn(), post: vi.fn(), put: vi.fn(), delete: vi.fn() },
}))

const mockedApi = vi.mocked(api)

function makeSupplier(overrides: Partial<Supplier> = {}): Supplier {
  return {
    id: 'supplier-1',
    name: 'Henry Schein',
    contact_name: null,
    phone: null,
    email: null,
    address: null,
    notes: null,
    is_active: true,
    created_at: '2026-07-26T00:00:00+00:00',
    updated_at: '2026-07-26T00:00:00+00:00',
    ...overrides,
  }
}

beforeEach(() => {
  setActivePinia(createPinia())
  vi.clearAllMocks()
})

describe('useSuppliersStore.fetchAll', () => {
  it('fetches once and caches indefinitely', async () => {
    mockedApi.get.mockResolvedValue({ data: [makeSupplier()] })
    const store = useSuppliersStore()

    await store.fetchAll()
    await store.fetchAll()

    expect(mockedApi.get).toHaveBeenCalledTimes(1)
    expect(store.items).toHaveLength(1)
    expect(store.loaded).toBe(true)
  })

  it('refetches when force is true', async () => {
    mockedApi.get.mockResolvedValue({ data: [makeSupplier()] })
    const store = useSuppliersStore()

    await store.fetchAll()
    await store.fetchAll(true)

    expect(mockedApi.get).toHaveBeenCalledTimes(2)
  })

  it('dedupes concurrent calls made before the first one resolves into a single request', async () => {
    mockedApi.get.mockResolvedValue({ data: [makeSupplier()] })
    const store = useSuppliersStore()

    await Promise.all([store.fetchAll(), store.fetchAll(), store.fetchAll()])

    expect(mockedApi.get).toHaveBeenCalledTimes(1)
  })

  it('sets a translation-key error and clears loading on failure, instead of rejecting', async () => {
    mockedApi.get.mockRejectedValueOnce(new Error('network down'))
    const store = useSuppliersStore()

    await store.fetchAll()

    expect(store.error).toBe('inventory.suppliers.loadError')
    expect(store.loading).toBe(false)
    expect(store.loaded).toBe(false)
  })
})

describe('useSuppliersStore mutations', () => {
  it('create appends the new supplier to the cached list', async () => {
    mockedApi.get.mockResolvedValueOnce({ data: [makeSupplier()] })
    mockedApi.post.mockResolvedValueOnce({ data: makeSupplier({ id: 'supplier-2', name: 'Patterson' }) })

    const store = useSuppliersStore()
    await store.fetchAll()
    await store.create({ name: 'Patterson' })

    expect(store.items.map((s) => s.name)).toEqual(['Henry Schein', 'Patterson'])
  })

  it('update replaces the matching item in place', async () => {
    mockedApi.get.mockResolvedValueOnce({ data: [makeSupplier()] })
    mockedApi.put.mockResolvedValueOnce({ data: makeSupplier({ name: 'Henry Schein (Updated)' }) })

    const store = useSuppliersStore()
    await store.fetchAll()
    await store.update('supplier-1', { name: 'Henry Schein (Updated)' })

    expect(store.items[0].name).toBe('Henry Schein (Updated)')
  })

  it('deactivate marks the matching item inactive without removing it', async () => {
    mockedApi.get.mockResolvedValueOnce({ data: [makeSupplier()] })
    mockedApi.delete.mockResolvedValueOnce({ data: null })

    const store = useSuppliersStore()
    await store.fetchAll()
    await store.deactivate('supplier-1')

    expect(store.items).toHaveLength(1)
    expect(store.items[0].is_active).toBe(false)
  })
})
