<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { parseServerDateTime } from '@/lib/date'
import type { Appointment, AppointmentStatus } from '@/types/appointment'

const props = defineProps<{ appointment: Appointment }>()

const { t, locale } = useI18n()

type TimestampField = 'created_at' | 'checked_in_at' | 'started_at' | 'completed_at'

interface TimelineStepDef {
  key: string
  labelKey: string
  /**
   * `null` means there is no dedicated timestamp column for this step (only `confirmed` today —
   * confirmed via the `appointments` migration, no `confirmed_at` column exists, and Check-In can
   * happen directly from `scheduled` per §4.6, so status order alone can't prove confirmation
   * happened once the appointment has moved further). Reached-state for a `null` step falls back
   * to comparing against the *current* status only, a known, documented approximation.
   *
   * This array is the only thing a future timestamp field requires editing — e.g. if the backend
   * ever adds `confirmed_at`, changing this one line to `timestampField: 'confirmed_at'` makes it
   * behave exactly like every other ground-truth step, with no template change needed.
   */
  timestampField: TimestampField | null
}

const STATUS_ORDER: AppointmentStatus[] = ['scheduled', 'confirmed', 'checked_in', 'in_progress', 'completed']

const STEPS: TimelineStepDef[] = [
  { key: 'scheduled', labelKey: 'appointments.detail.timeline.scheduled', timestampField: 'created_at' },
  { key: 'confirmed', labelKey: 'appointments.detail.timeline.confirmed', timestampField: null },
  { key: 'checked_in', labelKey: 'appointments.detail.timeline.checkedIn', timestampField: 'checked_in_at' },
  { key: 'in_progress', labelKey: 'appointments.detail.timeline.inProgress', timestampField: 'started_at' },
  { key: 'completed', labelKey: 'appointments.detail.timeline.completed', timestampField: 'completed_at' },
]

interface RenderedStep {
  key: string
  label: string
  timestamp: Date | null
  reached: boolean
  hasGroundTruth: boolean
  terminal?: 'cancelled' | 'no_show'
}

function evaluateStep(step: TimelineStepDef, appointment: Appointment): RenderedStep {
  const rawTimestamp = step.timestampField ? appointment[step.timestampField] : null
  const reached = step.timestampField
    ? rawTimestamp != null
    : STATUS_ORDER.indexOf(appointment.status) >= STATUS_ORDER.indexOf(step.key as AppointmentStatus)

  return {
    key: step.key,
    label: t(step.labelKey),
    timestamp: rawTimestamp ? parseServerDateTime(rawTimestamp) : null,
    reached,
    hasGroundTruth: step.timestampField !== null,
  }
}

const steps = computed<RenderedStep[]>(() => {
  const appointment = props.appointment
  const evaluated = STEPS.map((step) => evaluateStep(step, appointment))

  const isCancelled = appointment.status === 'cancelled' && Boolean(appointment.cancelled_at)
  const isNoShow = appointment.status === 'no_show' && Boolean(appointment.no_show_at)

  if (!isCancelled && !isNoShow) return evaluated

  // §4.1: the chain visually terminates at a cancellation/no-show — never a ghost "Completed
  // (pending)" step past the point it actually stopped. Ground-truth steps (real timestamp
  // columns) gate the cutoff; the one step with no dedicated column (`confirmed`) is always
  // shown but never gates what comes after it, since its own reached-state is only an
  // approximation once the appointment has moved past it.
  const kept: RenderedStep[] = []
  for (const step of evaluated) {
    if (!step.hasGroundTruth) {
      kept.push(step)
      continue
    }
    if (!step.reached) break
    kept.push(step)
  }

  kept.push({
    key: isCancelled ? 'cancelled' : 'no_show',
    label: t(isCancelled ? 'appointments.detail.timeline.cancelled' : 'appointments.detail.timeline.noShow'),
    timestamp: parseServerDateTime(
      (isCancelled ? appointment.cancelled_at : appointment.no_show_at) as string,
    ),
    reached: true,
    hasGroundTruth: true,
    terminal: isCancelled ? 'cancelled' : 'no_show',
  })

  return kept
})

function formatTimestamp(value: Date): string {
  return value.toLocaleString(locale.value, { dateStyle: 'medium', timeStyle: 'short' })
}

const markerClass = (step: RenderedStep) => {
  if (step.terminal === 'cancelled') return 'bg-red-500 border-red-500'
  if (step.terminal === 'no_show') return 'bg-amber-500 border-amber-500'
  if (step.reached) return 'bg-primary-500 border-primary-500'
  return 'bg-transparent border-surface-300 dark:border-surface-600'
}
</script>

<template>
  <ol class="flex flex-col">
    <li v-for="(step, index) in steps" :key="step.key" class="flex gap-3">
      <div class="flex flex-col items-center">
        <span class="h-3 w-3 shrink-0 rounded-full border-2" :class="markerClass(step)" />
        <span
          v-if="index < steps.length - 1"
          class="w-px flex-1 bg-surface-200 dark:bg-surface-700"
          style="min-height: 1.5rem"
        />
      </div>
      <div class="flex flex-col pb-4" :class="step.reached ? '' : 'opacity-50'">
        <span
          class="text-sm font-medium"
          :class="
            step.terminal === 'cancelled'
              ? 'text-red-600 dark:text-red-400'
              : step.terminal === 'no_show'
                ? 'text-amber-600 dark:text-amber-400'
                : 'text-surface-900 dark:text-surface-0'
          "
        >
          {{ step.label }}
        </span>
        <span v-if="step.timestamp" class="text-xs text-surface-500 dark:text-surface-400">
          {{ formatTimestamp(step.timestamp) }}
        </span>
        <span
          v-if="step.terminal === 'cancelled' && appointment.cancellation_reason"
          class="text-xs text-surface-500 dark:text-surface-400"
        >
          {{
            t('appointments.detail.timeline.cancellationReason', { reason: appointment.cancellation_reason })
          }}
        </span>
      </div>
    </li>
  </ol>
</template>
