import { test, expect } from '@playwright/test'
import { loginAsEnglish } from './fixtures'

/** Runs only under the "mobile" Playwright project (iPhone 13 viewport) — see playwright.config.ts. */
test.describe('mobile viewport', () => {
  test('Dashboard has no horizontal overflow', async ({ page }) => {
    await loginAsEnglish(page, 'admin')
    await page.goto('/')
    const { scrollWidth, clientWidth } = await page.evaluate(() => ({
      scrollWidth: document.documentElement.scrollWidth,
      clientWidth: document.documentElement.clientWidth,
    }))
    expect(scrollWidth).toBeLessThanOrEqual(clientWidth + 2)
  })

  test('Appointments Board has no horizontal overflow', async ({ page }) => {
    await loginAsEnglish(page, 'admin')
    await page.goto('/appointments')
    await page.waitForTimeout(1000) // let FullCalendar finish its initial render pass
    const { scrollWidth, clientWidth } = await page.evaluate(() => ({
      scrollWidth: document.documentElement.scrollWidth,
      clientWidth: document.documentElement.clientWidth,
    }))
    expect(scrollWidth).toBeLessThanOrEqual(clientWidth + 2)
  })
})
