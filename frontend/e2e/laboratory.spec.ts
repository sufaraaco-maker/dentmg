import { test, expect, type Page } from '@playwright/test'
import { API_BASE_URL, loginAsEnglish } from './fixtures'

/** Same overlay-close race documented in `dental-chart.spec.ts`/`clinical-notes.spec.ts` — a
 *  `.p-select`'s option list closes on a CSS leave-transition, not instantly on click. */
async function waitForSelectOverlayClosed(page: Page) {
  await expect(page.locator('.p-select-overlay')).toBeHidden()
}

async function selectFirstOption(dialog: import('@playwright/test').Locator, labelText: string, page: Page) {
  await dialog.locator(`div:has(> label:text-is("${labelText}")) .p-select`).click()
  await page.locator('li.p-select-option:visible').first().click()
  await waitForSelectOverlayClosed(page)
}

/** Creates a fresh patient via the app's own API — same pattern as `clinical-notes.spec.ts`'s
 *  `createPatient`, needed here so each test can search for a guaranteed-findable patient rather
 *  than depending on seeded demo data. */
async function createPatient(page: Page): Promise<{ id: string; fullName: string }> {
  return page.evaluate(async (apiUrl) => {
    const xsrfToken = decodeURIComponent(
      document.cookie
        .split('; ')
        .find((row) => row.startsWith('XSRF-TOKEN='))
        ?.split('=')[1] ?? '',
    )
    const stamp = Date.now().toString().slice(-6)
    const firstName = `E2ELab${stamp}`
    const res = await fetch(`${apiUrl}/patients`, {
      method: 'POST',
      credentials: 'include',
      headers: { 'Content-Type': 'application/json', 'X-XSRF-TOKEN': xsrfToken },
      body: JSON.stringify({
        first_name: firstName,
        last_name: 'Patient',
        date_of_birth: '1990-01-15',
        gender: 'male',
        phone: '0555000556',
      }),
    })
    if (!res.ok) throw new Error(`POST /patients failed: ${res.status} ${await res.text()}`)
    const patient = await res.json()
    return { id: patient.id as string, fullName: `${firstName} Patient` }
  }, API_BASE_URL)
}

/** Real logout via the app's own API, mirroring `clinical-notes.spec.ts`'s identical helper —
 *  needed to switch actors within a single test without hitting `/login`'s `guestOnly` redirect
 *  guard while a session cookie is still active. */
async function logout(page: Page) {
  await page.evaluate(async (apiUrl) => {
    const xsrfToken = decodeURIComponent(
      document.cookie
        .split('; ')
        .find((row) => row.startsWith('XSRF-TOKEN='))
        ?.split('=')[1] ?? '',
    )
    await fetch(`${apiUrl}/logout`, {
      method: 'POST',
      credentials: 'include',
      headers: { 'X-XSRF-TOKEN': xsrfToken },
    })
  }, API_BASE_URL)
}

