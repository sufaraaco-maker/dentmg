export type BillingStatus = 'paid' | 'partial' | 'overdue' | 'no_activity'

/** `GET /patients/{patient}/billing-summary` response (design doc §6.3/§8/§11.4, Phase 2.2) — the
 *  Billing tab's Outstanding Balance hero + summary row. Computed server-side via SQL aggregates
 *  only, scoped to `issued` invoices. */
export interface BillingSummary {
  total_invoiced: string
  total_paid: string
  invoice_count: number
  last_payment_date: string | null
  outstanding_balance: string
  status: BillingStatus
  currency_code: string
}
