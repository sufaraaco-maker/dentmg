import { test, expect, type Page } from '@playwright/test'
import { API_BASE_URL, loginAsEnglish } from './fixtures'

/** Minimal valid 1x1 JPEG — real bytes (not a placeholder string), needed so the backend's
 *  `mimes:jpg,jpeg,png,webp` validation and GD-based thumbnail generation both succeed for real. */
const TINY_JPEG_BASE64 =
  '/9j/4AAQSkZJRgABAQEAYABgAAD/2wBDAAgGBgcGBQgHBwcJCQgKDBQNDAsLDBkSEw8UHRofHh0aHBwgJC4nICIsIxwcKDcpLDAxNDQ0Hyc5PTgyPC4zNDL/2wBDAQkJCQwLDBgNDRgyIRwhMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjL/wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAv/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFQEBAQAAAAAAAAAAAAAAAAAAAAX/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIRAxEAPwCdABmX/9k='

/** Creates a fresh patient via the app's own API — same pattern as `laboratory.spec.ts`'s
 *  `createPatient`, so each test has a guaranteed-findable patient rather than depending on seeded
 *  demo data. */
async function createPatient(page: Page): Promise<{ id: string; fullName: string }> {
  return page.evaluate(async (apiUrl) => {
    const xsrfToken = decodeURIComponent(
      document.cookie
        .split('; ')
        .find((row) => row.startsWith('XSRF-TOKEN='))
        ?.split('=')[1] ?? '',
    )
    const stamp = Date.now().toString().slice(-6)
    const firstName = `E2EImg${stamp}`
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

/** Real logout via the app's own API, mirroring `laboratory.spec.ts`'s identical helper. */
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

async function goToImagingTab(page: Page, patientId: string) {
  await page.goto(`/patients/${patientId}`)
  await page.getByRole('tab', { name: 'Imaging' }).click()
}

async function uploadOneImage(page: Page, options: { imageType?: string; tooth?: string } = {}) {
  await page.getByRole('button', { name: 'Upload Images' }).click()
  const dialog = page.locator('.p-dialog')
  // Scoped to the dialog's own title span, not `getByText` — the dialog also contains its own
  // "Upload Images" submit button, and both would otherwise match a plain text locator.
  await expect(dialog.locator('.p-dialog-title')).toHaveText('Upload Images')

  await dialog.locator('input[type="file"]').setInputFiles({
    name: 'xray.jpg',
    mimeType: 'image/jpeg',
    buffer: Buffer.from(TINY_JPEG_BASE64, 'base64'),
  })
  await expect(dialog.getByText('xray.jpg')).toBeVisible()

  // Scoped to the open overlay's own option items (`.p-select-option`), not a page-wide text
  // search — `getByText`/`page.getByText` would otherwise happily match unrelated "16"/type text
  // anywhere else on the page (e.g. behind the modal), same class of bug already worked around in
  // `laboratory.spec.ts`'s `selectFirstOption` helper.
  if (options.imageType) {
    await dialog.locator('div:has(> label:text-is("Type")) .p-select').click()
    await page.locator('li.p-select-option:visible', { hasText: options.imageType }).first().click()
    await expect(page.locator('.p-select-overlay')).toBeHidden()
  }

  if (options.tooth) {
    await dialog.locator('div:has(> label:text-is("Tooth")) .p-select').click()
    await page.locator('li.p-select-option:visible', { hasText: options.tooth }).first().click()
    await expect(page.locator('.p-select-overlay')).toBeHidden()
  }

  await dialog.getByRole('button', { name: 'Upload', exact: true }).click()
  await expect(page.getByText('Images uploaded.')).toBeVisible({ timeout: 10_000 })
}

test.describe('imaging', () => {
  test('admin uploads, views, edits, and deletes a patient image through its full lifecycle', async ({
    page,
  }) => {
    await loginAsEnglish(page, 'admin')
    const patient = await createPatient(page)

    await goToImagingTab(page, patient.id)
    await expect(page.getByText('No images uploaded yet.')).toBeVisible()

    // --- Upload one image, tagged to a tooth.
    await uploadOneImage(page, { imageType: 'Periapical X-ray', tooth: '16' })
    await expect(page.getByText('No images uploaded yet.')).toHaveCount(0)

    // --- Open the lightbox and exercise its non-destructive viewer controls.
    await page.locator('button:has(img)').first().click()
    const lightbox = page.getByRole('dialog', { name: 'Image viewer' })
    await expect(lightbox).toBeVisible({ timeout: 10_000 })
    await expect(lightbox.getByText('Tooth: 16')).toBeVisible()
    await lightbox.getByLabel('Invert colors').click()
    await lightbox.getByLabel('Close').click()
    await expect(lightbox).toBeHidden()

    // --- Edit metadata via the hover pencil action. Scoped to the thumbnail's own `.group`
    // container, not the page — `PatientDetailView`'s header has its own "Edit"/"Delete" buttons
    // for the patient record itself, which would otherwise collide with these.
    const thumbnail = page.locator('.group').first()
    await thumbnail.hover()
    await thumbnail.getByRole('button', { name: 'Edit' }).click()
    const editDialog = page.locator('.p-dialog')
    await expect(editDialog.getByText('Edit Image')).toBeVisible()
    await editDialog.locator('#edit-image-notes').fill('Baseline periapical, tooth 16')
    await editDialog.getByRole('button', { name: 'Save' }).click()
    await expect(page.getByText('Image updated.')).toBeVisible({ timeout: 10_000 })

    // --- Delete (admin-only).
    await thumbnail.hover()
    await thumbnail.getByRole('button', { name: 'Delete' }).click()
    const confirmDialog = page.locator('.p-confirmdialog')
    await confirmDialog.getByRole('button', { name: 'Delete' }).click()
    await expect(page.getByText('Image deleted.')).toBeVisible({ timeout: 10_000 })
    await expect(page.getByText('No images uploaded yet.')).toBeVisible()
  })

  test('receptionist can upload but not delete; dentist has full access except delete', async ({ page }) => {
    await loginAsEnglish(page, 'receptionist')
    const patient = await createPatient(page)

    await goToImagingTab(page, patient.id)
    await expect(page.getByRole('button', { name: 'Upload Images' })).toBeVisible()
    await uploadOneImage(page)
    const receptionistThumbnail = page.locator('.group').first()
    await receptionistThumbnail.hover()
    await expect(receptionistThumbnail.getByRole('button', { name: 'Delete' })).toHaveCount(0)
    await expect(receptionistThumbnail.getByRole('button', { name: 'Edit' })).toBeVisible()

    await logout(page)
    await loginAsEnglish(page, 'dentist')
    await goToImagingTab(page, patient.id)
    await expect(page.getByRole('button', { name: 'Upload Images' })).toBeVisible()
    const dentistThumbnail = page.locator('.group').first()
    await dentistThumbnail.hover()
    await expect(dentistThumbnail.getByRole('button', { name: 'Delete' })).toHaveCount(0)
  })
})
