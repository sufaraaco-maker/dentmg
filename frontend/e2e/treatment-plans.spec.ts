import { test, expect, type Page, type Locator } from '@playwright/test'
import { API_BASE_URL, DEMO_USERS, loginAsEnglish, forceArabicLocale } from './fixtures'

/**
 * Treatment Plans — permanent E2E suite (TECH_DEBT.md: "No permanent E2E suite for Treatment
 * Plans"). Scenarios mirror `docs/modules/treatment-plans-design.md` §19 as closely as the
 * shipped UI actually supports — see the one deliberate deviation noted at `linkAppointmentToItem`
 * below, logged as a new TECH_DEBT.md finding rather than built here (production hardening scope,
 * not a new feature).
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

/** Raw status code, for "direct API access is blocked" assertions (expects a non-2xx). */
async function apiStatus(page: Page, path: string, method = 'GET', body?: unknown): Promise<number> {
  return page.evaluate(
    async ({ apiUrl, path, method, body }) => {
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
        body: body === undefined ? undefined : JSON.stringify(body),
      })
      return res.status
    },
    { apiUrl: API_BASE_URL, path, method, body },
  )
}

async function createPatient(page: Page): Promise<{ id: string; fullName: string }> {
  const stamp = Date.now().toString().slice(-6)
  const firstName = `E2ETxPlan${stamp}`
  const patient = await apiRequest<{ id: string }>(page, '/patients', {
    method: 'POST',
    body: {
      first_name: firstName,
      last_name: 'Patient',
      date_of_birth: '1988-05-10',
      gender: 'male',
      phone: '0555000777',
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

async function findAppointmentTypeId(page: Page): Promise<string> {
  const types = await apiRequest<{ id: string; is_active: boolean }[]>(page, '/appointment-types')
  const active = types.find((type) => type.is_active)
  if (!active) throw new Error('No active appointment type found in the seeded data')
  return active.id
}

/**
 * Same fix `notifications.spec.ts`'s `ensureWorkingHoursCoverDay`/`findAvailableSlot` already
 * apply to this exact bug class (see that file's own comments for the full incident history):
 * unconditionally cover tomorrow's day-of-week before asking the backend for a real free slot,
 * rather than assuming Sunday-Thursday seeding or hand-computing an hour that could collide with
 * another attempt's own booking.
 */
async function createAppointmentForPatient(page: Page, patientId: string, dentistId: string): Promise<string> {
  const date = new Date()
  date.setDate(date.getDate() + 1)
  const pad = (value: number) => String(value).padStart(2, '0')
  const dateParam = `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`

  await apiRequest(page, `/dentists/${dentistId}/working-hours`, {
    method: 'POST',
    body: { day_of_week: date.getDay(), start_time: '08:00', end_time: '20:00' },
  })

  const { slots } = await apiRequest<{ slots: string[] }>(
    page,
    `/available-slots?dentist_id=${dentistId}&date=${dateParam}&duration_minutes=30`,
  )
  if (slots.length === 0) throw new Error(`No available slots for dentist ${dentistId} on ${dateParam}`)

  const appointment = await apiRequest<{ id: string }>(page, '/appointments', {
    method: 'POST',
    body: {
      patient_id: patientId,
      dentist_id: dentistId,
      appointment_type_id: await findAppointmentTypeId(page),
      start_at: slots[0],
      duration_minutes: 30,
      reason: 'E2E treatment plan fixture',
    },
  })
  return appointment.id
}

/**
 * `?tab=treatmentPlans` is a real, already-established deep-link `PatientDetailView.vue` validates
 * against its own `tabDefinitions` (added for Dashboard 2.0's Unscheduled Treatment widget) —
 * locale-independent, unlike clicking a translated tab label, so this works identically whether
 * the active locale is English or Arabic.
 */
async function gotoTreatmentPlansTab(page: Page, patientId: string) {
  await page.goto(`/patients/${patientId}?tab=treatmentPlans`)
  await expect(page.getByRole('tabpanel')).toBeVisible({ timeout: 10_000 })
}

/** See `dental-chart.spec.ts`'s identical helper/comment — a `.p-select` overlay closes via a CSS
 *  leave-transition, not instantly on selection. */
async function waitForSelectOverlayClosed(page: Page) {
  await expect(page.locator('.p-select-overlay')).toBeHidden()
}

async function selectFirstDentist(dialog: Locator, page: Page) {
  await dialog.locator('div:has(> label:text-is("Dentist")) .p-select').click()
  await page.locator('li.p-select-option:visible').first().click()
  await waitForSelectOverlayClosed(page)
}

/** Creates a `draft` plan from the patient's Treatment Plans tab and returns its id (read back via
 *  the API — the panel's own list doesn't expose the id in the DOM). */
async function createPlan(page: Page, patientId: string, title: string): Promise<string> {
  await page.getByRole('button', { name: 'New Treatment Plan' }).click()
  const dialog = page.locator('.p-dialog')
  await expect(dialog.getByText('New Treatment Plan')).toBeVisible()

  await selectFirstDentist(dialog, page)
  await dialog.locator('div:has(> label:text-is("Title")) input').fill(title)

  await dialog.getByRole('button', { name: 'Save' }).click()
  await expect(page.getByText('Treatment plan saved')).toBeVisible({ timeout: 10_000 })
  await expect(dialog).toBeHidden({ timeout: 10_000 })

  const { data: plans } = await apiRequest<{ data: { id: string; title: string }[] }>(
    page,
    `/patients/${patientId}/treatment-plans`,
  )
  const created = plans.find((plan) => plan.title === title)
  if (!created) throw new Error(`Created plan "${title}" not found in the patient's plan list`)
  return created.id
}

async function openPlanDetail(page: Page, patientId: string, title: string) {
  await page.locator('tr', { has: page.getByText(title, { exact: true }) }).click()
  await expect(page.getByRole('heading', { name: title })).toBeVisible({ timeout: 10_000 })
}

async function addItem(page: Page, procedureName: string, toothLabel: string) {
  await page.getByRole('button', { name: 'Add Item' }).click()
  const dialog = page.locator('.p-dialog')
  await expect(dialog.getByText('Add Item', { exact: true })).toBeVisible()

  await dialog.getByRole('combobox', { name: 'Select a procedure' }).click()
  await page.getByRole('option', { name: procedureName }).click()
  await waitForSelectOverlayClosed(page)

  await dialog.getByRole('combobox', { name: 'Select a tooth (optional)' }).click()
  await page.getByRole('option', { name: toothLabel }).click()
  await waitForSelectOverlayClosed(page)

  await dialog.getByRole('button', { name: 'Save' }).click()
  await expect(page.getByText('Item saved')).toBeVisible({ timeout: 10_000 })
  await expect(dialog).toBeHidden({ timeout: 10_000 })
}

/**
 * `.last()`, not a bare `getByText` -- every action in this suite shares the identical toast text
 * ("Treatment plan updated"), and PrimeVue's toast stacks rather than replaces: two calls in quick
 * succession (e.g. Present then Accept) can both still be mounted when the second assertion runs,
 * which a strict-mode `getByText` fails on ("resolved to 2 elements") instead of picking one. The
 * most-recently-added toast is always the one this call itself triggered.
 */
async function runPlanAction(page: Page, label: string) {
  await page.getByRole('button', { name: label, exact: true }).click()
  await expect(page.getByText('Treatment plan updated').last()).toBeVisible({ timeout: 10_000 })
}

async function confirmPlanAction(page: Page, label: string) {
  await page.getByRole('button', { name: label, exact: true }).click()
  await page.getByRole('alertdialog').getByRole('button', { name: label, exact: true }).click()
  await expect(page.getByText('Treatment plan updated').last()).toBeVisible({ timeout: 10_000 })
}

test.describe('treatment plans', () => {
  test('golden path: draft -> add items -> present -> accept -> start -> complete items -> complete plan', async ({
    page,
  }) => {
    await loginAsEnglish(page, 'admin')
    const patient = await createPatient(page)
    const dentistId = await findDentistId(page)
    const title = `Golden Path ${Date.now()}`

    await gotoTreatmentPlansTab(page, patient.id)
    const planId = await createPlan(page, patient.id, title)
    await openPlanDetail(page, patient.id, title)

    // Two items across different teeth, per design doc §19.
    await addItem(page, 'Extraction', '18 —')
    await addItem(page, 'Crown', '46 —')
    await expect(page.getByText('Extraction')).toBeVisible()
    await expect(page.getByText('Crown')).toBeVisible()

    await runPlanAction(page, 'Present')
    await runPlanAction(page, 'Accept')

    // Deliberate deviation from the design doc's literal "link an appointment to one item" step:
    // neither TreatmentPlanItemDialog.vue nor any Appointment-side UI exposes a control to set
    // `treatment_plan_items.appointment_id`, even though PUT /treatment-plan-items/{id} accepts it
    // and TreatmentPlanItemsTable.vue already renders the linked appointment's date/status chip
    // when present (`items.linked` column) -- a real, previously-undocumented gap, logged in
    // TECH_DEBT.md rather than built here (out of this pass's "no new UI" scope). Exercises the
    // real, currently-shipped behavior instead: link via the same API the backend already
    // supports, then confirm the read side renders it.
    const planWithItems = await apiRequest<{ items: { id: string; procedure_name: string }[] }>(
      page,
      `/treatment-plans/${planId}`,
    )
    const extractionItem = planWithItems.items.find((item) => item.procedure_name === 'Extraction')
    if (!extractionItem) throw new Error('Extraction item not found on the created plan')
    const appointmentId = await createAppointmentForPatient(page, patient.id, dentistId)
    await apiRequest(page, `/treatment-plan-items/${extractionItem.id}`, {
      method: 'PUT',
      body: { appointment_id: appointmentId },
    })

    await runPlanAction(page, 'Start')
    await page.reload()
    await expect(page.getByRole('heading', { name: title })).toBeVisible({ timeout: 10_000 })

    // The linked appointment now renders in the item row's "Linked" column.
    const extractionRow = page.locator('tr', { has: page.getByText('Extraction') })
    await expect(extractionRow.getByText('Scheduled')).toBeVisible({ timeout: 10_000 })

    const rows = page.locator('table tr')
    for (const procedure of ['Extraction', 'Crown']) {
      const row = rows.filter({ has: page.getByText(procedure) })
      await row.getByRole('button', { name: 'Complete Item' }).click()
      await expect(page.getByText('Item saved')).toBeVisible({ timeout: 10_000 })
    }

    await runPlanAction(page, 'Complete')

    const finalPlan = await apiRequest<{ status: string; completed_at: string | null }>(
      page,
      `/treatment-plans/${planId}`,
    )
    expect(finalPlan.status).toBe('completed')
    expect(finalPlan.completed_at).not.toBeNull()
  })

  test('reject path: presented plan is rejected and becomes terminal', async ({ page }) => {
    await loginAsEnglish(page, 'admin')
    const patient = await createPatient(page)
    const title = `Reject Path ${Date.now()}`

    await gotoTreatmentPlansTab(page, patient.id)
    const planId = await createPlan(page, patient.id, title)
    await openPlanDetail(page, patient.id, title)

    await runPlanAction(page, 'Present')
    await confirmPlanAction(page, 'Reject')

    const plan = await apiRequest<{ status: string; rejected_at: string | null }>(
      page,
      `/treatment-plans/${planId}`,
    )
    expect(plan.status).toBe('rejected')
    expect(plan.rejected_at).not.toBeNull()

    // Terminal: no plan-level action buttons remain.
    for (const label of ['Present', 'Accept', 'Reject', 'Start', 'Complete', 'Cancel Plan']) {
      await expect(page.getByRole('button', { name: label, exact: true })).toHaveCount(0)
    }
  })

  test('accepting one presented plan auto-rejects the patient\'s other presented plan', async ({ page }) => {
    await loginAsEnglish(page, 'admin')
    const patient = await createPatient(page)
    const titleA = `Option A ${Date.now()}`
    const titleB = `Option B ${Date.now()}`

    await gotoTreatmentPlansTab(page, patient.id)
    const planIdA = await createPlan(page, patient.id, titleA)
    await gotoTreatmentPlansTab(page, patient.id)
    const planIdB = await createPlan(page, patient.id, titleB)

    await apiRequest(page, `/treatment-plans/${planIdA}/present`, { method: 'POST' })
    await apiRequest(page, `/treatment-plans/${planIdB}/present`, { method: 'POST' })

    // Both plans are already cached client-side as `draft` from their own creation just above
    // (`treatmentPlansStore`'s cache upsert on create) -- the two raw API `present()` calls just
    // now bypassed that store entirely, so without a reload `TreatmentPlanDetailView.vue`'s `load()`
    // would find the (stale) cache already populated and skip its own fetch, rendering plan B as
    // still `draft` and never showing an "Accept" button at all. `page.reload()` wipes the SPA's
    // in-memory Pinia state so the detail view fetches fresh.
    await page.reload()
    await expect(page.getByRole('tabpanel')).toBeVisible({ timeout: 10_000 })
    await openPlanDetail(page, patient.id, titleB)
    await runPlanAction(page, 'Accept')

    const [planA, planB] = await Promise.all([
      apiRequest<{ status: string }>(page, `/treatment-plans/${planIdA}`),
      apiRequest<{ status: string }>(page, `/treatment-plans/${planIdB}`),
    ])
    expect(planB.status).toBe('accepted')
    expect(planA.status).toBe('rejected')
  })

  test('cancelling an accepted plan cascades to cancel its still-planned items', async ({ page }) => {
    await loginAsEnglish(page, 'admin')
    const patient = await createPatient(page)
    const title = `Cancel Cascade ${Date.now()}`

    await gotoTreatmentPlansTab(page, patient.id)
    const planId = await createPlan(page, patient.id, title)
    await openPlanDetail(page, patient.id, title)

    await addItem(page, 'Extraction', '18 —')
    await addItem(page, 'Crown', '46 —')

    await runPlanAction(page, 'Present')
    await runPlanAction(page, 'Accept')
    await confirmPlanAction(page, 'Cancel Plan')

    const plan = await apiRequest<{ status: string; items: { status: string }[] }>(
      page,
      `/treatment-plans/${planId}`,
    )
    expect(plan.status).toBe('cancelled')
    expect(plan.items).toHaveLength(2)
    for (const item of plan.items) {
      expect(item.status).toBe('cancelled')
    }
  })

  test('receptionist has read-only access to Treatment Plans', async ({ page }) => {
    await loginAsEnglish(page, 'receptionist')
    const patient = await createPatient(page)

    await gotoTreatmentPlansTab(page, patient.id)
    await expect(page.getByRole('button', { name: 'New Treatment Plan' })).not.toBeVisible()

    // Direct API access is blocked server-side too, not just hidden in the UI (defense in depth,
    // same three-way bar `dashboard.spec.ts`/`notifications.spec.ts` already established for a
    // security-critical case: rendered DOM, and here also direct API).
    const createStatus = await apiStatus(page, '/patients', 'GET')
    expect(createStatus).toBe(200) // sanity: the receptionist session itself is otherwise healthy
    const forbiddenCreate = await apiStatus(page, `/patients/${patient.id}/treatment-plans`, 'POST', {
      dentist_id: patient.id, // deliberately invalid — must 403 before validation ever runs
      title: 'Should be forbidden',
    })
    expect(forbiddenCreate).toBe(403)
  })

  test('RTL (Arabic) smoke check: layout mirrors and currency values stay LTR-isolated', async ({ page }) => {
    // Setup via the API while still on stable English selectors (`fixtures.ts`'s own convention:
    // every UI-driving spec forces English so its selectors don't depend on translated copy) — the
    // point of this test is to verify *rendering* in Arabic, not to drive Arabic-labeled dialogs.
    await loginAsEnglish(page, 'admin')
    const patient = await createPatient(page)
    const dentistId = await findDentistId(page)
    await apiRequest(page, `/patients/${patient.id}/treatment-plans`, {
      method: 'POST',
      body: { dentist_id: dentistId, title: `RTL Smoke ${Date.now()}` },
    })

    await forceArabicLocale(page)
    await page.goto(`/patients/${patient.id}?tab=treatmentPlans`)
    await expect(page.locator('html')).toHaveAttribute('dir', 'rtl', { timeout: 10_000 })
    await expect(page.getByRole('tabpanel')).toBeVisible({ timeout: 10_000 })

    const costCell = page.locator('table').getByText('0.00').first()
    await expect(costCell).toBeVisible({ timeout: 10_000 })
    await expect(costCell).toHaveAttribute('dir', 'ltr')
  })
})
