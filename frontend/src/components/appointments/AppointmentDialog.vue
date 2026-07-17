<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from 'primevue/usetoast'
import Dialog from 'primevue/dialog'
import Textarea from 'primevue/textarea'
import DatePicker from 'primevue/datepicker'
import ToggleSwitch from 'primevue/toggleswitch'
import Button from 'primevue/button'
import Message from 'primevue/message'
import Tabs from 'primevue/tabs'
import TabList from 'primevue/tablist'
import Tab from 'primevue/tab'
import TabPanels from 'primevue/tabpanels'
import TabPanel from 'primevue/tabpanel'
import PatientSearchSelect from './PatientSearchSelect.vue'
import DentistSelect from './DentistSelect.vue'
import AppointmentTypeSelect from './AppointmentTypeSelect.vue'
import DurationInput from './DurationInput.vue'
import SlotPicker from './SlotPicker.vue'
import ConflictAlert from './ConflictAlert.vue'
import AppointmentStatusChip from './AppointmentStatusChip.vue'
import { useAppointmentsStore } from '@/stores/appointments'
import { isAppointmentConflictError } from '@/services/appointments'
import { parseServerDateTime, toLocalDateTimeString } from '@/lib/date'
import type {
  Appointment,
  AppointmentConflictError,
  AppointmentPatientSummary,
  AppointmentPrefill,
  AppointmentType,
} from '@/types/appointment'
import type { Patient } from '@/types/patient'

const REASON_MAX = 1000
const NOTES_MAX = 2000

/**
 * Create/Edit dialog — Patient / Appointment / Notes tabs (design doc §3). Reused from the
 * Board (this step), and later the List/Patient-Detail/Dashboard call sites once they exist.
 */
const props = defineProps<{
  visible: boolean
  appointment?: Appointment | null
  prefill?: AppointmentPrefill | null
}>()

const emit = defineEmits<{
  'update:visible': [value: boolean]
  saved: [appointment: Appointment]
}>()

const { t, locale } = useI18n()
const toast = useToast()
const appointmentsStore = useAppointmentsStore()

const activeTab = ref('patient')
const saving = ref(false)
const errors = ref<Record<string, string[]>>({})
const conflictError = ref<AppointmentConflictError | null>(null)
const durationTouched = ref(false)
const showSlotPicker = ref(false)
const selectedPatientSummary = ref<Patient | AppointmentPatientSummary | null>(null)

const isEditMode = computed(() => Boolean(props.appointment))

function emptyForm() {
  return {
    patient_id: null as string | null,
    dentist_id: null as string | null,
    appointment_type_id: null as string | null,
    start_at: null as Date | null,
    duration_minutes: 30,
    reason: '',
    notes: '',
  }
}

const form = reactive(emptyForm())

watch(
  () => props.visible,
  (visible) => {
    if (!visible) return

    errors.value = {}
    conflictError.value = null
    showSlotPicker.value = false
    activeTab.value = 'patient'

    const source = props.appointment

    if (source) {
      Object.assign(form, {
        patient_id: source.patient_id,
        dentist_id: source.dentist_id,
        appointment_type_id: source.appointment_type_id,
        start_at: parseServerDateTime(source.start_at),
        duration_minutes: source.duration_minutes,
        reason: source.reason ?? '',
        notes: source.notes ?? '',
      })
      selectedPatientSummary.value = source.patient ?? null
      // An existing duration is a deliberate value — never silently overwritten by a type change.
      durationTouched.value = true
    } else {
      Object.assign(form, emptyForm())
      selectedPatientSummary.value = null
      durationTouched.value = false

      if (props.prefill?.dentist_id) form.dentist_id = props.prefill.dentist_id
      if (props.prefill?.start_at) form.start_at = parseServerDateTime(props.prefill.start_at)
      if (props.prefill?.patient_id) form.patient_id = props.prefill.patient_id
    }
  },
  // Also initializes correctly if a parent ever mounts this dialog already-visible (e.g. a
  // future deep-link flow), not just on the false→true transition.
  { immediate: true },
)

watch(
  () => form.patient_id,
  (id) => {
    if (!id) selectedPatientSummary.value = null
  },
)

function onPatientSelected(patient: Patient) {
  selectedPatientSummary.value = patient
}

function onTypeSelected(type: AppointmentType | undefined) {
  if (type && !durationTouched.value) {
    form.duration_minutes = type.default_duration_minutes
  }
}

function onDurationChange(value: number) {
  form.duration_minutes = value
  durationTouched.value = true
}

const endAt = computed(() => {
  if (!form.start_at || !form.duration_minutes) return null

  const end = new Date(form.start_at)
  end.setMinutes(end.getMinutes() + form.duration_minutes)
  return end
})

