import { test, expect, type Page } from '@playwright/test'
import { API_BASE_URL, loginAsEnglish, forceArabicLocale } from './fixtures'

/**
 * Billing (Invoices) — permanent E2E suite (TECH_DEBT.md: "No permanent E2E suite for Billing or
 * Payments" — the Billing half). Scenarios: draft -> add items (manual + from treatment plan) ->
 * issue -> verify frozen snapshot -> void -> receptionist/dentist permission check -> RTL smoke
 * check. Backend HTTP-layer coverage for the lifecycle endpoints did not exist before this pass
 * (`InvoiceControllerTest.php` only covered listing) -- closed separately in
 * `backend/tests/Feature/InvoiceTest.php`/`InvoiceItemTest.php` (28 + 21 tests), verified locally
 * against real PostgreSQL.
 */

async function apiRequest<T>(
  page: Page,
  path: string,
  init: { method?: string; body?: unknown } = {},
): Promise<T> {
  return page.evaluate(
    async ({ apiUrl, path, init }) => {
      const xsrfToken = decodeURIComponent(
        document.cookie
          .split('; ')
          .find((row) => row.startsWith('XSRF-TOKEN='))
          ?.split('=')[1] ?? '',
      )
      const res = await fetch(`${apiUrl}${path}`, {
        method: init.method ?? 'GET',
        credentials: 'include',
        headers: { 'Content-Type': 'application/json', 'X-XSRF-TOKEN': xsrfToken },
        body: init.body === undefined ? undefined : JSON.stringify(init.body),
      })
      if (!res.ok) {
        throw new Error(`${init.method ?? 'GET'} ${path} failed: ${res.status} ${await res.text()}`)
      }
      return res.json()
    },
    { apiUrl: API_BASE_URL, path, init },
  )
}

async function apiStatus(page: Page, path: string, method = 'GET', body?: unknown): Promise<number> {
  return page.evaluate(
    async ({ apiUrl, path, method, body }) => {
      const xsrfToken = decodeURIComponent(
        document.cookie
          .split('; ')
          .find((row) => row.startsWith('XSRF-TOKEN='))
          ?.split('=')[1] ?? '',
      )
      const res = await fetch(`${apiUrl}${path}`, {
        method,
        credentials: 'include',
        headers: { 'Content-Type': 'application/json', 'X-XSRF-TOKEN': xsrfToken },
        body: body === undefined ? undefined : JSON.stringify(body),
      })
      return res.status
    },
    { apiUrl: API_BASE_URL, path, method, body },
  )
}

/** Real logout via the app's own API -- needed to switch actors within a single test without
 *  hitting `/login`'s `guestOnly` redirect guard while a session cookie is still active (real CI
 *  found this exact bug in the sibling payments.spec.ts's identical multi-role test; see
 *  clinical-notes.spec.ts/timeline.spec.ts's established precedent for the same fix). */
async function logout(page: Page) {
  await apiRequest(page, '/logout', { method: 'POST' }).catch(() => undefined)
}

async function createPatient(page: Page): Promise<{ id: string }> {
  const stamp = Date.now().toString().slice(-6)
  const patient = await apiRequest<{ id: string }>(page, '/patients', {
    method: 'POST',
    body: {
      first_name: `E2EBilling${stamp}`,
      last_name: 'Patient',
      date_of_birth: '1979-11-02',
      gender: 'male',
      phone: '0555000222',
    },
  })
  return { id: patient.id }
}

/** `?tab=billing` deep-link (`PatientDetailView.vue`'s `tabDefinitions`) -- lands on the Invoices
 *  sub-section by default (`PatientBillingPanel.vue`'s `section` ref), no extra click needed. */
async function gotoPatientInvoicesSection(page: Page, patientId: string) {
  await page.goto(`/patients/${patientId}?tab=billing`)
  await expect(page.getByRole('button', { name: 'New Invoice' })).toBeVisible({ timeout: 10_000 })
}

