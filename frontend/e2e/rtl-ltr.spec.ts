import { test, expect } from '@playwright/test'
import { login, DEMO_USERS } from './fixtures'

test.describe('RTL / LTR', () => {
  test('Arabic (the real default locale) renders the page right-to-left', async ({ page }) => {
    await login(page, DEMO_USERS.admin) // no forceEnglishLocale — Arabic is the actual default
    await expect(page.locator('html')).toHaveAttribute('dir', 'rtl', { timeout: 10_000 })
    await expect(page.locator('html')).toHaveAttribute('lang', 'ar')
  })

  test('switching the language selector to English flips the page left-to-right', async ({
    page,
  }) => {
    await login(page, DEMO_USERS.admin)
    await expect(page.locator('html')).toHaveAttribute('dir', 'rtl', { timeout: 10_000 })

    await page.locator('.p-select').first().click()
    await page.getByRole('option', { name: 'English' }).click()

    await expect(page.locator('html')).toHaveAttribute('dir', 'ltr', { timeout: 10_000 })
    await expect(page.locator('html')).toHaveAttribute('lang', 'en')
  })
})
