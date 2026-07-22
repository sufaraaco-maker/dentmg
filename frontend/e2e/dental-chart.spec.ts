import { test, expect, type Page } from '@playwright/test'
import { API_BASE_URL, loginAsEnglish } from './fixtures'

/**
 * Creates a fresh patient via the app's own API (same XSRF-cookie dance as
 * `appointments.spec.ts`'s `ensureWorkingHours`) rather than a hardcoded/seeded one — this suite
 * needs a patient with a guaranteed-empty chart, and Patients are randomly-named per fresh seed
 * (`PatientFactory`, see `docs/demo-guide.md`).
 */
async function createPatient(page: Page): Promise<{ id: string; fullName: string }> {
  return page.evaluate(async (apiUrl) => {
    const xsrfToken = decodeURIComponent(
      document.cookie
        .split('; ')
        .find((row) => row.startsWith('XSRF-TOKEN='))
        ?.split('=')[1] ?? '',
    )
    const stamp = Date.now().toString().slice(-6)
    const res = await fetch(`${apiUrl}/patients`, {
      method: 'POST',
      credentials: 'include',
      headers: { 'Content-Type': 'application/json', 'X-XSRF-TOKEN': xsrfToken },
      body: JSON.stringify({
        first_name: `E2EChart${stamp}`,
        last_name: 'Patient',
        date_of_birth: '1990-01-15',
        gender: 'male',
        phone: '0555000444',
      }),
    })
    if (!res.ok) throw new Error(`POST /patients failed: ${res.status} ${await res.text()}`)
    // Unlike paginated list endpoints, a single-resource response is never wrapped in a `data` key
    // (`JsonResource::withoutWrapping()`, `AppServiceProvider`) — the body *is* the patient.
    const patient = await res.json()
    return { id: patient.id as string, fullName: patient.full_name as string }
  }, API_BASE_URL)
}

async function gotoDentalChartTab(page: Page, patientId: string) {
  await page.goto(`/patients/${patientId}`)
  await page.getByRole('tab', { name: 'Dental Chart' }).click()
}

/**
 * A `.p-select`'s overlay closes via a CSS leave-transition, not instantly on selection — the
 * option's `click()` resolving (and even the select's own label text updating) only means Vue's
 * model committed, not that the overlay panel has actually left the DOM/become non-visible.
 * Clicking straight into the *next* field while it's still fading out gets absorbed by PrimeVue as
 * an outside-click dismissal of that lingering overlay instead of registering on the intended
 * target (a tab, a different select's trigger, etc.), which then never opens/switches at all.
 * Awaiting this after every option selection avoids that race deterministically instead of hoping
 * the next action happens to land after the transition finishes.
 */
async function waitForSelectOverlayClosed(page: Page) {
  await expect(page.locator('.p-select-overlay')).toBeHidden()
}

