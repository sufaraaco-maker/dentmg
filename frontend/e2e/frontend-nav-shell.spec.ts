import { test, expect } from '@playwright/test'
import { loginAsEnglish } from './fixtures'

/**
 * Frontend UX & Navigation Redesign — Phase 1 (design doc `modules/frontend-ux-redesign.md` §5).
 * Every test gets a fresh, isolated browser context by default (no Playwright `storageState`
 * reuse configured for this project), so the `sidebarPreferences` `localStorage` state this suite
 * exercises (favorites, collapsed sections) never leaks between tests or specs.
 */
test.describe('frontend-nav-shell', () => {
  test('Command Palette opens via the header button and via Ctrl+K, and navigates', async ({ page }) => {
    await loginAsEnglish(page, 'admin')

    await page.getByRole('button', { name: 'Command Palette' }).click()
    await expect(page.getByPlaceholder('Search or jump to...')).toBeVisible()
    await page.keyboard.press('Escape')
    await expect(page.getByPlaceholder('Search or jump to...')).toBeHidden()

    await page.keyboard.press('Control+K')
    const input = page.getByPlaceholder('Search or jump to...')
    await expect(input).toBeVisible()

    await input.fill('Patients')
    await expect(page.getByRole('option', { name: 'Go to Patients' })).toBeVisible()
    await page.keyboard.press('Enter')

    await expect(page).toHaveURL(/\/patients$/)
    await expect(input).toBeHidden()
  })

  test('Billing sidebar entry is a real link to the clinic-wide invoice list, not "Soon"', async ({ page }) => {
    await loginAsEnglish(page, 'admin')

    const main = page.locator('aside').first()
    const billingLink = main.getByRole('link', { name: 'Billing' })
    await expect(billingLink).toBeVisible()

    await billingLink.click()
    await expect(page).toHaveURL(/\/invoices$/)
    await expect(page.getByRole('heading', { name: 'Billing' })).toBeVisible()
  })

  test('sections collapse/expand and favoriting an item surfaces it in a Favorites group', async ({ page }) => {
    await loginAsEnglish(page, 'admin')

    const sidebar = page.locator('aside').first()
    const operationsHeader = sidebar.getByRole('button', { name: 'Operations' })
    await expect(sidebar.getByRole('link', { name: 'Billing' })).toBeVisible()

    await operationsHeader.click()
    await expect(sidebar.getByRole('link', { name: 'Billing' })).toBeHidden()
    await operationsHeader.click()
    await expect(sidebar.getByRole('link', { name: 'Billing' })).toBeVisible()

    await expect(sidebar.getByText('Favorites')).toHaveCount(0)
    const billingRow = sidebar.getByRole('link', { name: 'Billing' })
    await billingRow.hover()
    await billingRow.getByRole('button', { name: 'Add to favorites' }).click()

    await expect(sidebar.getByText('Favorites')).toBeVisible()
  })

  test('keyboard shortcuts help opens on "?" and lists the go-to chords', async ({ page }) => {
    await loginAsEnglish(page, 'admin')

    await page.keyboard.press('?')
    await expect(page.getByRole('heading', { name: 'Keyboard Shortcuts' })).toBeVisible()
    await expect(page.getByText('Open Command Palette')).toBeVisible()
  })

  test('"g" then "p" navigates to Patients', async ({ page }) => {
    await loginAsEnglish(page, 'admin')

    await page.keyboard.press('g')
    await page.keyboard.press('p')

    await expect(page).toHaveURL(/\/patients$/)
  })
})
