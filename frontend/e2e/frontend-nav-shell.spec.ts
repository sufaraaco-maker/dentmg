import { test, expect, type Page } from '@playwright/test'
import { loginAsEnglish } from './fixtures'

/**
 * Frontend UX & Navigation Redesign — Phase 1 (design doc `modules/frontend-ux-redesign.md` §5).
 * Every test gets a fresh, isolated browser context by default (no Playwright `storageState`
 * reuse configured for this project), so the `sidebarPreferences` `localStorage` state this suite
 * exercises (favorites, collapsed sections) never leaks between tests or specs.
 */

/**
 * `loginAsEnglish` only waits for the URL to leave `/login` (a `waitForFunction` on
 * `location.pathname`), not for `DefaultLayout`/`useAppShortcuts` to actually finish mounting —
 * for tests that fire a raw keyboard shortcut as their very first page interaction, waiting for a
 * concrete shell element first removes that race instead of hoping the timing works out.
 */
async function loginAndWaitForShell(page: Page) {
  await loginAsEnglish(page, 'admin')
  await expect(page.locator('aside').first()).toBeVisible()
}

test.describe('frontend-nav-shell', () => {
  test('Command Palette opens via the header button and via Ctrl+K, and navigates', async ({ page }) => {
    await loginAndWaitForShell(page)

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
    await loginAndWaitForShell(page)

    const main = page.locator('aside').first()
    const billingLink = main.getByRole('link', { name: 'Billing' })
    await expect(billingLink).toBeVisible()

    await billingLink.click()
    await expect(page).toHaveURL(/\/invoices$/)
    await expect(page.getByRole('heading', { name: 'Billing' })).toBeVisible()
  })

  test('sections collapse/expand and favoriting an item surfaces it in a Favorites group', async ({ page }) => {
    await loginAndWaitForShell(page)

    const sidebar = page.locator('aside').first()
    const operationsHeader = sidebar.getByRole('button', { name: 'Operations' })
    await expect(sidebar.getByRole('link', { name: 'Billing' })).toBeVisible()

    await operationsHeader.click()
    await expect(sidebar.getByRole('link', { name: 'Billing' })).toBeHidden()
    await operationsHeader.click()
    await expect(sidebar.getByRole('link', { name: 'Billing' })).toBeVisible()

    await expect(sidebar.getByText('Favorites')).toHaveCount(0)
    // The favorite star is a *sibling* of the link, not a descendant (a `<button>` can never
    // nest inside an `<a>` — invalid HTML) — scope to the shared parent row via `xpath=..`
    // instead of searching inside the link itself, which would resolve to zero elements.
    const billingLink = sidebar.getByRole('link', { name: 'Billing' })
    const billingRow = billingLink.locator('xpath=..')
    await billingRow.hover()
    await billingRow.getByRole('button', { name: 'Add to favorites' }).click()

    await expect(sidebar.getByText('Favorites')).toBeVisible()
  })

  test('keyboard shortcuts help opens on "?" and lists the go-to chords', async ({ page }) => {
    await loginAndWaitForShell(page)

    await page.keyboard.press('?')
    await expect(page.getByRole('heading', { name: 'Keyboard Shortcuts' })).toBeVisible()
    await expect(page.getByText('Open Command Palette')).toBeVisible()
  })

  test('"g" then "p" navigates to Patients', async ({ page }) => {
    await loginAndWaitForShell(page)

    await page.keyboard.press('g')
    await page.keyboard.press('p')

    await expect(page).toHaveURL(/\/patients$/)
  })
})
