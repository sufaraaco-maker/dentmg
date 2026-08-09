import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { api, fetchCsrfCookie } from '@/lib/api'
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
