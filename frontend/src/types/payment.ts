export type PaymentMethod = 'cash' | 'card' | 'bank_transfer' | 'other'

export const PAYMENT_METHODS: PaymentMethod[] = ['cash', 'card', 'bank_transfer', 'other']

export interface PaymentUserSummary {
  id: string
  name: string
}

export interface Payment {
  id: string
  patient_id: string
  /** `null` = unapplied/advance credit sitting on the patient's account (backend design doc §4). */
  invoice_id: string | null
  /** Set only on a refund row — points back at the payment it reverses. */
  refunded_payment_id: string | null
  is_refund: boolean
  /** `amount + SUM(refunds against it)` — never stored; the max a `Refund` action may request. */
  remaining_refundable_amount: string
  method: PaymentMethod
  /** Decimal cast — serialized as a string. Positive for an ordinary payment, negative for a
   *  refund row (`is_refund === true`). */
  amount: string
  /** Snapshotted from `billing_settings` at creation — never assume a single global currency. */
  currency_code: string
  reference: string | null
  notes: string | null
  received_at: string
  created_at: string
  updated_at: string
  created_by?: PaymentUserSummary
}

export interface RecordPaymentPayload {
  invoice_id?: string | null
  method: PaymentMethod
  amount: number
  reference?: string | null
  notes?: string | null
  received_at?: string | null
}

export interface UpdatePaymentPayload {
  reference?: string | null
  notes?: string | null
  received_at?: string
}

export interface ApplyPaymentPayload {
  invoice_id: string
}

export interface RefundPaymentPayload {
  amount: number
  notes?: string | null
}

export type PaymentErrorCode = 'invalid_payment_operation' | 'payment_refund_exceeds_remaining_balance'

/** The `{message, code}` shape every Payment domain exception renders as (backend design doc §9/§12). */
export interface PaymentError {
  message: string
  code: PaymentErrorCode
}
