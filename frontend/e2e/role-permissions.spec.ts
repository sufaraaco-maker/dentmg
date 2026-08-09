import { test, expect, type Page } from '@playwright/test'
import { API_BASE_URL, loginAsEnglish } from './fixtures'

/** Real logout via the app's own API — same pattern as `laboratory.spec.ts`'s `logout`, needed to
 *  switch actors (admin -> receptionist -> admin) within a single test. */
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

type RolePermissionMatrix = { admin: string[]; dentist: string[]; receptionist: string[] }

async function fetchMatrix(page: Page): Promise<RolePermissionMatrix> {
  return page.evaluate(async (apiUrl) => {
    const res = await fetch(`${apiUrl}/role-permissions`, { credentials: 'include' })
    if (!res.ok) throw new Error(`GET /role-permissions failed: ${res.status}`)
    return res.json()
  }, API_BASE_URL)
}

/** `role_permissions` is a global table shared by every other E2E spec (design doc §7) — this is
 *  the same authenticated-fetch pattern `laboratory.spec.ts`'s `createPatient` uses, applied to a
 *  direct API restore so a test that mutates the live matrix can always put it back exactly,
 *  regardless of how the test itself finishes. */
async function putMatrix(page: Page, assignments: RolePermissionMatrix): Promise<void> {
  await page.evaluate(
    async ({ apiUrl, assignments }) => {
      const xsrfToken = decodeURIComponent(
        document.cookie
          .split('; ')
          .find((row) => row.startsWith('XSRF-TOKEN='))
          ?.split('=')[1] ?? '',
      )
      const res = await fetch(`${apiUrl}/role-permissions`, {
        method: 'PUT',
        credentials: 'include',
        headers: { 'Content-Type': 'application/json', 'X-XSRF-TOKEN': xsrfToken },
        body: JSON.stringify({ assignments }),
      })
      if (!res.ok) throw new Error(`PUT /role-permissions failed: ${res.status} ${await res.text()}`)
    },
    { apiUrl: API_BASE_URL, assignments },
  )
}

test.describe('role permissions matrix', () => {
  test('the Admin/"Manage users" cell cannot be unlocked — server-enforced self-lockout mirrored in the UI', async ({
    page,
  }) => {
    await loginAsEnglish(page, 'admin')
    await page.goto('/permissions')
    await page.waitForSelector('table')

    const row = page.locator('table tbody tr', { hasText: 'Manage users' })
    // Column order is Admin, Dentist, Receptionist (design doc §1.5/§1.6).
    const adminCheckbox = row.locator('input[type="checkbox"]').nth(0)
    await expect(adminCheckbox).toBeDisabled()
    await expect(adminCheckbox).toBeChecked()
  })

  test('admin toggles a permission off, it persists and takes effect for that role, and is audited with before/after', async ({
    page,
  }) => {
    test.setTimeout(120_000)

    await loginAsEnglish(page, 'admin')
    const originalMatrix = await fetchMatrix(page)

    try {
      await page.goto('/permissions')
      await page.waitForSelector('table')

      // Toggle off receptionist's `patients.manage` — has a directly observable effect
      // (`permissions.spec.ts` already proves the New Patient button depends on it).
      const row = page.locator('table tbody tr', { hasText: 'Manage patients' })
      const receptionistToggle = row.locator('.p-toggleswitch').nth(2)
      await receptionistToggle.click()
      await expect(page.getByText('You have unsaved changes')).toBeVisible()

      await page.getByRole('button', { name: 'Save Changes' }).click()
      await expect(page.getByText('Permissions saved successfully')).toBeVisible({ timeout: 15_000 })

      // Persists across a full reload, not just local component state.
      await page.reload()
      await page.waitForSelector('table')
      const reloadedCheckbox = page
        .locator('table tbody tr', { hasText: 'Manage patients' })
        .locator('input[type="checkbox"]')
        .nth(2)
      await expect(reloadedCheckbox).not.toBeChecked()

      // Takes effect for a live receptionist session, not just the matrix screen's own state.
      await logout(page)
      await loginAsEnglish(page, 'receptionist')
      await page.goto('/patients')
      await expect(page.getByRole('button', { name: 'New Patient' })).toHaveCount(0)

      // The change itself is audited with a real before/after diff. Filtered by action (rather
      // than assumed to be the newest row) since the receptionist/admin re-logins above each add
      // their own newer `login_succeeded` rows on top of it.
      await logout(page)
      await loginAsEnglish(page, 'admin')
      await page.goto('/audit-logs')
      await page.waitForSelector('table')

      await page.locator('div:has(> label:text-is("Action")) .p-select').click()
      await page.locator('li.p-select-option:visible', { hasText: 'Permissions matrix updated' }).click()
      await expect(page.locator('.p-select-overlay')).toBeHidden()
      await page.getByRole('button', { name: 'Apply' }).click()

      const matrixUpdateRow = page.locator('table tbody tr').first()
      await expect(matrixUpdateRow).toContainText('Permissions matrix updated')
      await matrixUpdateRow.locator('button[aria-expanded]').click()

      const expansion = page.locator('table tbody tr').nth(1)
      await expect(expansion).toContainText('receptionist')
      await expect(expansion).toContainText('patients.manage')
    } finally {
      await putMatrix(page, originalMatrix)
    }
  })

  test('a non-admin gets 403 on the permission-catalog and role-permissions-matrix endpoints', async ({ page }) => {
    await loginAsEnglish(page, 'receptionist')

    const statuses = await page.evaluate(async (apiUrl) => {
      // A PUT needs a real XSRF header or Laravel's CSRF middleware rejects it with 419 before
      // the request ever reaches the `manage-permissions` Gate this test means to exercise.
      const xsrfToken = decodeURIComponent(
        document.cookie
          .split('; ')
          .find((row) => row.startsWith('XSRF-TOKEN='))
          ?.split('=')[1] ?? '',
      )
      const [catalog, matrixGet, matrixPut] = await Promise.all([
        fetch(`${apiUrl}/permissions`, { credentials: 'include' }),
        fetch(`${apiUrl}/role-permissions`, { credentials: 'include' }),
        fetch(`${apiUrl}/role-permissions`, {
          method: 'PUT',
          credentials: 'include',
          headers: { 'Content-Type': 'application/json', 'X-XSRF-TOKEN': xsrfToken },
          body: JSON.stringify({ assignments: { admin: [], dentist: [], receptionist: [] } }),
        }),
      ])
      return { catalog: catalog.status, matrixGet: matrixGet.status, matrixPut: matrixPut.status }
    }, API_BASE_URL)

    expect(statuses.catalog).toBe(403)
    expect(statuses.matrixGet).toBe(403)
    expect(statuses.matrixPut).toBe(403)
  })
})
