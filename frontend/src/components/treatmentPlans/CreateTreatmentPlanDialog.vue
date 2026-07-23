<script setup lang="ts">
import { reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from 'primevue/usetoast'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import Textarea from 'primevue/textarea'
import Button from 'primevue/button'
import Message from 'primevue/message'
import DentistSelect from '@/components/appointments/DentistSelect.vue'
import { useTreatmentPlansStore } from '@/stores/treatmentPlans'
import { isTreatmentPlanError } from '@/services/treatmentPlans'
import { useDialogFocusRestore } from '@/composables/useDialogFocusRestore'
import type { TreatmentPlan } from '@/types/treatmentPlan'

const NOTES_MAX = 2000

/**
 * Create Treatment Plan dialog (design doc §11, Step 6 scope) — always creates a `draft` plan for
 * the patient fixed by the host tab, so unlike `AppointmentDialog.vue` there is no patient picker
 * and no edit mode; structurally closest to `ChartEntryDialog.vue`'s no-patient-picker shape and
 * its three-way error handling (typed `TreatmentPlanError` / 422 field errors / generic toast).
 */
const props = defineProps<{
  visible: boolean
  patientId: string
}>()

const emit = defineEmits<{
  'update:visible': [value: boolean]
  saved: [plan: TreatmentPlan]
}>()

const { t } = useI18n()
const toast = useToast()
const treatmentPlansStore = useTreatmentPlansStore()

const saving = ref(false)
const errors = ref<Record<string, string[]>>({})

function emptyForm() {
  return {
    dentist_id: null as string | null,
    title: '',
    notes: '',
  }
}

const form = reactive(emptyForm())

watch(
  () => props.visible,
  (visible) => {
    if (!visible) return
    errors.value = {}
    Object.assign(form, emptyForm())
  },
  { immediate: true },
)

useDialogFocusRestore(() => props.visible)

const notesRemaining = ref(NOTES_MAX)
watch(
  () => form.notes,
  (notes) => {
    notesRemaining.value = NOTES_MAX - notes.length
  },
)

function validate(): boolean {
  const nextErrors: Record<string, string[]> = {}
  if (!form.dentist_id) nextErrors.dentist_id = [t('treatmentPlans.dialog.fieldRequired')]
  errors.value = nextErrors
  return Object.keys(nextErrors).length === 0
}

async function submit() {
  if (!validate()) return

  saving.value = true
  errors.value = {}

  try {
    const saved = await treatmentPlansStore.create(props.patientId, {
      dentist_id: form.dentist_id!,
      title: form.title || null,
      notes: form.notes || null,
    })

    toast.add({ severity: 'success', summary: t('treatmentPlans.dialog.saved'), life: 3000 })
    emit('saved', saved)
    emit('update:visible', false)
  } catch (err: unknown) {
    if (isTreatmentPlanError(err)) {
      toast.add({ severity: 'error', summary: err.message, life: 4000 })
    } else if ((err as { response?: { status?: number } })?.response?.status === 422) {
      errors.value =
        (err as { response: { data: { errors?: Record<string, string[]> } } }).response.data.errors ?? {}
    } else {
      toast.add({ severity: 'error', summary: t('treatmentPlans.dialog.saveError'), life: 3000 })
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
    :header="t('treatmentPlans.dialog.createTitle')"
    class="w-full max-w-lg"
    @update:visible="(v) => emit('update:visible', v)"
  >
    <form class="flex flex-col gap-4" @submit.prevent="submit">
      <div class="flex flex-col gap-2">
        <label class="text-sm text-surface-700 dark:text-surface-200">{{
          t('treatmentPlans.dialog.dentist')
        }}</label>
        <DentistSelect v-model="form.dentist_id" :invalid="!!errors.dentist_id" autofocus />
        <Message v-if="errors.dentist_id" severity="error" size="small">{{ errors.dentist_id[0] }}</Message>
      </div>

      <div class="flex flex-col gap-2">
        <label class="text-sm text-surface-700 dark:text-surface-200">{{
          t('treatmentPlans.dialog.title')
        }}</label>
        <InputText
          v-model="form.title"
          :placeholder="t('treatmentPlans.dialog.titlePlaceholder')"
          :invalid="!!errors.title"
          fluid
        />
        <Message v-if="errors.title" severity="error" size="small">{{ errors.title[0] }}</Message>
      </div>

      <div class="flex flex-col gap-2">
        <label class="text-sm text-surface-700 dark:text-surface-200">{{
          t('treatmentPlans.dialog.notes')
        }}</label>
        <Textarea v-model="form.notes" rows="3" auto-resize maxlength="2000" />
        <span v-if="notesRemaining < NOTES_MAX * 0.2" class="text-xs text-surface-400">
          {{ t('treatmentPlans.dialog.charactersRemaining', { n: notesRemaining }) }}
        </span>
        <Message v-if="errors.notes" severity="error" size="small">{{ errors.notes[0] }}</Message>
      </div>

      <div class="flex flex-wrap items-center justify-end gap-2 pt-2">
        <Button type="button" :label="t('common.cancel')" text @click="emit('update:visible', false)" />
        <Button type="submit" :label="t('common.save')" :loading="saving" />
      </div>
    </form>
  </Dialog>
</template>
