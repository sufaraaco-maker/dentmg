<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { useToast } from 'primevue/usetoast'
import Card from 'primevue/card'
import Skeleton from 'primevue/skeleton'
import Button from 'primevue/button'
import Tag from 'primevue/tag'
import AppointmentCard from './AppointmentCard.vue'
import StatusActionButton from './StatusActionButton.vue'
import { useAppointmentsStore } from '@/stores/appointments'
import { useAuthStore } from '@/stores/auth'
import { parseServerDateTime } from '@/lib/date'
import type { Appointment } from '@/types/appointment'

/**
 * Dashboard widget (design doc §8) — today's appointments with "Waiting"/"Late" highlighting and
 * a one-click Check In for the next scheduled/confirmed appointment. Fetches its own range
 * directly via `appointments.ts` (shared cache with the Board — a no-op fetch if the Board
 * already loaded today), independent of `calendar.ts`'s filter/range state, which belongs to the
 * Board only.
 */
const props = defineProps<{ scope: 'own' | 'all' }>()

const emit = defineEmits<{ 'new-appointment': [] }>()

const { t } = useI18n()
const router = useRouter()
const toast = useToast()
const appointmentsStore = useAppointmentsStore()
const auth = useAuthStore()

const LATE_GRACE_MINUTES = 10
const NOW_TICK_INTERVAL_MS = 60_000

const loading = ref(true)
const checkInBusyId = ref<string | null>(null)
// Ticks once a minute so the Waiting/Late computation below doesn't go stale while the dashboard
// stays open — cheap (a plain ref bump), no extra network calls.
const now = ref(Date.now())
let tickHandle: ReturnType<typeof setInterval> | undefined

function todayRange(): { start: Date; end: Date } {
  const start = new Date()
  start.setHours(0, 0, 0, 0)
  const end = new Date()
  end.setHours(23, 59, 59, 999)
  return { start, end }
}

async function load() {
  loading.value = true
  try {
    const { start, end } = todayRange()
    await appointmentsStore.fetchRange(start, end)
  } finally {
    loading.value = false
  }
}

const todaysAppointments = computed<Appointment[]>(() => {
  const { start, end } = todayRange()
  const startTime = start.getTime()
  const endTime = end.getTime()

  return Array.from(appointmentsStore.cache.values())
    .filter((appointment) => {
      const startAt = parseServerDateTime(appointment.start_at).getTime()
      if (startAt < startTime || startAt > endTime) return false
      if (props.scope === 'own' && appointment.dentist_id !== auth.user?.id) return false
      return true
    })
    .sort(
      (a, b) => parseServerDateTime(a.start_at).getTime() - parseServerDateTime(b.start_at).getTime(),
    )
})

/** Operational highlighting only applies to the front-desk ("all") framing, not a dentist's own read-only view. */
function rowState(appointment: Appointment): 'waiting' | 'late' | null {
  if (props.scope !== 'all') return null

  if (appointment.status === 'checked_in' && !appointment.started_at) return 'waiting'

  if (['scheduled', 'confirmed'].includes(appointment.status)) {
    const minutesPast = (now.value - parseServerDateTime(appointment.start_at).getTime()) / 60_000
    if (minutesPast > LATE_GRACE_MINUTES) return 'late'
  }

  return null
}

/** Only the single next actionable appointment gets the inline Check In button (design doc §8). */
const nextActionableId = computed(() => {
  if (props.scope !== 'all') return null
  const next = todaysAppointments.value.find((a) => ['scheduled', 'confirmed'].includes(a.status))
  return next?.id ?? null
})

async function checkIn(id: string) {
  checkInBusyId.value = id
  try {
    await appointmentsStore.checkIn(id)
    toast.add({ severity: 'success', summary: t('appointments.detail.actionSuccess'), life: 3000 })
  } catch {
    toast.add({ severity: 'error', summary: t('appointments.detail.actionError'), life: 3000 })
  } finally {
    checkInBusyId.value = null
  }
}

function goToDetail(id: string) {
  router.push({ name: 'appointment-detail', params: { id } })
}

onMounted(() => {
  load()
  tickHandle = setInterval(() => {
    now.value = Date.now()
  }, NOW_TICK_INTERVAL_MS)
})

onUnmounted(() => {
  if (tickHandle) clearInterval(tickHandle)
})
</script>

<template>
  <Card>
    <template #title>
      {{ scope === 'all' ? t('dashboard.widgets.today.title') : t('dashboard.widgets.today.myTitle') }}
    </template>
    <template #content>
      <div v-if="loading" class="flex flex-col gap-2">
        <Skeleton height="4.5rem" />
        <Skeleton height="4.5rem" />
      </div>

      <div v-else-if="!todaysAppointments.length" class="flex flex-col items-start gap-3 py-2">
        <p class="text-sm text-surface-500">{{ t('dashboard.widgets.today.empty') }}</p>
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
          v-for="appointment in todaysAppointments"
          :key="appointment.id"
          variant="compact"
          :appointment="appointment"
          @click="goToDetail(appointment.id)"
        >
          <template v-if="rowState(appointment) || nextActionableId === appointment.id" #actions>
            <Tag
              v-if="rowState(appointment) === 'waiting'"
              :value="t('dashboard.widgets.today.waiting')"
              severity="warn"
            />
            <Tag
              v-if="rowState(appointment) === 'late'"
              :value="t('dashboard.widgets.today.late')"
              severity="danger"
            />
            <StatusActionButton
              v-if="nextActionableId === appointment.id"
              action="checkIn"
              :loading="checkInBusyId === appointment.id"
              @confirmed="checkIn(appointment.id)"
            />
          </template>
        </AppointmentCard>
      </div>
    </template>
  </Card>
</template>
