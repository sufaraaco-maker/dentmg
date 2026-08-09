export type AuditLogAction =
  | 'created'
  | 'updated'
  | 'deleted'
  | 'login_succeeded'
  | 'login_failed'
  | 'logged_out'
  | 'role_permissions_updated'

export const AUDIT_LOG_ACTIONS: AuditLogAction[] = [
  'created',
  'updated',
  'deleted',
  'login_succeeded',
  'login_failed',
  'logged_out',
  'role_permissions_updated',
]

/** `AuditLogResource` shape (Phase 4 Step 3 design doc §2.6) — `auditable_id`/`old_values`/
 *  `changes`/`context` are all nullable since a targetless event (e.g. `login_failed`) has no
 *  resolvable model and no field-level diff. */
export interface AuditLog {
  id: string
  action: AuditLogAction
  auditable_type: string | null
  auditable_id: string | null
  changes: Record<string, unknown> | null
  old_values: Record<string, unknown> | null
  context: Record<string, unknown> | null
  ip_address: string | null
  user_agent: string | null
  user: { id: string; name: string } | null
  created_at: string
}

export interface AuditLogFilters {
  user_id?: string
  auditable_type?: string
  action?: AuditLogAction
  date_from?: string
  date_to?: string
}

export interface PaginatedAuditLogs {
  data: AuditLog[]
  meta: { current_page: number; last_page: number; per_page: number; total: number }
}
