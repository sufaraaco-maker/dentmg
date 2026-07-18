import { test, expect } from '@playwright/test'
import { loginAsEnglish } from './fixtures'

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

    // Seeded patients have randomly-generated names (PatientFactory — see docs/demo-guide.md,
    // "exact names differ on every fresh migrate:fresh --seed") and `patient_code` isn't a
    // searchable field (see PatientService::paginate), so a hardcoded name only works against
    // whatever happened to be seeded locally when the test was written. Fetch a real one instead.
    const patientsResponse = await page.request.get('/api/patients?per_page=1')
    const { data: patients } = await patientsResponse.json()
    const patientName: string = patients[0].full_name

    await page.goto('/appointments')
    await page.getByRole('button', { name: 'New Appointment' }).click()

    // Scoped to the dialog — the Board's always-visible CalendarFilters sidebar has its own
    // PatientSearchSelect (for filtering the calendar) with this exact same placeholder text,
    // so an unscoped page-wide locator matches two elements (strict-mode violation).
    const dialog = page.locator('.p-dialog')
    const searchInput = dialog.getByPlaceholder('Search by name, code, or phone')
    await expect(searchInput).toBeVisible({ timeout: 15_000 })
    await searchInput.fill(patientName)

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

    await page.getByRole('button', { name: 'Save' }).click()
    await expect(page.getByText('Appointment saved successfully')).toBeVisible({ timeout: 10_000 })

    // Reschedule via the List view's most recently created row.
    await page.goto('/appointments')
    await page.getByRole('button', { name: 'List', exact: true }).click()
    const firstRow = page.locator('table tbody tr').first()
    await expect(firstRow).toBeVisible({ timeout: 10_000 })
    await firstRow.click()

    await page.getByRole('button', { name: 'Edit' }).click()
    const startAtInput = page.locator('div:has(> label:text-is("Date & Time")) input')
    await startAtInput.click()
    await page.keyboard.press('Control+A')
    await startAtInput.pressSequentially('2026-08-01 10:00', { delay: 20 })
    await page.keyboard.press('Escape')
    await page.getByRole('button', { name: 'Save' }).click()
    await expect(page.getByText('Appointment saved successfully')).toBeVisible({ timeout: 10_000 })

    // Cancel from the same detail page.
    await page.getByRole('button', { name: 'Cancel', exact: true }).click()
    await page.getByRole('button', { name: 'Cancel Appointment' }).click()
    await expect(page.getByText('Cancelled')).toBeVisible({ timeout: 10_000 })
  })
})
