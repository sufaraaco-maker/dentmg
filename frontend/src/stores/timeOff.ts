import { defineStore } from 'pinia'
import { reactive, ref } from 'vue'
import { timeOffApi } from '@/services/appointments'
import type { CreateTimeOffPayload, DentistTimeOff } from '@/types/appointment'

/**
 * Per-dentist time-off cache, backing the Board's time-off overlay and the Dentist Schedule
 * CRUD screen. See docs/modules/appointments-ui-design.md §10.1.
 */
export const useTimeOffStore = defineStore('timeOff', () => {
  const byDentist = reactive(new Map<string, DentistTimeOff[]>())
  const loading = ref(false)

  async function fetchForDentist(userId: string, force = false): Promise<void> {
    if (!force && byDentist.has(userId)) return

    loading.value = true

    try {
      byDentist.set(userId, await timeOffApi.listForDentist(userId))
    } finally {
      loading.value = false
    }
  }

  async function create(userId: string, payload: CreateTimeOffPayload): Promise<DentistTimeOff> {
    const created = await timeOffApi.create(userId, payload)
    byDentist.set(userId, [...(byDentist.get(userId) ?? []), created])
    return created
  }

  async function remove(userId: string, id: string): Promise<void> {
    await timeOffApi.remove(userId, id)
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
