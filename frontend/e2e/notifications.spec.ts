import { test, expect, type Page } from '@playwright/test'
import { API_BASE_URL, DEMO_USERS, forceEnglishLocale, login, loginAsEnglish } from './fixtures'

/**
 * Notification System (Phase 5A) — design doc §12.3.
 *
 * Proves the cross-stack claim no unit test can: a real domain action performed by one user in a
 * real browser produces a real notification for a *different* user, who sees it, opens it, and is
 * taken to the resource.
 *
 * Deliberately does NOT mutate the shared `role_permissions` matrix. The read-time category
 * re-check (authorization layer 2) is exhaustively covered by `NotificationEndpointTest`, which
 * can revoke a permission inside a transactional test database; doing the same here would leak
 * global state into every other spec in this suite, which `role-permissions.spec.ts` already has
 * to work hard to restore. What this spec proves instead is layer 1 — structural ownership —
 * asserted the three ways `dashboard.spec.ts` established as this project's bar for a
 * security-critical case: rendered DOM, the network response itself, and direct API access.
 */

/**
 * Calls the app's own API from inside the page, so the request carries the real session cookie
 * and XSRF header — same pattern `timeline.spec.ts`/`laboratory.spec.ts` use. Throws on a non-2xx
 * so a broken fixture fails loudly at its own line rather than as a confusing assertion later.
 */
async function apiRequest<T>(
  page: Page,
  path: string,
  init: { method?: string; body?: unknown } = {},
): Promise<T> {
  return page.evaluate(
    async ({ apiUrl, path, init }) => {
      const xsrfToken = decodeURIComponent(
        document.cookie
          .split('; ')
          .find((row) => row.startsWith('XSRF-TOKEN='))
          ?.split('=')[1] ?? '',
      )
      const res = await fetch(`${apiUrl}${path}`, {
        method: init.method ?? 'GET',
        credentials: 'include',
        headers: { 'Content-Type': 'application/json', 'X-XSRF-TOKEN': xsrfToken },
        body: init.body === undefined ? undefined : JSON.stringify(init.body),
      })
      if (!res.ok) {
        throw new Error(`${init.method ?? 'GET'} ${path} failed: ${res.status} ${await res.text()}`)
      }
      return res.json()
    },
    { apiUrl: API_BASE_URL, path, init },
  )
}

/** Raw status code, for the "direct API access is blocked" assertion (which expects a non-2xx). */
async function apiStatus(page: Page, path: string, method = 'GET'): Promise<number> {
  return page.evaluate(
    async ({ apiUrl, path, method }) => {
      const xsrfToken = decodeURIComponent(
        document.cookie
          .split('; ')
          .find((row) => row.startsWith('XSRF-TOKEN='))
          ?.split('=')[1] ?? '',
      )
      const res = await fetch(`${apiUrl}${path}`, {
        method,
        credentials: 'include',
        headers: { 'Content-Type': 'application/json', 'X-XSRF-TOKEN': xsrfToken },
      })
      return res.status
    },
    { apiUrl: API_BASE_URL, path, method },
  )
}

async function logout(page: Page) {
  await apiRequest(page, '/logout', { method: 'POST' }).catch(() => undefined)
}

async function createPatient(page: Page): Promise<{ id: string; fullName: string }> {
  const stamp = Date.now().toString().slice(-6)
  const firstName = `E2ENotif${stamp}`
  const patient = await apiRequest<{ id: string }>(page, '/patients', {
    method: 'POST',
    body: {
      first_name: firstName,
      last_name: 'Patient',
      date_of_birth: '1991-03-22',
      gender: 'female',
      phone: '0555000991',
    },
  })
  return { id: patient.id, fullName: `${firstName} Patient` }
}

async function findDentistId(page: Page): Promise<string> {
  const users = await apiRequest<{ data: { id: string; email: string }[] }>(page, '/users?search=dentist')
  const dentist = users.data.find((user) => user.email === DEMO_USERS.dentist.email)
  if (!dentist) throw new Error('Demo dentist account not found')
  return dentist.id
}

