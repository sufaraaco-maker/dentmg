import { test, expect } from '@playwright/test'
import { loginAsEnglish } from './fixtures'

test.describe('patients CRUD', () => {
  test('creates a new patient and finds it via search', async ({ page }) => {
    await loginAsEnglish(page, 'admin')
    await page.goto('/patients')
    await page.getByRole('button', { name: 'New Patient' }).click()
    await expect(page.getByText('First Name')).toBeVisible()

    const stamp = Date.now().toString().slice(-6)
    const firstName = `E2E${stamp}`
    const lastName = 'Suite'

    await page.locator('div:has(> label:text-is("First Name")) input').fill(firstName)
    await page.locator('div:has(> label:text-is("Last Name")) input').fill(lastName)
    await page.locator('div:has(> label:text-is("Phone")) input').fill('0555000333')

    // PrimeVue DatePicker's masked text input needs real keystrokes (`.fill()` bypasses the
    // input events it listens for and leaves the field's native `required` validation blocking
    // submit) — see TECH_DEBT.md's Production Gate entry for how this was diagnosed.
    const dobInput = page.locator('div:has(> label:text-is("Date of Birth")) input')
    await dobInput.click()
    await dobInput.pressSequentially('1990-01-15', { delay: 20 })
    await page.keyboard.press('Escape')

    await page.locator('div:has(> label:text-is("Gender")) .p-select').click()
    // { exact: true } matters here — "Female" contains "male" as a substring, so accessible-name
    // matching without it resolves ambiguously to both options (strict-mode violation).
    await page.getByRole('option', { name: 'Male', exact: true }).click()

    await page.getByRole('button', { name: 'Save' }).click()
    await expect(page.getByText('Patient saved successfully')).toBeVisible({ timeout: 10_000 })

    await page.goto('/patients')
    await page.getByPlaceholder(/Search by name/).fill(`${firstName} ${lastName}`)
    await expect(page.getByText(`${firstName} ${lastName}`)).toBeVisible({ timeout: 10_000 })
  })
})
