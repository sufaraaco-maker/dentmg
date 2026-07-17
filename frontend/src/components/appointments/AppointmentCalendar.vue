<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import FullCalendar from '@fullcalendar/vue3'
import dayGridPlugin from '@fullcalendar/daygrid'
import timeGridPlugin from '@fullcalendar/timegrid'
import interactionPlugin from '@fullcalendar/interaction'
import type { CalendarOptions, EventClickArg, EventContentArg, DatesSetArg } from '@fullcalendar/core'
import type { DateClickArg } from '@fullcalendar/interaction'
import ProgressBar from 'primevue/progressbar'
import AppointmentEventContent from './AppointmentEventContent.vue'
import type { AppointmentEventData } from './AppointmentEventContent.vue'
import { RTL_LOCALES, type SupportedLocale } from '@/locales'
import { toCalendarUtcDate } from '@/lib/date'
import type { AppointmentStatus } from '@/types/appointment'

/**
 * Genuinely reusable, decoupled FullCalendar wrapper (docs/modules/appointments-ui-design.md §2.12) —
 * fetches nothing itself, mutates nothing itself. Day/Week/Month only for now; the Dentists
 * (resource-column) view is on hold pending a FullCalendar Premium licensing decision (§20 item 8),
 * so no `@fullcalendar/resource*` plugin or `resources` prop exists here yet — adding it later is a
 * small additive change (new prop + plugin import), not a rework of this component's contract.
 */
export interface AppointmentCalendarEvent {
  id: string
  title: string
  start: string
  end: string
  backgroundColor?: string
  borderColor?: string
  classNames?: string[]
  /** `status` is absent for non-appointment events (e.g. the time-off background overlay) —
   * FullCalendar doesn't invoke `eventContent` for `display: 'background'` events, so
   * `toEventData` below never actually needs the fallback, but the type stays honest either way. */
  extendedProps: {
    status?: AppointmentStatus
    dentistName?: string
    hasNotes: boolean
  }
}

const props = defineProps<{
  view: 'timeGridDay' | 'timeGridWeek' | 'dayGridMonth'
  currentDate: Date
  events: AppointmentCalendarEvent[]
  backgroundEvents?: AppointmentCalendarEvent[]
  businessHours?: CalendarOptions['businessHours']
  slotMinTime: string
  slotMaxTime: string
  loading: boolean
}>()

const emit = defineEmits<{
  (e: 'event-click', appointmentId: string): void
  (e: 'date-click', payload: { start: Date }): void
  (e: 'range-change', payload: { start: Date; end: Date }): void
}>()

const { locale } = useI18n()
const isRtl = computed(() => RTL_LOCALES.includes(locale.value as SupportedLocale))

const calendarOptions = computed<CalendarOptions>(() => ({
  plugins: [dayGridPlugin, timeGridPlugin, interactionPlugin],
  initialView: props.view,
  headerToolbar: false,
  direction: isRtl.value ? 'rtl' : 'ltr',
  // This is a single-clinic system with no real per-request timezone conversion (backend
  // `start_at`/`end_at` digits ARE the clinic's own wall-clock time, just serialized with a
  // "+00:00" suffix — see lib/date.ts's `parseServerDateTime`). Without this, FullCalendar's
  // default `timeZone: 'local'` re-expresses those UTC-labeled digits through the browser's
  // own offset, silently rendering every event at the wrong grid slot whenever a user's
  // timezone isn't UTC+0 (confirmed directly: a 10:00 appointment rendered at 1:00 PM in this
  // module's real-browser verification). `dateClick`'s emitted Date must be read via its UTC
  // getters now, not local ones — see AppointmentsView.vue's `openSlotDialog`.
  timeZone: 'UTC',
  // `props.currentDate` is a genuinely local Date (`calendar.ts`) — FullCalendar, now in
  // `timeZone: 'UTC'` mode, reads `initialDate`/`gotoDate()` via UTC getters, so without this
  // conversion it lands one day off for any positive-UTC-offset browser (confirmed directly:
  // clicking "Today" showed Thursday instead of Friday). See `lib/date.ts`'s `toCalendarUtcDate`.
  initialDate: toCalendarUtcDate(props.currentDate),
  events: [
    ...props.events,
    ...(props.backgroundEvents ?? []).map((event) => ({ ...event, display: 'background' as const })),
  ],
  businessHours: props.businessHours,
  slotMinTime: props.slotMinTime,
  slotMaxTime: props.slotMaxTime,
  slotDuration: '00:15:00',
  nowIndicator: props.view !== 'dayGridMonth',
  height: 'auto',
  dayMaxEvents: true,
  eventClick: (arg: EventClickArg) => emit('event-click', arg.event.id),
  dateClick: (arg: DateClickArg) => emit('date-click', { start: arg.date }),
  datesSet: (arg: DatesSetArg) => emit('range-change', { start: arg.start, end: arg.end }),
}))

const calendarRef = ref<InstanceType<typeof FullCalendar>>()

// `initialView`/`initialDate` are exactly that — respected only when FullCalendar first
// initializes. Switching the toolbar's view/date afterward needs the imperative Calendar API;
// re-passing a new `initialView`/`initialDate` through reactive `options` has no effect.
watch(
  () => props.view,
  (view) => calendarRef.value?.getApi().changeView(view),
)
watch(
  () => props.currentDate,
  (date) => calendarRef.value?.getApi().gotoDate(toCalendarUtcDate(date)),
)

function toEventData(arg: EventContentArg): AppointmentEventData {
  return {
    id: arg.event.id,
    patientName: arg.event.title,
    dentistName: arg.event.extendedProps.dentistName as string | undefined,
    status: (arg.event.extendedProps.status as AppointmentStatus | undefined) ?? 'scheduled',
    timeText: arg.timeText,
    hasNotes: Boolean(arg.event.extendedProps.hasNotes),
  }
}
</script>

<template>
  <div class="relative">
    <div v-if="loading" class="absolute inset-x-0 top-0 z-10">
      <ProgressBar mode="indeterminate" style="height: 3px" />
    </div>
    <FullCalendar ref="calendarRef" :options="calendarOptions">
      <template #eventContent="arg">
        <AppointmentEventContent :event="toEventData(arg)" @activate="emit('event-click', arg.event.id)" />
      </template>
    </FullCalendar>
  </div>
</template>