const formattedEndAt = computed(() =>
  endAt.value ? endAt.value.toLocaleTimeString(locale.value, { hour: '2-digit', minute: '2-digit' }) : null,
)

const slotPickerDate = computed(() => form.start_at ?? new Date())

function onSlotSelected(start: Date) {
  form.start_at = start
}

const reasonRemaining = computed(() => REASON_MAX - form.reason.length)
const notesRemaining = computed(() => NOTES_MAX - form.notes.length)
const showReasonCounter = computed(() => form.reason.length > REASON_MAX * 0.8)
const showNotesCounter = computed(() => form.notes.length > NOTES_MAX * 0.8)

function validate(): boolean {
  const nextErrors: Record<string, string[]> = {}

  if (!form.patient_id) nextErrors.patient_id = [t('appointments.dialog.fieldRequired')]
  if (!form.dentist_id) nextErrors.dentist_id = [t('appointments.dialog.fieldRequired')]
  if (!form.appointment_type_id) nextErrors.appointment_type_id = [t('appointments.dialog.fieldRequired')]
  if (!form.start_at) nextErrors.start_at = [t('appointments.dialog.fieldRequired')]
  if (!form.duration_minutes || form.duration_minutes < 5 || form.duration_minutes > 480) {
    nextErrors.duration_minutes = [t('appointments.dialog.durationRange')]
  }

  errors.value = nextErrors
  return Object.keys(nextErrors).length === 0
}

function firstErrorTab(): string {
  if (errors.value.patient_id) return 'patient'
  if (
    errors.value.dentist_id ||
    errors.value.appointment_type_id ||
    errors.value.start_at ||
    errors.value.duration_minutes
  ) {
    return 'appointment'
  }
  return activeTab.value
}

async function submit(overridePayload: Record<string, boolean> = {}) {
  if (!validate()) {
    activeTab.value = firstErrorTab()
    return
  }

  saving.value = true
  errors.value = {}
  conflictError.value = null

  try {
    const basePayload = {
      dentist_id: form.dentist_id!,
      appointment_type_id: form.appointment_type_id!,
      start_at: toLocalDateTimeString(form.start_at!),
      duration_minutes: form.duration_minutes,
      reason: form.reason || null,
      notes: form.notes || null,
      ...overridePayload,
    }

    const saved = isEditMode.value
      ? await appointmentsStore.update(props.appointment!.id, basePayload)
      : await appointmentsStore.create({ ...basePayload, patient_id: form.patient_id! })

    toast.add({ severity: 'success', summary: t('appointments.saved'), life: 3000 })
    emit('saved', saved)
    emit('update:visible', false)
  } catch (err: unknown) {
    if (isAppointmentConflictError(err)) {
      conflictError.value = err
      activeTab.value = 'appointment'
    } else if ((err as { response?: { status?: number } })?.response?.status === 422) {
      errors.value =
        (err as { response: { data: { errors?: Record<string, string[]> } } }).response.data.errors ?? {}
    } else {
      toast.add({ severity: 'error', summary: t('appointments.saveError'), life: 3000 })
    }
  } finally {
    saving.value = false
  }
}

function onOverride(field: string) {
  submit({ [field]: true })
}
</script>

