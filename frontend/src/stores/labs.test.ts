import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { api } from '@/lib/api'
import { useLabsStore } from './labs'
import type { Lab } from '@/types/laboratory'

vi.mock('@/lib/api', () => ({
  api: { get: vi.fn(), post: vi.fn(), put: vi.fn(), delete: vi.fn() },
}))

const mockedApi = vi.mocked(api)

function makeLab(overrides: Partial<Lab> = {}): Lab {
  return {
    id: 'lab-1',
    name: 'Precision Dental Lab',
    contact_name: null,
    phone: null,
    email: null,
    address: null,
    default_turnaround_days: 7,
    notes: null,
    is_active: true,
    created_at: '2026-07-27T00:00:00+00:00',
    updated_at: '2026-07-27T00:00:00+00:00',
    ...overrides,
  }
}

beforeEach(() => {
  setActivePinia(createPinia())
  vi.clearAllMocks()
})

describe('useLabsStore.fetchAll', () => {
  it('fetches once and caches indefinitely', async () => {
    mockedApi.get.mockResolvedValue({ data: [makeLab()] })
    const store = useLabsStore()

    await store.fetchAll()
    await store.fetchAll()

    expect(mockedApi.get).toHaveBeenCalledTimes(1)
    expect(store.items).toHaveLength(1)
    expect(store.loaded).toBe(true)
  })

  it('refetches when force is true', async () => {
    mockedApi.get.mockResolvedValue({ data: [makeLab()] })
    const store = useLabsStore()

    await store.fetchAll()
    await store.fetchAll(true)

    expect(mockedApi.get).toHaveBeenCalledTimes(2)
  })

  it('dedupes concurrent calls made before the first one resolves into a single request', async () => {
    mockedApi.get.mockResolvedValue({ data: [makeLab()] })
    const store = useLabsStore()

    await Promise.all([store.fetchAll(), store.fetchAll(), store.fetchAll()])

    expect(mockedApi.get).toHaveBeenCalledTimes(1)
  })

  it('sets a translation-key error and clears loading on failure, instead of rejecting', async () => {
    mockedApi.get.mockRejectedValueOnce(new Error('network down'))
    const store = useLabsStore()

    await store.fetchAll()

    expect(store.error).toBe('laboratory.labs.loadError')
    expect(store.loading).toBe(false)
    expect(store.loaded).toBe(false)
  })
})

describe('useLabsStore mutations', () => {
  it('create appends the new lab to the cached list', async () => {
    mockedApi.get.mockResolvedValueOnce({ data: [makeLab()] })
    mockedApi.post.mockResolvedValueOnce({ data: makeLab({ id: 'lab-2', name: 'Bright Smile Lab' }) })

    const store = useLabsStore()
    await store.fetchAll()
    await store.create({ name: 'Bright Smile Lab' })

    expect(store.items.map((l) => l.name)).toEqual(['Precision Dental Lab', 'Bright Smile Lab'])
  })

  it('update replaces the matching item in place', async () => {
    mockedApi.get.mockResolvedValueOnce({ data: [makeLab()] })
    mockedApi.put.mockResolvedValueOnce({ data: makeLab({ name: 'Precision Dental Lab (Updated)' }) })

    const store = useLabsStore()
    await store.fetchAll()
    await store.update('lab-1', { name: 'Precision Dental Lab (Updated)' })

    expect(store.items[0].name).toBe('Precision Dental Lab (Updated)')
  })

  it('deactivate marks the matching item inactive without removing it', async () => {
    mockedApi.get.mockResolvedValueOnce({ data: [makeLab()] })
    mockedApi.delete.mockResolvedValueOnce({ data: null })

    const store = useLabsStore()
    await store.fetchAll()
    await store.deactivate('lab-1')

    expect(store.items).toHaveLength(1)
    expect(store.items[0].is_active).toBe(false)
  })
})
