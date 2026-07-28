import { test, expect, type Page } from '@playwright/test'
import { API_BASE_URL, loginAsEnglish } from './fixtures'

/**
 * Creates a fresh patient via the app's own API, mirroring `laboratory.spec.ts`'s/
 * `clinical-notes.spec.ts`'s identical helper — needed so this spec's Collections-report
 * assertion can search for a guaranteed-unique patient name rather than an aggregate total,
 * which would be polluted by every other spec's own fixtures sharing the same CI database and
 * the same "this month" date range (design doc §4.2 — Collections has no per-test isolation
 * mechanism, it reads across the whole system by design).
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
    const firstName = `E2EReports${stamp}`
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

async function recordPayment(page: Page, patientId: string, amount: string): Promise<void> {
  await page.evaluate(
    async ({ apiUrl, patientId, amount }) => {
      const xsrfToken = decodeURIComponent(
        document.cookie
          .split('; ')
          .find((row) => row.startsWith('XSRF-TOKEN='))
          ?.split('=')[1] ?? '',
      )
      const res = await fetch(`${apiUrl}/patients/${patientId}/payments`, {
        method: 'POST',
        credentials: 'include',
        headers: { 'Content-Type': 'application/json', 'X-XSRF-TOKEN': xsrfToken },
        body: JSON.stringify({ method: 'cash', amount }),
      })
      if (!res.ok) throw new Error(`POST /payments failed: ${res.status} ${await res.text()}`)
    },
    { apiUrl: API_BASE_URL, patientId, amount },
  )
}

test.describe('reports', () => {
  test('admin sees a recorded payment on the Collections report and can navigate every report from the home page', async ({
    page,
  }) => {
    await loginAsEnglish(page, 'admin')
    const patient = await createPatient(page)
    await recordPayment(page, patient.id, '321.50')

    const main = page.locator('main')

    await page.goto('/reports')
    await expect(page.getByRole('heading', { name: 'Reports' })).toBeVisible()
    for (const card of [
      'Production',
      'Collections',
      'A/R Aging',
      'Appointment Analytics',
      'Treatment Plan Acceptance',
      'New Patients',
    ]) {
      // Scoped to <main> — the same label also appears in the sidebar nav (design doc's own
      // "financial nav items must actually be hidden, not just the destination route" bar
      // surfaced a real AppSidebarItem.vue bug during this suite's own first run; this selector
      // just needs to not collide with that separate, already-visible sidebar link).
      await expect(main.getByText(card, { exact: true })).toBeVisible()
    }

    await main.getByText('Collections', { exact: true }).click()
    await expect(page).toHaveURL(/\/reports\/collections$/)
    await expect(page.getByText(patient.fullName)).toBeVisible()
    await expect(page.getByText('321.50').first()).toBeVisible()

    const downloadPromise = page.waitForEvent('download')
    await page.getByRole('button', { name: 'Export CSV' }).click()
    const download = await downloadPromise
    expect(download.suggestedFilename()).toBe('collections.csv')

    await page.goto('/reports/production')
    await expect(page.getByRole('heading', { name: 'Production' })).toBeVisible()

    await page.goto('/reports/ar-aging')
    await expect(page.getByRole('heading', { name: 'A/R Aging' })).toBeVisible()

    await page.goto('/reports/new-patients')
    await expect(page.getByText(patient.fullName)).toBeVisible()
  })

  test('financial reports are hidden from non-admin roles and blocked by direct URL, while operational reports stay accessible', async ({
    page,
  }) => {
    await loginAsEnglish(page, 'receptionist')

    await page.goto('/reports')
    await expect(page.getByText('Production', { exact: true })).toHaveCount(0)
    await expect(page.locator('main').getByText('New Patients', { exact: true })).toBeVisible()

    await page.goto('/reports/production')
    await expect(page).toHaveURL(/\/forbidden$/, { timeout: 10_000 })

    await page.goto('/reports/appointments')
    await expect(page).toHaveURL(/\/reports\/appointments$/)
    await expect(page.getByRole('heading', { name: 'Appointment Analytics' })).toBeVisible()
  })
})
