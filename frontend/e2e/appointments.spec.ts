import { test, expect, type Page } from '@playwright/test'
import { API_BASE_URL, loginAsEnglish } from './fixtures'

/**
 * Working hours are seeded, dynamic per-dentist data (not a fixed 9-to-5), so a hardcoded
 * date/time reliably lands outside them (OutsideWorkingHoursException) — confirmed directly
 * against backend/storage/logs/laravel.log while debugging this suite. Fetch the first dentist's
 * actual active shift and compute a real, valid instant instead of guessing one.
 */
async function computeValidSlot(page: Page, weeksAhead = 1): Promise<string> {
  return page.evaluate(
    async ({ apiUrl, weeksAhead }) => {
      const usersRes = await fetch(`${apiUrl}/users?per_page=50`, { credentials: 'include' })
      const { data: users } = await usersRes.json()
      const dentist = users.find((u: { role: string }) => u.role === 'dentist')

      const hoursRes = await fetch(`${apiUrl}/dentists/${dentist.id}/working-hours`, {
        credentials: 'include',
      })
      const { data: hours } = await hoursRes.json()
      const shift = hours.find((h: { is_active: boolean }) => h.is_active)

      const [startHour, startMinute] = shift.start_time.split(':').map(Number)
      const target = new Date()
      const daysUntilShift = (shift.day_of_week - target.getDay() + 7) % 7 || 7 // next occurrence, never today
      target.setDate(target.getDate() + daysUntilShift + (weeksAhead - 1) * 7)
      target.setHours(startHour, startMinute, 0, 0)

      const pad = (n: number) => String(n).padStart(2, '0')
      return `${target.getFullYear()}-${pad(target.getMonth() + 1)}-${pad(target.getDate())} ${pad(target.getHours())}:${pad(target.getMinutes())}`
    },
    { apiUrl: API_BASE_URL, weeksAhead },
  )
}

test.describe('appointments', () => {
  test('Calendar Board loads and switches between Day/Week/Month/List views', async ({ page }) => {
    await loginAsEnglish(page, 'admin')
    await page.goto('/appointments')
    await expect(page.getByRole('button', { name: 'New Appointment' })).toBeVisible({
      timeout: 15_000,
    })

    for (const view of ['Day', 'Week', 'Month', 'List'] as const) {
      await page.getByRole('button', { name: view, exact: true }).click()
      await expect(page.getByRole('button', { name: view, exact: true })).toHaveAttribute(
        'aria-pressed',
        'true',
      )
    }
  })

  test('creates, reschedules, and cancels an appointment', async ({ page }) => {
    await loginAsEnglish(page, 'admin')
    await page.goto('/appointments')

    // Seeded patients have randomly-generated names (PatientFactory — see docs/demo-guide.md,
    // "exact names differ on every fresh migrate:fresh --seed") and `patient_code` isn't a
    // searchable field (see PatientService::paginate), so a hardcoded name only works against
    // whatever happened to be seeded locally when the test was written. Fetch a real one instead
    // — via the page's own fetch (not `page.request`, a separate API context whose requests lack
    // the Origin/Referer headers Sanctum's stateful-domain check keys off, which made the same
    // call 401 despite valid cookies) so it authenticates exactly like the app's own code does.
    const patientName: string = await page.evaluate(async (apiUrl) => {
      const res = await fetch(`${apiUrl}/patients?per_page=1`, { credentials: 'include' })
      const { data } = await res.json()
      return data[0].full_name
    }, API_BASE_URL)
    // PatientService::paginate's search matches first_name/last_name/phone/national_id/email
    // individually — never the concatenated full name — so searching "Aiden Kunde" as one
    // string matches neither field's own LIKE clause. Search on the first token only.
    const searchTerm = patientName.split(' ')[0]

    await page.getByRole('button', { name: 'New Appointment' }).click()

    // Scoped to the dialog — the Board's always-visible CalendarFilters sidebar has its own
    // PatientSearchSelect (for filtering the calendar) with this exact same placeholder text,
    // so an unscoped page-wide locator matches two elements (strict-mode violation).
    const dialog = page.locator('.p-dialog')
    const searchInput = dialog.getByPlaceholder('Search by name, code, or phone')
    await expect(searchInput).toBeVisible({ timeout: 15_000 })
    await searchInput.fill(searchTerm)

    // PatientSearchSelect renders results as a plain `<ul>/<li>` list (not a PrimeVue overlay
    // component) — the patient's name lives in a `<span>` inside each `<li>`, so scoping by
    // exact visible text is both correct and immune to the earlier debugging session's mistake
    // of guessing PrimeVue-style `.p-listbox-option`/`[role="option"]` classes that don't exist
    // on this component.
    const patientOption = dialog.locator('li', { hasText: patientName })
    await expect(patientOption).toBeVisible({ timeout: 10_000 })
    await patientOption.click()

    await page.getByRole('tab', { name: 'Appointment' }).click()

    await page.locator('div:has(> label:text-is("Dentist")) .p-select').click()
    await page.locator('li.p-select-option').first().click()

    await page.locator('div:has(> label:text-is("Appointment Type")) .p-select').click()
    await page.locator('li.p-select-option').first().click()

    const validSlot = await computeValidSlot(page)
    const createStartAtInput = page.locator('div:has(> label:text-is("Date & Time")) input')
    await createStartAtInput.click()
    await createStartAtInput.pressSequentially(validSlot, { delay: 20 })
    await page.keyboard.press('Escape')

    await page.getByRole('button', { name: 'Save' }).click()
    await expect(page.getByText('Appointment saved successfully')).toBeVisible({ timeout: 10_000 })

    // Reschedule via the List view's most recently created row.
    await page.goto('/appointments')
    await page.getByRole('button', { name: 'List', exact: true }).click()
    const firstRow = page.locator('table tbody tr').first()
    await expect(firstRow).toBeVisible({ timeout: 10_000 })
    await firstRow.click()

    await page.getByRole('button', { name: 'Edit' }).click()
    const rescheduleSlot = await computeValidSlot(page, 2) // a different week than the created slot
    const startAtInput = page.locator('div:has(> label:text-is("Date & Time")) input')
    await startAtInput.click()
    await page.keyboard.press('Control+A')
    await startAtInput.pressSequentially(rescheduleSlot, { delay: 20 })
    await page.keyboard.press('Escape')
    await page.getByRole('button', { name: 'Save' }).click()
    await expect(page.getByText('Appointment saved successfully')).toBeVisible({ timeout: 10_000 })

    // Cancel from the same detail page.
    await page.getByRole('button', { name: 'Cancel', exact: true }).click()
    await page.getByRole('button', { name: 'Cancel Appointment' }).click()
    await expect(page.getByText('Cancelled')).toBeVisible({ timeout: 10_000 })
  })
})
