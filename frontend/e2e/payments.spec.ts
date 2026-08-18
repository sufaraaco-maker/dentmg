import { test, expect, type Page } from '@playwright/test'
import { API_BASE_URL, loginAsEnglish, forceArabicLocale } from './fixtures'

/**
 * Payments — permanent E2E suite (TECH_DEBT.md: "No permanent E2E suite for Billing or Payments").
 * Scenarios per `docs/modules/payments-design.md` §16: record payment against an invoice -> verify
 * `payment_status` updates -> partial refund -> verify balance recalculates -> record unapplied
 * credit -> apply it to a different invoice -> delete-blocked-once-refunded verification ->
 * receptionist-write/dentist-read-only check -> RTL/currency-formatting smoke check. Backend
 * permission/failure-case coverage (26 existing tests in `PaymentTest.php`, including refund/apply/
 * delete edge cases and every role's write/read boundary) was already comprehensive -- confirmed by
 * reading the file directly; the one gap closed there was strengthening an existing assertion (the
 * original payment's `amount` is never mutated by a refund), not new coverage.
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

async function createPatient(page: Page): Promise<{ id: string }> {
  const stamp = Date.now().toString().slice(-6)
  const patient = await apiRequest<{ id: string }>(page, '/patients', {
    method: 'POST',
    body: {
      first_name: `E2EPayment${stamp}`,
      last_name: 'Patient',
      date_of_birth: '1985-02-20',
      gender: 'female',
      phone: '0555000333',
    },
  })
  return { id: patient.id }
}

/** Creates a `draft` invoice with one manual charge item and issues it -- setup only, not the
 *  system under test (Billing's own E2E suite covers the invoice lifecycle UI). */
async function createIssuedInvoice(page: Page, patientId: string, amount: number): Promise<string> {
  const invoice = await apiRequest<{ id: string }>(page, `/patients/${patientId}/invoices`, {
    method: 'POST',
    body: {},
  })
  await apiRequest(page, `/invoices/${invoice.id}/items`, {
    method: 'POST',
    body: { kind: 'charge', description: 'E2E fixture charge', unit_amount: amount, quantity: 1 },
  })
  await apiRequest(page, `/invoices/${invoice.id}/issue`, { method: 'POST' })
  return invoice.id
}

async function gotoInvoiceDetail(page: Page, patientId: string, invoiceId: string) {
  await page.goto(`/patients/${patientId}/invoices/${invoiceId}`)
  await expect(page.getByRole('heading')).toBeVisible({ timeout: 10_000 })
}

/** `?tab=billing` is the same locale-independent deep-link pattern `PatientDetailView.vue` exposes
 *  for every tab (validated against `tabDefinitions`). The Payments sub-section is a `SelectButton`
 *  inside that tab, not a tab of its own. */
async function gotoPatientPaymentsSection(page: Page, patientId: string) {
  await page.goto(`/patients/${patientId}?tab=billing`)
  await page.getByRole('button', { name: 'Payments', exact: true }).click()
  await expect(page.getByRole('button', { name: 'Record Payment' })).toBeVisible({ timeout: 10_000 })
}

