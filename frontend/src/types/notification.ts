/**
 * Notification System (Phase 5A) — `NotificationResource` shape, design doc §6.2/§7.
 */

/** The 8 whitelisted V1 types. Mirrors `NotificationRules::RULES` on the backend. */
export type NotificationType =
  | 'appointment.checked_in'
  | 'appointment.cancelled'
  | 'appointment.no_show'
  | 'treatment_plan.accepted'
  | 'treatment_plan.rejected'
  | 'lab_case.received'
  | 'payment.refunded'
  | 'invoice.voided'

/** Mirrors `NotificationPolicy::CATEGORY_SUBJECT_MAP`. Phase C adds `inventory` and `security`. */
export type NotificationCategory = 'appointments' | 'treatment_plans' | 'laboratory' | 'billing' | 'payments'

export const NOTIFICATION_CATEGORIES: NotificationCategory[] = [
  'appointments',
  'treatment_plans',
  'laboratory',
  'billing',
  'payments',
]

/**
 * The stored payload. Deliberately holds translation KEYS and RAW params, never rendered text
 * (design doc §11) — the row is written once server-side but read by a user whose locale is their
 * own and can change at any time, so vue-i18n renders it at display time instead.
 *
 * `params` values stay raw (ISO-8601 timestamps, unformatted amounts): dates are formatted through
 * `lib/date.ts` per the project-wide datetime policy, currency through the clinic's configured
 * code, both at render time.
 */
export interface NotificationPayload {
  type: NotificationType
  titleKey: string
  bodyKey: string
  params: Record<string, unknown>
  actorName: string | null
  route: {
    name: string
    params?: Record<string, string>
    query?: Record<string, string>
  }
}

export interface AppNotification {
  id: string
  category: NotificationCategory
  subject_type: string
  subject_id: string
  /** Null for Phase C's low-stock and security notifications, which have no patient. */
  patient_id: string | null
  data: NotificationPayload
  read_at: string | null
  created_at: string
}

export type NotificationStatusFilter = 'all' | 'unread' | 'read'

export interface NotificationFilters {
  status?: NotificationStatusFilter
  category?: NotificationCategory
}

export interface PaginatedNotifications {
  data: AppNotification[]
  meta: { current_page: number; last_page: number; per_page: number; total: number }
}
