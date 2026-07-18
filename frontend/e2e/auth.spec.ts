import { test, expect } from '@playwright/test'
import { DEMO_USERS, loginAsEnglish } from './fixtures'

test.describe('authentication', () => {
  test('logs in with valid credentials and reaches the dashboard', async ({ page }) => {
    await loginAsEnglish(page, 'admin')
    await expect(page).toHaveURL('/')
    await expect(page.getByText('Total Patients')).toBeVisible()
  })

  test('rejects an invalid password and stays on the login page', async ({ page }) => {
    await page.goto('/login')
    await page.waitForSelector('#email')
    await page.fill('#email', DEMO_USERS.admin.email)
    await page.locator('input[type="password"]').first().fill('definitely-wrong')
    await page.locator('button[type="submit"]').click()
    await expect(page.getByText('Invalid email or password')).toBeVisible({ timeout: 20_000 })
    await expect(page).toHaveURL(/\/login/)
  })

  test('redirects an unauthenticated guest away from a protected route', async ({ page }) => {
    await page.goto('/patients')
    await page.waitForFunction(() => window.location.pathname.includes('/login'), null, {
      timeout: 15_000,
    })
    await expect(page).toHaveURL(/\/login/)
  })
})
