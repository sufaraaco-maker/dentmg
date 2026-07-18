import { defineConfig, devices } from '@playwright/test'

/**
 * Live-browser E2E suite — drives the real app against a running docker compose stack (frontend
 * dev server on :5173, backend API on :8000). Not a replacement for the Vitest component suite
 * (`npm run test`) or the backend Feature test suite; this is the cross-stack, real-browser layer
 * for the handful of scenarios that only a real browser can prove (auth cookie flow, RTL/LTR
 * layout mirroring, responsive overflow, permission-gated navigation). See docs/deployment.md
 * "Production Testing Pass" and TECH_DEBT.md for scope notes.
 */
export default defineConfig({
  testDir: './e2e',
  fullyParallel: false, // shared seeded DB state (patients/appointments) — tests must not race each other
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 1 : 0,
  workers: 1,
  reporter: process.env.CI ? [['github'], ['html', { open: 'never' }]] : 'list',
  timeout: 45_000,
  use: {
    baseURL: process.env.E2E_BASE_URL ?? 'http://localhost:5173',
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
  },
  projects: [
    { name: 'chromium', use: { ...devices['Desktop Chrome'] } },
    { name: 'mobile', use: { ...devices['iPhone 13'] }, testMatch: /mobile\.spec\.ts/ },
  ],
})
