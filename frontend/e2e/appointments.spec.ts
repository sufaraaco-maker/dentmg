import { test, expect, type Page, type Locator } from '@playwright/test'
import { API_BASE_URL, loginAsEnglish } from './fixtures'

/**
 * Working hours are never seeded by DatabaseSeeder — deliberately (see docs/demo-guide.md: they
 * "become meaningful once the Appointments frontend exists to book against them," i.e. they're
 * clinic-configured setup, not demo data). A fresh CI seed genuinely has none for any dentist, so
 * `GET /dentists/{id}/working-hours` returns `[]` and the app's own SlotPicker would show "No
 * available slots" for any day. Create a real shift via the API first, so the test is
 * self-contained and doesn't assume anything about what's already configured.
 *
 * Returns a placeholder "yyyy-mm-dd HH:mm" string for the target day only — the *time* half is
 * never meant to be booked as-is; it just needs to be a syntactically valid value so the
 * DatePicker parses it into `form.start_at` and the SlotPicker (bound to that date) can fetch
 * real availability for the right day. See `pickFirstAvailableSlot` for why the actual time
 * always comes from the backend instead.
 */
async function ensureWorkingHours(page: Page, weeksAhead = 1): Promise<string> {
  return page.evaluate(
    async ({ apiUrl, weeksAhead }) => {
      const usersRes = await fetch(`${apiUrl}/users?per_page=50`, { credentials: 'include' })
      const { data: users } = await usersRes.json()
      const dentist = users.find((u: { role: string }) => u.role === 'dentist')

      const target = new Date()
      const daysAhead = 1 + (weeksAhead - 1) * 7 // tomorrow, or +weeksAhead weeks — never today
      target.setDate(target.getDate() + daysAhead)
      target.setHours(8, 0, 0, 0)

      // axios's withXSRFToken (frontend/src/lib/api.ts) reads the XSRF-TOKEN cookie and attaches
      // it automatically for state-changing requests — a raw fetch() has to do that by hand, or
      // Laravel's VerifyCsrfToken middleware rejects the POST.
      const xsrfToken = decodeURIComponent(
        document.cookie
          .split('; ')
          .find((row) => row.startsWith('XSRF-TOKEN='))
          ?.split('=')[1] ?? '',
      )

      const createRes = await fetch(`${apiUrl}/dentists/${dentist.id}/working-hours`, {
        method: 'POST',
        credentials: 'include',
        headers: { 'Content-Type': 'application/json', 'X-XSRF-TOKEN': xsrfToken },
        body: JSON.stringify({
          day_of_week: target.getDay(),
          start_time: '08:00',
          end_time: '20:00',
        }),
      })
      if (!createRes.ok) {
        throw new Error(
          `POST working-hours failed: ${createRes.status} ${await createRes.text()}`,
        )
      }

      const pad = (n: number) => String(n).padStart(2, '0')
      return `${target.getFullYear()}-${pad(target.getMonth() + 1)}-${pad(target.getDate())} ${pad(target.getHours())}:${pad(target.getMinutes())}`
    },
    { apiUrl: API_BASE_URL, weeksAhead },
  )
}

/**
 * Dismiss the DatePicker's own overlay popover without touching the outer PrimeVue Dialog.
 * Escape bubbles past the popover (it doesn't stop propagation) and triggers the Dialog's own
 * closeOnEscape, discarding the whole form. Clicking the dialog title doesn't work either — when
 * the time-picker view pushes the popover tall enough to flip and render *above* the input, it
 * overlaps the title and swallows the click meant for it. The one thing guaranteed clear of both
 * the popover (always positioned near the date input, inside the dialog) and the dialog panel
 * itself is the modal mask — `Dialog` here has no `dismissable-mask`, so a mask click can't close
 * it, but it's still a real "outside click" for the popover's own document-level listener.
 */
async function dismissDatePickerPopover(page: Page) {
  await page.mouse.click(5, 5)
}

