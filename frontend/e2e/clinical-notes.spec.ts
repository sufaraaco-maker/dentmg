import { test, expect, type Page } from '@playwright/test'
import { API_BASE_URL, loginAsEnglish } from './fixtures'

/**
 * Creates a fresh patient via the app's own API — same pattern as `dental-chart.spec.ts`'s
 * `createPatient`, needed here so each test starts from a guaranteed-empty Clinical Notes tab
 * rather than depending on seeded demo data.
 */
async function createPatient(page: Page): Promise<{ id: string }> {
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
        first_name: `E2ENote${stamp}`,
        last_name: 'Patient',
        date_of_birth: '1990-01-15',
        gender: 'male',
        phone: '0555000555',
      }),
    })
    if (!res.ok) throw new Error(`POST /patients failed: ${res.status} ${await res.text()}`)
    const patient = await res.json()
    return { id: patient.id as string }
  }, API_BASE_URL)
}

/** Real logout via the app's own API, mirroring `auth.ts`'s `logout()` — needed to switch actors
 *  (admin -> receptionist) within a single test without hitting `/login`'s `guestOnly` redirect
 *  guard while a session cookie is still active (see fixtures.ts's `login()` comment). */
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

async function gotoClinicalNotesTab(page: Page, patientId: string) {
  await page.goto(`/patients/${patientId}`)
  await page.getByRole('tab', { name: 'Clinical Notes' }).click()
}

/** Same overlay-close race documented in `dental-chart.spec.ts` — a `.p-select`'s option list
 *  closes on a CSS leave-transition, not instantly on click. */
async function waitForSelectOverlayClosed(page: Page) {
  await expect(page.locator('.p-select-overlay')).toBeHidden()
}

/** Creates a draft note through the real "New Clinical Note" dialog and waits for the app's own
 *  post-save navigation to the Note Detail route. Returns once the detail view has loaded. */
async function createDraftNote(page: Page, patientId: string, noteType: string) {
  await gotoClinicalNotesTab(page, patientId)
  await page.getByRole('button', { name: 'New Clinical Note' }).click()

  const dialog = page.locator('.p-dialog')
  await expect(dialog.getByText('New Clinical Note')).toBeVisible()

  await dialog.locator('div:has(> label:text-is("Dentist")) .p-select').click()
  await page.locator('li.p-select-option:visible').first().click()
  await waitForSelectOverlayClosed(page)

  await dialog.locator('div:has(> label:text-is("Note Type")) .p-select').click()
  await page.getByRole('option', { name: noteType }).click()
  await waitForSelectOverlayClosed(page)

  await page.getByRole('button', { name: 'Save', exact: true }).click()
  await expect(page.getByText('Clinical note created')).toBeVisible({ timeout: 10_000 })
  await page.waitForURL(/\/clinical-notes\/[^/]+$/, { timeout: 10_000 })
}

