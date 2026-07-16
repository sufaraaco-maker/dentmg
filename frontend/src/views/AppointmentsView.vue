<script setup lang="ts">
import { computed, onMounted, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { useToast } from 'primevue/usetoast'
import Card from 'primevue/card'
import CalendarToolbar from '@/components/appointments/CalendarToolbar.vue'
import CalendarFilters from '@/components/appointments/CalendarFilters.vue'
import AppointmentCalendar from '@/components/appointments/AppointmentCalendar.vue'
import type { BoardViewMode } from '@/components/appointments/CalendarToolbar.vue'
import type { AppointmentCalendarEvent } from '@/components/appointments/AppointmentCalendar.vue'
import { useCalendarStore, type CalendarFilters as CalendarFiltersState } from '@/stores/calendar'
import { useAppointmentsStore } from '@/stores/appointments'
import { useProvidersStore } from '@/stores/providers'
import { useWorkingHoursStore } from '@/stores/workingHours'
import { useTimeOffStore } from '@/stores/timeOff'
import { APPOINTMENT_STATUS_COLORS, type Appointment } from '@/types/appointment'

const DEFAULT_SLOT_MIN_TIME = '08:00:00'
const DEFAULT_SLOT_MAX_TIME = '20:00:00'
const SLOT_PADDING_MINUTES = 60

const { t, locale } = useI18n()
const router = useRouter()
const toast = useToast()

const calendar = useCalendarStore()
const appointments = useAppointmentsStore()
const providers = useProvidersStore()
const workingHours = useWorkingHoursStore()
const timeOff = useTimeOffStore()

const boardViewMode = computed<BoardViewMode>({
  get: () =>
    calendar.viewMode === 'timeGridDay' || calendar.viewMode === 'dayGridMonth'
      ? calendar.viewMode
      : 'timeGridWeek',
  set: (mode) => calendar.setViewMode(mode),
})

/** Only meaningful (per §2.4/§2.5) when the Dentist filter narrows to exactly one dentist. */
const singleSelectedDentistId = computed(() =>
  calendar.filters.dentistIds.length === 1 ? calendar.filters.dentistIds[0] : null,
)

watch(
  singleSelectedDentistId,
  (dentistId) => {
    if (dentistId) {
      workingHours.fetchForDentist(dentistId)
      timeOff.fetchForDentist(dentistId)
    }
  },
  { immediate: true },
)

const rangeLabel = computed(() => {
  const formatterOptions: Intl.DateTimeFormatOptions =
    calendar.viewMode === 'dayGridMonth'
      ? { month: 'long', year: 'numeric' }
      : calendar.viewMode === 'timeGridDay'
        ? { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' }
        : { month: 'short', day: 'numeric' }

  const formatter = new Intl.DateTimeFormat(locale.value, formatterOptions)

  if (calendar.viewMode === 'timeGridWeek') {
    const { start, end } = calendar.currentRange
    return `${formatter.format(start)} – ${formatter.format(end)}, ${end.getFullYear()}`
  }

  return formatter.format(calendar.currentDate)
})

const dentistName = (dentistId: string) => providers.items.find((user) => user.id === dentistId)?.name

function toCalendarEvent(appointment: Appointment): AppointmentCalendarEvent {
  const color = appointment.appointment_type?.color ?? APPOINTMENT_STATUS_COLORS[appointment.status]
  const classNames =
    appointment.status === 'cancelled'
      ? ['fc-event-cancelled']
      : appointment.status === 'no_show'
        ? ['fc-event-no-show']
        : []

  return {
    id: appointment.id,
    title: appointment.patient?.full_name ?? t('appointments.untitled'),
    start: appointment.start_at,
    end: appointment.end_at,
    backgroundColor: color,
    borderColor: color,
    classNames,
    extendedProps: {
      status: appointment.status,
      dentistName: appointment.dentist?.name ?? dentistName(appointment.dentist_id),
      hasNotes: Boolean(appointment.notes),
    },
  }
}

const events = computed(() => appointments.filteredAppointments.map(toCalendarEvent))

const backgroundEvents = computed<AppointmentCalendarEvent[]>(() => {
  const dentistId = singleSelectedDentistId.value
  if (!dentistId) return []

  return (timeOff.byDentist.get(dentistId) ?? []).map((entry) => ({
    id: `timeoff-${entry.id}`,
    title: entry.reason ?? '',
    start: entry.start_at,
    end: entry.end_at,
    backgroundColor: 'rgba(239, 68, 68, 0.18)',
    extendedProps: { hasNotes: false },
  }))
})

function timeToMinutes(time: string): number {
  const [hours, minutes] = time.split(':').map(Number)
  return hours * 60 + minutes
}

function minutesToTime(minutes: number): string {
  const clamped = Math.max(0, Math.min(24 * 60, minutes))
  const hours = String(Math.floor(clamped / 60)).padStart(2, '0')
  const mins = String(clamped % 60).padStart(2, '0')
  return `${hours}:${mins}:00`
}

const slotBounds = computed(() => {
  const dentistId = singleSelectedDentistId.value
  const shifts = dentistId ? (workingHours.byDentist.get(dentistId) ?? []).filter((row) => row.is_active) : []

  if (!shifts.length) {
    return { min: DEFAULT_SLOT_MIN_TIME, max: DEFAULT_SLOT_MAX_TIME }
  }

  const earliest = Math.min(...shifts.map((row) => timeToMinutes(row.start_time)))
  const latest = Math.max(...shifts.map((row) => timeToMinutes(row.end_time)))

  return {
    min: minutesToTime(earliest - SLOT_PADDING_MINUTES),
    max: minutesToTime(latest + SLOT_PADDING_MINUTES),
  }
})

const businessHours = computed(() => {
  const dentistId = singleSelectedDentistId.value
  if (!dentistId) return undefined

  const shifts = (workingHours.byDentist.get(dentistId) ?? []).filter((row) => row.is_active)
  if (!shifts.length) return undefined

  return shifts.map((row) => ({
    daysOfWeek: [row.day_of_week],
    startTime: row.start_time,
    endTime: row.end_time,
  }))
})

function onFiltersChange(next: CalendarFiltersState) {
  ;(Object.keys(next) as (keyof CalendarFiltersState)[]).forEach((key) => {
    if (calendar.filters[key] !== next[key]) calendar.setFilter(key, next[key])
  })
}

function onNavigate(direction: 'prev' | 'next' | 'today') {
  if (direction === 'prev') calendar.goPrev()
  else if (direction === 'next') calendar.goNext()
  else calendar.goToday()
}

function onRangeChange(range: { start: Date; end: Date }) {
  appointments.fetchRange(range.start, range.end)
}

function onEventClick(appointmentId: string) {
  router.push({ name: 'appointment-detail', params: { id: appointmentId } })
}

function showCreationComingSoon() {
  toast.add({ severity: 'info', summary: t('appointments.calendar.newAppointmentComingSoon'), life: 4000 })
}

onMounted(() => {
  providers.fetchAll()
  appointments.fetchRange(calendar.currentRange.start, calendar.currentRange.end)
})
</script>

<template>
  <div class="flex flex-col gap-4">
    <h1 class="text-2xl font-semibold text-surface-900 dark:text-surface-0">{{ t('appointments.title') }}</h1>

    <CalendarFilters :model-value="calendar.filters" @update:model-value="onFiltersChange" />

    <Card>
      <template #content>
        <div class="flex flex-col gap-4">
          <CalendarToolbar
            v-model:view-mode="boardViewMode"
            :range-label="rangeLabel"
            @navigate="onNavigate"
            @new-appointment="showCreationComingSoon"
          />
          <AppointmentCalendar
            :view="boardViewMode"
            :current-date="calendar.currentDate"
            :events="events"
            :background-events="backgroundEvents"
            :business-hours="businessHours"
            :slot-min-time="slotBounds.min"
            :slot-max-time="slotBounds.max"
            :loading="appointments.loading"
            @event-click="onEventClick"
            @date-click="showCreationComingSoon"
            @range-change="onRangeChange"
          />
        </div>
      </template>
    </Card>
  </div>
</template>
