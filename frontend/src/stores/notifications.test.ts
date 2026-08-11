import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { notificationsApi } from '@/services/notifications'
import { useNotificationsStore } from './notifications'
import type { AppNotification, PaginatedNotifications } from '@/types/notification'

vi.mock('@/services/notifications', () => ({
  notificationsApi: {
    list: vi.fn(),
    unreadCount: vi.fn(),
    markAsRead: vi.fn(),
    markAllAsRead: vi.fn(),
  },
}))

const mockedApi = vi.mocked(notificationsApi)

function makeNotification(overrides: Partial<AppNotification> = {}): AppNotification {
  return {
    id: 'n1',
    category: 'appointments',
    subject_type: 'App\\Models\\Appointment',
    subject_id: 'a1',
    patient_id: 'p1',
    data: {
      type: 'appointment.cancelled',
      titleKey: 'notifications.types.appointment.cancelled.title',
      bodyKey: 'notifications.types.appointment.cancelled.body',
      params: { patientName: 'Jane Doe', startAt: '2026-08-12T09:00:00+00:00' },
      actorName: 'Front Desk',
      route: { name: 'appointment-detail', params: { id: 'a1' } },
    },
    read_at: null,
    created_at: '2026-08-11T10:00:00+00:00',
    ...overrides,
  }
}

function makePage(overrides: Partial<PaginatedNotifications> = {}): PaginatedNotifications {
  return {
    data: [makeNotification()],
    meta: { current_page: 1, last_page: 1, per_page: 15, total: 1 },
    ...overrides,
  }
}

beforeEach(() => {
  setActivePinia(createPinia())
  vi.clearAllMocks()
})

describe('notifications store — fetching', () => {
  it('loads a page and records pagination state', async () => {
    mockedApi.list.mockResolvedValue(makePage())
    const store = useNotificationsStore()

    await store.fetch()

    expect(store.items).toHaveLength(1)
    expect(store.total).toBe(1)
    expect(store.loading).toBe(false)
    expect(store.error).toBeNull()
  })

  it('exposes the load error as an i18n key, not a rendered string', async () => {
    mockedApi.list.mockRejectedValue(new Error('network'))
    const store = useNotificationsStore()

    await store.fetch()

    expect(store.error).toBe('notifications.loadError')
    expect(store.loading).toBe(false)
  })

  it('appends on loadMore but replaces on a fresh fetch', async () => {
    mockedApi.list.mockResolvedValue(
      makePage({ meta: { current_page: 1, last_page: 2, per_page: 15, total: 2 } }),
    )
    const store = useNotificationsStore()
    await store.fetch()
    expect(store.hasMore).toBe(true)

    mockedApi.list.mockResolvedValue(
      makePage({
        data: [makeNotification({ id: 'n2' })],
        meta: { current_page: 2, last_page: 2, per_page: 15, total: 2 },
      }),
    )
    await store.loadMore()
    expect(store.items.map((item) => item.id)).toEqual(['n1', 'n2'])
    expect(store.hasMore).toBe(false)

    // A fresh page-1 fetch must replace, so a filter change can never leave stale rows visible.
    mockedApi.list.mockResolvedValue(makePage({ data: [makeNotification({ id: 'n3' })] }))
    await store.fetch()
    expect(store.items.map((item) => item.id)).toEqual(['n3'])
  })

  it('does not load more when there is no further page', async () => {
    mockedApi.list.mockResolvedValue(makePage())
    const store = useNotificationsStore()
    await store.fetch()
    mockedApi.list.mockClear()

    await store.loadMore()

    expect(mockedApi.list).not.toHaveBeenCalled()
  })

  it('sends the active status and category filters to the API', async () => {
    mockedApi.list.mockResolvedValue(makePage())
    const store = useNotificationsStore()

    await store.setStatus('unread')
    await store.setCategory('laboratory')

    expect(mockedApi.list).toHaveBeenLastCalledWith({ status: 'unread', category: 'laboratory' }, 1)
  })

  it('omits the category parameter entirely when no category is active', async () => {
    mockedApi.list.mockResolvedValue(makePage())
    const store = useNotificationsStore()

    await store.fetch()

    expect(mockedApi.list).toHaveBeenCalledWith({ status: 'all' }, 1)
  })
})