test.describe('clinical notes', () => {
  test('dentist creates a draft, edits it, signs it, and adds addendums through the full lifecycle', async ({
    page,
  }) => {
    // Logged in as admin, not dentist: Patients is admin/receptionist-write, dentist-read-only
    // (same matrix as Dental Chart/Appointments — see `dental-chart.spec.ts`'s identical choice),
    // so a dentist session can't POST /patients to set up this test's fixture patient. Admin can
    // still exercise the full note lifecycle below — `ClinicalNotePolicy` grants admin the same
    // create/update/sign/addendum abilities as dentist (design doc §10).
    await loginAsEnglish(page, 'admin')
    await page.goto('/patients')
    const patient = await createPatient(page)

    await createDraftNote(page, patient.id, 'Progress Note')

    // --- Freshly created draft: status chip reads Draft, content fields are editable.
    await expect(page.locator('.p-tag')).toContainText('Draft')
    await expect(page.locator('div:has(> label:text-is("Subjective")) textarea')).toBeVisible()

    // --- Editing and saving a draft.
    await page.locator('div:has(> label:text-is("Subjective")) textarea').fill('Patient reports mild sensitivity on the upper right molar.')
    await page.locator('div:has(> label:text-is("Objective")) textarea').fill('Visual exam shows minor enamel wear, no visible decay.')
    await page.locator('div:has(> label:text-is("Assessment")) textarea').fill('Early-stage sensitivity, likely from enamel wear.')
    await page.locator('div:has(> label:text-is("Plan")) textarea').fill('Recommend desensitizing toothpaste, re-evaluate in 3 months.')
    await page.getByRole('button', { name: 'Save Draft' }).click()
    await expect(page.getByText('Draft saved')).toBeVisible({ timeout: 10_000 })

    // A reload proves the save actually persisted server-side, not just in local component state.
    await page.reload()
    await expect(page.locator('div:has(> label:text-is("Subjective")) textarea')).toHaveValue(
      'Patient reports mild sensitivity on the upper right molar.',
    )

    // --- Signing a note (irreversible action, gated behind a confirm dialog).
    await page.getByRole('button', { name: 'Sign Note' }).click()
    const confirmDialog = page.locator('.p-confirmdialog')
    await expect(confirmDialog.getByText('Sign Clinical Note')).toBeVisible()
    await confirmDialog.getByRole('button', { name: 'Sign Note' }).click()
    await expect(page.getByText('Clinical note signed')).toBeVisible({ timeout: 10_000 })

    // --- Confirming locked state: chip flips to Signed, content renders read-only (no textareas,
    // no Save Draft button), and the lock notice is shown.
    await expect(page.locator('.p-tag')).toContainText('Signed')
    await expect(page.locator('div:has(> label:text-is("Subjective")) textarea')).toHaveCount(0)
    await expect(
      page.getByText('This note is signed and can no longer be edited. Add an addendum instead.'),
    ).toBeVisible()
    await expect(page.getByRole('button', { name: 'Save Draft' })).toHaveCount(0)
    await expect(page.getByText('Patient reports mild sensitivity on the upper right molar.')).toBeVisible()

    // --- Adding an addendum.
    await page.getByPlaceholder('Add a follow-up note...').fill('Patient called back — sensitivity improved with new toothpaste.')
    await page.getByRole('button', { name: 'Add Addendum' }).click()
    await expect(page.getByText('Addendum added')).toBeVisible({ timeout: 10_000 })
    await expect(page.getByText('Patient called back — sensitivity improved with new toothpaste.')).toBeVisible()

    // --- Confirming addendums are append-only: the rendered addendum entry has no edit/delete
    // affordance at all (no button anywhere on the row) — corrections are only possible via a new
    // addendum, never a mutation of an existing one.
    const firstAddendumRow = page.locator('li', {
      has: page.getByText('Patient called back — sensitivity improved with new toothpaste.'),
    })
    await expect(firstAddendumRow.getByRole('button')).toHaveCount(0)

    // A second addendum appends alongside the first without altering it — proving "append", not
    // "replace".
    await page.getByPlaceholder('Add a follow-up note...').fill('Follow-up: no further complaints at next visit.')
    await page.getByRole('button', { name: 'Add Addendum' }).click()
    await expect(page.getByText('Addendum added')).toBeVisible({ timeout: 10_000 })
    await expect(page.getByText('Follow-up: no further complaints at next visit.')).toBeVisible()
    await expect(page.getByText('Patient called back — sensitivity improved with new toothpaste.')).toBeVisible()
  })

  test('cannot sign a completely blank clinical note', async ({ page }) => {
    await loginAsEnglish(page, 'admin')
    await page.goto('/patients')
    const patient = await createPatient(page)

    await createDraftNote(page, patient.id, 'Consultation')

    // No SOAP field touched — signing must be rejected server-side (design doc §8 rule 2) and the
    // note must remain a draft.
    await page.getByRole('button', { name: 'Sign Note' }).click()
    const confirmDialog = page.locator('.p-confirmdialog')
    await confirmDialog.getByRole('button', { name: 'Sign Note' }).click()
    await expect(
      page.getByText('Cannot sign an empty note — complete at least one section first.'),
    ).toBeVisible({ timeout: 10_000 })
    await expect(page.locator('.p-tag')).toContainText('Draft')
    await expect(page.locator('div:has(> label:text-is("Subjective")) textarea')).toBeVisible()
  })

  test('receptionist has no access to Clinical Notes at all', async ({ page }) => {
    await loginAsEnglish(page, 'admin')
    await page.goto('/patients')
    const patient = await createPatient(page)
    await createDraftNote(page, patient.id, 'Progress Note')
    const noteUrl = page.url()

    await logout(page)
    await loginAsEnglish(page, 'receptionist')

    // The tab itself must not render — not just be disabled (design doc §10/§15 Decision D:
    // receptionist is excluded entirely, unlike Dental Chart/Treatment Plans' read-only access).
    await page.goto(`/patients/${patient.id}`)
    await expect(page.getByRole('tab', { name: 'Clinical Notes' })).toHaveCount(0)

    // Direct navigation to an existing note's URL must also be blocked at the router level, not
    // just hidden in the UI — `router/index.ts`'s `meta: { roles: ['admin', 'dentist'] }` guard.
    await page.goto(noteUrl)
    await expect(page).toHaveURL(/\/forbidden$/)
    await expect(page.getByText('Access Denied')).toBeVisible()
  })
})
