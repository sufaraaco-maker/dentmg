/** Period-over-period comparison — `change_pct` is `null`, never `0`, when the previous period had
 *  no activity (a percentage computed against zero would be fabricated, not real). */
export interface DashboardTrend {
  current: string
  previous: string
  change_pct: number | null
}

export interface UnscheduledAcceptedTreatmentItem {
  patient: string
  patient_id: string
  treatment_plan_id: string
  item_description: string
  accepted_at: string | null
}

/** `GET /dashboard/summary` response (Dashboard 2.0 design doc §1.1) — operational data, open to
 *  every role. Deliberately carries no financial figures; see `DashboardFinancialSummary`. */
export interface DashboardSummary {
  total_patients: number
  today_appointments: number
  unscheduled_accepted_treatment: {
    count: number
    items: UnscheduledAcceptedTreatmentItem[]
  }
}

export interface ArAgingSnapshot {
  total: string
  buckets: {
    current: string
    '1_30': string
    '31_60': string
    '61_90': string
    '90_plus': string
  }
}

/** `GET /dashboard/financial-summary` response (design doc §1.2) — admin-only
 *  (`view-financial-reports`). Each figure is a thin wrapper around an existing Reports aggregate. */
export interface DashboardFinancialSummary {
  monthly_revenue: string
  production_trend: DashboardTrend
  collections_trend: DashboardTrend
  ar_aging: ArAgingSnapshot
}
