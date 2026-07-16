import { defineStore } from 'pinia'
import { ref } from 'vue'
import { providersApi } from '@/services/appointments'
import type { AuthUser } from '@/types/user'

/**
 * Deliberately temporary — NOT a permanent domain model. Exists only because there is no
 * dedicated dentist/provider-list endpoint yet (see TECH_DEBT.md and
 * docs/modules/appointments-ui-design.md §10.2). Read-only: dentist/provider account
 * management stays entirely in the Users module. Once a real `/api/dentists` (or
 * `/api/providers`) endpoint exists, only `providersApi.listAll()`'s internals need to change —
 * this store's shape and every consumer of `items` stay the same.
 */
export const useProvidersStore = defineStore('providers', () => {
  const items = ref<AuthUser[]>([])
  const loaded = ref(false)
  const loading = ref(false)

  async function fetchAll(force = false): Promise<void> {
    if (loaded.value && !force) return

    loading.value = true

    try {
      items.value = await providersApi.listAll()
      loaded.value = true
    } finally {
      loading.value = false
    }
  }

  function $reset() {
    items.value = []
    loaded.value = false
    loading.value = false
  }

  return { items, loaded, loading, fetchAll, $reset }
})
