import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import type { AppointmentStatus, CalendarViewMode } from '@/types/appointment'

export interface CalendarFilters {
  dentistIds: string[]
  statuses: AppointmentStatus[]
  typeIds: string[]
  patientId: string | null
}

export interface DateRange {
  start: Date
  end: Date
}

function emptyFilters(): CalendarFilters {
  return { dentistIds: [], statuses: [], typeIds: [], patientId: null }
}

function startOfDay(date: Date): Date {
  const result = new Date(date)
  result.setHours(0, 0, 0, 0)
  return result
}

function endOfDay(date: Date): Date {
  const result = new Date(date)
  result.setHours(23, 59, 59, 999)
  return result
}

function startOfWeek(date: Date): Date {
  const result = startOfDay(date)
  result.setDate(result.getDate() - result.getDay())
  return result
}

function endOfWeek(date: Date): Date {
  const result = startOfWeek(date)
  result.setDate(result.getDate() + 6)
  return endOfDay(result)
}

function startOfMonth(date: Date): Date {
  return new Date(date.getFullYear(), date.getMonth(), 1)
}

function endOfMonth(date: Date): Date {
  return endOfDay(new Date(date.getFullYear(), date.getMonth() + 1, 0))
}

/**
 * Pure UI/view state — view mode, current date, active filters. No API calls of its own;
 * `appointments.ts`'s `fetchRange` is driven by this store's `currentRange` getter.
 * See docs/modules/appointments-ui-design.md §10.1.
 */
export const useCalendarStore = defineStore('calendar', () => {
  const viewMode = ref<CalendarViewMode>('timeGridWeek')
  const currentDate = ref(new Date())
  const filters = ref<CalendarFilters>(emptyFilters())

  const currentRange = computed<DateRange>(() => {
    switch (viewMode.value) {
      case 'timeGridDay':
      case 'resourceTimeGridDay':
        return { start: startOfDay(currentDate.value), end: endOfDay(currentDate.value) }
      case 'dayGridMonth':
        return { start: startOfMonth(currentDate.value), end: endOfMonth(currentDate.value) }
      case 'timeGridWeek':
      case 'list':
      default:
        return { start: startOfWeek(currentDate.value), end: endOfWeek(currentDate.value) }
    }
  })

  function setViewMode(mode: CalendarViewMode) {
    viewMode.value = mode
  }

  function goToday() {
    currentDate.value = new Date()
  }

  function shift(direction: 1 | -1): Date {
    const next = new Date(currentDate.value)

    if (viewMode.value === 'dayGridMonth') {
      next.setMonth(next.getMonth() + direction)
    } else if (viewMode.value === 'timeGridWeek' || viewMode.value === 'list') {
      next.setDate(next.getDate() + direction * 7)
    } else {
      next.setDate(next.getDate() + direction)
    }

    return next
  }

  function goNext() {
    currentDate.value = shift(1)
  }

  function goPrev() {
    currentDate.value = shift(-1)
  }

  function setFilter<K extends keyof CalendarFilters>(key: K, value: CalendarFilters[K]) {
    filters.value = { ...filters.value, [key]: value }
  }

  function resetFilters() {
    filters.value = emptyFilters()
  }

  function $reset() {
    viewMode.value = 'timeGridWeek'
    currentDate.value = new Date()
    filters.value = emptyFilters()
  }

  return {
    viewMode,
    currentDate,
    filters,
    currentRange,
    setViewMode,
    goToday,
    goNext,
    goPrev,
    setFilter,
    resetFilters,
    $reset,
  }
})
