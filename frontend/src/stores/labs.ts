import { defineStore } from 'pinia'
import { ref } from 'vue'
import { api } from '@/lib/api'
import type { CreateLabPayload, Lab, UpdateLabPayload } from '@/types/laboratory'

/**
 * Small, rarely-changing clinic configuration data (dropdown source + admin CRUD backing) —
 * mirrors `stores/suppliers.ts` exactly (design doc §5/§6).
 */
export const useLabsStore = defineStore('labs', () => {
  const items = ref<Lab[]>([])
  const loaded = ref(false)
  const loading = ref(false)

  let inFlight: Promise<void> | null = null

  async function fetchAll(force = false): Promise<void> {
    if (loaded.value && !force) return
    if (inFlight) return inFlight

    loading.value = true

    inFlight = (async () => {
      try {
        const { data } = await api.get<Lab[]>('/labs')
        items.value = data
        loaded.value = true
      } finally {
        loading.value = false
        inFlight = null
      }
    })()

    return inFlight
  }

  async function create(payload: CreateLabPayload): Promise<Lab> {
    const { data } = await api.post<Lab>('/labs', payload)
    items.value = [...items.value, data]
    return data
  }

  async function update(id: string, payload: UpdateLabPayload): Promise<Lab> {
    const { data } = await api.put<Lab>(`/labs/${id}`, payload)
    items.value = items.value.map((item) => (item.id === id ? data : item))
    return data
  }

  async function deactivate(id: string): Promise<void> {
    await api.delete(`/labs/${id}`)
    items.value = items.value.map((item) => (item.id === id ? { ...item, is_active: false } : item))
  }

  function $reset() {
    items.value = []
    loaded.value = false
    loading.value = false
  }

  return { items, loaded, loading, fetchAll, create, update, deactivate, $reset }
})
