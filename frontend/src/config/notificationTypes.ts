import {
  Beaker,
  CalendarClock,
  CalendarX2,
  ClipboardCheck,
  Clock,
  FileX2,
  KeyRound,
  LogIn,
  PackageX,
  ShieldAlert,
  Undo2,
  UserX,
} from 'lucide-vue-next'
import type { Component } from 'vue'
import type { NotificationCategory, NotificationType } from '@/types/notification'

/**
 * Notification System (Phase 5A) design doc §10.1. Phase 5C (design doc §5.2/§5.3) added the
 * `inventory` / `security` categories and their types — same "no raw backend value ever reaches the
 * UI" role, extended rather than reworked.
 *
 * The "no raw backend value ever reaches the UI" layer, same role `config/auditableTypes.ts` plays
 * for the Audit Log viewer. A notification row arrives carrying only a stable type string and a
 * category; everything visual (which icon, which accent) and every i18n key is derived here rather
 * than being stored on the row — so restyling or re-wording a notification type never requires a
 * data migration, and an old row keeps rendering after a rewording.
 */

/** Per-category accent, used by the category filter chips and each row's icon tint. */
export const CATEGORY_STYLES: Record<NotificationCategory, { icon: Component; accent: string }> = {
  appointments: { icon: CalendarX2, accent: 'text-sky-600 dark:text-sky-400' },
  treatment_plans: { icon: ClipboardCheck, accent: 'text-violet-600 dark:text-violet-400' },
  laboratory: { icon: Beaker, accent: 'text-amber-600 dark:text-amber-400' },
  billing: { icon: FileX2, accent: 'text-rose-600 dark:text-rose-400' },
  payments: { icon: Undo2, accent: 'text-emerald-600 dark:text-emerald-400' },
  inventory: { icon: PackageX, accent: 'text-orange-600 dark:text-orange-400' },
  security: { icon: ShieldAlert, accent: 'text-red-600 dark:text-red-400' },
}

/**
 * Per-type icon override where the category icon would be misleading — a check-in is an arrival,
 * not a cancellation, even though both are `appointments`.
 */
const TYPE_ICONS: Partial<Record<NotificationType, Component>> = {
  'appointment.checked_in': LogIn,
  'appointment.no_show': UserX,
  'lab_case.overdue': Clock,
  'appointment.unconfirmed': CalendarClock,
  'permissions.matrix_updated': KeyRound,
}

export function notificationIcon(type: NotificationType, category: NotificationCategory): Component {
  return TYPE_ICONS[type] ?? CATEGORY_STYLES[category]?.icon ?? CalendarX2
}

export function categoryAccent(category: NotificationCategory): string {
  return CATEGORY_STYLES[category]?.accent ?? 'text-surface-500'
}

/** `notifications.categories.<category>` — one key per category, parity-checked across en/ar/tr. */
export function categoryLabelKey(category: NotificationCategory): string {
  return `notifications.categories.${category}`
}
