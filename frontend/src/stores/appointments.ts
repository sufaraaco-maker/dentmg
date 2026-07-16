import { defineStore } from 'pinia'
import { computed, reactive, ref } from 'vue'
import { appointmentsApi } from '@/services/appointments'
import { toLocalDateString } from '@/lib/date'
import { useCalendarStore } from './calendar'
import type {
  Appointment,
  CancelAppointmentPayload,
  CreateAppointmentPayload,
  MarkNoShowPayload,
  UpdateAppointmentPayload,
} from '@/types/appointment'

interface LoadedInterval {
  start: number
  end: number
}

/** How far outside the currently-viewed range a cached appointment may sit before eviction. */
const CACHE_EVICTION_PADDING_DAYS = 60

function toTime(date: Date): number {
  return date.getTime()
}

/** Sorts and merges overlapping/adjacent intervals so `isRangeLoaded` can check coverage in one pass. */
function mergeIntervals(intervals: LoadedInterval[]): LoadedInterval[] {
  if (intervals.length === 0) return []

  const sorted = [...intervals].sort((a, b) => a.start - b.start)
  const merged: LoadedInterval[] = [{ ...sorted[0] }]

  for (let i = 1; i < sorted.length; i += 1) {
    const last = merged[merged.length - 1]
    const current = sorted[i]

    if (current.start <= last.end) {
      last.end = Math.max(last.end, current.end)
    } else {
      merged.push({ ...current })
    }
  }

  return merged
}

/**
 * Range-keyed cache of Appointment records, shared by the Board/List/Dashboard/Detail views.
 * Owns every mutation (create/update/reschedule/status-transitions/delete). See
 * docs/modules/appointments-ui-design.md §10.1, §11.3 (pagination forward-compat),
 * §11.4 (post-mutation rehydration), §13.4 (range cache + eviction).
 */
export const useAppointmentsStore = defineStore('appointments', () => {
  const cache = reactive(new Map<string, Appointment>())
  const loadedRanges = ref<LoadedInterval[]>([])
  const loading = ref(false)
  const error = ref<string | null>(null)

  const calendar = useCalendarStore()

  /** Appointments in the calendar store's current range, narrowed by its active filters. */
  const filteredAppointments = computed(() => {
    const { start, end } = calendar.currentRange
    const startTime = toTime(start)
    const endTime = toTime(end)
    const filters = calendar.filters

    return Array.from(cache.values()).filter((appointment) => {
      const appointmentStart = new Date(appointment.start_at).getTime()

      if (appointmentStart < startTime || appointmentStart > endTime) return false
      if (filters.dentistIds.length && !filters.dentistIds.includes(appointment.dentist_id)) return false
      if (filters.statuses.length && !filters.statuses.includes(appointment.status)) return false
      if (filters.typeIds.length && !filters.typeIds.includes(appointment.appointment_type_id)) return false
      if (filters.patientId && appointment.patient_id !== filters.patientId) return false

      return true
    })
  })

  function isRangeLoaded(start: Date, end: Date): boolean {
    const startTime = toTime(start)
    const endTime = toTime(end)

    return loadedRanges.value.some((interval) => interval.start <= startTime && interval.end >= endTime)
  }

  function markRangeLoaded(start: Date, end: Date) {
    loadedRanges.value = mergeIntervals([...loadedRanges.value, { start: toTime(start), end: toTime(end) }])
  }

  function evictOutsideRange(start: Date, end: Date) {
    const paddingMs = CACHE_EVICTION_PADDING_DAYS * 24 * 60 * 60 * 1000
    const lowerBound = toTime(start) - paddingMs
    const upperBound = toTime(end) + paddingMs

    for (const [id, appointment] of cache.entries()) {
      const appointmentStart = new Date(appointment.start_at).getTime()

      if (appointmentStart < lowerBound || appointmentStart > upperBound) {
        cache.delete(id)
      }
    }
  }

  function upsert(appointment: Appointment) {
    cache.set(appointment.id, appointment)
  }

  /**
   * Fetches a date range and merges it into the cache. Skips the network call entirely when
   * the range is already fully covered by a previously-loaded interval, unless `force` is set.
   */
  async function fetchRange(start: Date, end: Date, force = false): Promise<void> {
    if (!force && isRangeLoaded(start, end)) return

    loading.value = true
    error.value = null

    try {
      const results = await appointmentsApi.list({
        date_from: toLocalDateString(start),
        date_to: toLocalDateString(end),
      })
      results.forEach(upsert)
      markRangeLoaded(start, end)
      evictOutsideRange(start, end)
    } catch {
      error.value = 'appointments.loadError'
    } finally {
      loading.value = false
    }
  }

  /** Fetches and caches a single appointment — eager-loads patient/dentist/type (index/show do). */
  async function fetchOne(id: string): Promise<Appointment> {
    const appointment = await appointmentsApi.get(id)
    upsert(appointment)
    return appointment
  }

  /**
   * Every mutation below re-fetches the single appointment after the write, because mutation
   * endpoints don't eager-load patient/dentist/appointment_type the way index/show do (a
   * documented, temporary backend gap — see TECH_DEBT.md and §11.4 of the design doc). Remove
   * the extra `fetchOne` call here once the backend eager-loads those relations on mutation
   * responses; no other code needs to change.
   */
  async function create(payload: CreateAppointmentPayload): Promise<Appointment> {
    const created = await appointmentsApi.create(payload)
    return fetchOne(created.id)
  }

  async function update(id: string, payload: UpdateAppointmentPayload): Promise<Appointment> {
    await appointmentsApi.update(id, payload)
    return fetchOne(id)
  }

  async function confirm(id: string): Promise<Appointment> {
    await appointmentsApi.confirm(id)
    return fetchOne(id)
  }

  async function checkIn(id: string): Promise<Appointment> {
    await appointmentsApi.checkIn(id)
    return fetchOne(id)
  }

  async function start(id: string): Promise<Appointment> {
    await appointmentsApi.start(id)
    return fetchOne(id)
  }

  async function complete(id: string): Promise<Appointment> {
    await appointmentsApi.complete(id)
    return fetchOne(id)
  }

  async function cancel(id: string, payload: CancelAppointmentPayload = {}): Promise<Appointment> {
    await appointmentsApi.cancel(id, payload)
    return fetchOne(id)
  }

  async function noShow(id: string, payload: MarkNoShowPayload = {}): Promise<Appointment> {
    await appointmentsApi.noShow(id, payload)
    return fetchOne(id)
  }

  async function remove(id: string): Promise<void> {
    await appointmentsApi.remove(id)
    cache.delete(id)
  }

  function $reset() {
    cache.clear()
    loadedRanges.value = []
    loading.value = false
    error.value = null
  }

  return {
    cache,
    loading,
    error,
    filteredAppointments,
    fetchRange,
    fetchOne,
    create,
    update,
    confirm,
    checkIn,
    start,
    complete,
    cancel,
    noShow,
    remove,
    $reset,
  }
})
