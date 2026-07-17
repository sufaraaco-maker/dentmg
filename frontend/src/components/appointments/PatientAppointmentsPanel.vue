<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import Card from 'primevue/card'
import Button from 'primevue/button'
import AppointmentListTable from './AppointmentListTable.vue'
import AppointmentDialog from './AppointmentDialog.vue'
import { useAppointmentsStore } from '@/stores/appointments'
import { useAuthStore } from '@/stores/auth'
import { parseServerDateTime } from '@/lib/date'
import type { Appointment } from '@/types/appointment'
import type { Patient } from '@/types/patient'

/**
 * Patient Detail's Appointments panel (design doc §9) — reuses `AppointmentListTable` (read) and
 * `AppointmentDialog` (create, prefilled with this patient), added as another stacked `Card`
 * section on `PatientDetailView` (matching its existing Demographics/Contact/.../History
 * convention) rather than introducing a new Tabs layout to a frozen design system.
 *
 * Fetches via `appointments.ts`'s shared range cache (`fetchRange`, unfiltered — same technique
 * `TimeOffFormDialog` already uses to read appointments for a specific dentist), filtered
 * client-side to this patient. A `patient_id`-filtered API call was deliberately avoided: it
 * would still mark the full date range "loaded" in the shared cache while only ever having
 * stored one patient's rows in it, silently hiding every other patient's appointments from the
 * Board/Dashboard the next time they render that same range (design doc §13).
 *
 * Range is bounded to +/- a few months (not unbounded "full history") — a deliberate, documented
 * scope choice to keep this an ordinary shared-cache range fetch rather than an unbounded
 * clinic-wide query; see TECH_DEBT.md if a genuine full-history view is ever requested.
 */
const props = defineProps<{
  patientId: string
  /** Optional — when the caller already has the full record (PatientDetailView does), passing it
   *  lets the "New Appointment" dialog show a patient summary immediately, no extra fetch. */
  patient?: Patient | null
}>()

const RANGE_PAST_MONTHS = 3
const RANGE_FUTURE_MONTHS = 6

const { t } = useI18n()
const router = useRouter()
const appointmentsStore = useAppointmentsStore()
const auth = useAuthStore()

const loading = ref(true)
const dialogVisible = ref(false)

function panelRange(): { start: Date; end: Date } {
  const now = new Date()
  const start = new Date(now.getFullYear(), now.getMonth() - RANGE_PAST_MONTHS, now.getDate())
  const end = new Date(now.getFullYear(), now.getMonth() + RANGE_FUTURE_MONTHS, now.getDate())
  return { start, end }
}

async function load() {
  loading.value = true
  try {
    const { start, end } = panelRange()
    await appointmentsStore.fetchRange(start, end)
  } finally {
    loading.value = false
  }
}

const patientAppointments = computed<Appointment[]>(() => {
  const { start, end } = panelRange()
  const startTime = start.getTime()
  const endTime = end.getTime()

  return Array.from(appointmentsStore.cache.values()).filter((appointment) => {
    if (appointment.patient_id !== props.patientId) return false
    const startAt = parseServerDateTime(appointment.start_at).getTime()
    return startAt >= startTime && startAt <= endTime
  })
})

function goToDetail(id: string) {
  router.push({ name: 'appointment-detail', params: { id } })
}

function onSaved() {
  // No refetch needed: `appointmentsStore.create()` already upserts the new appointment into the
  // same shared reactive cache `patientAppointments` reads from — an explicit reload here would
  // be a wasted request (and, for an already-loaded range, `fetchRange` would just skip it
  // anyway, per its own cache-hit check).
  dialogVisible.value = false
}

watch(() => props.patientId, load, { immediate: true })
</script>

<template>
  <Card v-bind="$attrs">
    <template #title>
      <div class="flex items-center justify-between">
        <span>{{ t('patients.appointmentsPanel.title') }}</span>
        <Button
          v-if="auth.canManageAppointments"
          :label="t('patients.appointmentsPanel.new')"
          icon="pi pi-plus"
          size="small"
          @click="dialogVisible = true"
        />
      </div>
    </template>
    <template #content>
      <p v-if="!loading && !patientAppointments.length" class="text-sm text-surface-500">
        {{ t('patients.appointmentsPanel.empty') }}
      </p>
      <AppointmentListTable
        v-else
        :appointments="patientAppointments"
        :loading="loading"
        @row-click="goToDetail"
      />
    </template>
  </Card>

  <AppointmentDialog
    v-model:visible="dialogVisible"
    :prefill="{ patient_id: patientId, patient: patient ?? undefined }"
    @saved="onSaved"
  />
</template>