/** Tomorrow at 10:00 local, in the naive "digits-labeled" format the API expects. */
function tomorrowAt(hour: number): string {
  const date = new Date()
  date.setDate(date.getDate() + 1)
  date.setHours(hour, 0, 0, 0)
  const pad = (value: number) => String(value).padStart(2, '0')
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())} ${pad(hour)}:00:00`
}

async function createAppointmentForDentist(
  page: Page,
  patientId: string,
  dentistId: string,
): Promise<string> {
  const appointment = await apiRequest<{ id: string }>(page, '/appointments', {
    method: 'POST',
    body: {
      patient_id: patientId,
      dentist_id: dentistId,
      start_at: tomorrowAt(10),
      duration_minutes: 30,
      reason: 'E2E notification fixture',
    },
  })
  return appointment.id
}

test.describe('Notification Center', () => {
  test('a cancellation by the front desk notifies the assigned dentist, who can open it', async ({
    page,
  }) => {
    await loginAsEnglish(page, 'admin')
    const patient = await createPatient(page)
    const dentistId = await findDentistId(page)
    const appointmentId = await createAppointmentForDentist(page, patient.id, dentistId)

    // The receptionist performs the cancellation — so they are the actor, and by the universal
    // actor-exclusion rule must NOT be notified of their own action.
    await logout(page)
    await login(page, DEMO_USERS.receptionist)
    await apiRequest(page, `/appointments/${appointmentId}/cancel`, {
      method: 'POST',
      body: { cancellation_reason: 'E2E cancellation' },
    })

    const actorFeed = await apiRequest<{ data: { subject_id: string }[] }>(page, '/notifications?per_page=50')
    expect(actorFeed.data.some((row) => row.subject_id === appointmentId)).toBe(false)

    // The assigned dentist, however, must see it.
    await logout(page)
    await forceEnglishLocale(page)
    await login(page, DEMO_USERS.dentist)
    await page.goto('/')

    const badge = page.getByTestId('notifications-badge')
    await expect(badge).toBeVisible({ timeout: 20_000 })

    await page.getByTestId('notifications-bell').click()
    const row = page.getByTestId(`notification-${await notificationIdFor(page, appointmentId)}`)
    await expect(row).toBeVisible()
    await expect(row).toContainText('Appointment cancelled')
    await expect(row).toContainText(patient.fullName)
    await expect(row).toHaveAttribute('data-unread', 'true')

    // Opening it marks it read and deep-links to the appointment.
    await row.click()
    await page.waitForFunction((id) => window.location.pathname.includes(id), appointmentId, {
      timeout: 20_000,
    })

    // ...and the badge reflects one fewer unread after a full reload (i.e. it was persisted,
    // not just optimistically hidden in memory).
    await page.reload()
    await page.getByTestId('notifications-bell').click()
    await expect(
      page.getByTestId(`notification-${await notificationIdFor(page, appointmentId)}`),
    ).toHaveAttribute('data-unread', 'false')
  })

  test('mark all as read persists across a reload', async ({ page }) => {
    await loginAsEnglish(page, 'admin')
    const patient = await createPatient(page)
    const dentistId = await findDentistId(page)
    const appointmentId = await createAppointmentForDentist(page, patient.id, dentistId)

    await logout(page)
    await login(page, DEMO_USERS.receptionist)
    await apiRequest(page, `/appointments/${appointmentId}/cancel`, {
      method: 'POST',
      body: { cancellation_reason: 'E2E mark-all' },
    })

    await logout(page)
    await forceEnglishLocale(page)
    await login(page, DEMO_USERS.dentist)
    await page.goto('/notifications')

    const markAll = page.getByTestId('notifications-mark-all')
    await expect(markAll).toBeVisible({ timeout: 20_000 })
    await markAll.click()

    await expect(page.getByTestId('notifications-badge')).toBeHidden({ timeout: 20_000 })

    await page.reload()
    await expect(page.getByTestId('notifications-badge')).toBeHidden({ timeout: 20_000 })
  })

  /**
   * The security-critical case, asserted the three ways this project requires (the precedent
   * `dashboard.spec.ts` set): the rendered DOM, the network response itself, and direct API
   * access. A notification addressed to the dentist must be invisible and unreachable to the
   * receptionist — layer 1, structural ownership.
   */
  test("one user can never see or act on another user's notification", async ({ page }) => {
    await loginAsEnglish(page, 'admin')
    const patient = await createPatient(page)
    const dentistId = await findDentistId(page)
    const appointmentId = await createAppointmentForDentist(page, patient.id, dentistId)

    await logout(page)
    await login(page, DEMO_USERS.receptionist)
    await apiRequest(page, `/appointments/${appointmentId}/cancel`, {
      method: 'POST',
      body: { cancellation_reason: 'E2E isolation' },
    })

    // Capture the dentist's own notification id.
    await logout(page)
    await forceEnglishLocale(page)
    await login(page, DEMO_USERS.dentist)
    const dentistNotificationId = await notificationIdFor(page, appointmentId)
    expect(dentistNotificationId).toBeTruthy()

    // Back to the receptionist — the row must be absent three different ways.
    await logout(page)
    await forceEnglishLocale(page)
    await login(page, DEMO_USERS.receptionist)
    await page.goto('/notifications')
    await expect(page.getByTestId('notifications-status-all')).toBeVisible({ timeout: 20_000 })

    // 1. Never rendered.
    await expect(page.getByTestId(`notification-${dentistNotificationId}`)).toHaveCount(0)

    // 2. Never present in the network response either — not merely hidden by the UI.
    const feed = await apiRequest<{ data: { id: string }[] }>(page, '/notifications?per_page=50')
    expect(feed.data.some((row) => row.id === dentistNotificationId)).toBe(false)

    // 3. Direct API access is blocked server-side regardless of the UI — 404, because the row is
    //    never in scope rather than being found and then rejected.
    expect(await apiStatus(page, `/notifications/${dentistNotificationId}/read`, 'POST')).toBe(404)
  })

  test('renders correctly in Arabic (RTL) and at a mobile viewport', async ({ page }) => {
    await loginAsEnglish(page, 'admin')
    const patient = await createPatient(page)
    const dentistId = await findDentistId(page)
    const appointmentId = await createAppointmentForDentist(page, patient.id, dentistId)

    await logout(page)
    await login(page, DEMO_USERS.receptionist)
    await apiRequest(page, `/appointments/${appointmentId}/cancel`, {
      method: 'POST',
      body: { cancellation_reason: 'E2E rtl' },
    })

    await logout(page)
    // Arabic is the app's real default locale — assert the module ships all 3 locales rather than
    // being English-first with translation deferred.
    await page.addInitScript(() => localStorage.setItem('dentalsuite.locale', 'ar'))
    await login(page, DEMO_USERS.dentist)
    await page.setViewportSize({ width: 390, height: 844 })
    await page.goto('/notifications')

    await expect(page.locator('html')).toHaveAttribute('dir', 'rtl')
    await expect(page.getByText('تم إلغاء الموعد').first()).toBeVisible({ timeout: 20_000 })

    // The page must never scroll horizontally at a phone width.
    const overflows = await page.evaluate(
      () => document.documentElement.scrollWidth > document.documentElement.clientWidth + 1,
    )
    expect(overflows).toBe(false)
  })
})

/** Finds the caller's own notification for a given appointment, via the app's own API. */
async function notificationIdFor(page: Page, subjectId: string): Promise<string> {
  const feed = await apiRequest<{ data: { id: string; subject_id: string }[] }>(
    page,
    '/notifications?per_page=50',
  )
  return feed.data.find((row) => row.subject_id === subjectId)?.id ?? ''
}
