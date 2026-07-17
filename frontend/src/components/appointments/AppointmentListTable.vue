<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import AppointmentStatusChip from './AppointmentStatusChip.vue'
import { parseServerDateTime } from '@/lib/date'
import type { Appointment } from '@/types/appointment'

/**
 * Client-paginated DataTable list view (docs/modules/appointments-ui-design.md §2.9/§9) — the one
 * place the existing Patients/Users lazy-server-paginated DataTable pattern doesn't apply verbatim,
 * since GET /api/appointments has no server-side pagination to hook into (deliberate backend design).
 * Renders whatever range is already fetched/filtered by the parent — fetches nothing itself.
 */
defineProps<{
  appointments: Appointment[]
  loading: boolean
}>()

const emit = defineEmits<{ (e: 'row-click', appointmentId: string): void }>()

const { t, locale } = useI18n()

function formatDateTime(startAt: string): string {
  // `startAt` is the backend's naive-digits-labeled timestamp (lib/date.ts) — must be read via
  // `parseServerDateTime`, not a raw `new Date(...)`, or this column displays the wrong time in
  // a browser whose OS timezone isn't UTC.
  return new Intl.DateTimeFormat(locale.value, { dateStyle: 'medium', timeStyle: 'short' }).format(
    parseServerDateTime(startAt),
  )
}
</script>

<template>
  <DataTable
    :value="appointments"
    :loading="loading"
    data-key="id"
    paginator
    :rows="20"
    sort-field="start_at"
    :sort-order="1"
    class="cursor-pointer"
    @row-click="({ data }) => emit('row-click', data.id)"
  >
    <template #empty>{{ t('appointments.list.empty') }}</template>

    <Column field="start_at" :header="t('appointments.list.dateTime')" sortable>
      <template #body="{ data }">{{ formatDateTime(data.start_at) }}</template>
    </Column>

    <Column :header="t('appointments.list.patient')">
      <template #body="{ data }">{{ data.patient?.full_name ?? t('appointments.untitled') }}</template>
    </Column>

    <Column :header="t('appointments.list.dentist')">
      <template #body="{ data }">{{ data.dentist?.name ?? '—' }}</template>
    </Column>

    <Column :header="t('appointments.list.type')">
      <template #body="{ data }">
        <div class="flex items-center gap-2">
          <span
            v-if="data.appointment_type?.color"
            class="inline-block h-2.5 w-2.5 shrink-0 rounded-full"
            :style="{ backgroundColor: data.appointment_type.color }"
          />
          <span>{{ data.appointment_type?.name ?? '—' }}</span>
        </div>
      </template>
    </Column>

    <Column :header="t('appointments.list.duration')">
      <template #body="{ data }">
        {{ t('appointments.list.durationMinutes', { n: data.duration_minutes }) }}
      </template>
    </Column>

    <Column :header="t('appointments.list.status')">
      <template #body="{ data }">
        <AppointmentStatusChip :status="data.status" size="small" />
      </template>
    </Column>
  </DataTable>
</template>
