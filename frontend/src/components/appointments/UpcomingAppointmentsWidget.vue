<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import Card from 'primevue/card'
import Skeleton from 'primevue/skeleton'
import Button from 'primevue/button'
import AppointmentCard from './AppointmentCard.vue'
import { useAppointmentsStore } from '@/stores/appointments'
import { useAuthStore } from '@/stores/auth'
import { parseServerDateTime } from '@/lib/date'
import type { Appointment, AppointmentStatus } from '@/types/appointment'

/**
 * Dashboard widget (design doc §8) — the next few days' appointments beyond today, "what's
 * coming" at a glance. Fetches its own range directly via `appointments.ts` (shared cache with
 * the Board), independent of `calendar.ts`.
 */
const props = defineProps<{ scope: 'own' | 'all' }>()

const emit = defineEmits<{ 'new-appointment': [] }>()

const { t } = useI18n()
const router = useRouter()
const appointmentsStore = useAppointmentsStore()
const auth = useAuthStore()

const UPCOMING_WINDOW_DAYS = 6
const MAX_ROWS = 5
// A cancelled/no-show entry several days out is no longer "what's coming" — unlike the Board
// (design doc §2.3), which must keep history legible, this widget's purpose is forward planning.
const EXCLUDED_STATUSES: AppointmentStatus[] = ['cancelled', 'no_show']

const loading = ref(true)

function upcomingRange(): { start: Date; end: Date } {
  const start = new Date()
  start.setDate(start.getDate() + 1)
  start.setHours(0, 0, 0, 0)
  const end = new Date(start)
  end.setDate(end.getDate() + UPCOMING_WINDOW_DAYS)
  end.setHours(23, 59, 59, 999)
  return { start, end }
}

async function load() {
  loading.value = true
  try {
    const { start, end } = upcomingRange()
    await appointmentsStore.fetchRange(start, end)
  } finally {
    loading.value = false
  }
}

const upcomingAppointments = computed<Appointment[]>(() => {
  const { start, end } = upcomingRange()
  const startTime = start.getTime()
  const endTime = end.getTime()

  return Array.from(appointmentsStore.cache.values())
    .filter((appointment) => {
      const startAt = parseServerDateTime(appointment.start_at).getTime()
      if (startAt < startTime || startAt > endTime) return false
      if (EXCLUDED_STATUSES.includes(appointment.status)) return false
      if (props.scope === 'own' && appointment.dentist_id !== auth.user?.id) return false
      return true
    })
    .sort(
      (a, b) => parseServerDateTime(a.start_at).getTime() - parseServerDateTime(b.start_at).getTime(),
    )
    .slice(0, MAX_ROWS)
})

function goToDetail(id: string) {
  router.push({ name: 'appointment-detail', params: { id } })
}

onMounted(load)
</script>

<template>
  <Card>
    <template #title>{{ t('dashboard.widgets.upcoming.title') }}</template>
    <template #content>
      <div v-if="loading" class="flex flex-col gap-2">
        <Skeleton height="4.5rem" />
        <Skeleton height="4.5rem" />
      </div>

      <div v-else-if="!upcomingAppointments.length" class="flex flex-col items-start gap-3 py-2">
        <p class="text-sm text-surface-500">{{ t('dashboard.widgets.upcoming.empty') }}</p>
        <Button
          :label="t('dashboard.widgets.newAppointment')"
          icon="pi pi-plus"
          size="small"
          text
          @click="emit('new-appointment')"
        />
      </div>

      <div v-else class="flex flex-col gap-2">
        <AppointmentCard
          v-for="appointment in upcomingAppointments"
          :key="appointment.id"
          variant="compact"
          :appointment="appointment"
          @click="goToDetail(appointment.id)"
        />
      </div>
    </template>
  </Card>
</template>
