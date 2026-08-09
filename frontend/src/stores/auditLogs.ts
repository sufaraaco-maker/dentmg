import { defineStore } from 'pinia'
import { ref } from 'vue'
import { api } from '@/lib/api'
import { auditLogsApi } from '@/services/auditLogs'
import type { AuditLog, AuditLogFilters } from '@/types/auditLog'
import type { AuthUser } from '@/types/user'

interface PaginatedUsersResponse {
  data: AuthUser[]
  links: { next: string | null }
}

/** Backs `AuditLogsView.vue` (Phase 4 Step 4, general admin-only viewer, design doc §2.6). */
export const useAuditLogsStore = defineStore('auditLogs', () => {
  const logs = ref<AuditLog[]>([])
  const currentPage = ref(1)
  const lastPage = ref(1)
  const perPage = ref(15)
  const total = ref(0)
  const loading = ref(false)
  const error = ref<string | null>(null)

  // Powers the "User" filter dropdown — loaded once and cached, not re-fetched per filter change.
  const filterUsers = ref<AuthUser[]>([])
  const filterUsersLoaded = ref(false)

  async function fetch(filters: AuditLogFilters, page = 1): Promise<void> {
    loading.value = true
    error.value = null

    try {
      const result = await auditLogsApi.list(filters, page)
      logs.value = result.data
      currentPage.value = result.meta.current_page
      lastPage.value = result.meta.last_page
      perPage.value = result.meta.per_page
      total.value = result.meta.total
    } catch {
      error.value = 'auditLog.loadError'
    } finally {
      loading.value = false
    }
  }

  /**
   * Paginates through the generic `GET /users` endpoint the same way
   * `services/appointments/providersApi.ts` does for dentists, but without the role filter — this
   * filter needs every staff account, not just dentists, so that existing helper doesn't fit.
   */
  async function fetchFilterUsers(): Promise<void> {
    if (filterUsersLoaded.value) return

    const users: AuthUser[] = []
    let page = 1
    let hasNextPage = true

    while (hasNextPage) {
      const { data } = await api.get<PaginatedUsersResponse>('/users', { params: { page } })
      users.push(...data.data)
      hasNextPage = data.links.next !== null
      page += 1
    }

    filterUsers.value = users
    filterUsersLoaded.value = true
  }

  function $reset() {
    logs.value = []
    currentPage.value = 1
    lastPage.value = 1
    perPage.value = 15
    total.value = 0
    loading.value = false
    error.value = null
    filterUsers.value = []
    filterUsersLoaded.value = false
  }

  return {
    logs,
    currentPage,
    lastPage,
    perPage,
    total,
    loading,
    error,
    filterUsers,
    filterUsersLoaded,
    fetch,
    fetchFilterUsers,
    $reset,
  }
})
