<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import IconField from 'primevue/iconfield'
import InputIcon from 'primevue/inputicon'
import InputText from 'primevue/inputtext'
import Button from 'primevue/button'
import ProgressSpinner from 'primevue/progressspinner'
import { api } from '@/lib/api'
import PatientSummaryCard from './PatientSummaryCard.vue'
import PatientFormDialog from '@/components/patients/PatientFormDialog.vue'
import type { Patient } from '@/types/patient'
import type { AppointmentPatientSummary } from '@/types/appointment'

/**
 * Debounced (300ms, matching PatientsView) typeahead against `GET /patients?search=` plus
 * inline "Create New Patient" (opens the existing `PatientFormDialog`, not a duplicate form —
 * design doc §3.2/§3.3). Locked to a read-only summary in edit mode via `disabled`, since
 * `patient_id` isn't editable on an existing appointment (§3.1).
 */
const props = defineProps<{
  modelValue: string | null
  /** The already-known patient object to show without a fresh search — edit mode only has the
   *  partial `AppointmentPatientSummary` nested on the appointment; create-mode prefill has none. */
  selectedPatient?: Patient | AppointmentPatientSummary | null
  disabled?: boolean
}>()

const emit = defineEmits<{
  'update:modelValue': [value: string | null]
  'patient-selected': [patient: Patient]
}>()

const { t } = useI18n()

const query = ref('')
const results = ref<Patient[]>([])
const searching = ref(false)
const showDropdown = ref(false)
const createDialogVisible = ref(false)
const pickedPatient = ref<Patient | null>(null)

const displayPatient = computed<Patient | AppointmentPatientSummary | null>(
  () => pickedPatient.value ?? props.selectedPatient ?? null,
)

let searchTimeout: ReturnType<typeof setTimeout>

watch(query, (value) => {
  clearTimeout(searchTimeout)

  if (!value.trim()) {
    results.value = []
    showDropdown.value = false
    return
  }

  searchTimeout = setTimeout(async () => {
    searching.value = true

    try {
      const { data } = await api.get<{ data: Patient[] }>('/patients', { params: { search: value } })
      results.value = data.data
      showDropdown.value = true
    } finally {
      searching.value = false
    }
  }, 300)
})

watch(
  () => props.modelValue,
  (id) => {
    if (!id) pickedPatient.value = null
  },
)

function selectPatient(patient: Patient) {
  pickedPatient.value = patient
  query.value = ''
  results.value = []
  showDropdown.value = false
  emit('update:modelValue', patient.id)
  emit('patient-selected', patient)
}

function clearSelection() {
  pickedPatient.value = null
  emit('update:modelValue', null)
}

function onPatientCreated(patient: Patient) {
  createDialogVisible.value = false
  selectPatient(patient)
}
</script>

<template>
  <div class="flex flex-col gap-2">
    <div v-if="modelValue" class="flex flex-col gap-2">
      <PatientSummaryCard v-if="displayPatient" :patient="displayPatient" />
      <Button
        v-if="!disabled"
        type="button"
        :label="t('appointments.dialog.changePatient')"
        text
        size="small"
        icon="pi pi-times"
        class="self-start"
        @click="clearSelection"
      />
    </div>

    <div v-else class="flex flex-col gap-2">
      <IconField>
        <InputIcon class="pi pi-search" />
        <InputText
          v-model="query"
          :placeholder="t('appointments.dialog.patientSearchPlaceholder')"
          :disabled="disabled"
          class="w-full"
          @focus="showDropdown = results.length > 0"
        />
      </IconField>

      <div v-if="searching" class="flex items-center justify-center py-2">
        <ProgressSpinner style="width: 1.5rem; height: 1.5rem" stroke-width="6" />
      </div>

      <ul
        v-else-if="showDropdown && results.length"
        class="flex max-h-56 flex-col overflow-y-auto rounded-lg border border-surface-200 dark:border-surface-700"
      >
        <li
          v-for="patient in results"
          :key="patient.id"
          tabindex="0"
          class="cursor-pointer border-b border-surface-100 px-3 py-2 last:border-b-0 hover:bg-surface-50 focus:bg-surface-50 dark:border-surface-800 dark:hover:bg-surface-800 dark:focus:bg-surface-800"
          @click="selectPatient(patient)"
          @keydown.enter="selectPatient(patient)"
        >
          <span class="font-medium text-surface-900 dark:text-surface-0">{{ patient.full_name }}</span>
          <span class="text-sm text-surface-500 dark:text-surface-400">
            · {{ patient.patient_code }} · {{ patient.phone }}
          </span>
        </li>
      </ul>

      <p v-else-if="showDropdown && !results.length" class="text-sm text-surface-500 dark:text-surface-400">
        {{ t('appointments.dialog.noPatientsFound') }}
      </p>

      <Button
        type="button"
        :label="t('appointments.dialog.createPatient')"
        icon="pi pi-user-plus"
        text
        size="small"
        class="self-start"
        :disabled="disabled"
        @click="createDialogVisible = true"
      />
    </div>

    <PatientFormDialog v-model:visible="createDialogVisible" :patient="null" @saved="onPatientCreated" />
  </div>
</template>
