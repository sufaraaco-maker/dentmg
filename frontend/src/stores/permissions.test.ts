import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { permissionsApi } from '@/services/permissions'
import { usePermissionsStore } from './permissions'
import type { Permission, RolePermissionMatrix } from '@/types/permission'

vi.mock('@/services/permissions', () => ({
  permissionsApi: { catalog: vi.fn(), matrix: vi.fn(), updateMatrix: vi.fn() },
}))

const mockedApi = vi.mocked(permissionsApi)

function makeCatalog(): Permission[] {
  return [
    { key: 'patients.view', group: 'patients', description: null },
    { key: 'users.manage', group: 'users', description: null },
  ]
}

function makeMatrix(): RolePermissionMatrix {
  return {
    admin: ['patients.view', 'users.manage'],
    dentist: ['patients.view'],
    receptionist: ['patients.view'],
  }
}

beforeEach(() => {
  setActivePinia(createPinia())
  vi.clearAllMocks()
})

describe('usePermissionsStore.fetchAll', () => {
  it('fetches the catalog and matrix together', async () => {
    mockedApi.catalog.mockResolvedValueOnce(makeCatalog())
    mockedApi.matrix.mockResolvedValueOnce(makeMatrix())
    const store = usePermissionsStore()

    await store.fetchAll()

    expect(store.catalog).toEqual(makeCatalog())
    expect(store.matrix).toEqual(makeMatrix())
    expect(store.loading).toBe(false)
    expect(store.error).toBeNull()
  })

  it('sets a translation-key error on failure', async () => {
    mockedApi.catalog.mockRejectedValueOnce(new Error('network error'))
    mockedApi.matrix.mockResolvedValueOnce(makeMatrix())
    const store = usePermissionsStore()

    await store.fetchAll()

    expect(store.error).toBe('permissions.loadError')
    expect(store.loading).toBe(false)
  })
})

describe('usePermissionsStore.updateMatrix', () => {
  it('updates the matrix and returns true on success', async () => {
    const updated = { ...makeMatrix(), dentist: [] }
    mockedApi.updateMatrix.mockResolvedValueOnce(updated)
    const store = usePermissionsStore()

    const result = await store.updateMatrix(updated)

    expect(result).toBe(true)
    expect(store.matrix).toEqual(updated)
    expect(store.saving).toBe(false)
    expect(store.saveError).toBeNull()
  })

  it('returns false with the self-lockout error key on a 422', async () => {
    mockedApi.updateMatrix.mockRejectedValueOnce({ response: { status: 422 } })
    const store = usePermissionsStore()

    const result = await store.updateMatrix(makeMatrix())

    expect(result).toBe(false)
    expect(store.saveError).toBe('permissions.selfLockoutError')
  })

  it('returns false with a generic error key on any other failure', async () => {
    mockedApi.updateMatrix.mockRejectedValueOnce(new Error('network error'))
    const store = usePermissionsStore()

    const result = await store.updateMatrix(makeMatrix())

    expect(result).toBe(false)
    expect(store.saveError).toBe('permissions.saveError')
  })
})

describe('usePermissionsStore.$reset', () => {
  it('clears catalog, matrix, and error state', async () => {
    mockedApi.catalog.mockResolvedValueOnce(makeCatalog())
    mockedApi.matrix.mockResolvedValueOnce(makeMatrix())
    const store = usePermissionsStore()
    await store.fetchAll()

    store.$reset()

    expect(store.catalog).toEqual([])
    expect(store.matrix).toBeNull()
    expect(store.error).toBeNull()
  })
})
