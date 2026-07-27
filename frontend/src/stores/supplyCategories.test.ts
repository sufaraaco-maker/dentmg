import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { api } from '@/lib/api'
import { useSupplyCategoriesStore } from './supplyCategories'
import type { SupplyCategory } from '@/types/inventory'

vi.mock('@/lib/api', () => ({
  api: { get: vi.fn(), post: vi.fn(), put: vi.fn(), delete: vi.fn() },
}))

const mockedApi = vi.mocked(api)

function makeCategory(overrides: Partial<SupplyCategory> = {}): SupplyCategory {
  return {
    id: 'category-1',
    name: 'PPE',
    sort_order: 1,
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

describe('useSupplyCategoriesStore.fetchAll', () => {
  it('fetches once and caches indefinitely', async () => {
    mockedApi.get.mockResolvedValue({ data: [makeCategory()] })
    const store = useSupplyCategoriesStore()

    await store.fetchAll()
    await store.fetchAll()

    expect(mockedApi.get).toHaveBeenCalledTimes(1)
    expect(store.items).toHaveLength(1)
    expect(store.loaded).toBe(true)
  })

  it('dedupes concurrent calls made before the first one resolves into a single request', async () => {
    mockedApi.get.mockResolvedValue({ data: [makeCategory()] })
    const store = useSupplyCategoriesStore()

    await Promise.all([store.fetchAll(), store.fetchAll()])

    expect(mockedApi.get).toHaveBeenCalledTimes(1)
  })
})

describe('useSupplyCategoriesStore mutations', () => {
  it('create appends the new category to the cached list', async () => {
    mockedApi.get.mockResolvedValueOnce({ data: [makeCategory()] })
    mockedApi.post.mockResolvedValueOnce({ data: makeCategory({ id: 'category-2', name: 'Anesthetics' }) })

    const store = useSupplyCategoriesStore()
    await store.fetchAll()
    await store.create({ name: 'Anesthetics' })

    expect(store.items.map((c) => c.name)).toEqual(['PPE', 'Anesthetics'])
  })

  it('update replaces the matching item in place', async () => {
    mockedApi.get.mockResolvedValueOnce({ data: [makeCategory()] })
    mockedApi.put.mockResolvedValueOnce({ data: makeCategory({ name: 'PPE (Updated)' }) })

    const store = useSupplyCategoriesStore()
    await store.fetchAll()
    await store.update('category-1', { name: 'PPE (Updated)' })

    expect(store.items[0].name).toBe('PPE (Updated)')
  })

  it('deactivate marks the matching item inactive without removing it', async () => {
    mockedApi.get.mockResolvedValueOnce({ data: [makeCategory()] })
    mockedApi.delete.mockResolvedValueOnce({ data: null })

    const store = useSupplyCategoriesStore()
    await store.fetchAll()
    await store.deactivate('category-1')

    expect(store.items).toHaveLength(1)
    expect(store.items[0].is_active).toBe(false)
  })
})
