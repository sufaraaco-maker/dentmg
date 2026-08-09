import { test, expect } from '@playwright/test'
import { loginAsEnglish } from './fixtures'

test.describe('permission boundaries', () => {
  test('dentist has read-only access to Patients (no New Patient button)', async ({ page }) => {
    await loginAsEnglish(page, 'dentist')
    await page.goto('/patients')
    await expect(page.getByRole('button', { name: 'New Patient' })).not.toBeVisible()
  })

  test('receptionist can create patients but not users', async ({ page }) => {
    await loginAsEnglish(page, 'receptionist')

    await page.goto('/patients')
    await expect(page.getByRole('button', { name: 'New Patient' })).toBeVisible()

    await page.goto('/users')
    await expect(page.getByRole('button', { name: 'New User' })).not.toBeVisible()
  })

  test('a non-admin cannot reach the admin-only Appointment Types route by direct URL', async ({ page }) => {
    await loginAsEnglish(page, 'receptionist')
    await page.goto('/appointments/types')
    await expect(page.getByText('Access Denied')).toBeVisible({ timeout: 10_000 })
  })

  /**
   * Phase 4 Step 4 (design doc §1.6/§2.6) — the Permissions matrix and Audit Log are both
   * admin-only screens, gated the same `roles: ['admin']` route-meta way as Appointment Types
   * above. `role-permissions.spec.ts`/`audit-log.spec.ts` cover each screen's own behavior; this
   * file covers the boundary itself (nav visibility + direct-URL blocking), matching its existing
   * "permission boundaries" scope.
   */
  test('admin sees Permissions and Audit Log in the Sidebar and can reach both routes', async ({ page }) => {
    await loginAsEnglish(page, 'admin')
    await page.goto('/')

    await expect(page.locator('a[href="/permissions"]')).toBeVisible()
    await expect(page.locator('a[href="/audit-logs"]')).toBeVisible()

    await page.goto('/permissions')
    await expect(page.getByRole('heading', { name: 'Permissions' })).toBeVisible({ timeout: 10_000 })

    await page.goto('/audit-logs')
    await expect(page.getByRole('heading', { name: 'Audit Log' })).toBeVisible({ timeout: 10_000 })
  })

  test('a non-admin has no Permissions/Audit Log nav entry and is blocked on direct URL to either', async ({
    page,
  }) => {
    await loginAsEnglish(page, 'dentist')
    await page.goto('/')

    await expect(page.locator('a[href="/permissions"]')).toHaveCount(0)
    await expect(page.locator('a[href="/audit-logs"]')).toHaveCount(0)

    await page.goto('/permissions')
    await expect(page).toHaveURL(/\/forbidden$/, { timeout: 10_000 })

    await page.goto('/audit-logs')
    await expect(page).toHaveURL(/\/forbidden$/, { timeout: 10_000 })
  })
})
