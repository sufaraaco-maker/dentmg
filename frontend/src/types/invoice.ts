export type InvoiceStatus = 'draft' | 'issued' | 'void'

export const INVOICE_STATUSES: InvoiceStatus[] = ['draft', 'issued', 'void']

export type InvoiceItemKind = 'charge' | 'discount' | 'tax'

export const INVOICE_ITEM_KINDS: InvoiceItemKind[] = ['charge', 'discount', 'tax']

export interface InvoiceUserSummary {
  id: string
  name: string
}

/** The embedded `treatment_plan_item` shape on an `InvoiceItem` — traceability only, never used to
 *  resolve description/amount once written (backend design doc §7, Decision Log item 6). */
export interface InvoiceItemTreatmentPlanItemSummary {
  id: string
  procedure_name: string
}

export interface InvoiceItem {
  id: string
  invoice_id: string
  treatment_plan_item_id: string | null
  kind: InvoiceItemKind
  description: string
  quantity: number
  /** Decimal cast — serialized as a string by the backend (e.g. `"150.00"`), always positive
   *  regardless of `kind`; the sign is applied by `kind` at the invoice-total level, never stored. */
  unit_amount: string
  /** `unit_amount * quantity`, computed server-side — never stored. */
  amount: string
  sequence: number | null
  notes: string | null
  created_at: string
  updated_at: string
  treatment_plan_item?: InvoiceItemTreatmentPlanItemSummary | null
  created_by?: InvoiceUserSummary
}

export interface Invoice {
  id: string
  patient_id: string
  created_by_id: string
  sequence_number: number | null
  /** Assigned exactly once, at the `draft -> issued` transition — `null` while still `draft`. */
  invoice_number: string | null
  /** Snapshotted from `billing_settings` at creation — never assume a single global currency
   *  anywhere in the UI; always display this field. */
  currency_code: string
  status: InvoiceStatus
  notes: string | null
  issue_date: string | null
  due_date: string | null
  issued_at: string | null
  voided_at: string | null
  created_at: string
  updated_at: string
  /** `SUM(charge) + SUM(tax) - SUM(discount)` — computed server-side, never stored. Present
   *  whenever `items` is loaded, which every API response is (index/show/every mutation). */
  total?: string
  /** `SUM(payments.amount)`, refunds included (Payments backend design doc §4/§6/§9) — present
   *  whenever `payments` is loaded, which every API response is. */
  amount_paid?: string
  /** `total - amount_paid` — can go negative on an overpayment/credit. */
  balance_due?: string
  payment_status?: 'unpaid' | 'partially_paid' | 'paid'
  created_by?: InvoiceUserSummary
  items?: InvoiceItem[]
}

export interface CreateInvoicePayload {
  notes?: string | null
  issue_date?: string | null
  due_date?: string | null
}

export type UpdateInvoicePayload = Partial<CreateInvoicePayload>

export interface CreateInvoiceItemPayload {
  kind: InvoiceItemKind
  /** Only meaningful on a `charge`-kind item — must belong to the same patient as the invoice
   *  (enforced server-side; the UI only ever offers items already scoped to this patient). */
  treatment_plan_item_id?: string | null
  /** Nullable at the HTTP layer only so it can default from the referenced treatment plan item's
   *  procedure name — but required when no `treatment_plan_item_id` is set. */
  description?: string | null
  unit_amount?: number | null
  quantity?: number
  sequence?: number | null
  notes?: string | null
}

export type UpdateInvoiceItemPayload = Partial<CreateInvoiceItemPayload>

export type InvoiceErrorCode =
  'invalid_invoice_status_transition' | 'invoice_item_locked' | 'invalid_invoice_item'

/** The `{message, code}` shape every Invoice domain exception renders as (backend design doc §9/§12). */
export interface InvoiceError {
  message: string
  code: InvoiceErrorCode
}
