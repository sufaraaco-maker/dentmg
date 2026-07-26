<script setup lang="ts">
import { reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from 'primevue/usetoast'
import Dialog from 'primevue/dialog'
import Select from 'primevue/select'
import Textarea from 'primevue/textarea'
import Button from 'primevue/button'
import Message from 'primevue/message'
import DentistSelect from '@/components/appointments/DentistSelect.vue'
import { useClinicalNotesStore } from '@/stores/clinicalNotes'
import { isClinicalNoteError } from '@/services/clinicalNotes'
import { useDialogFocusRestore } from '@/composables/useDialogFocusRestore'
import { CLINICAL_NOTE_TYPES } from '@/types/clinicalNote'
import type { ClinicalNote } from '@/types/clinicalNote'

const CHIEF_COMPLAINT_MAX = 1000

/**
 * Create Clinical Note dialog — always creates a `draft` note for the patient fixed by the host
 * tab, mirroring `CreateTreatmentPlanDialog.vue`'s shape exactly: no patient picker, no edit mode,
 * three-way error handling (typed `ClinicalNoteError` / 422 field errors / generic toast). Only
 * asks for the minimum to start a draft (dentist, note type, optional chief complaint) — the full
 * SOAP body is filled in on the dedicated Note Detail view afterward, same "create then detail"
 * flow as Treatment Plans.
 */
const props = defineProps<{
  visible: boolean
  patientId: string
}>()

const emit = defineEmits<{
  'update:visible': [value: boolean]
  saved: [note: ClinicalNote]
}>()

const { t } = useI18n()
const toast = useToast()
const clinicalNotesStore = useClinicalNotesStore()

const saving = ref(false)
const errors = ref<Record<string, string[]>>({})

function emptyForm() {
  return {
    dentist_id: null as string | null,
    note_type: null as (typeof CLINICAL_NOTE_TYPES)[number] | null,
    chief_complaint: '',
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

const chiefComplaintRemaining = ref(CHIEF_COMPLAINT_MAX)
watch(
  () => form.chief_complaint,
  (value) => {
    chiefComplaintRemaining.value = CHIEF_COMPLAINT_MAX - value.length
  },
)

function validate(): boolean {
  const nextErrors: Record<string, string[]> = {}
  if (!form.dentist_id) nextErrors.dentist_id = [t('clinicalNotes.dialog.fieldRequired')]
  if (!form.note_type) nextErrors.note_type = [t('clinicalNotes.dialog.fieldRequired')]
  errors.value = nextErrors
  return Object.keys(nextErrors).length === 0
}

async function submit() {
  if (!validate()) return

  saving.value = true
  errors.value = {}

  try {
    const saved = await clinicalNotesStore.create(props.patientId, {
      dentist_id: form.dentist_id!,
      note_type: form.note_type!,
      chief_complaint: form.chief_complaint || null,
    })

    toast.add({ severity: 'success', summary: t('clinicalNotes.dialog.saved'), life: 3000 })
    emit('saved', saved)
    emit('update:visible', false)
  } catch (err: unknown) {
    if (isClinicalNoteError(err)) {
      toast.add({ severity: 'error', summary: err.message, life: 4000 })
    } else if ((err as { response?: { status?: number } })?.response?.status === 422) {
      errors.value =
        (err as { response: { data: { errors?: Record<string, string[]> } } }).response.data.errors ?? {}
    } else {
      toast.add({ severity: 'error', summary: t('clinicalNotes.dialog.saveError'), life: 3000 })
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
    :header="t('clinicalNotes.dialog.createTitle')"
    class="w-full max-w-lg"
    @update:visible="(v) => emit('update:visible', v)"
  >
    <form class="flex flex-col gap-4" @submit.prevent="submit">
      <div class="flex flex-col gap-2">
        <label class="text-sm text-surface-700 dark:text-surface-200">{{
          t('clinicalNotes.dialog.dentist')
        }}</label>
        <DentistSelect v-model="form.dentist_id" :invalid="!!errors.dentist_id" autofocus />
        <Message v-if="errors.dentist_id" severity="error" size="small">{{ errors.dentist_id[0] }}</Message>
      </div>

      <div class="flex flex-col gap-2">
        <label class="text-sm text-surface-700 dark:text-surface-200">{{
          t('clinicalNotes.dialog.noteType')
        }}</label>
        <Select
          v-model="form.note_type"
          :options="CLINICAL_NOTE_TYPES"
          :option-label="(value: string) => t(`clinicalNotes.type.${value}`)"
          :invalid="!!errors.note_type"
          :placeholder="t('clinicalNotes.dialog.selectNoteType')"
          fluid
        />
        <Message v-if="errors.note_type" severity="error" size="small">{{ errors.note_type[0] }}</Message>
      </div>

      <div class="flex flex-col gap-2">
        <label class="text-sm text-surface-700 dark:text-surface-200">{{
          t('clinicalNotes.detail.chiefComplaint')
        }}</label>
        <Textarea v-model="form.chief_complaint" rows="2" auto-resize maxlength="1000" />
        <span v-if="chiefComplaintRemaining < CHIEF_COMPLAINT_MAX * 0.2" class="text-xs text-surface-400">
          {{ t('clinicalNotes.dialog.charactersRemaining', { n: chiefComplaintRemaining }) }}
        </span>
        <Message v-if="errors.chief_complaint" severity="error" size="small">{{
          errors.chief_complaint[0]
        }}</Message>
      </div>

      <div class="flex flex-wrap items-center justify-end gap-2 pt-2">
        <Button type="button" :label="t('common.cancel')" text @click="emit('update:visible', false)" />
        <Button type="submit" :label="t('common.save')" :loading="saving" />
      </div>
    </form>
  </Dialog>
</template>
