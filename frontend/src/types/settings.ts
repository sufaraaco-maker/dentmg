export interface ClinicSetting {
  id: string
  name: string
  phone: string | null
  address: string | null
  email: string | null
  updated_at: string
}

export interface UpdateClinicSettingPayload {
  name: string
  phone?: string | null
  address?: string | null
  email?: string | null
}

export interface BillingSetting {
  id: string
  currency_code: string
  tax_rate: string | null
  invoice_number_prefix: string
  /** Read-only — system-managed by InvoiceService, never sent in an update payload. */
  next_invoice_sequence: number
  updated_at: string
}

export interface UpdateBillingSettingPayload {
  currency_code: string
  tax_rate?: number | null
  invoice_number_prefix: string
}

export interface UpdateProfilePayload {
  name?: string
  email?: string
}

export interface UpdateProfilePasswordPayload {
  current_password: string
  password: string
  password_confirmation: string
}
