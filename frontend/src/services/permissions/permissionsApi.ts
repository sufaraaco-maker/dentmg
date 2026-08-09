import { api } from '@/lib/api'
import type { Permission, RolePermissionMatrix } from '@/types/permission'

export const permissionsApi = {
  async catalog(): Promise<Permission[]> {
    const { data } = await api.get<Permission[]>('/permissions')
    return data
  },

  async matrix(): Promise<RolePermissionMatrix> {
    const { data } = await api.get<RolePermissionMatrix>('/role-permissions')
    return data
  },

  async updateMatrix(assignments: RolePermissionMatrix): Promise<RolePermissionMatrix> {
    const { data } = await api.put<RolePermissionMatrix>('/role-permissions', { assignments })
    return data
  },
}