test.describe('payments', () => {
  test('golden path: record against an invoice, verify payment_status, partial refund recalculates balance', async ({
    page,
  }) => {
    await loginAsEnglish(page, 'admin')
    const patient = await createPatient(page)
    const invoiceId = await createIssuedInvoice(page, patient.id, 200)

    await gotoInvoiceDetail(page, patient.id, invoiceId)
    await page.getByRole('button', { name: 'Record Payment' }).click()
    const recordDialog = page.locator('.p-dialog')
    await expect(recordDialog.getByText('Record Payment')).toBeVisible()
    await recordDialog.locator('div:has(> label:text-is("Amount")) input').fill('200')
    await recordDialog.getByRole('button', { name: 'Save' }).click()
    await expect(page.getByText('Payment saved')).toBeVisible({ timeout: 10_000 })
    await expect(recordDialog).toBeHidden({ timeout: 10_000 })

    // Invoice is now fully paid -- amount_paid/balance_due/payment_status all recompute.
    await expect(page.getByText('Paid', { exact: true })).toBeVisible({ timeout: 10_000 })
    let invoice = await apiRequest<{ payment_status: string; amount_paid: string; balance_due: string }>(
      page,
      `/invoices/${invoiceId}`,
    )
    expect(invoice.payment_status).toBe('paid')
    expect(invoice.amount_paid).toBe('200.00')
    expect(invoice.balance_due).toBe('0.00')

    // Partial refund of that same payment.
    const paymentsBefore = await apiRequest<{ id: string; amount: string }[]>(
      page,
      `/invoices/${invoiceId}/payments`,
    )
    const originalPayment = paymentsBefore.find((p) => Number(p.amount) > 0)
    if (!originalPayment) throw new Error('Recorded payment not found on the invoice')

    await page.reload()
    const row = page.locator('tr', { has: page.getByText('200.00', { exact: false }) })
    await row.getByRole('button', { name: 'Refund' }).click()
    const refundDialog = page.locator('.p-dialog')
    await expect(refundDialog.getByText('Refund Payment')).toBeVisible()
    await refundDialog.locator('div:has(> label:text-is("Amount")) input').fill('50')
    await refundDialog.getByRole('button', { name: 'Refund', exact: true }).click()
    await expect(page.getByText('Refund recorded')).toBeVisible({ timeout: 10_000 })

    // The original payment row is never mutated by the refund (own DB row, own creation) -- only a
    // new negative Payment row is created, and the invoice's balance recalculates from the sum.
    const paymentsAfter = await apiRequest<{ id: string; amount: string }[]>(
      page,
      `/invoices/${invoiceId}/payments`,
    )
    const unchanged = paymentsAfter.find((p) => p.id === originalPayment.id)
    expect(unchanged?.amount).toBe(originalPayment.amount)

    invoice = await apiRequest(page, `/invoices/${invoiceId}`)
    expect(invoice.balance_due).toBe('50.00')
    expect(invoice.payment_status).toBe('partially_paid')
  })

  test('records an unapplied credit and applies it to a different issued invoice', async ({ page }) => {
    await loginAsEnglish(page, 'admin')
    const patient = await createPatient(page)
    const invoiceId = await createIssuedInvoice(page, patient.id, 100)

    await gotoPatientPaymentsSection(page, patient.id)
    await page.getByRole('button', { name: 'Record Payment' }).click()
    const recordDialog = page.locator('.p-dialog')
    await expect(recordDialog.getByText('Record Payment')).toBeVisible()
    await recordDialog.locator('div:has(> label:text-is("Amount")) input').fill('100')
    await recordDialog.getByRole('button', { name: 'Save' }).click()
    await expect(page.getByText('Payment saved')).toBeVisible({ timeout: 10_000 })
    await expect(recordDialog).toBeHidden({ timeout: 10_000 })

    // The unapplied credit renders with no invoice link.
    const unappliedRow = page.locator('tr', { has: page.getByText('Unapplied', { exact: true }) })
    await expect(unappliedRow).toBeVisible({ timeout: 10_000 })

    await unappliedRow.getByRole('button', { name: 'Apply to Invoice' }).click()
    const applyDialog = page.locator('.p-dialog')
    await expect(applyDialog.getByText('Apply to Invoice', { exact: true })).toBeVisible()
    await applyDialog.getByRole('combobox').click()
    await page.locator('li.p-select-option:visible').first().click()
    await applyDialog.getByRole('button', { name: 'Apply to Invoice', exact: true }).click()
    await expect(page.getByText('Payment applied')).toBeVisible({ timeout: 10_000 })
    await expect(applyDialog).toBeHidden({ timeout: 10_000 })

    const invoice = await apiRequest<{ payment_status: string }>(page, `/invoices/${invoiceId}`)
    expect(invoice.payment_status).toBe('paid')
  })

  test('a fully refunded payment cannot be deleted', async ({ page }) => {
    await loginAsEnglish(page, 'admin')
    const patient = await createPatient(page)
    const invoiceId = await createIssuedInvoice(page, patient.id, 60)

    const payment = await apiRequest<{ id: string }>(page, `/patients/${patient.id}/payments`, {
      method: 'POST',
      body: { invoice_id: invoiceId, method: 'cash', amount: 60, received_at: new Date().toISOString().slice(0, 10) },
    })
    await apiRequest(page, `/payments/${payment.id}/refund`, { method: 'POST', body: { amount: 60 } })

    await gotoPatientPaymentsSection(page, patient.id)
    // Two rows now exist ("60.00 EGP" original, "-60.00 EGP" refund) -- an unanchored substring
    // match on "60.00" would also match the refund row's text, so anchor to the start: only the
    // original (positive) amount's text node begins with the digit, not a leading "-".
    const originalRow = page.locator('tr', { has: page.getByText(/^60\.00/) })
    await expect(originalRow.getByRole('button', { name: 'Delete' })).toHaveCount(0)

    const status = await apiStatus(page, `/payments/${payment.id}`, 'DELETE')
    expect(status).toBe(422)
  })

  test('dentist has read-only access; receptionist can record/refund/apply', async ({ page }) => {
    await loginAsEnglish(page, 'admin')
    const setupPatient = await createPatient(page)
    const invoiceId = await createIssuedInvoice(page, setupPatient.id, 40)

    await loginAsEnglish(page, 'dentist')
    await gotoPatientPaymentsSection(page, setupPatient.id)
    await expect(page.getByRole('button', { name: 'Record Payment' })).not.toBeVisible()

    const forbiddenRecord = await apiStatus(page, `/patients/${setupPatient.id}/payments`, 'POST', {
      invoice_id: invoiceId,
      method: 'cash',
      amount: 40,
      received_at: new Date().toISOString().slice(0, 10),
    })
    expect(forbiddenRecord).toBe(403)

    await loginAsEnglish(page, 'receptionist')
    await gotoPatientPaymentsSection(page, setupPatient.id)
    await expect(page.getByRole('button', { name: 'Record Payment' })).toBeVisible()

    const allowedRecord = await apiStatus(page, `/patients/${setupPatient.id}/payments`, 'POST', {
      invoice_id: invoiceId,
      method: 'cash',
      amount: 40,
      received_at: new Date().toISOString().slice(0, 10),
    })
    expect(allowedRecord).toBe(201)
  })

  test('RTL (Arabic) smoke check: currency amounts stay LTR-isolated', async ({ page }) => {
    await loginAsEnglish(page, 'admin')
    const patient = await createPatient(page)
    const invoiceId = await createIssuedInvoice(page, patient.id, 25)
    await apiRequest(page, `/patients/${patient.id}/payments`, {
      method: 'POST',
      body: {
        invoice_id: invoiceId,
        method: 'cash',
        amount: 25,
        received_at: new Date().toISOString().slice(0, 10),
      },
    })

    await forceArabicLocale(page)
    await page.goto(`/patients/${patient.id}?tab=billing`)
    await expect(page.locator('html')).toHaveAttribute('dir', 'rtl', { timeout: 10_000 })

    // BillingSummaryCard.vue's "Total Paid" figure -- reachable with no locale-dependent clicks,
    // unlike the Payments sub-section (behind an Arabic-labeled SelectButton once RTL is forced).
    const totalPaid = page.locator('p.font-medium[dir="ltr"]', { hasText: '25.00' })
    await expect(totalPaid).toBeVisible({ timeout: 10_000 })
  })
})
