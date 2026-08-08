<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from 'primevue/usetoast'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import Select from 'primevue/select'
import DatePicker from 'primevue/datepicker'
import Textarea from 'primevue/textarea'
import Message from 'primevue/message'
import Button from 'primevue/button'
import { useMedicalHistoryStore } from '@/stores/medicalHistory'
import { useDialogFocusRestore } from '@/composables/useDialogFocusRestore'
import { parseLocalDate, toLocalDateString } from '@/lib/date'
import { MEDICAL_CONDITION_STATUSES } from '@/types/medicalHistory'
import type { MedicalConditionStatus, PatientMedicalCondition } from '@/types/medicalHistory'

const props = defineProps<{
  visible: boolean
  patientId: string
  condition?: PatientMedicalCondition | null
}>()

const emit = defineEmits<{ 'update:visible': [value: boolean]; saved: [] }>()

const { t } = useI18n()
const toast = useToast()
const store = useMedicalHistoryStore()

const isEditMode = computed(() => Boolean(props.condition))
const saving = ref(false)
const errors = ref<Record<string, string[]>>({})

function emptyForm() {
  return {
    condition_name: '',
    status: 'active' as MedicalConditionStatus,
    diagnosed_date: null as Date | null,
    notes: '',
  }
}

const form = reactive(emptyForm())

watch(
  () => props.visible,
  (visible) => {
    if (!visible) return

    errors.value = {}
    const source = props.condition

    if (source) {
      Object.assign(form, {
        condition_name: source.condition_name,
        status: source.status,
        diagnosed_date: source.diagnosed_date ? parseLocalDate(source.diagnosed_date) : null,
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
  if (!form.condition_name.trim()) nextErrors.condition_name = [t('medicalHistory.dialog.fieldRequired')]
  errors.value = nextErrors
  return Object.keys(nextErrors).length === 0
}

async function submit() {
  if (!validate()) return

  saving.value = true
  errors.value = {}

  const payload = {
    condition_name: form.condition_name,
    status: form.status,
    diagnosed_date: form.diagnosed_date ? toLocalDateString(form.diagnosed_date) : null,
    notes: form.notes || null,
  }

  try {
    if (isEditMode.value) {
      await store.updateCondition(props.condition!.id, payload)
    } else {
      await store.createCondition(props.patientId, payload)
    }

    toast.add({ severity: 'success', summary: t('medicalHistory.dialog.saved'), life: 3000 })
    emit('saved')
    emit('update:visible', false)
  } catch (err: unknown) {
    if ((err as { response?: { status?: number } })?.response?.status === 422) {
      errors.value =
        (err as { response: { data: { errors?: Record<string, string[]> } } }).response.data.errors ?? {}
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
      isEditMode ? t('medicalHistory.conditions.editTitle') : t('medicalHistory.conditions.createTitle')
    "
    class="w-full max-w-lg"
    @update:visible="(v) => emit('update:visible', v)"
  >
    <form class="flex flex-col gap-4" @submit.prevent="submit">
      <div class="flex flex-col gap-2">
        <label class="text-sm text-surface-700 dark:text-surface-200">{{
          t('medicalHistory.conditions.conditionName')
        }}</label>
        <InputText v-model="form.condition_name" :invalid="!!errors.condition_name" fluid />
        <Message v-if="errors.condition_name" severity="error" size="small">{{
          errors.condition_name[0]
        }}</Message>
      </div>

      <div class="flex flex-col gap-2">
        <label class="text-sm text-surface-700 dark:text-surface-200">{{
          t('medicalHistory.conditions.status')
        }}</label>
        <Select
          v-model="form.status"
          :options="
            MEDICAL_CONDITION_STATUSES.map((s) => ({
              label: t(`medicalHistory.conditions.statuses.${s}`),
              value: s,
            }))
          "
          option-label="label"
          option-value="value"
          fluid
        />
      </div>

      <div class="flex flex-col gap-2">
        <label class="text-sm text-surface-700 dark:text-surface-200">{{
          t('medicalHistory.conditions.diagnosedDate')
        }}</label>
        <DatePicker v-model="form.diagnosed_date" date-format="yy-mm-dd" show-icon fluid />
      </div>

      <div class="flex flex-col gap-2">
        <label class="text-sm text-surface-700 dark:text-surface-200">{{
          t('medicalHistory.dialog.notes')
        }}</label>
        <Textarea v-model="form.notes" rows="3" auto-resize maxlength="2000" />
      </div>

      <div class="flex flex-wrap items-center justify-end gap-2 pt-2">
        <Button type="button" :label="t('common.cancel')" text @click="emit('update:visible', false)" />
        <Button type="submit" :label="t('common.save')" :loading="saving" />
      </div>
    </form>
  </Dialog>
</template>