<template>
  <Dialog
    :visible="visible"
    modal
    :header="isEditMode ? t('appointments.dialog.editTitle') : t('appointments.dialog.createTitle')"
    class="w-full max-w-2xl"
    @update:visible="(v) => emit('update:visible', v)"
  >
    <form class="flex flex-col gap-4" @submit.prevent="submit()">
      <ConflictAlert :error="conflictError" @override="onOverride" />

      <Tabs v-model:value="activeTab">
        <TabList>
          <Tab value="patient">{{ t('appointments.dialog.tabs.patient') }}</Tab>
          <Tab value="appointment">{{ t('appointments.dialog.tabs.appointment') }}</Tab>
          <Tab value="notes">{{ t('appointments.dialog.tabs.notes') }}</Tab>
        </TabList>
        <TabPanels>
          <TabPanel value="patient">
            <div class="flex flex-col gap-2 pt-2">
              <PatientSearchSelect
                v-model="form.patient_id"
                :selected-patient="selectedPatientSummary"
                :disabled="isEditMode"
                @patient-selected="onPatientSelected"
              />
              <Message v-if="errors.patient_id" severity="error" size="small">{{
                errors.patient_id[0]
              }}</Message>
              <p v-if="isEditMode" class="text-sm text-surface-500 dark:text-surface-400">
                {{ t('appointments.dialog.patientLockedNote') }}
              </p>
            </div>
          </TabPanel>

          <TabPanel value="appointment">
            <div class="grid grid-cols-1 gap-4 pt-2 sm:grid-cols-2">
              <div class="flex flex-col gap-2">
                <label class="text-sm text-surface-700 dark:text-surface-200">
                  {{ t('appointments.dialog.dentist') }}
                </label>
                <DentistSelect v-model="form.dentist_id" :invalid="!!errors.dentist_id" />
                <Message v-if="errors.dentist_id" severity="error" size="small">{{
                  errors.dentist_id[0]
                }}</Message>
              </div>

              <div class="flex flex-col gap-2">
                <label class="text-sm text-surface-700 dark:text-surface-200">
                  {{ t('appointments.dialog.type') }}
                </label>
                <AppointmentTypeSelect
                  v-model="form.appointment_type_id"
                  :invalid="!!errors.appointment_type_id"
                  @type-selected="onTypeSelected"
                />
                <Message v-if="errors.appointment_type_id" severity="error" size="small">
                  {{ errors.appointment_type_id[0] }}
                </Message>
              </div>

              <div class="flex flex-col gap-2">
                <label class="text-sm text-surface-700 dark:text-surface-200">
                  {{ t('appointments.dialog.startAt') }}
                </label>
                <DatePicker
                  v-model="form.start_at"
                  show-time
                  hour-format="24"
                  date-format="yy-mm-dd"
                  show-icon
                  fluid
                  :invalid="!!errors.start_at"
                />
                <Message v-if="errors.start_at" severity="error" size="small">{{
                  errors.start_at[0]
                }}</Message>
                <p v-if="formattedEndAt" class="text-sm text-surface-500 dark:text-surface-400">
                  {{ t('appointments.dialog.endAtPreview', { time: formattedEndAt }) }}
                </p>
              </div>

              <div class="flex flex-col gap-2">
                <label class="text-sm text-surface-700 dark:text-surface-200">
                  {{ t('appointments.dialog.duration') }}
                </label>
                <DurationInput
                  :model-value="form.duration_minutes"
                  :invalid="!!errors.duration_minutes"
                  @update:model-value="onDurationChange"
                />
                <Message v-if="errors.duration_minutes" severity="error" size="small">
                  {{ errors.duration_minutes[0] }}
                </Message>
              </div>

              <div class="flex flex-col gap-2 sm:col-span-2">
                <label
                  class="flex items-center gap-2"
                  :class="
                    form.dentist_id && form.appointment_type_id ? 'cursor-pointer' : 'cursor-not-allowed'
                  "
                >
                  <ToggleSwitch
                    v-model="showSlotPicker"
                    :disabled="!form.dentist_id || !form.appointment_type_id"
                  />
                  <span class="text-sm text-surface-700 dark:text-surface-200">
                    {{ t('appointments.dialog.showAvailableSlots') }}
                  </span>
                </label>
                <SlotPicker
                  v-if="showSlotPicker && form.dentist_id && form.appointment_type_id"
                  :dentist-id="form.dentist_id"
                  :date="slotPickerDate"
                  :duration-minutes="form.duration_minutes"
                  @slot-selected="onSlotSelected"
                />
              </div>
            </div>
          </TabPanel>

          <TabPanel value="notes">
            <div class="flex flex-col gap-4 pt-2">
              <div class="flex flex-col gap-2">
                <label class="text-sm text-surface-700 dark:text-surface-200">
                  {{ t('appointments.dialog.reason') }}
                </label>
                <Textarea v-model="form.reason" rows="2" auto-resize maxlength="1000" />
                <span v-if="showReasonCounter" class="text-xs text-surface-400">
                  {{ t('appointments.dialog.charactersRemaining', { n: reasonRemaining }) }}
                </span>
              </div>

              <div class="flex flex-col gap-2">
                <label class="text-sm text-surface-700 dark:text-surface-200">
                  {{ t('appointments.dialog.notes') }}
                </label>
                <Textarea v-model="form.notes" rows="3" auto-resize maxlength="2000" />
                <span v-if="showNotesCounter" class="text-xs text-surface-400">
                  {{ t('appointments.dialog.charactersRemaining', { n: notesRemaining }) }}
                </span>
              </div>

              <div v-if="isEditMode && appointment" class="flex flex-col gap-2">
                <label class="text-sm text-surface-700 dark:text-surface-200">
                  {{ t('appointments.dialog.status') }}
                </label>
                <AppointmentStatusChip :status="appointment.status" />
                <p class="text-sm text-surface-500 dark:text-surface-400">
                  {{ t('appointments.dialog.statusNote') }}
                </p>
              </div>
            </div>
          </TabPanel>
        </TabPanels>
      </Tabs>

      <div class="flex justify-end gap-2 pt-2">
        <Button type="button" :label="t('common.cancel')" text @click="emit('update:visible', false)" />
        <Button type="submit" :label="t('common.save')" :loading="saving" />
      </div>
    </form>
  </Dialog>
</template>
