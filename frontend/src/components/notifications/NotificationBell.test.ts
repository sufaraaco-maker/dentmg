import { enableAutoUnmount, flushPromises, mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import NotificationBell from './NotificationBell.vue'
import { notificationsApi } from '@/services/notifications'
import { useNotificationsStore } from '@/stores/notifications'

vi.mock('vue-router', () => ({
  useRouter: () => ({ push: vi.fn() }),
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

function setViewportWidth(width: number) {
  Object.defineProperty(window, 'innerWidth', { value: width, writable: true, configurable: true })
}

function setVisibility(state: DocumentVisibilityState) {
  Object.defineProperty(document, 'visibilityState', {
    value: state,
    writable: true,
    configurable: true,
  })
}

async function mountBell() {
  setActivePinia(createPinia())
  const wrapper = mount(NotificationBell)
  await flushPromises()
  return wrapper
}

/**
 * Required, not cosmetic: this component registers a `visibilitychange` listener on the shared
 * jsdom `document`. Without auto-unmount, every previously-mounted bell keeps listening, so a
 * later test that dispatches the event sees one call per surviving instance instead of one.
 */
enableAutoUnmount(afterEach)

beforeEach(() => {
  vi.clearAllMocks()
  vi.useFakeTimers({ shouldAdvanceTime: true })
  setViewportWidth(1280)
  setVisibility('visible')
  mockedApi.unreadCount.mockResolvedValue(0)
  mockedApi.list.mockResolvedValue({
    data: [],
    meta: { current_page: 1, last_page: 1, per_page: 15, total: 0 },
  })
})

afterEach(() => {
  vi.useRealTimers()
})

describe('NotificationBell', () => {
  it('fetches the unread count on mount', async () => {
    mockedApi.unreadCount.mockResolvedValue(3)

    const wrapper = await mountBell()

    expect(mockedApi.unreadCount).toHaveBeenCalledTimes(1)
    expect(wrapper.find('[data-testid="notifications-badge"]').text()).toBe('3')
  })

  it('hides the badge entirely when there is nothing unread', async () => {
    mockedApi.unreadCount.mockResolvedValue(0)

    const wrapper = await mountBell()

    expect(wrapper.find('[data-testid="notifications-badge"]').exists()).toBe(false)
  })

  it('caps the badge at 9+', async () => {
    mockedApi.unreadCount.mockResolvedValue(42)

    const wrapper = await mountBell()

    expect(wrapper.find('[data-testid="notifications-badge"]').text()).toBe('9+')
  })

  it('polls the count on an interval while the tab is visible', async () => {
    await mountBell()
    expect(mockedApi.unreadCount).toHaveBeenCalledTimes(1)

    await vi.advanceTimersByTimeAsync(60_000)
    expect(mockedApi.unreadCount).toHaveBeenCalledTimes(2)

    await vi.advanceTimersByTimeAsync(60_000)
    expect(mockedApi.unreadCount).toHaveBeenCalledTimes(3)
  })

  /** A backgrounded tab must cost nothing — this is why the poll is visibility-gated. */
  it('does not poll while the tab is hidden', async () => {
    await mountBell()
    expect(mockedApi.unreadCount).toHaveBeenCalledTimes(1)

    setVisibility('hidden')
    await vi.advanceTimersByTimeAsync(180_000)

    expect(mockedApi.unreadCount).toHaveBeenCalledTimes(1)
  })

  it('catches up immediately when the tab becomes visible again', async () => {
    await mountBell()
    setVisibility('hidden')
    await vi.advanceTimersByTimeAsync(60_000)
    expect(mockedApi.unreadCount).toHaveBeenCalledTimes(1)

    setVisibility('visible')
    document.dispatchEvent(new Event('visibilitychange'))
    await flushPromises()

    expect(mockedApi.unreadCount).toHaveBeenCalledTimes(2)
  })

  it('stops polling once unmounted', async () => {
    const wrapper = await mountBell()
    wrapper.unmount()

    await vi.advanceTimersByTimeAsync(180_000)

    expect(mockedApi.unreadCount).toHaveBeenCalledTimes(1)
  })

  it('opens a drawer instead of a popover on a narrow viewport', async () => {
    setViewportWidth(390)

    const wrapper = await mountBell()
    await wrapper.find('[data-testid="notifications-bell"]').trigger('click')
    await flushPromises()

    // The mobile treatment swaps the layout rather than shrinking the popover.
    expect(wrapper.findComponent({ name: 'Drawer' }).exists()).toBe(true)
    expect(wrapper.findComponent({ name: 'Popover' }).exists()).toBe(false)
  })

  it('uses a popover on a desktop viewport', async () => {
    const wrapper = await mountBell()

    expect(wrapper.findComponent({ name: 'Popover' }).exists()).toBe(true)
    expect(wrapper.findComponent({ name: 'Drawer' }).exists()).toBe(false)
  })

  it('reflects a store count change without a refetch', async () => {
    const wrapper = await mountBell()
    const store = useNotificationsStore()

    store.unreadCount = 5
    await flushPromises()

    expect(wrapper.find('[data-testid="notifications-badge"]').text()).toBe('5')
  })
})
