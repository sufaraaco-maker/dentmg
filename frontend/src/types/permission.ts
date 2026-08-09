import type { UserRole } from './user'

export interface Permission {
  key: string
  group: string
  description: string | null
}

/** `GET`/`PUT /role-permissions` response shape — one permission-key array per role. */
export type RolePermissionMatrix = Record<UserRole, string[]>