describe('notifications store — unread count', () => {
  it('reads the count from its own endpoint rather than deriving it from items', async () => {
    mockedApi.unreadCount.mockResolvedValue(7)
    const store = useNotificationsStore()

    await store.fetchUnreadCount()

    expect(store.unreadCount).toBe(7)
    expect(store.items).toHaveLength(0)
  })

  it('stays silent on a polling failure so a blip never shows an app-wide error', async () => {
    mockedApi.unreadCount.mockRejectedValue(new Error('offline'))
    const store = useNotificationsStore()
    store.unreadCount = 3

    await store.fetchUnreadCount()

    expect(store.error).toBeNull()
    expect(store.unreadCount).toBe(3)
  })
})

describe('notifications store — marking read', () => {
  it('marks one as read optimistically and decrements the badge', async () => {
    mockedApi.list.mockResolvedValue(makePage())
    mockedApi.markAsRead.mockResolvedValue(makeNotification({ read_at: '2026-08-11T11:00:00+00:00' }))
    const store = useNotificationsStore()
    await store.fetch()
    store.unreadCount = 1

    await store.markAsRead('n1')

    expect(store.items[0].read_at).not.toBeNull()
    expect(store.unreadCount).toBe(0)
  })

  it('rolls back the optimistic update when the request fails', async () => {
    mockedApi.list.mockResolvedValue(makePage())
    mockedApi.markAsRead.mockRejectedValue(new Error('boom'))
    const store = useNotificationsStore()
    await store.fetch()
    store.unreadCount = 1

    await store.markAsRead('n1')

    expect(store.items[0].read_at).toBeNull()
    expect(store.unreadCount).toBe(1)
    expect(store.error).toBe('notifications.markReadError')
  })

  it('is a no-op on an already-read notification', async () => {
    mockedApi.list.mockResolvedValue(
      makePage({ data: [makeNotification({ read_at: '2026-08-11T09:00:00+00:00' })] }),
    )
    const store = useNotificationsStore()
    await store.fetch()

    await store.markAsRead('n1')

    expect(mockedApi.markAsRead).not.toHaveBeenCalled()
  })

  it('scopes mark-all-as-read to the active category and re-reads the true count', async () => {
    mockedApi.list.mockResolvedValue(makePage())
    mockedApi.markAllAsRead.mockResolvedValue(1)
    // With a category filter active, unread rows in other categories legitimately survive — so the
    // badge must come from the server, not from an optimistic zero.
    mockedApi.unreadCount.mockResolvedValue(4)
    const store = useNotificationsStore()
    await store.setCategory('laboratory')

    await store.markAllAsRead()

    expect(mockedApi.markAllAsRead).toHaveBeenCalledWith('laboratory')
    expect(store.unreadCount).toBe(4)
    expect(store.items[0].read_at).not.toBeNull()
  })

  it('rolls back every row when mark-all fails', async () => {
    mockedApi.list.mockResolvedValue(
      makePage({
        data: [makeNotification({ id: 'n1' }), makeNotification({ id: 'n2', read_at: 'x' })],
      }),
    )
    mockedApi.markAllAsRead.mockRejectedValue(new Error('boom'))
    const store = useNotificationsStore()
    await store.fetch()
    store.unreadCount = 1

    await store.markAllAsRead()

    expect(store.items[0].read_at).toBeNull()
    expect(store.items[1].read_at).toBe('x')
    expect(store.unreadCount).toBe(1)
    expect(store.error).toBe('notifications.markAllReadError')
  })
})

describe('notifications store — reset', () => {
  it('clears everything so a signed-out session leaves nothing in memory', async () => {
    mockedApi.list.mockResolvedValue(makePage())
    const store = useNotificationsStore()
    await store.setCategory('billing')
    store.unreadCount = 5

    store.reset()

    expect(store.items).toEqual([])
    expect(store.unreadCount).toBe(0)
    expect(store.category).toBeNull()
    expect(store.status).toBe('all')
  })
})
