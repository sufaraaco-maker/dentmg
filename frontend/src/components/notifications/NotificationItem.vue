<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { parseServerDateTime } from '@/lib/date'
import { categoryAccent, notificationIcon } from '@/config/notificationTypes'
import type { AppNotification } from '@/types/notification'

/**
 * Notification System (Phase 5A) design doc §10.1 — one row.
 *
 * Renders entirely from the stored payload's translation keys + raw params (design doc §11), so
 * switching language re-renders an existing notification correctly with no backfill. Nothing here
 * displays a raw backend string.
 */
const props = defineProps<{ notification: AppNotification }>()

defineEmits<{ (e: 'select', notification: AppNotification): void }>()

const { t, locale } = useI18n()

const icon = computed(() => notificationIcon(props.notification.data.type, props.notification.category))
const accent = computed(() => categoryAccent(props.notification.category))
const isUnread = computed(() => props.notification.read_at === null)

/**
 * Params are stored raw (design doc §11.2). Timestamps are formatted here, at render time, through
 * `parseServerDateTime` — a raw `new Date(...)` would display the wrong time in any browser whose
 * OS timezone isn't UTC (the project-wide datetime policy, `lib/date.ts`).
 */
const messageParams = computed(() => {
  const params = { ...props.notification.data.params } as Record<string, unknown>

  if (typeof params.startAt === 'string') {
    params.startAt = new Intl.DateTimeFormat(locale.value, {
      dateStyle: 'medium',
      timeStyle: 'short',
    }).format(parseServerDateTime(params.startAt))
  }

  if (typeof params.amount === 'number') {
    params.amount = new Intl.NumberFormat(locale.value, {
      style: 'currency',
      currency: typeof params.currencyCode === 'string' ? params.currencyCode : 'USD',
    }).format(params.amount)
  }

  return params
})

const relativeTime = computed(() => {
  const created = parseServerDateTime(props.notification.created_at)
  const diffMs = created.getTime() - Date.now()
  const formatter = new Intl.RelativeTimeFormat(locale.value, { numeric: 'auto' })

  const units: [Intl.RelativeTimeFormatUnit, number][] = [
    ['day', 86_400_000],
    ['hour', 3_600_000],
    ['minute', 60_000],
  ]

  for (const [unit, ms] of units) {
    if (Math.abs(diffMs) >= ms) {
      return formatter.format(Math.round(diffMs / ms), unit)
    }
  }

  return formatter.format(0, 'minute')
})
</script>

<template>
  <button
    type="button"
    class="flex w-full items-start gap-3 border-b border-surface-200 px-4 py-3 text-start transition-colors hover:bg-surface-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-primary-400 dark:border-surface-700 dark:hover:bg-surface-800"
    :class="isUnread ? 'bg-primary-50/60 dark:bg-primary-950/30' : ''"
    :data-testid="`notification-${notification.id}`"
    :data-unread="isUnread"
    @click="$emit('select', notification)"
  >
    <component :is="icon" :size="18" class="mt-0.5 shrink-0" :class="accent" aria-hidden="true" />

    <span class="flex min-w-0 flex-1 flex-col gap-0.5">
      <span
        class="truncate text-sm"
        :class="
          isUnread
            ? 'font-semibold text-surface-900 dark:text-surface-0'
            : 'font-medium text-surface-600 dark:text-surface-300'
        "
      >
        {{ t(notification.data.titleKey, messageParams) }}
      </span>
      <span class="line-clamp-2 text-xs text-surface-500 dark:text-surface-400">
        {{ t(notification.data.bodyKey, messageParams) }}
      </span>
      <!-- dir="ltr" so a formatted timestamp keeps its own direction inside an RTL layout. -->
      <span class="text-xs text-surface-400 dark:text-surface-500" dir="ltr">
        {{ relativeTime }}
      </span>
    </span>

    <span
      v-if="isUnread"
      class="mt-1.5 size-2 shrink-0 rounded-full bg-primary-500"
      :aria-label="t('notifications.unread')"
    />
  </button>
</template>
