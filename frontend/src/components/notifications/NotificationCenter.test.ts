import { flushPromises, mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import NotificationCenter from './NotificationCenter.vue'
import { notificationsApi } from '@/services/notifications'
import type { AppNotification, PaginatedNotifications } from '@/types/notification'

const push = vi.fn()

vi.mock('vue-router', () => ({
  useRouter: () => ({ push }),
  RouterLink: { template: '<a><slot /></a>' },
}))

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
    created_at: new Date().toISOString(),
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

async function mountCenter(compact = false) {
  setActivePinia(createPinia())
  const wrapper = mount(NotificationCenter, { props: { compact } })
  await flushPromises()
  return wrapper
}

beforeEach(() => {
  vi.clearAllMocks()
})

describe('NotificationCenter', () => {
  it('renders a row from its translation keys and interpolated params', async () => {
    mockedApi.list.mockResolvedValue(makePage())

    const wrapper = await mountCenter()

    // Proves the design's core localization decision: the row carries keys + raw params, and the
    // rendered sentence is produced by vue-i18n here, not stored on the backend.
    expect(wrapper.text()).toContain('Appointment cancelled')
    expect(wrapper.text()).toContain('Jane Doe')
  })

  it('marks the notification read and navigates to its deep link on click', async () => {
    mockedApi.list.mockResolvedValue(makePage())
    mockedApi.markAsRead.mockResolvedValue(makeNotification({ read_at: 'now' }))

    const wrapper = await mountCenter()
    await wrapper.find('[data-testid="notification-n1"]').trigger('click')
    await flushPromises()

    expect(mockedApi.markAsRead).toHaveBeenCalledWith('n1')
    expect(push).toHaveBeenCalledWith({
      name: 'appointment-detail',
      params: { id: 'a1' },
      query: undefined,
    })
  })

  it('marks an unread row visually distinct from a read one', async () => {
    mockedApi.list.mockResolvedValue(
      makePage({
        data: [
          makeNotification({ id: 'n1' }),
          makeNotification({ id: 'n2', read_at: '2026-08-11T09:00:00+00:00' }),
        ],
        meta: { current_page: 1, last_page: 1, per_page: 15, total: 2 },
      }),
    )

    const wrapper = await mountCenter()

    expect(wrapper.find('[data-testid="notification-n1"]').attributes('data-unread')).toBe('true')
    expect(wrapper.find('[data-testid="notification-n2"]').attributes('data-unread')).toBe('false')
  })

  it('shows the empty state when there is nothing to show', async () => {
    mockedApi.list.mockResolvedValue(
      makePage({ data: [], meta: { current_page: 1, last_page: 1, per_page: 15, total: 0 } }),
    )

    const wrapper = await mountCenter()

    expect(wrapper.find('[data-testid="notifications-empty"]').exists()).toBe(true)
  })

  it('surfaces a load failure as a translated message, not a raw error', async () => {
    mockedApi.list.mockRejectedValue(new Error('network'))

    const wrapper = await mountCenter()

    expect(wrapper.find('[data-testid="notifications-error"]').text()).toBe('Could not load notifications.')
  })

  it('applies a category filter through the API rather than filtering client-side', async () => {
    mockedApi.list.mockResolvedValue(makePage())

    const wrapper = await mountCenter()
    await wrapper.find('[data-testid="notifications-category-laboratory"]').trigger('click')
    await flushPromises()

    expect(mockedApi.list).toHaveBeenLastCalledWith({ status: 'all', category: 'laboratory' }, 1)
  })

  it('toggles a category off when the active chip is clicked again', async () => {
    mockedApi.list.mockResolvedValue(makePage())

    const wrapper = await mountCenter()
    const chip = '[data-testid="notifications-category-billing"]'
    await wrapper.find(chip).trigger('click')
    await flushPromises()
    await wrapper.find(chip).trigger('click')
    await flushPromises()

    expect(mockedApi.list).toHaveBeenLastCalledWith({ status: 'all' }, 1)
  })

  it('filters to unread through the status chips', async () => {
    mockedApi.list.mockResolvedValue(makePage())

    const wrapper = await mountCenter()
    await wrapper.find('[data-testid="notifications-status-unread"]').trigger('click')
    await flushPromises()

    expect(mockedApi.list).toHaveBeenLastCalledWith({ status: 'unread' }, 1)
  })

  it('only offers "View all" in the compact panel, since the full page is that destination', async () => {
    mockedApi.list.mockResolvedValue(makePage())

    const panel = await mountCenter(true)
    expect(panel.find('[data-testid="notifications-view-all"]').exists()).toBe(true)

    const page = await mountCenter(false)
    expect(page.find('[data-testid="notifications-view-all"]').exists()).toBe(false)
  })

  it('hides "Mark all as read" when nothing is unread', async () => {
    mockedApi.list.mockResolvedValue(makePage())
    mockedApi.unreadCount.mockResolvedValue(0)

    const wrapper = await mountCenter()

    expect(wrapper.find('[data-testid="notifications-mark-all"]').exists()).toBe(false)
  })

  /**
   * `Popover`/`Drawer` unmount and remount this component's whole tree on every open/close (real
   * PrimeVue behaviour, confirmed by reading its render output — not simulated here), but the
   * Pinia store instance is shared across the app's whole lifetime. This reproduces exactly that:
   * one Pinia instance, mount → unmount ("close") → mount again ("reopen"), and asserts the second
   * open actually refetches instead of trusting the first page loaded forever.
   */
  it('refetches on every reopen so a notification that arrived while closed is shown', async () => {
    setActivePinia(createPinia())
    mockedApi.list.mockResolvedValueOnce(makePage({ data: [makeNotification({ id: 'n1' })] }))

    const first = mount(NotificationCenter)
    await flushPromises()
    expect(first.find('[data-testid="notification-n1"]').exists()).toBe(true)
    first.unmount()

    mockedApi.list.mockResolvedValueOnce(
      makePage({ data: [makeNotification({ id: 'n2' }), makeNotification({ id: 'n1' })] }),
    )

    const second = mount(NotificationCenter)
    await flushPromises()

    expect(mockedApi.list).toHaveBeenCalledTimes(2)
    expect(second.find('[data-testid="notification-n2"]').exists()).toBe(true)
    expect(second.find('[data-testid="notification-n1"]').exists()).toBe(true)
  })
})
