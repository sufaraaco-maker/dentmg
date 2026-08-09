import { test, expect, type Page } from '@playwright/test'
import { API_BASE_URL, loginAsEnglish } from './fixtures'

/** Same overlay-close race documented in `laboratory.spec.ts`/`clinical-notes.spec.ts` — a
 *  `.p-select`'s option list closes on a CSS leave-transition, not instantly on click. */
async function waitForSelectOverlayClosed(page: Page) {
  await expect(page.locator('.p-select-overlay')).toBeHidden()
}

/** Creates a fresh patient via the app's own API — same pattern as `laboratory.spec.ts`'s
 *  `createPatient`. */
async function createPatient(page: Page): Promise<{ id: string; fullName: string }> {
  return page.evaluate(async (apiUrl) => {
    const xsrfToken = decodeURIComponent(
      document.cookie
        .split('; ')
        .find((row) => row.startsWith('XSRF-TOKEN='))
        ?.split('=')[1] ?? '',
    )
    const stamp = Date.now().toString().slice(-6)
    const firstName = `E2ETimeline${stamp}`
    const res = await fetch(`${apiUrl}/patients`, {
      method: 'POST',
      credentials: 'include',
      headers: { 'Content-Type': 'application/json', 'X-XSRF-TOKEN': xsrfToken },
      body: JSON.stringify({
        first_name: firstName,
        last_name: 'Patient',
        date_of_birth: '1990-01-15',
        gender: 'male',
        phone: '0555000557',
      }),
    })
    if (!res.ok) throw new Error(`POST /patients failed: ${res.status} ${await res.text()}`)
    const patient = await res.json()
    return { id: patient.id as string, fullName: `${firstName} Patient` }
  }, API_BASE_URL)
}

/** Real logout via the app's own API — same pattern as `laboratory.spec.ts`'s `logout`, needed to
 *  switch actors (admin -> receptionist) within a single test. */
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

/**
 * Drives two real domain actions that each dispatch a `PatientActivityOccurred` event (Phase
 * 2.6a): sending a Lab Case (`category=laboratory`, reusing `laboratory.spec.ts`'s proven flow)
 * and signing a Clinical Note (`category=clinical_notes`, reusing `clinical-notes.spec.ts`'s).
 * Returns the lab case's generated `case_number` since its Timeline summary embeds it and it
 * can't be known in advance.
 */
async function recordLabAndClinicalActivity(page: Page, patient: { id: string; fullName: string }) {
  const labName = `E2E Timeline Lab ${Date.now().toString().slice(-8)}`
  await page.goto('/laboratory/labs')
  await page.getByRole('button', { name: 'New Lab' }).click()
  const labDialog = page.locator('.p-dialog')
  await labDialog.getByLabel('Name', { exact: true }).fill(labName)
  await labDialog.getByRole('button', { name: 'Save' }).click()
  await expect(page.getByText('Lab saved')).toBeVisible({ timeout: 10_000 })

  await page.goto('/lab-cases')
  await page.getByRole('button', { name: 'New Lab Case' }).click()
  const caseDialog = page.locator('.p-dialog')
  const searchInput = caseDialog.getByPlaceholder('Search by name, code, or phone')
  await expect(searchInput).toBeVisible({ timeout: 15_000 })
  await searchInput.fill(patient.fullName.split(' ')[0])
  const patientOption = caseDialog.locator('li', { hasText: patient.fullName })
  await expect(patientOption).toBeVisible({ timeout: 10_000 })
  await patientOption.click()
  await caseDialog.locator('div:has(> label:text-is("Lab")) .p-select').click()
  await page.getByText(labName).click()
  await waitForSelectOverlayClosed(page)
  await caseDialog.getByRole('button', { name: 'Create' }).click()
  await page.waitForURL(/\/lab-cases\/[^/]+$/, { timeout: 10_000 })
  const caseNumber = (await page.locator('h1').innerText()).trim()

  await page.getByRole('button', { name: 'Send to Lab' }).click()
  await expect(page.getByText('Case sent to lab')).toBeVisible({ timeout: 10_000 })

  await page.goto(`/patients/${patient.id}`)
  await page.getByRole('tab', { name: 'Clinical Notes' }).click()
  await page.getByRole('button', { name: 'New Clinical Note' }).click()
  const noteDialog = page.locator('.p-dialog')
  await noteDialog.locator('div:has(> label:text-is("Dentist")) .p-select').click()
  await page.locator('li.p-select-option:visible').first().click()
  await waitForSelectOverlayClosed(page)
  await noteDialog.locator('div:has(> label:text-is("Note Type")) .p-select').click()
  await page.getByRole('option', { name: 'Progress Note' }).click()
  await waitForSelectOverlayClosed(page)
  await page.getByRole('button', { name: 'Save', exact: true }).click()
  await expect(page.getByText('Clinical note created')).toBeVisible({ timeout: 10_000 })
  await page.waitForURL(/\/clinical-notes\/[^/]+$/, { timeout: 10_000 })

  await page.locator('div:has(> label:text-is("Subjective")) textarea').fill('E2E timeline test note.')
  await page.getByRole('button', { name: 'Save Draft' }).click()
  await expect(page.getByText('Draft saved')).toBeVisible({ timeout: 10_000 })

  await page.getByRole('button', { name: 'Sign Note' }).click()
  const confirmDialog = page.locator('.p-confirmdialog')
  await confirmDialog.getByRole('button', { name: 'Sign Note' }).click()
  await expect(page.getByText('Clinical note signed')).toBeVisible({ timeout: 10_000 })

  return { caseNumber }
}

