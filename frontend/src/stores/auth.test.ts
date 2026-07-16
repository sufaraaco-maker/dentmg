import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it } from 'vitest'
import { useAuthStore } from './auth'
import type { AuthUser, UserRole } from '@/types/user'

function makeUser(role: UserRole): AuthUser {
  return { id: 'u1', name: 'Test', email: 'test@example.com', role }
}

beforeEach(() => {
  setActivePinia(createPinia())
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
})
