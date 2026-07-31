import { test, expect } from '@playwright/test'
import { loginAsEnglish } from './fixtures'

/**
 * AI Assistant E2E (design doc §2/§5, Approval 2026-07-31). No ANTHROPIC_API_KEY is configured in
 * the E2E environment, so this suite never submits a real question — it proves the module's
 * central guarantee instead: every AI entry point across the app is *absent*, not just disabled,
 * until an admin explicitly enables it in Settings.
 *
 * `ai_assistant_enabled`/`ai_assistant_phi_features_acknowledged` live on the same singleton
 * `clinic_settings` row every other Settings spec shares — this test always leaves both back at
 * `false` before finishing, mirroring `settings.spec.ts`'s own precedent for shared-state cleanup.
 * PHI-feature gating itself (Clinical Notes draft-assist / Treatment Suggestions requiring the
 * separate BAA acknowledgment) is covered by the backend Feature suite
 * (`AiAssistantGatingTest`) and by `AiAssistantSettingsView`'s own Vitest coverage; it isn't
 * re-proven here to avoid depending on seeded draft-note/plan fixtures this suite doesn't own.
 */
test.describe('ai-assistant', () => {
  test('AI entry points are absent by default and appear across the app once enabled', async ({ page }) => {
    await loginAsEnglish(page, 'admin')

    // --- Settings: toggle off by default; PHI block hidden until the general toggle is on.
    await page.goto('/settings/ai-assistant')
    await expect(page.getByRole('heading', { name: 'AI Assistant Settings' })).toBeVisible()
    const switches = page.getByRole('switch')
    await expect(switches).toHaveCount(1)
    await expect(switches.nth(0)).not.toBeChecked()
    await expect(page.getByText('Business Associate Agreement')).toHaveCount(0)

    // --- Confirm the zero-PHI entry points are absent everywhere before enabling.
    await page.goto('/dashboard')
    await expect(page.getByText('Ask a question about your data')).toHaveCount(0)
    await page.goto('/patients')
    await expect(page.getByText('Smart Search', { exact: true })).toHaveCount(0)
    await page.goto('/reports/production')
    await expect(page.getByText('Explain this report')).toHaveCount(0)

    // --- Enable the general toggle; the PHI block appears but stays unacknowledged by default.
    await page.goto('/settings/ai-assistant')
    await switches.nth(0).click()
    await expect(page.getByText('Business Associate Agreement')).toBeVisible()
    await expect(switches).toHaveCount(2)
    await expect(switches.nth(1)).not.toBeChecked()
    await page.getByRole('button', { name: 'Save' }).click()
    await expect(page.getByText('AI Assistant settings saved successfully')).toBeVisible()

    // --- Zero-PHI entry points now appear across Dashboard, Patients, and Reports.
    await page.goto('/dashboard')
    await expect(page.getByText('Ask a question about your data')).toBeVisible()
    await page.goto('/patients')
    await expect(page.getByText('Smart Search', { exact: true })).toBeVisible()
    await page.goto('/reports/production')
    await expect(page.getByText('Explain this report')).toBeVisible()

    // --- Cleanup: leave the shared singleton back at its default, off state.
    await page.goto('/settings/ai-assistant')
    await switches.nth(0).click()
    await page.getByRole('button', { name: 'Save' }).click()
    await expect(page.getByText('AI Assistant settings saved successfully')).toBeVisible()
    await page.reload()
    await expect(switches.nth(0)).not.toBeChecked()

    // --- And confirm the entry points are gone again.
    await page.goto('/dashboard')
    await expect(page.getByText('Ask a question about your data')).toHaveCount(0)
  })

  test('AI Assistant Settings is hidden from non-admins and blocked by direct URL', async ({ page }) => {
    await loginAsEnglish(page, 'receptionist')

    await page.goto('/settings/ai-assistant')
    await expect(page).toHaveURL(/\/forbidden$/, { timeout: 10_000 })
  })
})
