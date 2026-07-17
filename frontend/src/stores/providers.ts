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

  // Every consumer (CalendarFilters, DentistSelect, AppointmentsView, ...) calls fetchAll() on
  // its own mount, all before `loaded` flips true — without this, each one would fire its own
  // GET /api/users, and briefly toggling `loading` true→false→true→... as those redundant
  // requests interleave is also what caused a transient duplicated-placeholder render in the
  // MultiSelects bound to it. Concurrent callers now share the one in-flight request instead.
  let inFlight: Promise<void> | null = null

  async function fetchAll(force = false): Promise<void> {
    if (loaded.value && !force) return
    if (inFlight) return inFlight

    loading.value = true

    inFlight = (async () => {
      try {
        items.value = await providersApi.listAll()
        loaded.value = true
      } finally {
        loading.value = false
        inFlight = null
      }
    })()

    return inFlight
  }

  function $reset() {
    items.value = []
    loaded.value = false
    loading.value = false
  }

  return { items, loaded, loading, fetchAll, $reset }
})
