import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { dentalConditionsApi } from '@/services/dentalChart'
import { useDentalConditionsStore } from './dentalConditions'
import type { DentalCondition } from '@/types/dentalChart'

vi.mock('@/services/dentalChart', () => ({
  dentalConditionsApi: {
    list: vi.fn(),
    create: vi.fn(),
    update: vi.fn(),
    remove: vi.fn(),
  },
}))

const mockedApi = vi.mocked(dentalConditionsApi)

function makeCondition(overrides: Partial<DentalCondition> = {}): DentalCondition {
  return {
    id: 'condition-1',
    name: 'Caries',
    category: 'finding',
    applies_to_surface: true,
    default_color: '#DC2626',
    icon_key: 'caries',
    default_cost: null,
    description: null,
    is_active: true,
    sort_order: 1,
    created_at: '2026-07-15T00:00:00+00:00',
    updated_at: '2026-07-15T00:00:00+00:00',
    ...overrides,
  }
}

beforeEach(() => {
  setActivePinia(createPinia())
  vi.clearAllMocks()
})

describe('useDentalConditionsStore.fetchAll', () => {
  it('fetches once and caches indefinitely', async () => {
    mockedApi.list.mockResolvedValue([makeCondition()])
    const store = useDentalConditionsStore()

    await store.fetchAll()
    await store.fetchAll()

    expect(mockedApi.list).toHaveBeenCalledTimes(1)
    expect(store.items).toHaveLength(1)
    expect(store.loaded).toBe(true)
  })

  it('refetches when force is true', async () => {
    mockedApi.list.mockResolvedValue([makeCondition()])
    const store = useDentalConditionsStore()

    await store.fetchAll()
    await store.fetchAll(true)

    expect(mockedApi.list).toHaveBeenCalledTimes(2)
  })

  it('dedupes concurrent calls made before the first one resolves into a single request', async () => {
    mockedApi.list.mockResolvedValue([makeCondition()])
    const store = useDentalConditionsStore()

    await Promise.all([store.fetchAll(), store.fetchAll(), store.fetchAll()])

    expect(mockedApi.list).toHaveBeenCalledTimes(1)
    expect(store.loaded).toBe(true)
  })
})

describe('useDentalConditionsStore mutations', () => {
  it('create appends the new condition to the cached list', async () => {
    mockedApi.list.mockResolvedValueOnce([makeCondition()])
    mockedApi.create.mockResolvedValueOnce(makeCondition({ id: 'condition-2', name: 'Composite Filling' }))

    const store = useDentalConditionsStore()
    await store.fetchAll()
    await store.create({
      name: 'Composite Filling',
      category: 'procedure',
      applies_to_surface: true,
      default_color: '#2563EB',
    })

    expect(store.items.map((c) => c.name)).toEqual(['Caries', 'Composite Filling'])
  })

  it('update replaces the matching item in place', async () => {
    mockedApi.list.mockResolvedValueOnce([makeCondition()])
    mockedApi.update.mockResolvedValueOnce(makeCondition({ name: 'Caries (Updated)' }))

    const store = useDentalConditionsStore()
    await store.fetchAll()
    await store.update('condition-1', { name: 'Caries (Updated)' })

    expect(store.items[0].name).toBe('Caries (Updated)')
  })

  it('remove filters the deleted item out of the cached list', async () => {
    mockedApi.list.mockResolvedValueOnce([makeCondition()])
    mockedApi.remove.mockResolvedValueOnce(undefined)

    const store = useDentalConditionsStore()
    await store.fetchAll()
    await store.remove('condition-1')

    expect(store.items).toHaveLength(0)
  })
})
