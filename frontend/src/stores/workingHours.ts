import { defineStore } from 'pinia'
import { reactive, ref } from 'vue'
import { workingHoursApi } from '@/services/appointments'
import type { CreateWorkingHourPayload, DentistWorkingHour } from '@/types/appointment'

/**
 * Per-dentist working-hours cache, backing the Board's business-hours overlay and the
 * Dentist Schedule CRUD screen. See docs/modules/appointments-ui-design.md §10.1.
 */
export const useWorkingHoursStore = defineStore('workingHours', () => {
  const byDentist = reactive(new Map<string, DentistWorkingHour[]>())
  const loading = ref(false)

  async function fetchForDentist(userId: string, force = false): Promise<void> {
    if (!force && byDentist.has(userId)) return

    loading.value = true

    try {
      byDentist.set(userId, await workingHoursApi.listForDentist(userId))
    } finally {
      loading.value = false
    }
  }

  async function create(userId: string, payload: CreateWorkingHourPayload): Promise<DentistWorkingHour> {
    const created = await workingHoursApi.create(userId, payload)
    byDentist.set(userId, [...(byDentist.get(userId) ?? []), created])
    return created
  }

  async function remove(userId: string, id: string): Promise<void> {
    await workingHoursApi.remove(userId, id)
    byDentist.set(
      userId,
      (byDentist.get(userId) ?? []).filter((entry) => entry.id !== id),
    )
  }

  function $reset() {
    byDentist.clear()
    loading.value = false
  }

  return { byDentist, loading, fetchForDentist, create, remove, $reset }
})
