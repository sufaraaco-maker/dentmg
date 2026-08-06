import { defineStore } from 'pinia'
import { ref } from 'vue'
import { appointmentTypesApi } from '@/services/appointments'
import type {
  AppointmentType,
  CreateAppointmentTypePayload,
  UpdateAppointmentTypePayload,
} from '@/types/appointment'

/**
 * Small, rarely-changing clinic configuration data (dropdown source + admin CRUD backing).
 * Cached indefinitely after first load — see docs/modules/appointments-ui-design.md §10.1.
 */
export const useAppointmentTypesStore = defineStore('appointmentTypes', () => {
  const items = ref<AppointmentType[]>([])
  const loaded = ref(false)
  const loading = ref(false)
  const error = ref<string | null>(null)

  // Same concurrent-mount race as providers.ts: every consumer (CalendarFilters,
  // AppointmentTypeSelect, ...) calls fetchAll() on its own mount, all before `loaded` flips
  // true. Concurrent callers now share the one in-flight request instead of each firing its own.
  let inFlight: Promise<void> | null = null

  async function fetchAll(force = false): Promise<void> {
    if (loaded.value && !force) return
    if (inFlight) return inFlight

    loading.value = true
    error.value = null

    inFlight = (async () => {
      try {
        items.value = await appointmentTypesApi.list()
        loaded.value = true
      } catch {
        error.value = 'appointments.types.loadError'
      } finally {
        loading.value = false
        inFlight = null
      }
    })()

    return inFlight
  }

  async function create(payload: CreateAppointmentTypePayload): Promise<AppointmentType> {
    const created = await appointmentTypesApi.create(payload)
    items.value = [...items.value, created]
    return created
  }

  async function update(id: string, payload: UpdateAppointmentTypePayload): Promise<AppointmentType> {
    const updated = await appointmentTypesApi.update(id, payload)
    items.value = items.value.map((item) => (item.id === id ? updated : item))
    return updated
  }

  async function remove(id: string): Promise<void> {
    await appointmentTypesApi.remove(id)
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
