# Inventory — Module Design (Implementation Complete, 2026-07-26)

**Status: Design approved and implemented (backend + frontend) same-day, 2026-07-26.** Suppliers/Supply
Categories/Supplies catalogs, the immutable `stock_movements` ledger, and the full Purchase Order
draft→placed→partially_received→received lifecycle are all in place, backend and frontend. Backend:
`pint`/`phpstan analyse` — Pint clean; PHPStan reproduces a pre-existing, environment-only breakage
unrelated to this module (confirmed via `git stash` against untouched `main`, see `TECH_DEBT.md`) — 771/771
backend tests green (68 Inventory-specific: Feature + Unit). Frontend: `vue-tsc`/ESLint clean, 19 new Vitest
tests (stores + typed-error service) green. A permanent Playwright E2E suite
(`frontend/e2e/inventory.spec.ts`) was written and structurally verified correct via direct browser
inspection, but a full local green run is blocked by the same Windows Docker Desktop networking latency
already logged against Dental Chart/Clinical Notes — **not yet CI-confirmed** (this branch hasn't been
pushed yet); see `TECH_DEBT.md` for the full diagnostic trail, including two real E2E-spec bugs and an
unrelated dev-database reseed found and fixed along the way.

## Implementation Summary (added at Final Review, 2026-07-26)

**Backend**: `Supplier`/`SupplyCategory`/`Supply` catalogs (`is_active` soft-disable, no hard delete or
`Auditable`, mirroring `AppointmentType`/`DentalCondition`); `StockMovement` (immutable, append-only ledger —
`Supply::quantity_on_hand`/`is_low_stock` computed live via `SUM(quantity_delta)`, never stored, with a
`withQuantityOnHand()`/`lowStock()` query-scope pair that joins the aggregate once for an entire paginated
list rather than N+1 per row); `PurchaseOrder` (`Auditable`, soft-deletable, draft-only) + `PurchaseOrderItem`
(snapshot `description`/`unit_cost`, hard-capped `quantity_received`). Services:
`SupplierService`/`SupplyCategoryService`/`SupplyService` (thin catalog CRUD), `StockMovementService`
(row-locks the `Supply` before checking the below-zero guard, mirroring `PaymentService::refund()`),
`PurchaseOrderService` (row-locks the `PurchaseOrder` for `place()`/`receive()`/`cancel()`, mirroring
`InvoiceService::issue()`). Permissions exactly as approved: dentists may record `used`/`wasted`/`expired`
movements only; Supplier/Category management and Purchase Order procurement are admin+receptionist; Purchase
Order delete is admin-only.

**Frontend**: `SuppliersView.vue`/`SupplyCategoriesView.vue` (admin-only catalog CRUD, mirroring
`AppointmentTypesView.vue`), `SuppliesView.vue` (paginated list, mirroring `PatientsView.vue`'s
direct-`api`-call pattern rather than a Pinia store) + `SupplyDetailView.vue` (ledger table + Record
Usage/Adjustment dialog), `PurchaseOrdersView.vue` + `PurchaseOrderDetailView.vue` (full lifecycle actions).
Small Pinia stores (`stores/suppliers.ts`/`stores/supplyCategories.ts`) only for the two genuinely small,
dropdown-backing catalogs — Supplies/Purchase Orders deliberately have no store, consistent with §13's own
"paginated, not a small cache" framing. New top-level **Inventory** sidebar group (Decision 4) and a
`LowStockWidget.vue` Dashboard card (Decision-adjacent, §11). Full en/ar/tr i18n, zero key-parity gaps
verified across all three locale files.

**Deviation from a design-doc closing sentence, noted for the record**: §6's closing paragraph said
`purchase_order_items` is excluded from `Auditable` (grouped with the immutable `stock_movements`/catalog
tables); the actual implementation follows this literally (no `Auditable` trait on `PurchaseOrderItem`),
even though `InvoiceItem` — the closest prior-module analogue — does carry `Auditable` despite also having
no soft-delete. Kept as designed rather than silently "fixed" mid-implementation, since it's a defensible,
approved call (an item's own edit history while still draft is low-stakes, and the design was already
approved as written) — flagged here rather than left as an unexplained inconsistency for a future reader.

## Approval & Decision Log (2026-07-26)

All five open items from §15 resolved in a single approval pass, implementation authorized immediately:

1. **Dentist write access for logging usage** (§15 item 1) — **APPROVED as recommended.** Dentists may
   record `used`/`wasted`/`expired` Stock Movements only. Supplier/Category management and Purchase
   Ordering (create/place/cancel/receive) remain admin+receptionist-only.
