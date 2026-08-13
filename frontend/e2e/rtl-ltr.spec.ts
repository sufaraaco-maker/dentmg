import { test, expect } from '@playwright/test'
import { login, DEMO_USERS, forceArabicLocale } from './fixtures'

test.describe('RTL / LTR', () => {
  test('English (the real default locale) renders the page left-to-right', async ({ page }) => {
    await login(page, DEMO_USERS.admin) // no locale forced — English is the actual default (2026-08-13)
    await expect(page.locator('html')).toHaveAttribute('dir', 'ltr', { timeout: 10_000 })
    await expect(page.locator('html')).toHaveAttribute('lang', 'en')
  })

  test('a stored Arabic preference renders the page right-to-left', async ({ page }) => {
    await forceArabicLocale(page)
    await login(page, DEMO_USERS.admin)
    await expect(page.locator('html')).toHaveAttribute('dir', 'rtl', { timeout: 10_000 })
    await expect(page.locator('html')).toHaveAttribute('lang', 'ar')
  })

  test('switching the language selector to Arabic flips the page right-to-left', async ({ page }) => {
    await login(page, DEMO_USERS.admin)
    await expect(page.locator('html')).toHaveAttribute('dir', 'ltr', { timeout: 10_000 })

    await page.locator('.p-select').first().click()
    await page.getByRole('option', { name: 'العربية' }).click()

    await expect(page.locator('html')).toHaveAttribute('dir', 'rtl', { timeout: 10_000 })
    await expect(page.locator('html')).toHaveAttribute('lang', 'ar')
  })
})
