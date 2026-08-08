<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from 'primevue/usetoast'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import DatePicker from 'primevue/datepicker'
import Textarea from 'primevue/textarea'
import Message from 'primevue/message'
import Button from 'primevue/button'
import ToggleSwitch from 'primevue/toggleswitch'
import { useMedicalHistoryStore } from '@/stores/medicalHistory'
import { useDialogFocusRestore } from '@/composables/useDialogFocusRestore'
import { parseLocalDate, toLocalDateString } from '@/lib/date'
import type { PatientMedication } from '@/types/medicalHistory'

const props = defineProps<{
  visible: boolean
  patientId: string
  medication?: PatientMedication | null
}>()

const emit = defineEmits<{ 'update:visible': [value: boolean]; saved: [] }>()

const { t } = useI18n()
const toast = useToast()
const store = useMedicalHistoryStore()

const isEditMode = computed(() => Boolean(props.medication))
const saving = ref(false)
const errors = ref<Record<string, string[]>>({})

function emptyForm() {
  return {
    medication_name: '',
    dosage: '',
    frequency: '',
    is_current: true,
    start_date: null as Date | null,
    end_date: null as Date | null,
    notes: '',
  }
}

const form = reactive(emptyForm())

watch(
  () => props.visible,
  (visible) => {
    if (!visible) return

    errors.value = {}
    const source = props.medication

    if (source) {
      Object.assign(form, {
        medication_name: source.medication_name,
        dosage: source.dosage ?? '',
        frequency: source.frequency ?? '',
        is_current: source.is_current,
        start_date: source.start_date ? parseLocalDate(source.start_date) : null,
        end_date: source.end_date ? parseLocalDate(source.end_date) : null,
        notes: source.notes ?? '',
      })
    } else {
      Object.assign(form, emptyForm())
    }
  },
  { immediate: true },
)

useDialogFocusRestore(() => props.visible)

function validate(): boolean {
  const nextErrors: Record<string, string[]> = {}
  if (!form.medication_name.trim()) nextErrors.medication_name = [t('medicalHistory.dialog.fieldRequired')]
  if (form.start_date && form.end_date && form.end_date < form.start_date) {
    nextErrors.end_date = [t('medicalHistory.medications.endDateBeforeStart')]
  }
  errors.value = nextErrors
  return Object.keys(nextErrors).length === 0
}

async function submit() {
  if (!validate()) return

  saving.value = true
  errors.value = {}

  const payload = {
    medication_name: form.medication_name,
    dosage: form.dosage || null,
    frequency: form.frequency || null,
    is_current: form.is_current,
    start_date: form.start_date ? toLocalDateString(form.start_date) : null,
    end_date: form.end_date ? toLocalDateString(form.end_date) : null,
    notes: form.notes || null,
  }

  try {
    if (isEditMode.value) {
      await store.updateMedication(props.medication!.id, payload)
    } else {
      await store.createMedication(props.patientId, payload)
    }

    toast.add({ severity: 'success', summary: t('medicalHistory.dialog.saved'), life: 3000 })
    emit('saved')
    emit('update:visible', false)
  } catch (err: unknown) {
    if ((err as { response?: { status?: number } })?.response?.status === 422) {
      errors.value = (err as { response: { data: { errors?: Record<string, string[]> } } }).response.data.errors ?? {}
    } else {
      toast.add({ severity: 'error', summary: t('medicalHistory.dialog.saveError'), life: 3000 })
    }
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <Dialog
    :visible="visible"
    modal
    :header="
      isEditMode ? t('medicalHistory.medications.editTitle') : t('medicalHistory.medications.createTitle')
    "
    class="w-full max-w-lg"
    @update:visible="(v) => emit('update:visible', v)"
  >
    <form class="flex flex-col gap-4" @submit.prevent="submit">
      <div class="flex flex-col gap-2">
        <label class="text-sm text-surface-700 dark:text-surface-200">{{
          t('medicalHistory.medications.medicationName')
        }}</label>
        <InputText v-model="form.medication_name" :invalid="!!errors.medication_name" fluid />
        <Message v-if="errors.medication_name" severity="error" size="small">{{
          errors.medication_name[0]
        }}</Message>
      </div>

      <div class="grid grid-cols-2 gap-4">
        <div class="flex flex-col gap-2">
          <label class="text-sm text-surface-700 dark:text-surface-200">{{
            t('medicalHistory.medications.dosage')
          }}</label>
          <InputText v-model="form.dosage" fluid />
        </div>
        <div class="flex flex-col gap-2">
          <label class="text-sm text-surface-700 dark:text-surface-200">{{
            t('medicalHistory.medications.frequency')
          }}</label>
          <InputText v-model="form.frequency" fluid />
        </div>
      </div>

      <div class="grid grid-cols-2 gap-4">
        <div class="flex flex-col gap-2">
          <label class="text-sm text-surface-700 dark:text-surface-200">{{
            t('medicalHistory.medications.startDate')
          }}</label>
          <DatePicker v-model="form.start_date" date-format="yy-mm-dd" show-icon fluid />
        </div>
        <div class="flex flex-col gap-2">
          <label class="text-sm text-surface-700 dark:text-surface-200">{{
            t('medicalHistory.medications.endDate')
          }}</label>
          <DatePicker v-model="form.end_date" date-format="yy-mm-dd" show-icon fluid :invalid="!!errors.end_date" />
          <Message v-if="errors.end_date" severity="error" size="small">{{ errors.end_date[0] }}</Message>
        </div>
      </div>

      <div class="flex items-center gap-3">
        <ToggleSwitch v-model="form.is_current" input-id="is_current" />
        <label for="is_current" class="text-sm text-surface-700 dark:text-surface-200">{{
          t('medicalHistory.medications.current')
        }}</label>
      </div>

      <div class="flex flex-col gap-2">
        <label class="text-sm text-surface-700 dark:text-surface-200">{{ t('medicalHistory.dialog.notes') }}</label>
        <Textarea v-model="form.notes" rows="3" auto-resize maxlength="2000" />
      </div>

      <div class="flex flex-wrap items-center justify-end gap-2 pt-2">
        <Button type="button" :label="t('common.cancel')" text @click="emit('update:visible', false)" />
        <Button type="submit" :label="t('common.save')" :loading="saving" />
      </div>
    </form>
  </Dialog>
</template>
