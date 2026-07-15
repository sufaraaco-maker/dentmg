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
