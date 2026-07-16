import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it } from 'vitest'
import { router } from './index'
import { useAuthStore } from '@/stores/auth'
import type { AuthUser, UserRole } from '@/types/user'

function makeUser(role: UserRole): AuthUser {
  return { id: 'u1', name: 'Test', email: 'test@example.com', role }
}

beforeEach(() => {
  setActivePinia(createPinia())
})

describe('router authorization guard', () => {
  it('redirects unauthenticated users to login for a protected route', async () => {
    const auth = useAuthStore()
    auth.initialized = true
    auth.user = null

    await router.push({ name: 'users' })
    expect(router.currentRoute.value.name).toBe('login')
  })

  it('redirects an authenticated non-admin away from the admin-only Users route (URL access, not just hidden nav)', async () => {
    const auth = useAuthStore()
    auth.initialized = true
    auth.user = makeUser('dentist')

    await router.push({ name: 'users' })
    expect(router.currentRoute.value.name).toBe('forbidden')
  })

  it('also blocks a receptionist from the admin-only Users route', async () => {
    const auth = useAuthStore()
    auth.initialized = true
    auth.user = makeUser('receptionist')

    await router.push({ name: 'users' })
    expect(router.currentRoute.value.name).toBe('forbidden')
  })

  it('allows an authenticated admin to reach the Users route', async () => {
    const auth = useAuthStore()
    auth.initialized = true
    auth.user = makeUser('admin')

    await router.push({ name: 'users' })
    expect(router.currentRoute.value.name).toBe('users')
  })

  it('does not restrict routes with no roles meta beyond requiring authentication', async () => {
    const auth = useAuthStore()
    auth.initialized = true
    auth.user = makeUser('dentist')

    await router.push({ name: 'patients' })
    expect(router.currentRoute.value.name).toBe('patients')
  })

  it('redirects an already-authenticated user away from the guest-only login page', async () => {
    const auth = useAuthStore()
    auth.initialized = true
    auth.user = makeUser('receptionist')

    await router.push({ name: 'login' })
    expect(router.currentRoute.value.name).toBe('dashboard')
  })
})