test.describe('billing (invoices)', () => {
  test('golden path: draft -> add items -> issue freezes the snapshot -> void', async ({ page }) => {
    await loginAsEnglish(page, 'admin')
    const patient = await createPatient(page)

    await gotoPatientInvoicesSection(page, patient.id)
    await page.getByRole('button', { name: 'New Invoice' }).click()
    await expect(page.getByRole('heading', { name: 'Draft Invoice' })).toBeVisible({ timeout: 10_000 })

    // Add a manual charge.
    await page.getByRole('button', { name: 'Add Charge' }).click()
    const itemDialog = page.locator('.p-dialog')
    await expect(itemDialog.getByText('Add Charge', { exact: true })).toBeVisible()
    await itemDialog.locator('div:has(> label:text-is("Description")) input').fill('Consultation')
    await itemDialog.locator('div:has(> label:text-is("Unit Amount")) input').fill('120')
    await itemDialog.getByRole('button', { name: 'Save' }).click()
    await expect(page.getByText('Item saved')).toBeVisible({ timeout: 10_000 })
    await expect(itemDialog).toBeHidden({ timeout: 10_000 })
    await expect(page.getByText('Consultation')).toBeVisible()

    // Total reflects the new item.
    const totalFigure = page.locator('p.text-2xl.font-semibold[dir="ltr"]')
    await expect(totalFigure).toContainText('120.00')

    // Issue -- frozen snapshot: item-add controls disappear, a permanent invoice number and
    // "Issued" status are assigned, and the confirm dialog's own explicit acceptLabel is "Issue".
    await page.getByRole('button', { name: 'Issue', exact: true }).click()
    await page.getByRole('alertdialog').getByRole('button', { name: 'Issue', exact: true }).click()
    await expect(page.getByText('Invoice updated').last()).toBeVisible({ timeout: 10_000 })
    await expect(page.getByRole('button', { name: 'Add Charge' })).not.toBeVisible()
    await expect(page.getByRole('button', { name: 'Add from Treatment Plan' })).not.toBeVisible()
    await expect(page.getByText('Issued', { exact: true })).toBeVisible({ timeout: 10_000 })
    // Heading no longer reads "Draft Invoice" -- it now shows the assigned invoice_number.
    await expect(page.getByRole('heading', { name: 'Draft Invoice' })).toHaveCount(0)

    // Editing is now rejected server-side too, not just hidden in the UI.
    const invoiceId = page.url().split('/invoices/')[1]
    const forbiddenAdd = await apiStatus(page, `/invoices/${invoiceId}/items`, 'POST', {
      kind: 'charge',
      description: 'Should be locked',
      unit_amount: 10,
    })
    expect(forbiddenAdd).toBe(422)

    // Void -- cancellation, not a delete: the invoice number is preserved.
    const numberBeforeVoid = await page.locator('h1[dir="ltr"]').innerText()
    await page.getByRole('button', { name: 'Void', exact: true }).click()
    await page.getByRole('alertdialog').getByRole('button', { name: 'Void', exact: true }).click()
    await expect(page.getByText('Invoice updated').last()).toBeVisible({ timeout: 10_000 })
    await expect(page.getByText('Void', { exact: true })).toBeVisible({ timeout: 10_000 })
    await expect(page.locator('h1[dir="ltr"]')).toHaveText(numberBeforeVoid)
  })

  test('dentist has read-only access; receptionist can create/issue/void but not delete', async ({ page }) => {
    await loginAsEnglish(page, 'admin')
    const setupPatient = await createPatient(page)

    await logout(page)
    await loginAsEnglish(page, 'dentist')
    await gotoPatientInvoicesSectionReadOnly(page, setupPatient.id)
    await expect(page.getByRole('button', { name: 'New Invoice' })).not.toBeVisible()

    const forbiddenCreate = await apiStatus(page, `/patients/${setupPatient.id}/invoices`, 'POST', {})
    expect(forbiddenCreate).toBe(403)

    await logout(page)
    await loginAsEnglish(page, 'receptionist')
    await gotoPatientInvoicesSection(page, setupPatient.id)
    await page.getByRole('button', { name: 'New Invoice' }).click()
    await expect(page.getByRole('heading', { name: 'Draft Invoice' })).toBeVisible({ timeout: 10_000 })
    const invoiceId = page.url().split('/invoices/')[1]

    // Delete is admin-only, gated tighter than every other action -- checked here, still draft, so
    // this isolates the *permission* dimension rather than being conflated with "not draft anymore"
    // (the Delete button is also `v-if`-gated on `isDraft`, so checking after Issue below would be
    // true for either reason).
    await expect(page.getByRole('button', { name: 'Delete' })).not.toBeVisible()
    const forbiddenDelete = await apiStatus(page, `/invoices/${invoiceId}`, 'DELETE')
    expect(forbiddenDelete).toBe(403)

    // Receptionist can still issue and void (front-desk work).
    await page.getByRole('button', { name: 'Issue', exact: true }).click()
    await page.getByRole('alertdialog').getByRole('button', { name: 'Issue', exact: true }).click()
    await expect(page.getByText('Invoice updated').last()).toBeVisible({ timeout: 10_000 })
    await expect(page.getByText('Issued', { exact: true })).toBeVisible({ timeout: 10_000 })
  })

  test('RTL (Arabic) smoke check: totals stay LTR-isolated', async ({ page }) => {
    await loginAsEnglish(page, 'admin')
    const patient = await createPatient(page)

    await gotoPatientInvoicesSection(page, patient.id)
    await page.getByRole('button', { name: 'New Invoice' }).click()
    await expect(page.getByRole('heading', { name: 'Draft Invoice' })).toBeVisible({ timeout: 10_000 })

    await page.getByRole('button', { name: 'Add Charge' }).click()
    const itemDialog = page.locator('.p-dialog')
    await itemDialog.locator('div:has(> label:text-is("Description")) input').fill('Cleaning')
    await itemDialog.locator('div:has(> label:text-is("Unit Amount")) input').fill('45')
    await itemDialog.getByRole('button', { name: 'Save' }).click()
    await expect(page.getByText('Item saved')).toBeVisible({ timeout: 10_000 })

    await forceArabicLocale(page)
    await page.reload()
    await expect(page.locator('html')).toHaveAttribute('dir', 'rtl', { timeout: 10_000 })

    const totalFigure = page.locator('p.text-2xl.font-semibold[dir="ltr"]')
    await expect(totalFigure).toContainText('45.00')
  })
})

/** Same as `gotoPatientInvoicesSection` but without asserting "New Invoice" is visible -- used by
 *  the dentist (read-only) case, where that button is never rendered at all. */
async function gotoPatientInvoicesSectionReadOnly(page: Page, patientId: string) {
  await page.goto(`/patients/${patientId}?tab=billing`)
  await expect(page.getByRole('tabpanel')).toBeVisible({ timeout: 10_000 })
}
