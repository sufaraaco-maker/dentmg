<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import Card from 'primevue/card'
import AppointmentStatusChip from './AppointmentStatusChip.vue'
import { parseServerDateTime } from '@/lib/date'
import type { Appointment } from '@/types/appointment'

/**
 * Presentational summary card — Detail view header, future Dashboard rows, future Patient-panel
 * rows (design doc §4.3, §9). Deliberately store-free: takes a full `Appointment` as a prop and
 * emits `click`, so any future call site can reuse it without pulling in the appointments store
 * or any auth/permission logic — those decisions belong to the parent, not this component.
 */
const props = withDefaults(
  defineProps<{ appointment: Appointment; variant?: 'default' | 'compact'; clickable?: boolean }>(),
  { variant: 'default', clickable: false },
)

const emit = defineEmits<{ click: [] }>()

const { t, locale } = useI18n()

function onKeydown(event: KeyboardEvent) {
  if (event.key === 'Enter' || event.key === ' ') {
    event.preventDefault()
    emit('click')
  }
}

const startAt = computed(() => parseServerDateTime(props.appointment.start_at))
const endAt = computed(() => parseServerDateTime(props.appointment.end_at))

const dateLabel = computed(() =>
  startAt.value.toLocaleDateString(locale.value, {
    weekday: 'short',
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  }),
)

const timeRangeLabel = computed(() => {
  const opts: Intl.DateTimeFormatOptions = { hour: '2-digit', minute: '2-digit' }
  return `${startAt.value.toLocaleTimeString(locale.value, opts)} – ${endAt.value.toLocaleTimeString(locale.value, opts)}`
})

const durationLabel = computed(() =>
  t('appointments.list.durationMinutes', { n: props.appointment.duration_minutes }),
)
const typeColor = computed(() => props.appointment.appointment_type?.color ?? '#64748b')
const typeName = computed(() => props.appointment.appointment_type?.name ?? t('appointments.untitled'))
const dentistName = computed(() => props.appointment.dentist?.name ?? '—')
</script>

<template>
  <Card
    :tabindex="clickable ? 0 : undefined"
    :role="clickable ? 'button' : undefined"
    :class="
      clickable
        ? 'cursor-pointer focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 dark:focus-visible:ring-primary-400'
        : undefined
    "
    @click="emit('click')"
    @keydown="clickable && onKeydown($event)"
  >
    <template #content>
      <div class="flex flex-col gap-3" :class="variant === 'compact' ? 'text-sm' : ''">
        <div class="flex items-start justify-between gap-3">
          <div class="flex items-center gap-2">
            <span class="h-2.5 w-2.5 shrink-0 rounded-full" :style="{ backgroundColor: typeColor }" />
            <span class="font-medium text-surface-900 dark:text-surface-0">{{ typeName }}</span>
          </div>
          <AppointmentStatusChip
            :status="appointment.status"
            :size="variant === 'compact' ? 'small' : 'normal'"
          />
        </div>

        <div class="flex flex-col gap-1 text-surface-600 dark:text-surface-300">
          <span v-if="appointment.patient">{{ appointment.patient.full_name }}</span>
          <span>{{ dentistName }}</span>
          <span>{{ dateLabel }} · {{ timeRangeLabel }} ({{ durationLabel }})</span>
          <span v-if="appointment.reason">{{ appointment.reason }}</span>
        </div>

        <div v-if="$slots.actions" class="flex justify-end gap-2" @click.stop>
          <slot name="actions" />
        </div>
      </div>
    </template>
  </Card>
</template>
