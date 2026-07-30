import { test, expect } from '@playwright/test'
import { loginAsEnglish } from './fixtures'

/**
 * Settings E2E (design doc §4). Two deliberate constraints on what this suite mutates, because
 * both Settings tables are practice-wide singletons shared by every other spec in the same CI
 * database:
 *  - Billing Settings edits `invoice_number_prefix` only. `currency_code`/`tax_rate` feed
 *    `InvoiceService` and the money figures `reports.spec.ts` asserts on; the prefix feeds nothing
 *    any other spec reads.
 *  - My Account never actually changes a demo user's password — that would break every other
 *    spec's login (`fixtures.ts`'s shared `password`). The wrong-current-password rejection path
 *    is asserted instead, which proves the same `current_password` gate (design doc §4.3) without
 *    leaving the account in a changed state.
 */
test.describe('settings', () => {
  test('admin edits practice and billing settings from the Settings hub and they persist', async ({
    page,
  }) => {
    await loginAsEnglish(page, 'admin')

    const main = page.locator('main')
    const clinicName = `E2E Dental Clinic ${Date.now().toString().slice(-6)}`

    // --- Settings home: a card per settings screen (design doc §7).
    await page.goto('/settings')
    await expect(page.getByRole('heading', { name: 'Settings' })).toBeVisible()
    // Scoped to <main> — the same labels are not in the sidebar, but "Settings" itself is.
    await expect(main.getByText('Practice Settings', { exact: true })).toBeVisible()
    await expect(main.getByText('Billing Settings', { exact: true })).toBeVisible()

    // --- Practice Settings: save clinic identity, then confirm it survived a reload.
    await main.getByText('Practice Settings', { exact: true }).click()
    await expect(page).toHaveURL(/\/settings\/practice$/)

    await page.fill('#practice-name', clinicName)
    await page.fill('#practice-phone', '0555000601')
    await page.fill('#practice-address', '12 Clinic Road, Riyadh')
    await page.getByRole('button', { name: 'Save' }).click()
    await expect(page.getByText('Practice settings saved successfully')).toBeVisible()

    await page.reload()
    await expect(page.locator('#practice-name')).toHaveValue(clinicName)

    // --- Billing Settings: prefix is editable, next invoice number is not (design doc §8.3).
    await page.goto('/settings/billing')
    await expect(page.getByRole('heading', { name: 'Billing Settings' })).toBeVisible()
    await expect(page.locator('#billing-next-sequence')).toBeDisabled()

    const prefix = `E2E-${Date.now().toString().slice(-4)}-`
    await page.fill('#billing-prefix', prefix)
    await page.getByRole('button', { name: 'Save' }).click()
    await expect(page.getByText('Billing settings saved successfully')).toBeVisible()

    await page.reload()
    await expect(page.locator('#billing-prefix')).toHaveValue(prefix)
  })

  test('Settings is hidden from non-admins and blocked by direct URL', async ({ page }) => {
    await loginAsEnglish(page, 'receptionist')

    await page.goto('/dashboard')
    await expect(page.getByText('Settings', { exact: true })).toHaveCount(0)

    await page.goto('/settings')
    await expect(page).toHaveURL(/\/forbidden$/, { timeout: 10_000 })

    await page.goto('/settings/billing')
    await expect(page).toHaveURL(/\/forbidden$/, { timeout: 10_000 })
  })

  test('every role reaches My Account from the header menu and can edit their own profile', async ({
    page,
  }) => {
    await loginAsEnglish(page, 'receptionist')

    // --- Reachable from the user-avatar menu, not the admin-only Settings nav (design doc §7).
    await page.getByRole('button', { name: 'Account', exact: true }).click()
    await page.getByRole('menuitem', { name: 'My Account' }).click()
    await expect(page).toHaveURL(/\/account$/)
    await expect(page.locator('#account-email')).toHaveValue('receptionist@example.com')

    // --- Self-service profile edit, then restored so the seeded demo user stays as seeded.
    await page.fill('#account-name', 'Layla Front-Desk E2E')
    await page.getByRole('button', { name: 'Save' }).click()
    await expect(page.getByText('Profile updated successfully')).toBeVisible()

    await page.reload()
    await expect(page.locator('#account-name')).toHaveValue('Layla Front-Desk E2E')
    await page.fill('#account-name', 'Layla Front-Desk')
    await page.getByRole('button', { name: 'Save' }).click()
    await expect(page.getByText('Profile updated successfully')).toBeVisible()

    // --- A password change is gated on the current password (design doc §4.3).
    await page.locator('#account-current-password input').fill('definitely-not-the-password')
    await page.locator('#account-new-password input').fill('an-unused-new-password')
    await page.locator('#account-confirm-password input').fill('an-unused-new-password')
    await page.getByRole('button', { name: 'Change Password' }).click()
    await expect(page.getByText('The password is incorrect.')).toBeVisible()
  })
})