test.describe('laboratory', () => {
  test('admin manages the Labs catalog and runs a lab case through its full lifecycle', async ({ page }) => {
    const stamp = Date.now().toString().slice(-8)
    const labName = `E2E Dental Lab ${stamp}`

    await loginAsEnglish(page, 'admin')
    const patient = await createPatient(page)

    // --- Create a Lab (admin-only catalog screen).
    await page.goto('/laboratory/labs')
    await page.getByRole('button', { name: 'New Lab' }).click()
    const labDialog = page.locator('.p-dialog')
    await labDialog.getByLabel('Name', { exact: true }).fill(labName)
    await labDialog.getByLabel('Default Turnaround (days)').fill('5')
    await labDialog.getByRole('button', { name: 'Save' }).click()
    await expect(page.getByText('Lab saved')).toBeVisible({ timeout: 10_000 })
    await expect(page.getByRole('cell', { name: labName })).toBeVisible({ timeout: 10_000 })

    // --- Create a Lab Case for the fixture patient.
    await page.goto('/lab-cases')
    await page.getByRole('button', { name: 'New Lab Case' }).click()
    const dialog = page.locator('.p-dialog')
    await expect(dialog.getByText('New Lab Case')).toBeVisible()

    const searchInput = dialog.getByPlaceholder('Search by name, code, or phone')
    await expect(searchInput).toBeVisible({ timeout: 15_000 })
    await searchInput.fill(patient.fullName.split(' ')[0])
    const patientOption = dialog.locator('li', { hasText: patient.fullName })
    await expect(patientOption).toBeVisible({ timeout: 10_000 })
    await patientOption.click()

    await dialog.locator('div:has(> label:text-is("Lab")) .p-select').click()
    await page.getByText(labName).click()
    await waitForSelectOverlayClosed(page)

    await dialog.getByLabel('Shade').fill('A2')
    await dialog.getByRole('button', { name: 'Create' }).click()
    await page.waitForURL(/\/lab-cases\/[^/]+$/, { timeout: 10_000 })
    await expect(page.locator('.p-tag')).toContainText('Draft')

    // --- Send to the lab — due date auto-calculated from the lab's 5-day turnaround.
    await page.getByRole('button', { name: 'Send to Lab' }).click()
    await expect(page.getByText('Lab case updated')).toBeVisible({ timeout: 10_000 })
    await expect(page.locator('.p-tag')).toContainText('Sent')
    await expect(page.getByRole('button', { name: 'Send to Lab' })).toHaveCount(0)

    // --- Mark received.
    await page.getByRole('button', { name: 'Mark Received' }).click()
    await expect(page.getByText('Lab case updated')).toBeVisible({ timeout: 10_000 })
    await expect(page.locator('.p-tag')).toContainText('Received')

    // --- Quality check — terminal, no further actions offered.
    await page.getByRole('button', { name: 'Quality Check' }).click()
    await expect(page.getByText('Lab case updated')).toBeVisible({ timeout: 10_000 })
    await expect(page.locator('.p-tag')).toContainText('Quality Checked')
    await expect(page.getByRole('button', { name: 'Mark Received' })).toHaveCount(0)
    await expect(page.getByRole('button', { name: 'Cancel Case' })).toHaveCount(0)
  })

  test('a draft lab case can be cancelled', async ({ page }) => {
    await loginAsEnglish(page, 'admin')
    const patient = await createPatient(page)

    await page.goto('/lab-cases')
    await page.getByRole('button', { name: 'New Lab Case' }).click()
    const dialog = page.locator('.p-dialog')

    const searchInput = dialog.getByPlaceholder('Search by name, code, or phone')
    await searchInput.fill(patient.fullName.split(' ')[0])
    const patientOption = dialog.locator('li', { hasText: patient.fullName })
    await expect(patientOption).toBeVisible({ timeout: 10_000 })
    await patientOption.click()

    await selectFirstOption(dialog, 'Lab', page)
    await dialog.getByRole('button', { name: 'Create' }).click()
    await page.waitForURL(/\/lab-cases\/[^/]+$/, { timeout: 10_000 })

    await page.getByRole('button', { name: 'Cancel Case' }).click()
    const confirmDialog = page.locator('.p-confirmdialog')
    await confirmDialog.getByRole('button', { name: 'Cancel Case' }).click()
    await expect(page.getByText('Lab case updated')).toBeVisible({ timeout: 10_000 })
    await expect(page.locator('.p-tag')).toContainText('Cancelled')
    await expect(page.getByRole('button', { name: 'Send to Lab' })).toHaveCount(0)
    await expect(page.getByRole('button', { name: 'Cancel Case' })).toHaveCount(0)
  })

  test('receptionist cannot create cases or manage the Labs catalog; dentist can create but not manage the catalog', async ({
    page,
  }) => {
    await loginAsEnglish(page, 'receptionist')

    await page.goto('/lab-cases')
    await expect(page.getByRole('button', { name: 'New Lab Case' })).toHaveCount(0)

    await page.goto('/laboratory/labs')
    await expect(page).toHaveURL(/\/forbidden$/, { timeout: 10_000 })

    await logout(page)
    await loginAsEnglish(page, 'dentist')

    await page.goto('/lab-cases')
    await expect(page.getByRole('button', { name: 'New Lab Case' })).toBeVisible()

    await page.goto('/laboratory/labs')
    await expect(page).toHaveURL(/\/forbidden$/, { timeout: 10_000 })
  })
})
