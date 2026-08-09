import { test, expect } from '@playwright/test'
import { API_BASE_URL, loginAsEnglish } from './fixtures'

test.describe('dashboard', () => {
  test('admin sees both stat cards and the admin-only Financial Snapshot widget alongside Unscheduled Treatment', async ({
    page,
  }) => {
    await loginAsEnglish(page, 'admin')
    await page.goto('/')

    await expect(page.getByText('Total Patients')).toBeVisible({ timeout: 15_000 })
    await expect(page.getByText("Today's Appointments")).toBeVisible()
    await expect(page.getByText('Financial Snapshot')).toBeVisible({ timeout: 10_000 })
    await expect(page.getByText('Unscheduled Treatment')).toBeVisible()
  })

  /**
   * Regression test for a real, pre-existing leak found while designing Dashboard 2.0
   * (`docs/modules/dashboard-2.0-design.md` §0): `/dashboard/summary` used to return
   * `monthly_revenue` to every role with no authorization check at all, the same figure
   * `reports/collections` already restricted to admins. The fix splits financial data into a
   * separately-gated `/dashboard/financial-summary` endpoint — this asserts both that the UI never
   * renders it AND, mirroring `timeline.spec.ts`'s "not just hidden by the frontend" pattern, that
   * the network request itself is never even made for a non-admin session, plus that hitting the
   * endpoint directly is server-side blocked regardless of what the UI does.
   */
  test('financial data is never shown to or requested by a non-admin, and is server-side blocked on direct access', async ({
    page,
  }) => {
    const financialRequests: string[] = []
    page.on('request', (req) => {
      if (req.url().includes('/dashboard/financial-summary')) financialRequests.push(req.url())
    })

    await loginAsEnglish(page, 'receptionist')
    await page.goto('/')

    await expect(page.getByText('Total Patients')).toBeVisible({ timeout: 15_000 })
    await expect(page.getByText('Unscheduled Treatment')).toBeVisible()
    await expect(page.getByText('Financial Snapshot')).toHaveCount(0)
    expect(financialRequests).toHaveLength(0)

    const response = await page.evaluate(async (apiUrl) => {
      const res = await fetch(`${apiUrl}/dashboard/financial-summary`, { credentials: 'include' })
      return res.status
    }, API_BASE_URL)
    expect(response).toBe(403)
  })

  test('dentist also does not see the Financial Snapshot widget', async ({ page }) => {
    await loginAsEnglish(page, 'dentist')
    await page.goto('/')

    await expect(page.getByText('Total Patients')).toBeVisible({ timeout: 15_000 })
    await expect(page.getByText('Financial Snapshot')).toHaveCount(0)
  })
})
