import { test, expect, type Page } from '@playwright/test'
import { API_BASE_URL, forceEnglishLocale, loginAsEnglish } from './fixtures'

/** Same overlay-close race documented in `laboratory.spec.ts`/`timeline.spec.ts`. */
async function waitForSelectOverlayClosed(page: Page) {
  await expect(page.locator('.p-select-overlay')).toBeHidden()
}

async function filterByAction(page: Page, actionLabel: string) {
  await page.locator('div:has(> label:text-is("Action")) .p-select').click()
  const option = page.getByRole('option', { name: actionLabel, exact: true })
  await option.waitFor({ state: 'visible' })
  // `fetchFilterUsers()` keeps paginating through every user in the background under this
  // environment's documented per-request latency, occasionally shifting layout enough to fail
  // Playwright's strict "stable bounding box" click check on an unrelated Select's option — the
  // option is confirmed visible and correctly targeted above, so `force` skips that specific
  // stability re-check rather than the click itself.
  await option.click({ force: true })
  await waitForSelectOverlayClosed(page)

  // Waits for the filtered result itself, not just the click — under this environment's
  // documented per-request latency the re-fetch can take much longer than an assertion's default
  // 5s timeout, which previously made a genuinely-filtered row look "missing."
  const filteredResponse = page.waitForResponse(
    (r) => r.url().includes('/api/audit-logs') && r.request().method() === 'GET',
    { timeout: 60_000 },
  )
  await page.getByRole('button', { name: 'Apply' }).click()
  await filteredResponse
}

test.describe('audit log viewer', () => {
  // This environment's documented per-request latency (docs/PROJECT_STATUS.md §12) means a login
  // (~20s) plus a filtered re-fetch (up to 60s, see `filterByAction` below) can together exceed
  // the suite's default 45s test timeout even when every assertion the test cares about already
  // passed — confirmed by a failure screenshot showing the exact expected end-state at the moment
  // Playwright killed the test. Generous, not app-related.
  test.describe.configure({ timeout: 120_000 })

  test('filtering by action shows only matching rows, and a row expands to reveal it has no field diff', async ({
    page,
  }) => {
    await loginAsEnglish(page, 'admin') // itself produces a fresh `login_succeeded` row to find
    await page.goto('/audit-logs')
    await page.waitForSelector('table')
    // Settles the unfiltered mount-time fetch before filtering — under this environment's
    // documented per-request latency, that first request can resolve *after* the filtered one
    // fired below, overwriting its results with unfiltered rows (a real race, but not one a real
    // user would ever trigger by filtering within milliseconds of the page appearing).
    await page.waitForResponse((r) => r.url().includes('/api/audit-logs') && r.request().method() === 'GET')

    await filterByAction(page, 'Login succeeded')

    const rows = page.locator('table tbody tr')
    const rowCount = await rows.count()
    expect(rowCount).toBeGreaterThan(0)
    for (let i = 0; i < rowCount; i++) {
      await expect(rows.nth(i)).toContainText('Login succeeded')
    }

    await rows.first().locator('button[aria-expanded]').click()
    await expect(page.getByText('No field changes recorded')).toBeVisible()
  })

  test('a failed login is audited with the attempted email as its actor and in its context', async ({
    page,
  }) => {
    const attemptedEmail = `nonexistent-${Date.now()}@example.com`

    // Deliberately attempted *before* any real login on this page — `Auth::id()` inside
    // `AuditLogService::write()` reflects whoever is authenticated in the current session, not
    // who the attempt claims to be. Firing this from an already-authenticated admin session (as
    // an earlier version of this test did) records the *admin* as the actor, not `null` — this
    // only exercises the real "no resolved user" path when the session is genuinely anonymous.
    // Goes through the real login form (not a raw fetch) so Sanctum's CSRF cookie dance happens
    // exactly the way `fixtures.ts`'s own `login()` drives it.
    await forceEnglishLocale(page) // the app's real default is Arabic — `loginAsEnglish` isn't used
    // here since it performs a real login, which is exactly what this attempt must NOT be.
    await page.goto('/login')
    await page.waitForSelector('#email')
    await page.fill('#email', attemptedEmail)
    await page.locator('input[type="password"]').first().fill('definitely-wrong-password')
    await page.locator('button[type="submit"]').click()
    await expect(page.getByText('Invalid email or password')).toBeVisible({ timeout: 15_000 })

    await loginAsEnglish(page, 'admin')
    await page.goto('/audit-logs')
    await page.waitForSelector('table')
    await filterByAction(page, 'Login failed')

    const row = page.locator('table tbody tr', { hasText: attemptedEmail })
    await expect(row).toBeVisible()
    await expect(row).toContainText('Login failed')
    // Actor column already shows the attempted email once (no resolved `user`, design doc §2.2).
    await expect(page.getByText(attemptedEmail)).toHaveCount(1)

    await row.locator('button[aria-expanded]').click()
    await expect(page.getByText('No field changes recorded')).toBeVisible()
    await expect(page.getByText('Context')).toBeVisible()
    // The Context section renders the same email a second time — proof it's genuinely reading
    // `context.email`, not just re-displaying the Actor column value.
    await expect(page.getByText(attemptedEmail)).toHaveCount(2)
  })

  test('a non-admin gets 403 on the general audit log endpoint', async ({ page }) => {
    await loginAsEnglish(page, 'dentist')

    const status = await page.evaluate(async (apiUrl) => {
      const res = await fetch(`${apiUrl}/audit-logs`, { credentials: 'include' })
      return res.status
    }, API_BASE_URL)

    expect(status).toBe(403)
  })
})
