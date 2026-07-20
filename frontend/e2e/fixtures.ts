import { type Page } from '@playwright/test'

/**
 * `page.request` resolves relative URLs against Playwright's `baseURL` (the frontend dev
 * server), not the backend — Vite has no `/api` proxy configured (the app calls the backend via
 * an absolute `VITE_API_URL`, see `frontend/src/lib/api.ts`), so a relative `/api/...` request
 * here would hit Vite's SPA fallback and return `index.html` instead of JSON.
 */
export const API_BASE_URL = process.env.E2E_API_URL ?? 'http://localhost:8000/api'

export const DEMO_USERS = {
  admin: { email: 'admin@example.com', password: 'password' },
  dentist: { email: 'dentist@example.com', password: 'password' },
  receptionist: { email: 'receptionist@example.com', password: 'password' },
} as const

/**
 * Forces English before the first navigation so selectors can rely on stable English copy — the
 * app's real default locale is Arabic (RTL), which the dedicated `rtl-ltr.spec.ts` suite tests
 * on its own terms instead.
 */
export async function forceEnglishLocale(page: Page) {
  await page.addInitScript(() => localStorage.setItem('dentalsuite.locale', 'en'))
}

/**
 * Vue Router's client-side navigation (`history.pushState`) never fires the browser's `load`
 * lifecycle event Playwright's `waitForURL`/`waitForNavigation` default to — polling the DOM
 * location via `waitForFunction` is the reliable way to detect a post-login SPA redirect.
 */
export async function login(
  page: Page,
  { email, password }: { email: string; password: string },
) {
  await page.goto('/login')
  await page.waitForSelector('#email')
  await page.fill('#email', email)
  await page.locator('input[type="password"]').first().fill(password)
  await page.locator('button[type="submit"]').click()
  await page.waitForFunction(() => !window.location.pathname.includes('/login'), null, {
    timeout: 25_000,
  })
}

export async function loginAsEnglish(page: Page, user: keyof typeof DEMO_USERS = 'admin') {
  await forceEnglishLocale(page)
  await login(page, DEMO_USERS[user])
}
