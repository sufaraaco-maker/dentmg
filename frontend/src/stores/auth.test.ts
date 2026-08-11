import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { useAuthStore } from './auth'
import { useNotificationsStore } from './notifications'
import { api } from '@/lib/api'
import type { AuthUser, UserRole } from '@/types/user'

vi.mock('@/lib/api', () => ({
  api: { post: vi.fn().mockResolvedValue({ data: null }) },
  fetchCsrfCookie: vi.fn().mockResolvedValue(undefined),
}))

function makeUser(role: UserRole): AuthUser {
  return { id: 'u1', name: 'Test', email: 'test@example.com', role }
}

beforeEach(() => {
  setActivePinia(createPinia())
  vi.clearAllMocks()
})

describe('useAuthStore role getters', () => {
  it('isAdmin/isDentist/isReceptionist reflect the current user role', () => {
    const store = useAuthStore()

    store.user = makeUser('admin')
    expect(store.isAdmin).toBe(true)
    expect(store.isDentist).toBe(false)
    expect(store.isReceptionist).toBe(false)

    store.user = makeUser('dentist')
    expect(store.isAdmin).toBe(false)
    expect(store.isDentist).toBe(true)

    store.user = makeUser('receptionist')
    expect(store.isReceptionist).toBe(true)
  })

  it('all role getters are false when logged out', () => {
    const store = useAuthStore()
    store.user = null

    expect(store.isAdmin).toBe(false)
    expect(store.isDentist).toBe(false)
    expect(store.isReceptionist).toBe(false)
    expect(store.canManageAppointments).toBe(false)
  })

  it('canManageAppointments is true for admin and receptionist, false for dentist', () => {
    const store = useAuthStore()

    store.user = makeUser('admin')
    expect(store.canManageAppointments).toBe(true)

    store.user = makeUser('receptionist')
    expect(store.canManageAppointments).toBe(true)

    store.user = makeUser('dentist')
    expect(store.canManageAppointments).toBe(false)
  })

  it('canViewFinancials is true only for admin', () => {
    const store = useAuthStore()

    store.user = makeUser('admin')
    expect(store.canViewFinancials).toBe(true)

    store.user = makeUser('dentist')
    expect(store.canViewFinancials).toBe(false)

    store.user = makeUser('receptionist')
    expect(store.canViewFinancials).toBe(false)
  })
})

describe('useAuthStore logout', () => {
  /**
   * SECURITY: `NotificationBell` lives in the always-mounted `DefaultLayout`, so its store outlives
   * any one user's session in memory. Without this, logging out and a different user logging back
   * in on the same tab would briefly (and sometimes not so briefly) render the previous user's
   * notifications — a real cross-account PHI leak, not a cosmetic staleness bug.
   */
  it('clears the notifications store so the next session starts clean', async () => {
    const auth = useAuthStore()
    const notifications = useNotificationsStore()
    auth.user = makeUser('admin')
    notifications.items = [
      {
        id: 'n1',
        category: 'appointments',
        subject_type: 'App\\Models\\Appointment',
        subject_id: 'a1',
        patient_id: 'p1',
        data: {
          type: 'appointment.cancelled',
          titleKey: 'x',
          bodyKey: 'x',
          params: {},
          actorName: 'Jane Doe',
          route: { name: 'appointment-detail', params: { id: 'a1' } },
        },
        read_at: null,
        created_at: new Date().toISOString(),
      },
    ]
    notifications.unreadCount = 3

    await auth.logout()

    expect(vi.mocked(api.post)).toHaveBeenCalledWith('/logout')
    expect(auth.user).toBeNull()
    expect(notifications.items).toEqual([])
    expect(notifications.unreadCount).toBe(0)
  })
})
