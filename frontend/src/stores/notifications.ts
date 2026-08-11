import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { notificationsApi } from '@/services/notifications'
import type { AppNotification, NotificationCategory, NotificationStatusFilter } from '@/types/notification'

/**
 * Notification System (Phase 5A) design doc §10.1.
 *
 * Store-owns-error-state, with the error held as an i18n KEY rather than a rendered string —
 * matching `stores/auditLogs.ts` and `stores/dashboard.ts`.
 *
 * `unreadCount` is fetched from its own endpoint, never derived from `items`: the badge must stay
 * correct without the panel ever having been opened, and `items` only ever holds the pages that
 * have been loaded.
 */
export const useNotificationsStore = defineStore('notifications', () => {
  const items = ref<AppNotification[]>([])
  const unreadCount = ref(0)
  const currentPage = ref(1)
  const lastPage = ref(1)
  const total = ref(0)
  const loading = ref(false)
  const error = ref<string | null>(null)

  const status = ref<NotificationStatusFilter>('all')
  const category = ref<NotificationCategory | null>(null)

  const hasMore = computed(() => currentPage.value < lastPage.value)

  /**
   * `append` backs the panel's "Load more". A fresh fetch (page 1) always replaces, so changing a
   * filter can never leave stale rows from the previous filter visible.
   */
  async function fetch(page = 1, append = false): Promise<void> {
    loading.value = true
    error.value = null

    try {
      const result = await notificationsApi.list(
        {
          status: status.value,
          ...(category.value ? { category: category.value } : {}),
        },
        page,
      )

      items.value = append ? [...items.value, ...result.data] : result.data
      currentPage.value = result.meta.current_page
      lastPage.value = result.meta.last_page
      total.value = result.meta.total
    } catch {
      error.value = 'notifications.loadError'
    } finally {
      loading.value = false
    }
  }

  async function loadMore(): Promise<void> {
    if (!hasMore.value || loading.value) return
    await fetch(currentPage.value + 1, true)
  }

  /**
   * Polled by the bell every 60s while the tab is visible. Deliberately silent on failure: a
   * transient network blip must not surface an error banner over the whole app shell, and the next
   * tick recovers on its own.
   */
  async function fetchUnreadCount(): Promise<void> {
    try {
      unreadCount.value = await notificationsApi.unreadCount()
    } catch {
      // Intentionally ignored — see above.
    }
  }

  async function setStatus(next: NotificationStatusFilter): Promise<void> {
    status.value = next
    await fetch()
  }

  async function setCategory(next: NotificationCategory | null): Promise<void> {
    category.value = next
    await fetch()
  }

  /** Optimistic, with rollback — the row greys out instantly, but a failed request undoes it. */
  async function markAsRead(id: string): Promise<void> {
    const target = items.value.find((item) => item.id === id)
    if (!target || target.read_at !== null) return

    const previousReadAt = target.read_at
    const previousCount = unreadCount.value
    target.read_at = new Date().toISOString()
    unreadCount.value = Math.max(0, unreadCount.value - 1)

    try {
      await notificationsApi.markAsRead(id)
    } catch {
      target.read_at = previousReadAt
      unreadCount.value = previousCount
      error.value = 'notifications.markReadError'
    }
  }

  /**
   * Scoped to the active category filter, matching the backend — clearing a filtered view must not
   * silently clear the categories that aren't being looked at.
   */
  async function markAllAsRead(): Promise<void> {
    const snapshot = items.value.map((item) => item.read_at)
    const previousCount = unreadCount.value
    const readAt = new Date().toISOString()

    items.value.forEach((item) => {
      if (item.read_at === null) item.read_at = readAt
    })
    unreadCount.value = 0

    try {
      await notificationsApi.markAllAsRead(category.value ?? undefined)
      // Re-read rather than trusting the optimistic zero: with a category filter active, unread
      // rows in *other* categories legitimately survive, so the badge is not necessarily 0.
      await fetchUnreadCount()
    } catch {
      items.value.forEach((item, index) => {
        item.read_at = snapshot[index]
      })
      unreadCount.value = previousCount
      error.value = 'notifications.markAllReadError'
    }
  }

  /** Used by tests and by logout, so a signed-out session leaves nothing behind in memory. */
  function reset(): void {
    items.value = []
    unreadCount.value = 0
    currentPage.value = 1
    lastPage.value = 1
    total.value = 0
    loading.value = false
    error.value = null
    status.value = 'all'
    category.value = null
  }

  return {
    items,
    unreadCount,
    currentPage,
    lastPage,
    total,
    loading,
    error,
    status,
    category,
    hasMore,
    fetch,
    loadMore,
    fetchUnreadCount,
    setStatus,
    setCategory,
    markAsRead,
    markAllAsRead,
    reset,
  }
})
