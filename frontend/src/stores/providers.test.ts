import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { providersApi } from '@/services/appointments'
import { useProvidersStore } from './providers'
import type { AuthUser } from '@/types/user'

vi.mock('@/services/appointments', () => ({
  providersApi: { listAll: vi.fn() },
}))

const mockedApi = vi.mocked(providersApi)

const dentist: AuthUser = { id: 'd1', name: 'Dr. X', email: 'd1@example.com', role: 'dentist' }

beforeEach(() => {
  setActivePinia(createPinia())
  vi.clearAllMocks()
})

describe('useProvidersStore', () => {
  it('fetches once and caches for the session', async () => {
    mockedApi.listAll.mockResolvedValue([dentist])
    const store = useProvidersStore()

    await store.fetchAll()
    await store.fetchAll()

    expect(mockedApi.listAll).toHaveBeenCalledTimes(1)
    expect(store.items).toEqual([dentist])
    expect(store.loaded).toBe(true)
  })

  it('refetches when force is true', async () => {
    mockedApi.listAll.mockResolvedValue([dentist])
    const store = useProvidersStore()

    await store.fetchAll()
    await store.fetchAll(true)

    expect(mockedApi.listAll).toHaveBeenCalledTimes(2)
  })

  it('dedupes concurrent calls made before the first one resolves into a single request', async () => {
    mockedApi.listAll.mockResolvedValue([dentist])
    const store = useProvidersStore()

    await Promise.all([store.fetchAll(), store.fetchAll(), store.fetchAll()])

    expect(mockedApi.listAll).toHaveBeenCalledTimes(1)
    expect(store.items).toEqual([dentist])
    expect(store.loaded).toBe(true)
  })
})