/**
 * Pick a real, backend-confirmed free slot instead of guessing a time — via the app's own
 * "Show available slots" toggle (`SlotPicker.vue`), which calls the same `GET /available-slots`
 * endpoint a real user's browser would. This suite used to compute a fixed "tomorrow 9am"-style
 * slot by hand, which is exactly what made it flaky in CI: Playwright's automatic retry
 * (`playwright.config.ts`'s `retries: 1` in CI) re-runs the whole test from scratch after a
 * failure, so when attempt 1 successfully booked the hardcoded slot and then failed *later* for
 * an unrelated reason, the retry's own fresh attempt tried to book that identical hardcoded slot
 * again and collided with attempt 1's own leftover appointment — a `DentistConflictException`
 * that had nothing to do with the backend's conflict/availability logic (verified correct by
 * reading `AppointmentService::availableSlots`/`busyRangesForDentist`) and everything to do with
 * this test never asking the backend what was actually free. Querying real availability makes
 * every attempt — first run or retry — pick whatever slot is free *right now*, so this class of
 * self-collision can't happen again.
 */
async function pickFirstAvailableSlot(dialog: Locator) {
  await dialog.getByRole('switch', { name: 'Show available slots' }).click()
  // Only slot chips carry `aria-pressed` (see SlotPicker.vue); unavailable ones are `disabled`.
  const availableSlot = dialog.locator('button[aria-pressed]:not([disabled])').first()
  await expect(availableSlot).toBeVisible({ timeout: 15_000 })
  await availableSlot.click()
}

