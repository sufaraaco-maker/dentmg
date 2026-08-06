import { defineStore } from 'pinia'
import { ref } from 'vue'
import { api } from '@/lib/api'
import type { CreateSupplierPayload, Supplier, UpdateSupplierPayload } from '@/types/inventory'

/**
 * Small, rarely-changing clinic configuration data (dropdown source + admin CRUD backing) —
 * mirrors `stores/appointmentTypes.ts` exactly (backend design doc §5/§11).
 */
export const useSuppliersStore = defineStore('suppliers', () => {
  const items = ref<Supplier[]>([])
  const loaded = ref(false)
  const loading = ref(false)
  const error = ref<string | null>(null)

  let inFlight: Promise<void> | null = null

  async function fetchAll(force = false): Promise<void> {
    if (loaded.value && !force) return
    if (inFlight) return inFlight

    loading.value = true
    error.value = null

    inFlight = (async () => {
      try {
        const { data } = await api.get<Supplier[]>('/suppliers')
        items.value = data
        loaded.value = true
      } catch {
        error.value = 'inventory.suppliers.loadError'
      } finally {
        loading.value = false
        inFlight = null
      }
    })()

    return inFlight
  }

  async function create(payload: CreateSupplierPayload): Promise<Supplier> {
    const { data } = await api.post<Supplier>('/suppliers', payload)
    items.value = [...items.value, data]
    return data
  }

  async function update(id: string, payload: UpdateSupplierPayload): Promise<Supplier> {
    const { data } = await api.put<Supplier>(`/suppliers/${id}`, payload)
    items.value = items.value.map((item) => (item.id === id ? data : item))
    return data
  }

  async function deactivate(id: string): Promise<void> {
    await api.delete(`/suppliers/${id}`)
    items.value = items.value.map((item) => (item.id === id ? { ...item, is_active: false } : item))
  }

  function $reset() {
    items.value = []
    loaded.value = false
    loading.value = false
    error.value = null
  }

  return { items, loaded, loading, error, fetchAll, create, update, deactivate, $reset }
})
