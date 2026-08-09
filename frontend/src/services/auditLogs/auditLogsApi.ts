import { api } from '@/lib/api'
import type { AuditLogFilters, PaginatedAuditLogs } from '@/types/auditLog'

export const auditLogsApi = {
  async list(filters: AuditLogFilters, page: number): Promise<PaginatedAuditLogs> {
    const { data } = await api.get<PaginatedAuditLogs>('/audit-logs', {
      params: { ...filters, page },
    })
    return data
  },
}
