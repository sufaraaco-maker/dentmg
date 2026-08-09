import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { auditLogsApi } from '@/services/auditLogs'
import { api } from '@/lib/api'
import { useAuditLogsStore } from './auditLogs'
import type { AuditLog, PaginatedAuditLogs } from '@/types/auditLog'

vi.mock('@/services/auditLogs', () => ({
  auditLogsApi: { list: vi.fn() },
}))
vi.mock('@/lib/api', () => ({
  api: { get: vi.fn() },
}))

const mockedApi = vi.mocked(auditLogsApi)
const mockedHttp = vi.mocked(api)

function makeLog(overrides: Partial<AuditLog> = {}): AuditLog {
  return {
    id: 'log-1',
    action: 'updated',
    auditable_type: 'App\\Models\\Patient',
    auditable_id: 'p1',
    changes: { first_name: 'Jane' },
    old_values: { first_name: 'Janet' },
    context: null,
    ip_address: '127.0.0.1',
    user_agent: 'test-agent',
    user: { id: 'u1', name: 'Admin User' },
    created_at: '2026-08-09T10:00:00+00:00',
    ...overrides,
  }
}

function makePage(overrides: Partial<PaginatedAuditLogs> = {}): PaginatedAuditLogs {
  return {
    data: [makeLog()],
    meta: { current_page: 1, last_page: 1, per_page: 15, total: 1 },
    ...overrides,
  }
}

beforeEach(() => {
  setActivePinia(createPinia())
  vi.clearAllMocks()
})

describe('useAuditLogsStore.fetch', () => {
  it('fetches and stores a page of audit log entries', async () => {
    mockedApi.list.mockResolvedValueOnce(makePage())
    const store = useAuditLogsStore()

    await store.fetch({}, 1)

    expect(store.logs).toEqual([makeLog()])
    expect(store.total).toBe(1)
    expect(store.loading).toBe(false)
    expect(store.error).toBeNull()
  })

  it('sets a translation-key error on failure', async () => {
    mockedApi.list.mockRejectedValueOnce(new Error('network error'))
    const store = useAuditLogsStore()

    await store.fetch({}, 1)

    expect(store.error).toBe('auditLog.loadError')
    expect(store.loading).toBe(false)
  })
})

describe('useAuditLogsStore.fetchFilterUsers', () => {
  it('paginates through every user once and caches the result', async () => {
    mockedHttp.get
      .mockResolvedValueOnce({
        data: {
          data: [{ id: 'u1', name: 'A', email: 'a@x.com', role: 'admin' }],
          links: { next: '/users?page=2' },
        },
      })
      .mockResolvedValueOnce({
        data: { data: [{ id: 'u2', name: 'B', email: 'b@x.com', role: 'dentist' }], links: { next: null } },
      })
    const store = useAuditLogsStore()

    await store.fetchFilterUsers()

    expect(store.filterUsers).toHaveLength(2)
    expect(mockedHttp.get).toHaveBeenCalledTimes(2)
  })

  it('does not re-fetch once already loaded', async () => {
    mockedHttp.get.mockResolvedValueOnce({ data: { data: [], links: { next: null } } })
    const store = useAuditLogsStore()

    await store.fetchFilterUsers()
    await store.fetchFilterUsers()

    expect(mockedHttp.get).toHaveBeenCalledTimes(1)
  })
})

describe('useAuditLogsStore.$reset', () => {
  it('clears logs and filter users', async () => {
    mockedApi.list.mockResolvedValueOnce(makePage())
    const store = useAuditLogsStore()
    await store.fetch({}, 1)

    store.$reset()

    expect(store.logs).toEqual([])
    expect(store.total).toBe(0)
    expect(store.filterUsersLoaded).toBe(false)
  })
})
