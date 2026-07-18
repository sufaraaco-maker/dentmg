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

  test('a non-admin cannot reach the admin-only Appointment Types route by direct URL', async ({
    page,
  }) => {
    await loginAsEnglish(page, 'receptionist')
    await page.goto('/appointments/types')
    await expect(page.getByText('Access Denied')).toBeVisible({ timeout: 10_000 })
  })
})