test.describe('patient timeline', () => {
  test('the Timeline tab aggregates activity across modules and filters by category', async ({ page }) => {
    await loginAsEnglish(page, 'admin')
    const patient = await createPatient(page)
    const { caseNumber } = await recordLabAndClinicalActivity(page, patient)

    await page.goto(`/patients/${patient.id}`)
    await page.getByRole('tab', { name: 'Timeline' }).click()

    await expect(page.getByText('Clinical note signed')).toBeVisible({ timeout: 10_000 })
    await expect(page.getByText(`Lab case ${caseNumber} sent`)).toBeVisible()

    // Selecting a category chip re-queries the server for just that category — the Clinical Notes
    // entry disappears entirely, not merely greyed out (design doc §13's chip filter).
    await page.getByRole('button', { name: 'Laboratory', exact: true }).click()
    await expect(page.getByText(`Lab case ${caseNumber} sent`)).toBeVisible({ timeout: 10_000 })
    await expect(page.getByText('Clinical note signed')).toHaveCount(0)
  })

  test('the Overview tab shows a recent-activity preview that jumps to the Timeline tab', async ({
    page,
  }) => {
    await loginAsEnglish(page, 'admin')
    const patient = await createPatient(page)
    const { caseNumber } = await recordLabAndClinicalActivity(page, patient)

    await page.goto(`/patients/${patient.id}`)
    await expect(page.getByText('Recent Activity')).toBeVisible({ timeout: 10_000 })
    await expect(page.getByText('Clinical note signed')).toBeVisible({ timeout: 10_000 })

    await page.getByRole('button', { name: 'View Timeline' }).click()
    await expect(page.getByRole('tab', { name: 'Timeline', selected: true })).toBeVisible()
    await expect(page.getByText(`Lab case ${caseNumber} sent`)).toBeVisible({ timeout: 10_000 })
  })

  test('a receptionist sees Laboratory activity on the Timeline tab but never Clinical Notes activity', async ({
    page,
  }) => {
    await loginAsEnglish(page, 'admin')
    const patient = await createPatient(page)
    const { caseNumber } = await recordLabAndClinicalActivity(page, patient)

    await logout(page)
    await loginAsEnglish(page, 'receptionist')

    await page.goto(`/patients/${patient.id}`)
    await page.getByRole('tab', { name: 'Timeline' }).click()
    await expect(page.getByText(`Lab case ${caseNumber} sent`)).toBeVisible({ timeout: 10_000 })
    await expect(page.getByText('Clinical note signed')).toHaveCount(0)

    // Not just hidden by the frontend: the server excludes `clinical_notes` from the query itself
    // (design doc §9A/`PatientActivityPolicy::allowedCategories`) — explicitly filtering to it
    // must return nothing, not a client-side-hidden row.
    await page.getByRole('button', { name: 'Clinical Notes', exact: true }).click()
    await expect(page.getByText('No activity recorded for this patient yet.')).toBeVisible({
      timeout: 10_000,
    })
  })
})
