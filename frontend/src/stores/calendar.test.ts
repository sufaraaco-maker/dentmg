import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it } from 'vitest'
import { useCalendarStore } from './calendar'
import { toLocalDateString } from '@/lib/date'

beforeEach(() => {
  setActivePinia(createPinia())
})

describe('useCalendarStore currentRange', () => {
  it('computes a single day range for timeGridDay', () => {
    const store = useCalendarStore()
    store.currentDate = new Date(2026, 6, 15, 14, 30) // Wed 2026-07-15, 14:30
    store.setViewMode('timeGridDay')

    // Local-date comparison, not .toISOString() (UTC) — a positive-UTC-offset timezone would
    // otherwise shift local midnight onto the previous UTC calendar day and fail spuriously.
    expect(toLocalDateString(store.currentRange.start)).toBe('2026-07-15')
    expect(toLocalDateString(store.currentRange.end)).toBe('2026-07-15')
    expect(store.currentRange.start.getHours()).toBe(0)
    expect(store.currentRange.end.getHours()).toBe(23)
  })

  it('computes a Sunday-to-Saturday range for timeGridWeek', () => {
    const store = useCalendarStore()
    store.currentDate = new Date(2026, 6, 15) // Wednesday
    store.setViewMode('timeGridWeek')

    expect(store.currentRange.start.getDay()).toBe(0)
    expect(store.currentRange.end.getDay()).toBe(6)
  })

  it('computes a full-month range for dayGridMonth', () => {
    const store = useCalendarStore()
    store.currentDate = new Date(2026, 6, 15)
    store.setViewMode('dayGridMonth')

    expect(store.currentRange.start.getDate()).toBe(1)
    expect(store.currentRange.start.getMonth()).toBe(6)
    // July 2026 has 31 days
    expect(store.currentRange.end.getDate()).toBe(31)
  })
})

describe('useCalendarStore navigation', () => {
  it('goNext/goPrev shift by one day in day view', () => {
    const store = useCalendarStore()
    store.currentDate = new Date(2026, 6, 15)
    store.setViewMode('timeGridDay')

    store.goNext()
    expect(store.currentDate.getDate()).toBe(16)

    store.goPrev()
    store.goPrev()
    expect(store.currentDate.getDate()).toBe(14)
  })

  it('goNext shifts by 7 days in week view', () => {
    const store = useCalendarStore()
    store.currentDate = new Date(2026, 6, 15)
    store.setViewMode('timeGridWeek')

    store.goNext()
    expect(store.currentDate.getDate()).toBe(22)
  })

  it('goNext shifts by one month in month view', () => {
    const store = useCalendarStore()
    store.currentDate = new Date(2026, 6, 15)
    store.setViewMode('dayGridMonth')

    store.goNext()
    expect(store.currentDate.getMonth()).toBe(7)
  })

  it('goToday resets currentDate to now', () => {
    const store = useCalendarStore()
    store.currentDate = new Date(2020, 0, 1)

    store.goToday()

    expect(store.currentDate.toDateString()).toBe(new Date().toDateString())
  })
})

describe('useCalendarStore filters', () => {
  it('setFilter updates a single key without touching the others', () => {
    const store = useCalendarStore()
    store.setFilter('dentistIds', ['dentist-1'])
    store.setFilter('statuses', ['scheduled'])

    expect(store.filters.dentistIds).toEqual(['dentist-1'])
    expect(store.filters.statuses).toEqual(['scheduled'])
  })

  it('resetFilters clears every filter back to empty', () => {
    const store = useCalendarStore()
    store.setFilter('dentistIds', ['dentist-1'])
    store.setFilter('patientId', 'patient-1')

    store.resetFilters()

    expect(store.filters).toEqual({ dentistIds: [], statuses: [], typeIds: [], patientId: null })
  })
})
