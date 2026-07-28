import type { PaymentMethod } from './payment'

export interface ProductionByDentistRow {
  dentist: string
  amount: string
  count: number
}

export interface ProductionRow {
  invoice_number: string
  patient: string
  date: string | null
  description: string
  dentist: string
  amount: string
}

export interface ProductionReport {
  summary: {
    total: string
    by_dentist: ProductionByDentistRow[]
  }
  rows: ProductionRow[]
}

export interface CollectionsByMethodRow {
  method: PaymentMethod
  amount: string
  count: number
}

export interface CollectionsRow {
  date: string | null
  patient: string
  invoice_number: string | null
  method: PaymentMethod
  amount: string
}

export interface CollectionsReport {
  summary: {
    total: string
    by_method: CollectionsByMethodRow[]
  }
  rows: CollectionsRow[]
}

export type ArAgingBucket = 'current' | '1_30' | '31_60' | '61_90' | '90_plus'

export interface ArAgingRow {
  patient: string
  invoice_number: string
  due_date: string | null
  days_overdue: number
  balance_due: string
}

export interface ArAgingReport {
  summary: {
    total: string
    buckets: Record<ArAgingBucket, string>
  }
  rows: ArAgingRow[]
}

export interface AppointmentStatusCount {
  status: string
  count: number
}

export interface AppointmentAnalyticsRow {
  date: string
  patient: string
  dentist: string
  type: string | null
  status: string
}

export interface AppointmentAnalyticsReport {
  summary: {
    total: number
    by_status: AppointmentStatusCount[]
    no_show_rate: number
    cancellation_rate: number
  }
  rows: AppointmentAnalyticsRow[]
}

export interface TreatmentPlanAcceptanceRow {
  patient: string
  dentist: string | null
  presented_at: string | null
  status: string
  value: string
}

export interface TreatmentPlanAcceptanceReport {
  summary: {
    presented: number
    accepted: number
    rejected: number
    acceptance_rate: number
    accepted_value: string
  }
  rows: TreatmentPlanAcceptanceRow[]
}

export interface NewPatientsByMonthRow {
  month: string
  count: number
}

export interface NewPatientsRow {
  name: string
  patient_code: string
  registered_at: string
}

export interface NewPatientsReport {
  summary: {
    total: number
    by_month: NewPatientsByMonthRow[]
  }
  rows: NewPatientsRow[]
}
