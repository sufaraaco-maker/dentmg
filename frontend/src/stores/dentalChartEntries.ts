import { defineStore } from 'pinia'
import { ref } from 'vue'
import { dentalChartEntriesApi } from '@/services/dentalChart'
import type {
  CreateDentalChartEntryPayload,
  DentalChartEntry,
  UpdateDentalChartEntryPayload,
} from '@/types/dentalChart'

/**
 * One patient's full chart, cached for as long as that patient stays open. There is no
 * `GET /api/dental-chart-entries/{id}` endpoint (backend plan §1.9 — only the per-patient list
 * exists), so unlike `stores/appointments.ts`'s `fetchOne` rehydration, every mutation here
 * re-fetches the whole list instead: `store`/`update`/`complete`/`cancel` don't eager-load
 * `dental_condition`/`dentist`/`created_by`/`updated_by` (only the list endpoint does — the same
 * documented gap as Appointments, see TECH_DEBT.md), so a raw mutation response would render with
 * those fields missing until the next full reload anyway.
 */
export const useDentalChartEntriesStore = defineStore('dentalChartEntries', () => {
  const entries = ref<DentalChartEntry[]>([])
  const patientId = ref<string | null>(null)
  const loaded = ref(false)
  const loading = ref(false)
  const error = ref<string | null>(null)

  let inFlight: Promise<void> | null = null

  async function fetchForPatient(id: string, force = false): Promise<void> {
    if (loaded.value && patientId.value === id && !force) return
    if (inFlight && patientId.value === id) return inFlight

    patientId.value = id
    loading.value = true
    error.value = null

    inFlight = (async () => {
      try {
        entries.value = await dentalChartEntriesApi.list(id)
        loaded.value = true
      } catch {
        error.value = 'dentalChart.loadError'
      } finally {
        loading.value = false
        inFlight = null
      }
    })()

    return inFlight
  }

  async function create(id: string, payload: CreateDentalChartEntryPayload): Promise<DentalChartEntry> {
    const created = await dentalChartEntriesApi.create(id, payload)
    await fetchForPatient(id, true)
    return created
  }

  async function update(id: string, payload: UpdateDentalChartEntryPayload): Promise<DentalChartEntry> {
    const updated = await dentalChartEntriesApi.update(id, payload)
    if (patientId.value) await fetchForPatient(patientId.value, true)
    return updated
  }

  async function complete(id: string): Promise<DentalChartEntry> {
    const completed = await dentalChartEntriesApi.complete(id)
    if (patientId.value) await fetchForPatient(patientId.value, true)
    return completed
  }

  async function cancel(id: string): Promise<DentalChartEntry> {
    const cancelled = await dentalChartEntriesApi.cancel(id)
    if (patientId.value) await fetchForPatient(patientId.value, true)
    return cancelled
  }

  async function remove(id: string): Promise<void> {
    await dentalChartEntriesApi.remove(id)
    entries.value = entries.value.filter((entry) => entry.id !== id)
  }

  function $reset() {
    entries.value = []
    patientId.value = null
    loaded.value = false
    loading.value = false
    error.value = null
  }

  return {
    entries,
    patientId,
    loaded,
    loading,
    error,
    fetchForPatient,
    create,
    update,
    complete,
    cancel,
    remove,
    $reset,
  }
})
