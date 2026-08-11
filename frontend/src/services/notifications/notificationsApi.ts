import { api } from '@/lib/api'
import type {
  AppNotification,
  NotificationCategory,
  NotificationFilters,
  PaginatedNotifications,
} from '@/types/notification'

/**
 * Notification System (Phase 5A) API layer, design doc §7.
 *
 * `unreadCount` is deliberately its own endpoint rather than being read off a list response: the
 * bell polls it every 60 seconds per open session, and must not deserialize a page of rows just to
 * render a badge.
 */
export const notificationsApi = {
  async list(filters: NotificationFilters, page: number): Promise<PaginatedNotifications> {
    const { data } = await api.get<PaginatedNotifications>('/notifications', {
      params: { ...filters, page },
    })
    return data
  },

  async unreadCount(): Promise<number> {
    const { data } = await api.get<{ count: number }>('/notifications/unread-count')
    return data.count
  },

  async markAsRead(id: string): Promise<AppNotification> {
    const { data } = await api.post<AppNotification>(`/notifications/${id}/read`)
    return data
  },

  /** Scoped to `category` when one is active, so clearing a filtered view leaves the rest unread. */
  async markAllAsRead(category?: NotificationCategory): Promise<number> {
    const { data } = await api.post<{ marked: number }>('/notifications/read-all', { category })
    return data.marked
  },
}
