import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { api, fetchCsrfCookie } from '@/lib/api'
import { useNotificationsStore } from '@/stores/notifications'
import type { AuthUser } from '@/types/user'

export const useAuthStore = defineStore('auth', () => {
  const user = ref<AuthUser | null>(null)
  const initialized = ref(false)

  const isAuthenticated = computed(() => user.value !== null)
  const isAdmin = computed(() => user.value?.role === 'admin')
  const isDentist = computed(() => user.value?.role === 'dentist')
  const isReceptionist = computed(() => user.value?.role === 'receptionist')
  /** Create/update/reschedule/cancel/check-in — see docs/modules/appointments-ui-design.md §1.10. */
  const canManageAppointments = computed(() => isAdmin.value || isReceptionist.value)
  /** Mirrors the backend's `view-financial-reports` Gate (admin-only) — gates Reports' financial
   *  endpoints and, since Dashboard 2.0, `/dashboard/financial-summary` too. */
  const canViewFinancials = computed(() => isAdmin.value)

  async function login(email: string, password: string) {
    await fetchCsrfCookie()
    const { data } = await api.post<AuthUser>('/login', { email, password })
    user.value = data
  }

  async function logout() {
    await api.post('/logout')
    user.value = null
    // Notifications persist in memory for the app's whole lifetime (NotificationBell lives in the
    // always-mounted DefaultLayout, not a per-route view), so a same-tab login as a different user
    // would otherwise render the previous session's notifications until the next poll/fetch
    // overwrites them — a real PHI leak between two accounts on one device. Must be cleared here,
    // not left to the next fetch to overwrite, since that fetch is async and the stale rows would
    // render for that window.
    useNotificationsStore().reset()
  }

  async function fetchUser() {
    try {
      const { data } = await api.get<AuthUser>('/user')
      user.value = data
    } catch {
      user.value = null
    } finally {
      initialized.value = true
    }
  }

  return {
    user,
    initialized,
    isAuthenticated,
    isAdmin,
    isDentist,
    isReceptionist,
    canManageAppointments,
    canViewFinancials,
    login,
    logout,
    fetchUser,
  }
})
