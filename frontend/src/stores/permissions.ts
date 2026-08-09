import { defineStore } from 'pinia'
import { ref } from 'vue'
import { permissionsApi } from '@/services/permissions'
import type { Permission, RolePermissionMatrix } from '@/types/permission'

/**
 * Backs `PermissionsView.vue` (Phase 4 Step 4). `catalog` (the fixed, backend-owned permission
 * list) and `matrix` (the admin-editable role assignment) are fetched together since the view
 * always needs both to render a single row. `updateMatrix` returns a boolean rather than throwing
 * so the view can distinguish the server-enforced self-lockout rejection (422, design doc §1.4)
 * from a generic failure without re-parsing the Axios error itself.
 */
export const usePermissionsStore = defineStore('permissions', () => {
  const catalog = ref<Permission[]>([])
  const matrix = ref<RolePermissionMatrix | null>(null)
  const loading = ref(false)
  const error = ref<string | null>(null)
  const saving = ref(false)
  const saveError = ref<string | null>(null)

  async function fetchAll(): Promise<void> {
    loading.value = true
    error.value = null

    try {
      const [catalogResult, matrixResult] = await Promise.all([
        permissionsApi.catalog(),
        permissionsApi.matrix(),
      ])
      catalog.value = catalogResult
      matrix.value = matrixResult
    } catch {
      error.value = 'permissions.loadError'
    } finally {
      loading.value = false
    }
  }

  async function updateMatrix(assignments: RolePermissionMatrix): Promise<boolean> {
    saving.value = true
    saveError.value = null

    try {
      matrix.value = await permissionsApi.updateMatrix(assignments)
      return true
    } catch (err: unknown) {
      const status = (err as { response?: { status?: number } })?.response?.status
      saveError.value = status === 422 ? 'permissions.selfLockoutError' : 'permissions.saveError'
      return false
    } finally {
      saving.value = false
    }
  }

  function $reset() {
    catalog.value = []
    matrix.value = null
    loading.value = false
    error.value = null
    saving.value = false
    saveError.value = null
  }

  return { catalog, matrix, loading, error, saving, saveError, fetchAll, updateMatrix, $reset }
})