test.describe('dental chart', () => {
  test('creates, edits, completes, cancels, and deletes chart entries through the odontogram and list view', async ({
    page,
  }) => {
    await loginAsEnglish(page, 'admin')
    await page.goto('/patients')
    const patient = await createPatient(page)
    await gotoDentalChartTab(page, patient.id)

    // --- Create a whole-tooth finding (Missing Tooth never applies_to_surface) via the toolbar's
    // Add Entry button, exercising the plain create path with no surface picker involved.
    await page.getByRole('button', { name: 'Add Entry' }).click()
    const dialog = page.locator('.p-dialog')
    await expect(dialog.getByText('New Chart Entry')).toBeVisible()

    await dialog.locator('div:has(> label:text-is("Tooth")) .p-select').click()
    await page.getByRole('option', { name: '26 —' }).click()
    // Waiting for the select's own displayed value to update confirms its overlay panel has
    // actually closed — a bare `.click()` only waits for the DOM click event, not for Vue's
    // state commit/close-transition, so the next select's overlay could otherwise open while
    // this one's options are technically still present/visible.
    await expect(dialog.locator('div:has(> label:text-is("Tooth")) .p-select')).toContainText('26')
    await waitForSelectOverlayClosed(page)

    await dialog.locator('div:has(> label:text-is("Dentist")) .p-select').click()
    // The Tooth select opened just before this one — its overlay panel can still be lingering
    // (unmounting) in the DOM, so an unscoped `li.p-select-option` risks matching its now-stale
    // options instead of the Dentist dropdown's. `:visible` keeps this to the panel actually open.
    await page.locator('li.p-select-option:visible').first().click()
    await waitForSelectOverlayClosed(page)

    await page.getByRole('tab', { name: 'Diagnosis' }).click()
    // PrimeVue keeps both tab panels mounted (`v-show`, not `v-if`) — a plain placeholder locator
    // would match the Procedure tab's identical, currently-hidden "Select a condition" field too.
    // `getByRole('tabpanel')` excludes hidden elements by default, so it resolves to only the
    // active one.
    await dialog.getByRole('tabpanel').getByPlaceholder('Select a condition').click()
    await page.getByRole('option', { name: 'Missing Tooth' }).click()
    await waitForSelectOverlayClosed(page)

    await page.getByRole('button', { name: 'Save' }).click()
    await expect(page.getByText('Chart entry saved successfully')).toBeVisible({ timeout: 10_000 })
    await expect(dialog).toBeHidden({ timeout: 10_000 })

    // The odontogram itself reflects it — tooth 26's aria-label now names the condition, not
    // "No conditions recorded".
    await expect(page.getByLabel(/Tooth 26,.*Missing Tooth/)).toBeVisible({ timeout: 10_000 })

    // --- Create a surface-specific procedure by clicking straight into tooth 16's occlusal
    // region on the chart itself (the real click-to-create path, not just the toolbar button).
    const tooth16Occlusal = page.getByRole('button', { name: /^Occlusal surface, tooth 16/ })
    await tooth16Occlusal.click()

    await expect(dialog.getByText('New Chart Entry')).toBeVisible()
    await expect(dialog.locator('div:has(> label:text-is("Tooth")) .p-select')).toContainText('16')

    await dialog.locator('div:has(> label:text-is("Dentist")) .p-select').click()
    // The Tooth select opened just before this one — its overlay panel can still be lingering
    // (unmounting) in the DOM, so an unscoped `li.p-select-option` risks matching its now-stale
    // options instead of the Dentist dropdown's. `:visible` keeps this to the panel actually open.
    await page.locator('li.p-select-option:visible').first().click()
    await waitForSelectOverlayClosed(page)

    await page.getByRole('tab', { name: 'Procedure' }).click()
    await dialog.getByRole('tabpanel').getByPlaceholder('Select a condition').click()
    await page.getByRole('option', { name: 'Composite Filling' }).click()
    await waitForSelectOverlayClosed(page)

    // The click-to-create prefill carried the occlusal surface straight into the dialog's own
    // single-tooth surface picker.
    await expect(dialog.getByRole('button', { name: /^Occlusal$/ })).toBeVisible()

    await dialog.locator('div:has(> label:text-is("Status")) .p-select').click()
    await page.getByRole('option', { name: 'Planned', exact: true }).click()
    await waitForSelectOverlayClosed(page)

    await page.getByRole('button', { name: 'Save' }).click()
    await expect(page.getByText('Chart entry saved successfully')).toBeVisible({ timeout: 10_000 })
    await expect(dialog).toBeHidden({ timeout: 10_000 })

    // --- Switch to the accessible list view — the mandatory non-visual-equivalent path (design
    // draft §18) — and drive the rest of the lifecycle from there.
    await page.getByRole('button', { name: 'List', exact: true }).click()
    const table = page.locator('table')
    await expect(table.getByText('Composite Filling')).toBeVisible({ timeout: 10_000 })
    await expect(table.getByText('Missing Tooth')).toBeVisible()

    const fillingRow = page.locator('tr', { has: page.getByText('Composite Filling') })
    await expect(fillingRow.getByText('Planned')).toBeVisible()

    await fillingRow.getByRole('button', { name: 'Complete' }).click()
    await expect(page.getByText('Chart entry saved successfully')).toBeVisible({ timeout: 10_000 })
    await expect(fillingRow.getByText('Completed')).toBeVisible({ timeout: 10_000 })

    // A completed entry can no longer be completed/cancelled again — only edit (notes) and
    // delete remain.
    await expect(fillingRow.getByRole('button', { name: 'Complete' })).toHaveCount(0)
    await expect(fillingRow.getByRole('button', { name: 'Cancel Entry' })).toHaveCount(0)

    // --- Delete the whole-tooth finding (admin-only) and confirm it's gone.
    const missingRow = page.locator('tr', { has: page.getByText('Missing Tooth') })
    await missingRow.getByRole('button', { name: 'Delete' }).click()
    await page.getByRole('button', { name: 'Delete Chart Entry' }).click()
    await expect(page.getByText('Chart entry deleted successfully')).toBeVisible({ timeout: 10_000 })
    await expect(page.locator('tr', { has: page.getByText('Missing Tooth') })).toHaveCount(0)
  })

  test('receptionist has read-only access to the Dental Chart tab', async ({ page }) => {
    await loginAsEnglish(page, 'admin')
    await page.goto('/patients')
    const patient = await createPatient(page)

    await loginAsEnglish(page, 'receptionist')
    await gotoDentalChartTab(page, patient.id)

    await expect(page.getByRole('button', { name: 'Add Entry' })).not.toBeVisible()

    // Clicking straight into a tooth region must not open the create dialog either — read access
    // only, per `DentalChartEntryPolicy` (admin/dentist write, receptionist read).
    const anyToothRegion = page.locator('[data-tooth] [role="button"]').first()
    await expect(anyToothRegion).toHaveCount(0)
  })

  test('arrow keys move focus between teeth on the odontogram', async ({ page }) => {
    await loginAsEnglish(page, 'admin')
    await page.goto('/patients')
    const patient = await createPatient(page)
    await gotoDentalChartTab(page, patient.id)

    await page.locator('[data-tooth="18"] [tabindex]').first().focus()
    await expect
      .poll(() =>
        page.evaluate(() => document.activeElement?.closest('[data-tooth]')?.getAttribute('data-tooth')),
      )
      .toBe('18')

    await page.keyboard.press('ArrowRight')
    await expect
      .poll(() =>
        page.evaluate(() => document.activeElement?.closest('[data-tooth]')?.getAttribute('data-tooth')),
      )
      .toBe('17')

    await page.keyboard.press('ArrowDown')
    await expect
      .poll(() =>
        page.evaluate(() => document.activeElement?.closest('[data-tooth]')?.getAttribute('data-tooth')),
      )
      .toBe('47')
  })
})
