<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import ProgressSpinner from 'primevue/progressspinner'
import { appointmentsApi } from '@/services/appointments'
import { useWorkingHoursStore } from '@/stores/workingHours'
import { parseServerDateTime, toLocalDateString } from '@/lib/date'

/**
 * Available-slot chip grid (design doc §3.6). Computes the full range of candidate slot times
 * for the given day from `workingHours.ts` at 15-minute granularity (matching the backend's
 * default `slot_interval_minutes`), then cross-references `GET /available-slots` — returned
 * slots render as clickable chips, everything else in the working-hours range renders
 * disabled/muted ("outside hours or busy," not distinguished further — the backend doesn't say
 * why a slot is unavailable, only that it isn't in the list).
 */
const SLOT_INTERVAL_MINUTES = 15

const props = defineProps<{
  dentistId: string | null
  date: Date
  durationMinutes: number
}>()

const emit = defineEmits<{ 'slot-selected': [start: Date] }>()

const { t, locale } = useI18n()
const workingHours = useWorkingHoursStore()

const loadingSlots = ref(false)
const availableTimestamps = ref<Set<number>>(new Set())

function timeToMinutes(time: string): number {
  const [hours, minutes] = time.split(':').map(Number)
  return hours * 60 + minutes
}

const candidateSlots = computed<Date[]>(() => {
  if (!props.dentistId || !props.durationMinutes) return []

  const shifts = (workingHours.byDentist.get(props.dentistId) ?? []).filter(
    (row) => row.is_active && row.day_of_week === props.date.getDay(),
  )

  const slots: Date[] = []

  for (const shift of shifts) {
    const startMinutes = timeToMinutes(shift.start_time)
    const endMinutes = timeToMinutes(shift.end_time)

    for (let m = startMinutes; m + props.durationMinutes <= endMinutes; m += SLOT_INTERVAL_MINUTES) {
      const slot = new Date(props.date)
      slot.setHours(0, m, 0, 0)
      slots.push(slot)
    }
  }

  return slots.sort((a, b) => a.getTime() - b.getTime())
})

async function fetchAvailableSlots() {
  if (!props.dentistId || !props.durationMinutes) {
    availableTimestamps.value = new Set()
    return
  }

  loadingSlots.value = true

  try {
    const slots = await appointmentsApi.availableSlots({
      dentist_id: props.dentistId,
      date: toLocalDateString(props.date),
      duration_minutes: props.durationMinutes,
    })
    // `slots` are the backend's naive-digits-labeled timestamps (see lib/date.ts) — must go
    // through `parseServerDateTime`, not a raw `new Date(iso)`, or these would never match
    // `candidateSlots` (genuinely local Dates) in a browser whose OS timezone isn't UTC.
    availableTimestamps.value = new Set(slots.map((iso) => parseServerDateTime(iso).getTime()))
  } finally {
    loadingSlots.value = false
  }
}

watch(() => [props.dentistId, toLocalDateString(props.date), props.durationMinutes], fetchAvailableSlots, {
  immediate: true,
})

// `candidateSlots` reads `workingHours.byDentist` — nothing else guarantees that's populated for
// *this* dentist. The Board's own filter happens to trigger it as a side effect (AppointmentsView's
// watcher), but the dialog's dentist selection is independent of that filter, so relying on it left
// `candidateSlots` silently empty (and "No available slots" shown) for any dentist not currently
// selected in the Board filter — confirmed directly. `fetchForDentist` no-ops if already cached.
watch(
  () => props.dentistId,
  (dentistId) => {
    if (dentistId) workingHours.fetchForDentist(dentistId)
  },
  { immediate: true },
)

function isAvailable(slot: Date): boolean {
  return availableTimestamps.value.has(slot.getTime())
}

function formatTime(slot: Date): string {
  return slot.toLocaleTimeString(locale.value, { hour: '2-digit', minute: '2-digit' })
}

function pick(slot: Date) {
  if (isAvailable(slot)) emit('slot-selected', slot)
}
</script>

<template>
  <div class="flex flex-col gap-2">
    <div v-if="loadingSlots" class="flex items-center justify-center py-3">
      <ProgressSpinner style="width: 1.5rem; height: 1.5rem" stroke-width="6" />
    </div>

    <p v-else-if="!candidateSlots.length" class="text-sm text-surface-500 dark:text-surface-400">
      {{ t('appointments.dialog.noSlotsFound') }}
    </p>

    <div v-else class="flex flex-wrap gap-2">
      <button
        v-for="slot in candidateSlots"
        :key="slot.getTime()"
        type="button"
        :disabled="!isAvailable(slot)"
        class="rounded-full border px-3 py-1 text-sm transition-colors"
        :class="
          isAvailable(slot)
            ? 'cursor-pointer border-primary-300 bg-primary-50 text-primary-700 hover:bg-primary-100 dark:border-primary-700 dark:bg-primary-900/30 dark:text-primary-300 dark:hover:bg-primary-900/50'
            : 'cursor-not-allowed border-surface-200 text-surface-400 dark:border-surface-700 dark:text-surface-600'
        "
        @click="pick(slot)"
      >
        {{ formatTime(slot) }}
      </button>
    </div>
  </div>
</template>