2. **Expiration tracking depth** (§15 item 2) — **APPROVED as recommended, with a forward-compatibility
   requirement**: a single optional `expiration_date` column on `stock_movements` (received/initial-stock
   rows only), no full lot/batch/FIFO system in V1. The schema must be shaped so a future V2 lot/batch layer
   can extend it additively — see §6a below for the concrete compatibility note.
3. **Equipment/fixed-asset tracking** (§15 item 3) — **APPROVED as recommended.** Confirmed fully out of
   scope for V1; this module covers consumable inventory only.
4. **Navigation placement** (§15 item 4) — **APPROVED as recommended.** Top-level sidebar item.
5. **Over-receipt handling** (§15 item 5) — **APPROVED as recommended.** `quantity_received` is hard-capped
   at `quantity_ordered`; any genuine excess is a separate, explicit `correction` Stock Movement or a new
   Purchase Order — never silent over-receipt against the original order line.

## 0. Competitive Research (required before any design, per standing product philosophy)

| Source | Finding | Taken / Rejected for this design |
|---|---|---|
| **Open Dental** ([Supply Inventory](https://www.opendental.com/manual/supplyinventory.html), [Supplies](https://opendental.com/manual/supplies.html), [Supply Orders](https://www.opendental.com/manual/supplyorders.html)) | Four core entities — Suppliers, Supply Categories, Supplies, Orders — set up in that dependency order; a `Supply` carries a `Stock Level` (reorder threshold), `On Hand Qty`, and `Order Qty` (default reorder amount); an Order is "pending" until a Date Placed is entered, then "placed"; receiving supports per-item partial dates so a split shipment doesn't block the rest of the order; Order Item cost/qty are editable, most other fields read-only snapshots from the Supplies list. A separate "Supplies Needed" quick-add list exists independent of any supplier, for fast low-stock flagging. | **Taken**: the four-entity shape (Supplier / Category / Supply / Order+Items) — confirmed below by every other competitor as the real industry-standard shape, not a one-off. **Taken**: per-item partial receiving. **Taken (as a snapshot convention)**: Order Item cost/qty snapshotted at order time, mirroring this codebase's own `InvoiceItem`/`TreatmentPlanItem` snapshot pattern. **Deliberately rejected**: `On Hand Qty` as a mutable, directly-edited field. Open Dental's own forum/support content shows this value drifting from reality over time (double-entry errors, forgotten adjustments) with no way to reconstruct how it got there. §6 below replaces it with a computed, append-only ledger (`stock_movements`) — the same "derive it, never store it, keep full history" principle this codebase already uses for `Invoice.amountPaid`/`balanceDue` and `Payment`'s refund trail. **Deliberately rejected**: the implicit "null Date Placed = pending" status flag — replaced with an explicit `PurchaseOrderStatus` backed enum, matching every other module's status-lifecycle convention (`InvoiceStatus`, `TreatmentPlanStatus`, `ClinicalNoteStatus`, ...). |
| **Dentrix Ascend** ([overview via Dentrix.com / support articles](https://www.dentrix.com)) | Automated low-stock alerts, customizable reorder points, direct one-click ordering integration with Henry Schein/Patterson, and usage reports tied to specific procedures for cost analysis. | **Taken**: low-stock alerts and configurable reorder points (`reorder_level`, §6). **Rejected for V1**: direct supplier ordering integration (EDI/API punch-out) — no new external package/dependency per `PROJECT_CONTEXT.md`'s "never introduce unnecessary packages," and this is exactly the kind of external integration the standing AI-layer vision says belongs in a future separate Integration Layer, not wired into a core module. **Rejected for V1**: procedure-linked automatic consumption — named explicitly in §2/§16 as a deliberate, future-revisitable omission, not a silent gap. |
| **CareStack** ([Features](https://carestack.com/dental-software/features), [Integrations](https://carestack.com/dental-software/integrations)) | Purchase orders, supplier/vendor management, and inventory alerts that scale to multi-location groups from a single dashboard — but for *deep* replenishment automation and real-time usage/spend visibility, CareStack itself integrates with a dedicated third-party platform (Zimbis) rather than building that depth natively. | **Taken**: confirms the core PO + vendor-management + alerts shape is the right V1 scope. **Confirms the scoping decision, doesn't change it**: if CareStack — a comprehensive, mature PMS — chooses to hand deep procurement automation to a specialist partner rather than build it in-house, that reinforces keeping this module's V1 deliberately lean (ledger + PO + low-stock list) rather than chasing full procurement-suite depth on the first pass. |
| **Curve Dental** (via third-party comparison coverage — Curve's own manual is not publicly indexed) | Par-level reorder points, lot number and expiration-date tracking per supply batch, and ties inventory usage back to clinical procedures/billing. | **Taken**: par-level/reorder-point concept confirmed a second time (three of four competitors independently converge on the same "reorder threshold" idea). **Flagged, not silently built**: full lot/batch + expiration tracking is the one place this research surfaced a *patient-safety-relevant* feature (expired anesthetic, composite, and other dental materials are a real clinical risk, not just a bookkeeping nicety) that I can't fully spec with confidence from public docs alone — see §15 Decision 2 for a scoped-down proposal (a single optional `expiration_date` per stock-in movement, not full batch/FIFO tracking) rather than either silently omitting it or over-building an untested full lot-tracking system. |

**Net effect**: all four competitors converge on the same core shape (Supplier → Category → Supply →
Order/Receive, plus a reorder threshold). This design keeps that shape, replaces the one place a competitor's
approach (mutable on-hand quantity) conflicts with this codebase's own established "computed, never stored"
principle, and explicitly names three things deliberately deferred (procedure-linked auto-consumption, direct
supplier API integration, full lot/batch tracking) rather than silently building or silently skipping them.

## 1. Module Goal / Purpose

Give the clinic a single source of truth for what dental consumable supplies it has, how much of each,
when to reorder, and what was actually purchased from which supplier — reducing chairside stockouts and
waste, and creating the cost/vendor data a future Reports module can build on. This is a back-office
operational module: it does not touch patient records, and (per §2) it is deliberately *not* wired into
Appointments/Treatment Plans/Clinical Notes in V1.

## 2. Scope (V1)

**In scope:**
- **Suppliers** (admin-managed catalog): name, contact info, notes, active/inactive.
- **Supply Categories** (admin-managed catalog): a real table, not a fixed enum — dental supply categories
  genuinely vary per clinic (PPE, restorative, anesthetics, sterilization, lab materials, office supplies,
  ...), the same reasoning already used for `dental_conditions`/`AppointmentType` rather than a backed enum.
- **Supplies** (the stock-item catalog): name, SKU/catalog number, category, default supplier, unit of
  measure, unit cost, reorder level, default reorder quantity, active/inactive.
- **Stock Movements**: an append-only, immutable ledger of every quantity change (received, used, wasted,
  expired, correction, initial stock) — the actual source of truth `quantity_on_hand` is computed from
  (§4/§6), never a separately stored, independently-editable counter.
- **Purchase Orders + Items**: draft → placed → partially received → received lifecycle, per-item receiving
  (full or partial), automatic stock-movement generation on receipt, order cancellation while still
  pre-receipt.
- **Low Stock list**: supplies where computed on-hand ≤ `reorder_level`, plus a Dashboard widget (mirrors
  the existing Appointments dashboard-widget pattern).
- Full audit trail via the existing `Auditable` trait on `Supply`/`Supplier`/`PurchaseOrder` — no new
  mechanism, matching every prior module.
- Full en/ar/tr i18n, dark mode, RTL, keyboard access — enterprise UX bar per standing philosophy.

**Explicitly out of scope for V1** (named, not silently dropped):
- **Procedure-linked automatic consumption** — no `Appointment`/`TreatmentPlanItem`/`ClinicalNote` awareness
  of Inventory at all. Completing a filling does not auto-deduct composite material. Staff logs usage
  manually via a "Used" stock movement. A real, valuable future capability (§16), deliberately deferred: it
  is a genuine cross-module boundary decision (which module "owns" the deduction trigger?) that deserves its
  own design pass, not a rushed addition to this one.
- **Direct supplier ordering integration** (Henry Schein/Patterson punch-out, EDI) — a `PurchaseOrder` in
  this module is a record of what the clinic ordered, not an electronic transmission of that order. No new
  package dependency (§0).
- **Equipment/fixed-asset tracking** — Open Dental itself splits this out from consumable supplies (its own
  Equipment section exists "for payment of property taxes"); V1 tracks consumables only.
- **Full lot/batch number + FIFO consumption tracking** — see §15 Decision 2 for the scoped-down alternative
  proposed instead.
- **Barcode scanning / label printing.**
- **Inventory valuation / COGS reporting** — future Reports-module scope, same "reporting-readiness note, not
  a widget to build now" treatment `payments-design.md` §2/§13 gave outstanding-balance reporting.
- **Multi-location/branch-scoped stock pools** — one stock pool for V1, consistent with the system-wide
  "no table is branch-scoped yet" status in `docs/database-design.md`.

## 3. Full Workflow

```
Admin sets up Suppliers + Supply Categories
  → Supplies added to catalog (reorder_level, reorder_quantity, default_supplier, unit_cost)
  → Initial stock recorded (an `initial_stock` Stock Movement, or via receiving a first Purchase Order)
  → Ongoing: staff logs `used`/`wasted`/`expired`/`correction` movements as supplies are consumed
  → Once on-hand ≤ reorder_level, the Supply surfaces on the Low Stock list / Dashboard widget
  → Staff creates a Purchase Order (from the Low Stock list, or from scratch) to a Supplier, adding items
    (qty/cost pre-filled from the Supply's own defaults, editable per line)
  → Order moves draft → placed (an explicit "Place Order" action sets `ordered_at`)
  → Items arrive → staff "Receives" each item (full or partial quantity) → a `received` Stock Movement is
    generated automatically for each receipt (never a manual double-entry) → on-hand increases
  → Order status is recomputed after every receipt: partially_received while any item is incomplete, received
    once every item's quantity_received == quantity_ordered
  → Order can be cancelled only while every item still has quantity_received = 0 (§8) — once real stock has
    moved against it, it is real history, not erasable, mirroring `Invoice`'s own "no undo once real" rule
```

## 4. Core Concepts (definitions)

- **Supply**: one type of consumable item the clinic stocks (e.g., "Composite Resin A2", "Nitrile Gloves M").
- **Supplier**: a vendor the clinic buys from.
- **Supply Category**: an admin-managed grouping for Supplies (not a fixed enum — see §2).
- **Stock Movement**: one immutable, point-in-time fact about a Supply's quantity changing — a signed
  `quantity_delta` plus a `reason`. Never edited or deleted once created; a correction is always a *new*
  offsetting row, exactly mirroring how a `Payment` refund is a new row, never an edit to the original.
- **Quantity On Hand** (computed, never stored): `SUM(stock_movements.quantity_delta)` for that Supply — the
  same "derive it live, don't let a stored counter drift" principle as `Invoice.amountPaid`/`balanceDue`.
- **Reorder Level**: the threshold at which a Supply is considered low stock (Open Dental's "Stock Level",
  Curve's "par level" — same concept, three independent names).
- **Reorder Quantity**: the default quantity to pre-fill when adding this Supply to a new Purchase Order.
- **Purchase Order**: a record of what was ordered from one Supplier, and how much of it has arrived.
- **Low Stock**: a Supply whose computed on-hand ≤ its own `reorder_level` — never a stored flag, always
  computed at read time (same reasoning as every other derived-status field in this codebase).

## 5. Status Lifecycle

- **Supply / Supplier / Supply Category**: no status enum — a simple `is_active` boolean (mirrors
  `DentalCondition`'s own `is_active` catalog-entry convention exactly). Deactivating hides a row from
  "new Purchase Order item" / "new Supply" pickers without breaking historical FK references — never a hard
  delete of a catalog row that's been referenced by real history.
- **Purchase Order** — explicit `PurchaseOrderStatus` backed enum: `draft`, `placed`, `partially_received`,
  `received`, `cancelled`.
  - `draft → placed`: an explicit user action ("Place Order"), sets `ordered_at`. Real user-chosen
    transition, not derived.
  - `placed/partially_received → partially_received/received`: **not** user-chosen — recomputed by the
    Service layer after every "Receive" action, purely from comparing each item's `quantity_received` against
    `quantity_ordered`. The user never picks this state directly.
  - `draft/placed → cancelled`: allowed only while every item's `quantity_received` is still `0` (§8).
  - No transition ever leaves `received`/`cancelled` (both terminal, mirrors every other module's completed/
    voided states being terminal).
- **Stock Movement**: no lifecycle at all — an immutable, append-only ledger row (soft delete would be
  meaningless here; there is nothing to "undo" except by writing a new offsetting row).

## 6. Database Design

### `suppliers`

| Column | Type | Notes |
|---|---|---|
| `id` | uuid | PK |
| `name` | string | |
| `contact_name` | string, nullable | |
| `phone` | string, nullable | |
| `email` | string, nullable | |
| `address` | text, nullable | |
| `notes` | text, nullable | |
| `is_active` | boolean, default true | Catalog soft-disable, not a delete (§5) |
| `created_at` / `updated_at` | timestamp | |

### `supply_categories`

| Column | Type | Notes |
|---|---|---|
| `id` | uuid | PK |
| `name` | string, unique | |
| `sort_order` | integer, default 0 | Mirrors `DentalCondition.sort_order` |
| `is_active` | boolean, default true | |
| `created_at` / `updated_at` | timestamp | |

### `supplies`

| Column | Type | Notes |
|---|---|---|
| `id` | uuid | PK |
| `category_id` | uuid, FK → `supply_categories` | |
| `default_supplier_id` | uuid, nullable, FK → `suppliers` | Pre-fills a new Purchase Order item's supplier |
| `name` | string | |
| `sku` | string, nullable | Supplier catalog/item number |
| `unit_of_measure` | string | Free text (e.g. "box", "each", "pack") — too varied across dental supplies for a fixed enum |
| `unit_cost` | decimal(10,2), nullable | Default/last-known cost; snapshotted onto each `purchase_order_items` row at order time, same convention as `dental_conditions.default_cost` → `treatment_plan_items` |
| `reorder_level` | integer, default 0 | §4 |
| `reorder_quantity` | integer, nullable | §4 |
| `is_active` | boolean, default true | |
| `created_at` / `updated_at` | timestamp | |

Indexes: `(category_id)`, `(default_supplier_id)`, `(is_active)`.

**No `quantity_on_hand` column** — always computed from `stock_movements` (§4).

### `stock_movements` (new — the ledger; append-only, immutable)

| Column | Type | Notes |
|---|---|---|
| `id` | uuid | PK |
| `supply_id` | uuid, FK → `supplies` | |
| `quantity_delta` | integer, not nullable | Signed: positive = increase, negative = decrease |
| `reason` | string, cast to `StockMovementReason` enum (`initial_stock`, `received`, `used`, `wasted`, `expired`, `correction`) | |
| `purchase_order_item_id` | uuid, nullable, FK → `purchase_order_items`, `nullOnDelete` | Set only for `reason = received`; traces back to the PO line that generated it |
| `expiration_date` | date, nullable | Optional, lightweight — see §15 Decision 2. Only meaningful on an incoming (`received`/`initial_stock`) row |
| `notes` | text, nullable | e.g. why a `correction`/`wasted` row was made |
| `performed_by_id` | uuid, FK → `users`, not nullable | |
| `occurred_at` | timestamp, not nullable | Staff-editable (e.g. backdating a receipt), mirrors `Payment.received_at` |
| `created_at` / `updated_at` | timestamp | No `deleted_at` — immutable, never deleted (§5) |

Indexes: `(supply_id)`, `(purchase_order_item_id)`, `(occurred_at)`.

### `purchase_orders`

| Column | Type | Notes |
|---|---|---|
| `id` | uuid | PK |
| `supplier_id` | uuid, FK → `suppliers` | Immutable after creation |
| `sequence_number` | integer | Per-org auto-increment, mirrors `Invoice.sequence_number` |
| `order_number` | string | Human-readable, e.g. `PO-000001` — mirrors `invoice_number`/`patient_code` |
| `status` | string, cast to `PurchaseOrderStatus` | §5 |
| `notes` | text, nullable | |
| `ordered_at` | date, nullable | Null while `draft` |
| `expected_at` | date, nullable | |
| `created_by_id` | uuid, FK → `users`, not nullable | |
| `deleted_at` | timestamp, nullable | Soft delete — genuine data-entry-error correction only, admin-only, same guard class as Invoice (only while still safely correctable, §8) |
| `created_at` / `updated_at` | timestamp | |

### `purchase_order_items`

| Column | Type | Notes |
|---|---|---|
| `id` | uuid | PK |
| `purchase_order_id` | uuid, FK → `purchase_orders` | |
| `supply_id` | uuid, FK → `supplies` | |
| `description` | string | Snapshot of the Supply's name/SKU at order time — same snapshot convention as `InvoiceItem`/`TreatmentPlanItem`, so renaming a Supply later never rewrites order history |
| `quantity_ordered` | integer | |
| `quantity_received` | integer, default 0 | Running total, updated only via the Receive action (§8) |
| `unit_cost` | decimal(10,2) | Snapshot at order time |
| `created_at` / `updated_at` | timestamp | No `deleted_at` — owned entirely by its parent `PurchaseOrder`, same as `InvoiceItem` |

Every table above except `stock_movements`/`purchase_order_items` (immutable/owned rows, respectively) and
the catalog tables (`is_active`-only, §5) gets `Auditable`, `HasUuids` — matching every prior module's
convention. `stock_movements` itself is intentionally excluded from `Auditable` — it *is* its own audit
trail by construction (append-only, immutable, attributed via `performed_by_id`), the same reasoning
`Payment` rows already establish for financial ledger entries.

### 6a. Forward compatibility note: `expiration_date` → future V2 lot/batch tracking

`stock_movements.expiration_date` (§6) is deliberately a plain nullable column on the ledger row itself, not
a separate `stock_lots` table — V1 has no concept of "lots" at all, only "this particular incoming movement
happens to expire on this date." This is forward-compatible with a future V2 lot/batch system without a
breaking migration:

- A future `stock_lots` table (`supply_id`, `lot_number`, `expiration_date`, received-quantity/remaining-
  quantity bookkeeping) could be introduced additively, with a new nullable `stock_movements.stock_lot_id`
  FK added alongside the existing `expiration_date` column — not replacing it.
- Existing V1 rows (which have `expiration_date` but no lot concept) remain valid history under V2: they
  simply have `stock_lot_id = null`, read exactly as "an expiration-dated movement with no batch/FIFO
  tracking," which is literally what they are.
- No V1 column needs to be dropped, renamed, or reinterpreted for V2 to layer FIFO consumption logic on top
  — the same "additive column later, no reshape" pattern already used for every other future-facing decision
  in this codebase (e.g. `clinic_id` in §14).

## 7. Table Relationships

```
Supplier
  ├─ hasMany → Supply         (default_supplier_id)
  └─ hasMany → PurchaseOrder

SupplyCategory
  └─ hasMany → Supply

Supply
  ├─ hasMany → StockMovement
  └─ hasMany → PurchaseOrderItem

PurchaseOrder
  ├─ belongsTo → Supplier
  ├─ belongsTo → User (created_by_id)
  └─ hasMany → PurchaseOrderItem

PurchaseOrderItem
  ├─ belongsTo → PurchaseOrder
  ├─ belongsTo → Supply
  └─ hasMany → StockMovement    (inverse of stock_movements.purchase_order_item_id — receipt traceability only)

StockMovement
  ├─ belongsTo → Supply
  ├─ belongsTo → PurchaseOrderItem (nullable)
  └─ belongsTo → User (performed_by_id)
```

**Module boundary**: Inventory has zero relationship to `Patient`/`Appointment`/`TreatmentPlanItem`/
`ClinicalNote` — no column, no event, no callback in either direction (§2). It is a fully standalone
back-office module in V1, the same "look backward only, or not at all" discipline every prior module's own
boundary section already established, applied here as "not at all."

## 8. Business Rules (consolidated)

- `quantity_on_hand` is always `SUM(stock_movements.quantity_delta)` for that Supply — never stored, never
  drifts (§4, §0's deliberate deviation from Open Dental).
- `stock_movements` rows are immutable — never updated or deleted. A correction is a new offsetting row
  (`reason = correction`), exactly mirroring `Payment`'s "refund is a new row, never an edit" rule.
- A negative `quantity_delta` (`used`/`wasted`/`expired`/`correction`) is rejected server-side if it would
  take the computed on-hand balance below `0` — recomputed from the actual ledger at write time, never
  trusting a client-sent running total (same discipline as `Payment`'s refund-cap check).
- `purchase_order_items` are immutable once the parent order leaves `draft`, except `quantity_received`
  (updated only via the Receive action) — mirrors `Invoice`'s "header frozen once issued" rule.
- **Receiving** an item for quantity *Q*: `quantity_received += Q`, hard-capped so it can never exceed
  `quantity_ordered` (§15 Decision 5) — creates exactly one new `stock_movements` row
  (`reason = received`, `quantity_delta = +Q`, `purchase_order_item_id` = this item). If genuinely more
  arrived than was ordered, staff records a separate `correction` movement instead — kept as two distinct,
  honestly-labeled facts rather than silently overloading "received."
- A `PurchaseOrder` can only be cancelled while **every** item's `quantity_received` is still `0`. Once any
  real stock has moved against it, it is real history — not erasable — the same "can't delete a payment
  that's been refunded" reasoning `Payment`'s own design already applied.
- `PurchaseOrder.status` is explicitly set by the user only for `draft → placed`; every other transition
  (`→ partially_received`, `→ received`) is recomputed by the Service layer after each Receive action, never
  user-chosen directly (§5).

## 9. API Design

```
GET/POST      /api/suppliers
GET/PUT/DELETE /api/suppliers/{supplier}                (DELETE = deactivate, admin-only)

GET/POST      /api/supply-categories
GET/PUT/DELETE /api/supply-categories/{category}         (admin-only write, any-role read — mirrors AppointmentTypeController)

GET/POST      /api/supplies
GET/PUT/DELETE /api/supplies/{supply}
GET           /api/supplies/low-stock                    (computed: on-hand ≤ reorder_level)
GET           /api/supplies/{supply}/stock-movements     (ledger for one Supply, paginated)
POST          /api/supplies/{supply}/stock-movements     (record used/wasted/expired/correction/initial_stock)

GET/POST      /api/purchase-orders
GET/PUT/DELETE /api/purchase-orders/{order}              (PUT/DELETE only while draft, §8)
POST          /api/purchase-orders/{order}/items         (add item — draft only)
PUT/DELETE    /api/purchase-orders/{order}/items/{item}  (edit qty/cost — draft only)
POST          /api/purchase-orders/{order}/place          (draft → placed)
POST          /api/purchase-orders/{order}/items/{item}/receive   (body: quantity, expiration_date?)
POST          /api/purchase-orders/{order}/cancel
```

Error shapes: standard `422`/`403`/`401`/`404` per `api-guidelines.md`, plus one dedicated domain exception
(`InsufficientStockException`, 422) for the "would go below zero" guard (§8) — same pattern as
`ClinicalNoteLockedException`/`InvoiceLockedException` rendering their own JSON response.

## 10. Permissions

See §15 Decision 1 — this table reflects the **recommended** split, not yet approved.

| Action | admin | dentist | receptionist |
|---|---|---|---|
| View Supplies/Suppliers/Categories/Stock Movements/Purchase Orders | ✅ | ✅ (read-only) | ✅ |
| Manage Suppliers/Categories (create/edit/deactivate) | ✅ | ❌ | ❌ |
| Create/edit Supplies (catalog, reorder settings) | ✅ | ❌ | ✅ |
| Record a `used`/`wasted`/`expired` Stock Movement | ✅ | ✅ (recommended — dentists are the ones actually consuming chairside supplies) | ✅ |
| Create/place/cancel Purchase Orders, receive items | ✅ | ❌ | ✅ |
| Soft-delete a Purchase Order (data-entry correction) | ✅ | ❌ | ❌ |

## 11. Frontend UX Design (high-level — a full pass follows a later checkpoint, same as `billing-design.md` §11)

- New top-level sidebar item, **Inventory** — a clinic-wide operational area, not a per-patient tab (unlike
  Payments/Clinical Notes), and not buried under Settings (§15 Decision 4) — same standalone-module treatment
  as Patients/Appointments.
- **Supplies list**: DataTable (name, category, on-hand, reorder level, default supplier, a Low-Stock status
  chip reusing the existing `Tag`-severity-map convention), filterable by category/supplier/low-stock-only,
  searchable.
- **Supply Detail**: current on-hand + reorder settings, a paginated Stock Movements timeline, a "Record
  Usage/Adjustment" action.
- **Low Stock Dashboard widget** — mirrors the existing Appointments dashboard-widget pattern; count +
  quick link into the filtered Supplies list.
- **Purchase Orders**: a separate list view (mirrors the Invoices list) + a Purchase Order Detail view
  (items table, Place/Receive/Cancel actions, status chip).
- **Suppliers** and **Supply Categories**: simple admin-managed catalog pages, mirroring the existing
  `AppointmentType` admin CRUD pattern.
- Full en/ar/tr i18n (new `inventory.*` namespace), dark mode, RTL, keyboard access — enterprise UX bar per
  standing philosophy; currency read from each row's own context (unit_cost), never hardcoded, same rule as
  Billing/Payments.

## 12. Security Considerations

- Every write endpoint has a dedicated `FormRequest` whose `authorize()` delegates to a Policy — no
  exceptions, per `api-guidelines.md`.
- `Supply`/`Supplier`/`PurchaseOrder` use `Auditable` from their first migration.
- The "never below zero" stock guard (§8) is enforced in the Service layer as a hard backstop, not just Form
  Request validation — mirrors `InvoiceService::assertEditable()`'s role.
- No direct DB access from any consumer — Controller → Service → Resource only, consistent with every prior
  module.

## 13. Performance & Scalability Considerations

- Per-Supply on-hand is a single aggregate `SUM` query — cheap at realistic scale (a clinic realistically has
  low-hundreds of Supplies, low-thousands of movements/year).
- The **Supplies list** (potentially hundreds of rows, each needing a computed on-hand) must compute on-hand
  for all visible rows via one grouped aggregate query (`GROUP BY supply_id`), never N+1 per-row queries —
  the same N+1-avoidance discipline already applied to Appointments' calendar range queries.
- The **Low Stock** list is one query comparing each Supply's grouped on-hand sum against its own
  `reorder_level` — needs the `stock_movements(supply_id)` index above; no full-table scan per request.

## 14. SaaS Readiness

- No `tenant_id`/`clinic_id` anywhere yet, consistent with the system-wide single-organization V1 decision.
- **One genuine difference from prior modules**: every table in this module is clinic-wide, not
  patient-scoped — Patients/Appointments/Billing tables all inherit future `clinic_id` scoping transitively
  through `patient_id`, but Inventory's tables have no such anchor. A future multi-tenant migration would need
  to add `clinic_id` directly to `suppliers`/`supply_categories`/`supplies`/`purchase_orders` (and
  transitively to `stock_movements`/`purchase_order_items` through their own parent FKs) — worth naming
  explicitly here rather than assuming the same "inherits it for free" story every prior module could claim.
- Still a purely additive future migration (new nullable column, backfilled, indexed) — no reshape of any
  relationship declared above.

## 15. Open Decisions (need explicit approval before implementation)

1. **Dentist write access for logging usage.** Every prior admin/receptionist-write, dentist-read-only
   module (Billing, Payments) was back-office/administrative work no dentist needed to write to. Inventory
   is different: dentists are the ones actually consuming supplies chairside. *Recommendation*: let
   dentists record `used`/`wasted`/`expired` Stock Movements (they know what they used), while keeping
   Supplier/Category management and Purchase Ordering (procurement, a front-desk/admin function)
   admin+receptionist-only, per §10's table. *Alternative*: mirror Billing/Payments exactly
   (admin+receptionist write, dentist read-only) for consistency, and have receptionist log usage on the
   dentist's behalf instead.
2. **Expiration tracking depth.** §0 flagged this as the one research-surfaced, patient-safety-relevant gap.
   *Recommendation*: a single optional `expiration_date` field on an incoming Stock Movement (§6) — enough
   to know a batch of anesthetic/composite is nearing its date via a simple query, without building a full
   lot/batch-numbered FIFO consumption system. *Alternative*: skip expiration tracking entirely for V1
   (defer to §16), or go further and build full lot/batch tracking now (materially larger scope — a new
   `stock_lots` concept, FIFO consumption logic, per-lot receiving).
3. **Equipment/fixed-asset tracking.** *Recommendation*: fully out of scope for V1 (§2) — a genuinely
   different concern (depreciation, property tax, not consumable reordering) that would need its own design.
4. **Navigation placement.** *Recommendation*: a top-level sidebar item (§11) — Inventory is a
   frequently-used operational area, not a rarely-visited settings screen.
5. **Over-receipt handling.** *Recommendation*: hard-cap `quantity_received` at `quantity_ordered` (§8) —
   genuine over-shipment becomes an explicit `correction` movement instead, kept as an honestly-labeled
   separate fact rather than silently stretching what "received against this order" means.

## 16. Potential Risks / Deferred Features / Future Improvements

- **Procedure-linked automatic consumption** (§2) — real future value (Dentrix/CareStack/Curve all have some
  version of it), deliberately deferred: needs its own cross-module boundary design, not a rushed addition
  here.
- **Automated "generate a Purchase Order from the Low Stock list" one-click flow** — V1 supports manually
  creating a PO from that list (items pre-filled from each Supply's own reorder defaults), but not a fully
  automated "build it for me" button. A reasonable, cheap near-term follow-up.
- **Multi-branch stock pools** once multi-branch ships (§14).
- **Vendor price comparison / price history across multiple suppliers per Supply.**
- **Barcode scanning for faster stock counts** (§2).
- **Inventory valuation / COGS reporting** — future Reports-module scope (§2).
- **Direct supplier ordering integration** (§0/§2) — future Integration Layer concern, not core Inventory.

## 17. Future AI Integration Points (vision only — not built now)

Consistent with the standing AI-layer vision (assistive only, API-first, never wired directly into the core
module): AI-assisted reorder-quantity suggestions based on real usage velocity; anomaly detection on unusual
consumption patterns (possible shrinkage/waste); natural-language "what's running low" queries via a future
AI Analytics Assistant. None of this is built now — Inventory's own API surface (§9) is what a future AI
layer would consume, never a direct DB read.

## 18. Testing Strategy

Same discipline as Clinical Notes' own now-established standard (a permanent E2E suite shipped within the
module's own implementation, not deferred to `TECH_DEBT.md`):

- **Backend**: `PurchaseOrderTest`/`SupplyTest`/`StockMovementTest` (Feature — lifecycle transitions,
  receive-cannot-exceed-ordered, cancel-blocked-once-received, permission matrix per §10),
  `StockMovementServiceTest` (Unit — on-hand-cannot-go-negative, correct ledger math across mixed
  received/used/wasted/correction rows).
- **Frontend**: `stores/inventory.ts` tests, Supplies/Purchase Orders component tests (role-gated UI, i18n
  parity across en/ar/tr).
- **E2E**: create Supply → record initial stock → create Purchase Order → place → receive (partial, then
  full) → verify on-hand and status transitions → record a `used` movement → verify Low Stock list surfaces
  it once below `reorder_level` → permission-matrix check (§10) → RTL/dark-mode smoke check.

---

**Awaiting approval — including explicit resolution of §15's five open decisions — before any
migration/model/service/policy/controller/frontend code is written, per the mandatory two-phase process.**
