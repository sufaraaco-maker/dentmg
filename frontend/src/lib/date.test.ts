import { describe, expect, it } from 'vitest'
import {
  parseLocalDate,
  parseServerDateTime,
  toCalendarUtcDate,
  toLocalDateString,
  toLocalDateTimeString,
} from './date'

describe('parseLocalDate / toLocalDateString', () => {
  it('round-trips a date-only string without shifting days', () => {
    const date = parseLocalDate('1990-05-10')
    expect(toLocalDateString(date)).toBe('1990-05-10')
  })
})

describe('toLocalDateTimeString', () => {
  it('serializes local wall-clock components, not a UTC-converted instant', () => {
    const date = new Date(2026, 6, 23, 10, 0, 0) // July 23 2026, 10:00:00 local
    expect(toLocalDateTimeString(date)).toBe('2026-07-23T10:00:00')
  })

  it('zero-pads single-digit components', () => {
    const date = new Date(2026, 0, 5, 9, 5, 3)
    expect(toLocalDateTimeString(date)).toBe('2026-01-05T09:05:03')
  })
})

describe('parseServerDateTime', () => {
  it('reads the UTC-labeled digits as-is, regardless of the host timezone', () => {
    const parsed = parseServerDateTime('2026-07-16T10:00:00+00:00')
    expect(parsed.getFullYear()).toBe(2026)
    expect(parsed.getMonth()).toBe(6)
    expect(parsed.getDate()).toBe(16)
    expect(parsed.getHours()).toBe(10)
    expect(parsed.getMinutes()).toBe(0)
  })

  it('round-trips through toLocalDateTimeString back to the same digits', () => {
    const parsed = parseServerDateTime('2026-07-16T10:00:00Z')
    expect(toLocalDateTimeString(parsed)).toBe('2026-07-16T10:00:00')
  })
})

describe('toCalendarUtcDate', () => {
  it("produces a Date whose UTC getters carry the input's local digits, regardless of host timezone", () => {
    // Regression test for a confirmed real bug: FullCalendar runs in `timeZone: 'UTC'`
    // (AppointmentCalendar.vue), so `gotoDate()`/`initialDate` read a Date's *UTC* getters. A
    // genuinely local "midnight, July 17" Date, passed through unconverted, landed FullCalendar
    // on July 16 for any positive-UTC-offset host — confirmed directly via a real browser
    // (clicking "Today" showed the wrong, previous day).
    const localMidnight = new Date(2026, 6, 17, 0, 0, 0) // July 17 2026, 00:00 local
    const converted = toCalendarUtcDate(localMidnight)

    expect(converted.getUTCFullYear()).toBe(2026)
    expect(converted.getUTCMonth()).toBe(6)
    expect(converted.getUTCDate()).toBe(17)
    expect(converted.getUTCHours()).toBe(0)
  })

  it('is the exact inverse of parseServerDateTime', () => {
    const original = new Date(2026, 6, 23, 10, 15, 0)
    const roundTripped = parseServerDateTime(toCalendarUtcDate(original).toISOString())

    expect(roundTripped.getTime()).toBe(original.getTime())
  })
})
