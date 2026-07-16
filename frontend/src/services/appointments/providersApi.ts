import { api } from '@/lib/api'
import type { AuthUser } from '@/types/user'

interface PaginatedUsersResponse {
  data: AuthUser[]
  links: {
    first: string | null
    last: string | null
    prev: string | null
    next: string | null
  }
}

/**
 * Temporary workaround for the missing dedicated dentist/provider-list endpoint — see
 * TECH_DEBT.md ("No dedicated dentists/providers listing endpoint") and
 * docs/modules/appointments-ui-design.md §10.2/§11.5. Paginates through the generic
 * `GET /api/users` endpoint (fixed at 15/page, no role filter) and filters
 * `role === 'dentist'` client-side. Replace this function's internals — not its callers —
 * once a real endpoint exists.
 */
export const providersApi = {
  async listAll(): Promise<AuthUser[]> {
    const dentists: AuthUser[] = []
    let page = 1
    let hasNextPage = true

    while (hasNextPage) {
      const { data } = await api.get<PaginatedUsersResponse>('/users', { params: { page } })
      dentists.push(...data.data.filter((user) => user.role === 'dentist'))
      hasNextPage = data.links.next !== null
      page += 1
    }

    return dentists
  },
}
