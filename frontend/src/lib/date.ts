/**
 * `new Date("1990-05-10")` and `.toISOString()` both go through UTC, which silently shifts
 * the displayed/submitted date by a day in negative-UTC-offset timezones. These build/read
 * a date-only (yyyy-mm-dd) string using local date components instead.
 */
export function parseLocalDate(value: string): Date {
  const [year, month, day] = value.split('-').map(Number)
  return new Date(year, month - 1, day)
}

export function toLocalDateString(date: Date): string {
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  return `${year}-${month}-${day}`
}

/**
 * This is a single-clinic system (no per-request timezone conversion — `config/app.php`'s
 * `timezone` is `UTC`, used as a neutral/no-DST baseline, not a real UTC boundary): the digits
 * in a `start_at`/`end_at` timestamp already ARE the clinic's own wall-clock time. `.toISOString()`
 * instead re-derives those digits through *this machine's* OS timezone offset, which silently
 * shifts the submitted time whenever a user's browser isn't set to the same timezone as the
 * server — the exact class of bug `parseLocalDate`/`toLocalDateString` above already guards
 * against for date-only fields, extended here to date-time fields (design doc §3, discovered via
 * this module's real-browser verification: a "10:00" pick was submitted as `07:00:00.000Z`).
 */
export function toLocalDateTimeString(date: Date): string {
  const hours = String(date.getHours()).padStart(2, '0')
  const minutes = String(date.getMinutes()).padStart(2, '0')
  const seconds = String(date.getSeconds()).padStart(2, '0')
  return `${toLocalDateString(date)}T${hours}:${minutes}:${seconds}`
}

/**
 * The read-side counterpart of `toLocalDateTimeString`: the backend always serializes
 * `start_at`/`end_at` with an explicit UTC offset (e.g. `2026-07-16T10:00:00+00:00`), so
 * `new Date(iso)` correctly parses it as an absolute instant — but every local getter
 * (`getHours()`, the DatePicker's own display, etc.) would then re-express those UTC-labeled
 * digits through the browser's local offset instead of showing them as-is. This reads the
 * UTC-labeled components directly and rebuilds a plain Date carrying the same digits, so
 * anything reading it locally afterwards sees exactly what the clinic intended.
 */
export function parseServerDateTime(iso: string): Date {
  const parsed = new Date(iso)
  return new Date(
    parsed.getUTCFullYear(),
    parsed.getUTCMonth(),
    parsed.getUTCDate(),
    parsed.getUTCHours(),
    parsed.getUTCMinutes(),
    parsed.getUTCSeconds(),
  )
}

/**
 * The inverse of `parseServerDateTime`, needed anywhere a genuinely-local Date (e.g.
 * `calendar.ts`'s `currentDate`) is handed to FullCalendar, which runs in `timeZone: 'UTC'`
 * (`AppointmentCalendar.vue`) and therefore reads Date arguments to `gotoDate()`/`initialDate`
 * via their *UTC* getters, not their local ones. Without this, a local "midnight, July 17" Date
 * has UTC getters pointing at "21:00, July 16" for any positive-UTC-offset browser (confirmed
 * directly: clicking "Today" landed FullCalendar on the wrong, previous day) — constructing a
 * new Date from the local digits with an explicit `Z` suffix produces an instant whose UTC
 * getters carry those same digits, which is what FullCalendar needs to land on the right day.
 */
export function toCalendarUtcDate(date: Date): Date {
  return new Date(`${toLocalDateTimeString(date)}Z`)
}
