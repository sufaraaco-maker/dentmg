<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { parseLocalDate } from '@/lib/date'
import type { Patient } from '@/types/patient'
import type { AppointmentPatientSummary } from '@/types/appointment'

/**
 * Compact reusable patient summary (name/code/phone/age). Reused by `PatientSearchSelect`
 * (full `Patient` search results, which have phone/date_of_birth) and by `AppointmentDialog`'s
 * edit mode (only the `AppointmentPatientSummary` shape nested on the appointment, which
 * doesn't carry phone/date_of_birth) — see design doc §3.2/§4.4.
 */
const props = defineProps<{ patient: Patient | AppointmentPatientSummary }>()

const { t } = useI18n()

function hasPhone(patient: Patient | AppointmentPatientSummary): patient is Patient {
  return 'phone' in patient
}

const phone = computed(() => (hasPhone(props.patient) ? props.patient.phone : null))

const age = computed(() => {
  if (!hasPhone(props.patient)) return null

  const birth = parseLocalDate(props.patient.date_of_birth)
  const now = new Date()
  let years = now.getFullYear() - birth.getFullYear()
  const hadBirthdayThisYear =
    now.getMonth() > birth.getMonth() ||
    (now.getMonth() === birth.getMonth() && now.getDate() >= birth.getDate())

  if (!hadBirthdayThisYear) years -= 1

  return years
})
</script>

<template>
  <div class="flex items-center gap-3 rounded-lg border border-surface-200 p-3 dark:border-surface-700">
    <div
      class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-primary-100 text-primary-700 dark:bg-primary-900/40 dark:text-primary-300"
    >
      <i class="pi pi-user" />
    </div>
    <div class="flex min-w-0 flex-col">
      <span class="truncate font-medium text-surface-900 dark:text-surface-0">{{ patient.full_name }}</span>
      <span class="truncate text-sm text-surface-600 dark:text-surface-300">
        {{ patient.patient_code }}
        <template v-if="phone"> · {{ phone }}</template>
        <template v-if="age !== null"> · {{ t('appointments.dialog.ageYears', { n: age }) }}</template>
      </span>
    </div>
  </div>
</template>
