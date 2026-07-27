export interface Supplier {
  id: string
  name: string
  contact_name: string | null
  phone: string | null
  email: string | null
  address: string | null
  notes: string | null
  is_active: boolean
  created_at: string
  updated_at: string
}

export interface CreateSupplierPayload {
  name: string
  contact_name?: string | null
  phone?: string | null
  email?: string | null
  address?: string | null
  notes?: string | null
  is_active?: boolean
}

export type UpdateSupplierPayload = Partial<CreateSupplierPayload>

export interface SupplyCategory {
  id: string
  name: string
  sort_order: number
  is_active: boolean
  created_at: string
  updated_at: string
}

export interface CreateSupplyCategoryPayload {
  name: string
  sort_order?: number
  is_active?: boolean
}

export type UpdateSupplyCategoryPayload = Partial<CreateSupplyCategoryPayload>

export interface InventorySummaryRef {
  id: string
  name: string
}

export interface Supply {
  id: string
  category_id: string
  default_supplier_id: string | null
  name: string
  sku: string | null
  unit_of_measure: string
  /** Decimal cast — serialized as a string, or null if no default cost is on file. */
  unit_cost: string | null
  reorder_level: number
  reorder_quantity: number | null
  is_active: boolean
  /** Computed live from the stock_movements ledger — never stored (backend design doc §0/§4/§6). */
  quantity_on_hand: number
  is_low_stock: boolean
  created_at: string
  updated_at: string
  category?: InventorySummaryRef
  default_supplier?: InventorySummaryRef | null
}

export interface CreateSupplyPayload {
  category_id: string
  default_supplier_id?: string | null
  name: string
  sku?: string | null
  unit_of_measure: string
  unit_cost?: number | null
  reorder_level?: number
  reorder_quantity?: number | null
  is_active?: boolean
}

export type UpdateSupplyPayload = Partial<CreateSupplyPayload>

/** Excludes `received` — that reason is exclusively produced by receiving a Purchase Order item
 *  (backend design doc §2/§8), never a manual entry. */
export type StockMovementReason = 'initial_stock' | 'used' | 'wasted' | 'expired' | 'correction'

export const STOCK_MOVEMENT_REASONS: StockMovementReason[] = [
  'initial_stock',
  'used',
  'wasted',
  'expired',
  'correction',
]

export interface StockMovement {
  id: string
  supply_id: string
  /** Signed: positive = increase, negative = decrease. */
  quantity_delta: number
  reason: StockMovementReason | 'received'
  purchase_order_item_id: string | null
  expiration_date: string | null
  notes: string | null
  occurred_at: string
  created_at: string
  performed_by?: InventorySummaryRef
}

export interface RecordStockMovementPayload {
  reason: StockMovementReason
  quantity_delta: number
  expiration_date?: string | null
  notes?: string | null
  occurred_at?: string | null
}

export type PurchaseOrderStatus = 'draft' | 'placed' | 'partially_received' | 'received' | 'cancelled'

export interface PurchaseOrderItem {
  id: string
  purchase_order_id: string
  supply_id: string
  description: string
  quantity_ordered: number
  quantity_received: number
  quantity_remaining: number
  /** Decimal cast — serialized as a string. */
  unit_cost: string
  /** unit_cost * quantity_ordered — computed, never stored. */
  subtotal: string
  created_at: string
  updated_at: string
  supply?: { id: string; name: string; unit_of_measure: string }
}

export interface PurchaseOrder {
  id: string
  supplier_id: string
  sequence_number: number
  order_number: string
  status: PurchaseOrderStatus
  notes: string | null
  ordered_at: string | null
  expected_at: string | null
  created_at: string
  updated_at: string
  supplier?: InventorySummaryRef
  created_by?: InventorySummaryRef
  items?: PurchaseOrderItem[]
}

export interface CreatePurchaseOrderPayload {
  supplier_id: string
  notes?: string | null
  expected_at?: string | null
}

export interface UpdatePurchaseOrderPayload {
  notes?: string | null
  expected_at?: string | null
}

export interface AddPurchaseOrderItemPayload {
  supply_id: string
  description?: string | null
  quantity_ordered: number
  unit_cost?: number | null
}

export interface UpdatePurchaseOrderItemPayload {
  description?: string
  quantity_ordered?: number
  unit_cost?: number
}

export interface ReceivePurchaseOrderItemPayload {
  quantity: number
  expiration_date?: string | null
}

export type InventoryErrorCode = 'inventory_insufficient_stock' | 'invalid_purchase_order_operation'

/** The `{message, code}` shape every Inventory domain exception renders as (backend design doc §9/§12). */
export interface InventoryError {
  message: string
  code: InventoryErrorCode
}
