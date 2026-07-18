<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import Button from 'primevue/button'
import SelectButton from 'primevue/selectbutton'
import { RTL_LOCALES, type SupportedLocale } from '@/locales'
import type { CalendarViewMode } from '@/types/appointment'

/** Day/Week/Month/List — Dentists (resource) view is deferred, see docs/decisions.md 2026-07-16. */
export type BoardViewMode = Extract<
  CalendarViewMode,
  'timeGridDay' | 'timeGridWeek' | 'dayGridMonth' | 'list'
>

defineProps<{
  viewMode: BoardViewMode
  rangeLabel: string
}>()

const emit = defineEmits<{
  (e: 'update:viewMode', mode: BoardViewMode): void
  (e: 'navigate', direction: 'prev' | 'next' | 'today'): void
  (e: 'new-appointment'): void
}>()

const { t, locale } = useI18n()

// AppointmentCalendar mirrors the day grid itself for RTL (Saturday...Sunday, right to left), so
// "forward in time" already reads leftward there — these chevrons must point the same way or
// "next" would visually contradict the grid's own direction.
const isRtl = computed(() => RTL_LOCALES.includes(locale.value as SupportedLocale))

const viewOptions = computed(() => [
  { label: t('appointments.calendar.day'), value: 'timeGridDay' as BoardViewMode },
  { label: t('appointments.calendar.week'), value: 'timeGridWeek' as BoardViewMode },
  { label: t('appointments.calendar.month'), value: 'dayGridMonth' as BoardViewMode },
  { label: t('appointments.calendar.list'), value: 'list' as BoardViewMode },
])
</script>

<template>
  <div class="flex flex-wrap items-center justify-between gap-3">
    <div class="flex items-center gap-2">
      <Button
        :icon="isRtl ? 'pi pi-chevron-right' : 'pi pi-chevron-left'"
        text
        :aria-label="t('appointments.calendar.previous')"
        @click="emit('navigate', 'prev')"
      />
      <Button :label="t('appointments.calendar.today')" text @click="emit('navigate', 'today')" />
      <Button
        :icon="isRtl ? 'pi pi-chevron-left' : 'pi pi-chevron-right'"
        text
        :aria-label="t('appointments.calendar.next')"
        @click="emit('navigate', 'next')"
      />
      <span class="text-lg font-medium text-surface-900 dark:text-surface-0">{{ rangeLabel }}</span>
    </div>

    <div class="flex flex-wrap items-center gap-3">
      <SelectButton
        :model-value="viewMode"
        :options="viewOptions"
        option-label="label"
        option-value="value"
        :allow-empty="false"
        @update:model-value="emit('update:viewMode', $event)"
      />
      <Button
        :label="t('appointments.calendar.newAppointment')"
        icon="pi pi-plus"
        @click="emit('new-appointment')"
      />
    </div>
  </div>
</template>
