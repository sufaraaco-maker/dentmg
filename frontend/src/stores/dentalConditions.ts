import { defineStore } from 'pinia'
import { ref } from 'vue'
import { dentalConditionsApi } from '@/services/dentalChart'
import type {
  CreateDentalConditionPayload,
  DentalCondition,
  UpdateDentalConditionPayload,
} from '@/types/dentalChart'

/**
 * Small, rarely-changing clinic configuration data (condition picker source + admin CRUD
 * backing). Cached indefinitely after first load — mirrors `stores/appointmentTypes.ts` exactly.
 */
export const useDentalConditionsStore = defineStore('dentalConditions', () => {
  const items = ref<DentalCondition[]>([])
  const loaded = ref(false)
  const loading = ref(false)
  const error = ref<string | null>(null)

  // Same concurrent-mount race as appointmentTypes.ts: every consumer calls fetchAll() on its
  // own mount, all before `loaded` flips true. Concurrent callers share the one in-flight request.
  let inFlight: Promise<void> | null = null

  async function fetchAll(force = false): Promise<void> {
    if (loaded.value && !force) return
    if (inFlight) return inFlight

    loading.value = true
    error.value = null

    inFlight = (async () => {
      try {
        items.value = await dentalConditionsApi.list()
        loaded.value = true
      } catch {
        error.value = 'dentalChart.conditions.loadError'
      } finally {
        loading.value = false
        inFlight = null
      }
    })()

    return inFlight
  }

  async function create(payload: CreateDentalConditionPayload): Promise<DentalCondition> {
    const created = await dentalConditionsApi.create(payload)
    items.value = [...items.value, created]
    return created
  }

  async function update(id: string, payload: UpdateDentalConditionPayload): Promise<DentalCondition> {
    const updated = await dentalConditionsApi.update(id, payload)
    items.value = items.value.map((item) => (item.id === id ? updated : item))
    return updated
  }

  async function remove(id: string): Promise<void> {
    await dentalConditionsApi.remove(id)
    items.value = items.value.filter((item) => item.id !== id)
  }

  function $reset() {
    items.value = []
    loaded.value = false
    loading.value = false
    error.value = null
  }

  return { items, loaded, loading, error, fetchAll, create, update, remove, $reset }
})
