export interface ClinicSetting {
  id: string
  name: string
  phone: string | null
  address: string | null
  email: string | null
  logo_url: string | null
  ai_assistant_enabled: boolean
  ai_assistant_phi_features_acknowledged: boolean
  /** The raw key is never sent to the frontend (ai-assistant-settings-api-key-design.md §5) —
   *  only whether one is set and its last 4 characters, enough to recognize it without exposing it. */
  ai_assistant_api_key_configured: boolean
  ai_assistant_api_key_last4: string | null
  updated_at: string
}

export interface UpdateClinicSettingPayload {
  /** Only required when actually setting the practice name (Practice Settings' own form always
   *  sends it); omit entirely for a partial update, e.g. AI Assistant Settings' toggles-only save. */
  name?: string
  phone?: string | null
  address?: string | null
  email?: string | null
  ai_assistant_enabled?: boolean
  ai_assistant_phi_features_acknowledged?: boolean
  /** Omit to leave a previously-saved key untouched; `null` clears it ("Remove key"); a string sets/
   *  replaces it. Never pre-filled from a GET response — the API never returns the raw value. */
  ai_assistant_api_key?: string | null
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
