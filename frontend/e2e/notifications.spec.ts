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

/**
 * Hands out a distinct hour per call, so the four tests in this file never book the same dentist
 * into an overlapping slot on the shared seeded database (`fullyParallel: false`, so this is a
 * simple counter rather than anything concurrent).
 */
let slotHour = 8
function nextHour(): number {
  slotHour += 1
  return slotHour
}

/** Tomorrow at the given hour, in the naive "digits-labeled" format the API expects. */
function tomorrowAt(hour: number): string {
  const date = new Date()
  date.setDate(date.getDate() + 1)
  date.setHours(hour, 0, 0, 0)
  const pad = (value: number) => String(value).padStart(2, '0')
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())} ${pad(hour)}:00:00`
}

/**
 * `appointment_type_id` is required and must reference an *active* type — see StoreAppointmentRequest.
 * `GET /appointment-types` returns a bare array, not `{ data: [...] }` — `AppointmentTypeController::index()`
 * returns `AppointmentTypeResource::collection()` with no pagination, and `JsonResource::withoutWrapping()`
 * is set globally (see docs/decisions.md), so there is no `data` envelope to unwrap.
 */
async function findAppointmentTypeId(page: Page): Promise<string> {
  const types = await apiRequest<{ id: string; is_active: boolean }[]>(page, '/appointment-types')
  const active = types.find((type) => type.is_active)
  if (!active) throw new Error('No active appointment type found in the seeded data')
  return active.id
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
      appointment_type_id: await findAppointmentTypeId(page),
      // Each test needs its own slot: the suite runs against a shared seeded database and the
      // backend rejects overlapping appointments for the same dentist.
      start_at: tomorrowAt(nextHour()),
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

  /**
   * appointment.created — added later as a genuine gap fix, not part of the original 8/13
   * whitelisted types: AppointmentService::create() never dispatched PatientActivityOccurred at
   * all, so the assigned dentist never learned a new appointment had landed on their schedule.
   */
  test('creating an appointment notifies the assigned dentist, but not the creator', async ({ page }) => {
    await loginAsEnglish(page, 'admin')
    const patient = await createPatient(page)
    const dentistId = await findDentistId(page)
    const appointmentId = await createAppointmentForDentist(page, patient.id, dentistId)

    // The admin created it — by the universal actor-exclusion rule they must not see it either,
    // though here recipients() never resolves the admin role at all for this type.
    const actorFeed = await apiRequest<{ data: { subject_id: string }[] }>(page, '/notifications?per_page=50')
    expect(actorFeed.data.some((row) => row.subject_id === appointmentId)).toBe(false)

    await logout(page)
    await forceEnglishLocale(page)
    await login(page, DEMO_USERS.dentist)
    await page.goto('/')

    const badge = page.getByTestId('notifications-badge')
    await expect(badge).toBeVisible({ timeout: 20_000 })

    const unread = await apiRequest<{ count: number }>(page, '/notifications/unread-count')
    expect(unread.count).toBeGreaterThan(0)

    await page.getByTestId('notifications-bell').click()
    const row = page.getByTestId(`notification-${await notificationIdFor(page, appointmentId)}`)
    await expect(row).toBeVisible()
    await expect(row).toContainText('New appointment scheduled')
    await expect(row).toContainText(patient.fullName)
    await expect(row).toHaveAttribute('data-unread', 'true')
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

  /**
   * Phase 5C (design doc §5.3, type 12) — proves the `security` category, introduced this phase,
   * actually renders in a real browser (TECH_DEBT.md's "Phase 5C not yet E2E/real-browser
   * verified"). `AuditLogObserver`/`AuthService` (Decision D9) fire this reactively, with no
   * scheduler involved, so it needs no CI-side fixture: 5 real failed logins for one never-before-
   * seen email, through the real login form, is the whole trigger. A fresh generated email keeps
   * this test's own count isolated from `auth.spec.ts`'s single failed-login case.
   */
  test('5 failed logins for one email raise a security-category alert for admins', async ({ page }) => {
    const attemptEmail = `e2e-security-${Date.now()}@example.com`
    await forceEnglishLocale(page)

    for (let attempt = 0; attempt < 5; attempt += 1) {
      await page.goto('/login')
      await page.waitForSelector('#email')
      await page.fill('#email', attemptEmail)
      await page.locator('input[type="password"]').first().fill('wrong-password')
      await page.locator('button[type="submit"]').click()
      await expect(page.getByText('Invalid email or password')).toBeVisible({ timeout: 20_000 })
    }

    await loginAsEnglish(page, 'admin')
    await page.goto('/notifications')
    await page.getByTestId('notifications-category-security').click()

    const row = page.getByText('Repeated login failures').first()
    await expect(row).toBeVisible({ timeout: 20_000 })
    await expect(page.getByText(attemptEmail)).toBeVisible()
  })

  /**
   * Phase 5C (design doc §5.2, type 10) — proves the other new category, `inventory`, also renders
   * real content. Unlike `security`, this type only ever comes from the daily
   * `notifications:low-stock-digest` command, which the E2E CI job now runs once against a
   * guaranteed-low-stock supply before the frontend even starts (see ci.yml) — this test only
   * reads, matching how every other spec in this file treats CI-seeded state.
   */
  test('the inventory category shows the scheduled low-stock digest', async ({ page }) => {
    await loginAsEnglish(page, 'admin')
    await page.goto('/notifications')
    await page.getByTestId('notifications-category-inventory').click()

    await expect(page.getByText('Low stock alert').first()).toBeVisible({ timeout: 20_000 })
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
