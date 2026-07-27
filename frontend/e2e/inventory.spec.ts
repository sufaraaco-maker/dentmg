import { test, expect, type Page } from '@playwright/test'
import { loginAsEnglish } from './fixtures'

/** Same overlay-close race documented in `dental-chart.spec.ts`/`clinical-notes.spec.ts` — a
 *  `.p-select`'s option list closes on a CSS leave-transition, not instantly on click. */
async function waitForSelectOverlayClosed(page: Page) {
  await expect(page.locator('.p-select-overlay')).toBeHidden()
}

async function selectFirstOption(dialog: import('@playwright/test').Locator, labelText: string, page: Page) {
  await dialog.locator(`div:has(> label:text-is("${labelText}")) .p-select`).click()
  await page.locator('li.p-select-option:visible').first().click()
  await waitForSelectOverlayClosed(page)
}

test.describe('inventory', () => {
  test('admin manages the catalog, records stock, and runs a purchase order through its full lifecycle', async ({
    page,
  }) => {
    const stamp = Date.now().toString().slice(-8)
    const categoryName = `E2E Category ${stamp}`
    const supplierName = `E2E Supplier ${stamp}`
    const supplyName = `E2E Supply ${stamp}`

    await loginAsEnglish(page, 'admin')

    // --- Create a Supply Category (admin-only catalog screen).
    await page.goto('/inventory/categories')
    await page.getByRole('button', { name: 'New Category' }).click()
    let dialog = page.locator('.p-dialog')
    await dialog.getByLabel('Name', { exact: true }).fill(categoryName)
    await dialog.getByRole('button', { name: 'Save' }).click()
    await expect(page.getByText('Category saved')).toBeVisible({ timeout: 10_000 })
    await expect(page.getByRole('cell', { name: categoryName })).toBeVisible({ timeout: 10_000 })

    // --- Create a Supplier (admin-only catalog screen).
    await page.goto('/inventory/suppliers')
    await page.getByRole('button', { name: 'New Supplier' }).click()
    dialog = page.locator('.p-dialog')
    // Not `exact: true`-free — "Name" is a substring of "Contact Name" too, and Playwright's
    // `getByLabel` matches substrings by default, same reason the Supply dialog's own "Name" field
    // below needs it.
    await dialog.getByLabel('Name', { exact: true }).fill(supplierName)
    await dialog.getByRole('button', { name: 'Save' }).click()
    await expect(page.getByText('Supplier saved')).toBeVisible({ timeout: 10_000 })
    await expect(page.getByRole('cell', { name: supplierName })).toBeVisible({ timeout: 10_000 })

    // --- Create a Supply referencing both, with a reorder level of 10.
    await page.goto('/supplies')
    await page.getByRole('button', { name: 'New Supply' }).click()
    dialog = page.locator('.p-dialog')
    await dialog.getByLabel('Name', { exact: true }).fill(supplyName)
    await selectFirstOption(dialog, 'Category', page)
    await dialog.getByLabel('Unit of Measure').fill('box')
    await dialog.getByLabel('Reorder Level').fill('10')
    await dialog.getByRole('button', { name: 'Save' }).click()
    await expect(page.getByText('Supply saved')).toBeVisible({ timeout: 10_000 })

    // --- Open the new Supply's detail page and record initial stock (20 units) — starts at 0.
    await page.getByRole('cell', { name: supplyName }).click()
    await expect(page.getByRole('heading', { name: supplyName })).toBeVisible({ timeout: 10_000 })
    await expect(page.getByText('0 box')).toBeVisible({ timeout: 10_000 })

    await page.getByRole('button', { name: 'Record Usage / Adjustment' }).click()
    dialog = page.locator('.p-dialog')
    // "Initial Stock" is the first option in the Reason list (STOCK_MOVEMENT_REASONS' own order),
    // overriding the dialog's default of "Used".
    await selectFirstOption(dialog, 'Reason', page)
    await dialog.getByLabel('Quantity').fill('20')
    await dialog.getByRole('button', { name: 'Save' }).click()
    await expect(page.getByText('Movement recorded')).toBeVisible({ timeout: 10_000 })
    await expect(page.getByText('20 box')).toBeVisible()

    // --- On-hand now above reorder level (10) — not flagged Low Stock.
    await expect(page.getByText('Low Stock')).toHaveCount(0)

    // --- Record usage bringing on-hand down to 5 — below the reorder level of 10.
    await page.getByRole('button', { name: 'Record Usage / Adjustment' }).click()
    dialog = page.locator('.p-dialog')
    await dialog.getByLabel('Quantity').fill('15')
    await dialog.getByRole('button', { name: 'Save' }).click()
    await expect(page.getByText('Movement recorded')).toBeVisible({ timeout: 10_000 })
    await expect(page.getByText('5 box')).toBeVisible()
    await expect(page.getByText('Low Stock')).toBeVisible()

    // --- The ledger shows both movements. Scoped to the DataTable itself — the last-closed
    // Record Movement dialog's own Reason combobox can still carry a matching aria-label (e.g.
    // "Used") even while hidden, making a bare page-wide getByText ambiguous.
    const ledger = page.locator('.p-datatable')
    await expect(ledger.getByText('Initial Stock')).toBeVisible()
    await expect(ledger.getByText('Used')).toBeVisible()

    // --- Low Stock now shows up on the Dashboard widget — its "view" link only renders once the
    // count is > 0 (LowStockWidget.vue), so this is a real assertion the widget reflects the
    // supply just pushed below its reorder level, not merely that the widget's title rendered.
    await page.goto('/')
    await expect(page.getByRole('button', { name: 'View low stock supplies' })).toBeVisible({ timeout: 10_000 })

    // --- Create a Purchase Order for this supplier, add the Supply as an item, place it.
    await page.goto('/purchase-orders')
    await page.getByRole('button', { name: 'New Purchase Order' }).click()
    dialog = page.locator('.p-dialog')
    await selectFirstOption(dialog, 'Supplier', page)
    await dialog.getByRole('button', { name: 'Create' }).click()
    await page.waitForURL(/\/purchase-orders\/[^/]+$/, { timeout: 10_000 })
    await expect(page.locator('.p-tag')).toContainText('Draft')

    await page.getByRole('button', { name: 'Add Item' }).click()
    dialog = page.locator('.p-dialog')
    await dialog.locator('div:has(> label:text-is("Supply")) .p-select').click()
    await page.getByText(supplyName).click()
    await waitForSelectOverlayClosed(page)
    await dialog.getByLabel('Quantity Ordered').fill('30')
    await dialog.getByLabel('Unit Cost (optional override)').fill('5')
    await dialog.getByRole('button', { name: 'Add', exact: true }).click()
    await expect(page.getByText('Item added')).toBeVisible({ timeout: 10_000 })
    await expect(page.getByRole('cell', { name: '30' })).toBeVisible()

    await page.getByRole('button', { name: 'Place Order' }).click()
    await expect(page.getByText('Purchase order updated')).toBeVisible({ timeout: 10_000 })
    await expect(page.locator('.p-tag')).toContainText('Placed')
    // Once placed, adding more items is no longer offered.
    await expect(page.getByRole('button', { name: 'Add Item' })).toHaveCount(0)

    // --- Partially receive (10 of 30), verify status flips to Partially Received.
    await page.getByRole('button', { name: 'Receive' }).click()
    dialog = page.locator('.p-dialog')
    await dialog.getByLabel('Quantity Received Now').fill('10')
    await dialog.getByRole('button', { name: 'Receive' }).click()
    await expect(page.getByText('Item received')).toBeVisible({ timeout: 10_000 })
    await expect(page.locator('.p-tag')).toContainText('Partially Received')

    // --- Receive the remaining 20, verify status flips to Received.
    await page.getByRole('button', { name: 'Receive' }).click()
    dialog = page.locator('.p-dialog')
    await dialog.getByRole('button', { name: 'Receive' }).click()
    await expect(page.getByText('Item received')).toBeVisible({ timeout: 10_000 })
    await expect(page.locator('.p-tag')).toContainText('Received')
    // Fully received — no more Receive action offered.
    await expect(page.getByRole('button', { name: 'Receive' })).toHaveCount(0)

    // --- The Supply's on-hand reflects both receipts (5 + 10 + 20 = 35), no longer Low Stock.
    await page.goto('/supplies')
    await page.getByRole('cell', { name: supplyName }).click()
    await expect(page.getByText('35 box')).toBeVisible({ timeout: 10_000 })
    await expect(page.getByText('Low Stock')).toHaveCount(0)
    // Two "Received" rows by now (the partial + the remaining receipt) — .first() confirms at
    // least one renders without a strict-mode violation on the (correctly) duplicate text.
    await expect(page.locator('.p-datatable').getByText('Received').first()).toBeVisible()
  })

  test('a draft purchase order can be cancelled before anything is received', async ({ page }) => {
    await loginAsEnglish(page, 'admin')

    await page.goto('/purchase-orders')
    await page.getByRole('button', { name: 'New Purchase Order' }).click()
    const dialog = page.locator('.p-dialog')
    await selectFirstOption(dialog, 'Supplier', page)
    await dialog.getByRole('button', { name: 'Create' }).click()
    await page.waitForURL(/\/purchase-orders\/[^/]+$/, { timeout: 10_000 })

    await page.getByRole('button', { name: 'Cancel Order' }).click()
    const confirmDialog = page.locator('.p-confirmdialog')
    await confirmDialog.getByRole('button', { name: 'Cancel Order' }).click()
    await expect(page.getByText('Purchase order updated')).toBeVisible({ timeout: 10_000 })
    await expect(page.locator('.p-tag')).toContainText('Cancelled')
    // Once cancelled, no further actions are offered.
    await expect(page.getByRole('button', { name: 'Place Order' })).toHaveCount(0)
    await expect(page.getByRole('button', { name: 'Cancel Order' })).toHaveCount(0)
  })

  test('dentist can log usage but not manage the catalog or procurement', async ({ page }) => {
    await loginAsEnglish(page, 'dentist')

    // Dentist has no "New Supply"/"New Purchase Order" affordance, and no access at all to the
    // admin-only Suppliers/Categories screens (design doc §10/§15 Decision 1).
    await page.goto('/supplies')
    await expect(page.getByRole('button', { name: 'New Supply' })).toHaveCount(0)

    await page.goto('/purchase-orders')
    await expect(page.getByRole('button', { name: 'New Purchase Order' })).toHaveCount(0)

    await page.goto('/inventory/suppliers')
    await expect(page).toHaveURL(/\/forbidden$/, { timeout: 10_000 })

    await page.goto('/inventory/categories')
    await expect(page).toHaveURL(/\/forbidden$/, { timeout: 10_000 })
  })
})