test.describe('appointments', () => {
  test('Calendar Board loads and switches between Day/Week/Month/List views', async ({ page }) => {
    await loginAsEnglish(page, 'admin')
    await page.goto('/appointments')
    await expect(page.getByRole('button', { name: 'New Appointment' })).toBeVisible({
      timeout: 15_000,
    })

    for (const view of ['Day', 'Week', 'Month', 'List'] as const) {
      await page.getByRole('button', { name: view, exact: true }).click()
      await expect(page.getByRole('button', { name: view, exact: true })).toHaveAttribute(
        'aria-pressed',
        'true',
      )
    }
  })

  test('creates, reschedules, and cancels an appointment', async ({ page }) => {
    await loginAsEnglish(page, 'admin')
    await page.goto('/appointments')

    // Seeded patients have randomly-generated names (PatientFactory — see docs/demo-guide.md,
    // "exact names differ on every fresh migrate:fresh --seed") and `patient_code` isn't a
    // searchable field (see PatientService::paginate), so a hardcoded name only works against
    // whatever happened to be seeded locally when the test was written. Fetch a real one instead
    // — via the page's own fetch (not `page.request`, a separate API context whose requests lack
    // the Origin/Referer headers Sanctum's stateful-domain check keys off, which made the same
    // call 401 despite valid cookies) so it authenticates exactly like the app's own code does.
    const patientName: string = await page.evaluate(async (apiUrl) => {
      const res = await fetch(`${apiUrl}/patients?per_page=1`, { credentials: 'include' })
      const { data } = await res.json()
      return data[0].full_name
    }, API_BASE_URL)
    // PatientService::paginate's search matches first_name/last_name/phone/national_id/email
    // individually — never the concatenated full name — so searching "Aiden Kunde" as one
    // string matches neither field's own LIKE clause. Search on the first token only.
    const searchTerm = patientName.split(' ')[0]

    await page.getByRole('button', { name: 'New Appointment' }).click()

    // Scoped to the dialog — the Board's always-visible CalendarFilters sidebar has its own
    // PatientSearchSelect (for filtering the calendar) with this exact same placeholder text,
    // so an unscoped page-wide locator matches two elements (strict-mode violation).
    const dialog = page.locator('.p-dialog')
    const searchInput = dialog.getByPlaceholder('Search by name, code, or phone')
    await expect(searchInput).toBeVisible({ timeout: 15_000 })
    await searchInput.fill(searchTerm)

    // PatientSearchSelect renders results as a plain `<ul>/<li>` list (not a PrimeVue overlay
    // component) — the patient's name lives in a `<span>` inside each `<li>`, so scoping by
    // exact visible text is both correct and immune to the earlier debugging session's mistake
    // of guessing PrimeVue-style `.p-listbox-option`/`[role="option"]` classes that don't exist
    // on this component.
    const patientOption = dialog.locator('li', { hasText: patientName })
    await expect(patientOption).toBeVisible({ timeout: 10_000 })
    await patientOption.click()

    await page.getByRole('tab', { name: 'Appointment' }).click()

    await page.locator('div:has(> label:text-is("Dentist")) .p-select').click()
    await page.locator('li.p-select-option').first().click()

    await page.locator('div:has(> label:text-is("Appointment Type")) .p-select').click()
    await page.locator('li.p-select-option').first().click()

    const placeholderDate = await ensureWorkingHours(page)
    const createStartAtInput = page.locator('div:has(> label:text-is("Date & Time")) input')
    await createStartAtInput.click()
    await createStartAtInput.pressSequentially(placeholderDate, { delay: 20 })
    await dismissDatePickerPopover(page)
    await pickFirstAvailableSlot(dialog)

    // Capture the created appointment's own id from the real API response instead of assuming
    // it lands on any particular row/page of the List view afterwards — with `DemoDataSeeder`'s
    // ~110 seeded patients (and their own appointments) sharing the same current-week range, "the
    // first row" is no longer reliably *this* appointment (see TECH_DEBT.md's `DemoDataSeeder`
    // entry). Listening for the real `POST /appointments` response is deterministic regardless of
    // how much other data shares the same week.
    const createResponsePromise = page.waitForResponse(
      (res) => res.request().method() === 'POST' && res.url().includes('/appointments'),
    )
    await page.getByRole('button', { name: 'Save' }).click()
    // `Locator.isVisible()` doesn't wait/retry — it checks once and returns immediately, so it
    // was racing the save request and reporting failure before the toast had a chance to render.
    // `expect(...).toBeVisible()` actually polls, and on a genuine failure we still want the
    // dialog's contents (a validation/conflict message) instead of a bare "not found" timeout.
    const successToast = page.getByText('Appointment saved successfully')
    try {
      await expect(successToast).toBeVisible({ timeout: 15_000 })
    } catch {
      const dialogText = await dialog.innerText().catch(() => '(dialog not found)')
      throw new Error(`Save did not succeed. Dialog contents:\n${dialogText}`)
    }
    const { id: createdAppointmentId } = await (await createResponsePromise).json()

    // Navigate straight to the created appointment's own detail page instead of the List view's
    // "first row" — immune to how many other appointments share the current week (see above).
    await page.goto(`/appointments/${createdAppointmentId}`)

    await page.getByRole('button', { name: 'Edit' }).click()
    // The dialog always opens on the Patient tab (`AppointmentDialog.vue`'s `visible` watcher
    // resets `activeTab` unconditionally, edit mode included) — without this click, the Date &
    // Time field lives in a TabPanel that isn't even rendered yet, and CI's own logs show exactly
    // that: a 45s timeout with "element is not visible" on `startAtInput`, not a DatePicker issue.
    await page.getByRole('tab', { name: 'Appointment' }).click()

    const reschedulePlaceholderDate = await ensureWorkingHours(page, 2) // a different week than the created slot
    const startAtInput = page.locator('div:has(> label:text-is("Date & Time")) input')
    await startAtInput.click()
    await page.keyboard.press('Control+A')
    await startAtInput.pressSequentially(reschedulePlaceholderDate, { delay: 20 })
    await dismissDatePickerPopover(page)
    await pickFirstAvailableSlot(dialog)

    await page.getByRole('button', { name: 'Save' }).click()
    const rescheduleToast = page.getByText('Appointment saved successfully')
    try {
      await expect(rescheduleToast).toBeVisible({ timeout: 15_000 })
    } catch {
      const dialogText = await dialog.innerText().catch(() => '(dialog not found)')
      throw new Error(`Reschedule save did not succeed. Dialog contents:\n${dialogText}`)
    }

    // The Edit dialog's own DOM node (and its own "Cancel" footer button) can still be present
    // right after a successful save — `Dialog`'s close isn't instant — and would otherwise
    // strict-mode-collide with the detail page's real Cancel button below.
    await expect(dialog).toBeHidden({ timeout: 10_000 })

    // Cancel from the same detail page.
    await page.getByRole('button', { name: 'Cancel', exact: true }).click()
    await page.getByRole('button', { name: 'Cancel Appointment' }).click()
    // A real cancellation renders "Cancelled" in two places at once — the status chip
    // (`AppointmentStatusChip.vue`, which sets `aria-label` to the status label) and the
    // Timeline's own plain-text step — so `getByText('Cancelled')` is a strict-mode violation the
    // moment the page actually updates in time to show both (confirmed via CI, where the update
    // is fast enough to hit this; local runs were slow enough to mask it entirely). `getByLabel`
    // targets only the status chip.
    await expect(page.getByLabel('Cancelled')).toBeVisible({ timeout: 15_000 })
  })
})
