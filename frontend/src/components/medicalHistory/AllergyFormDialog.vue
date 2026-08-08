<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from 'primevue/usetoast'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import Select from 'primevue/select'
import Textarea from 'primevue/textarea'
import Message from 'primevue/message'
import Button from 'primevue/button'
import { useMedicalHistoryStore } from '@/stores/medicalHistory'
import { useDialogFocusRestore } from '@/composables/useDialogFocusRestore'
import { ALLERGY_SEVERITIES } from '@/types/medicalHistory'
import type { AllergySeverity, PatientAllergy } from '@/types/medicalHistory'

/**
 * Create/Edit dialog for one allergy — mirrors `ChartEntryDialog.vue`'s structure at a much
 * smaller scale (3 fields, no tabs/status-machine).
 */
const props = defineProps<{
  visible: boolean
  patientId: string
  allergy?: PatientAllergy | null
}>()

const emit = defineEmits<{ 'update:visible': [value: boolean]; saved: [] }>()

const { t } = useI18n()
const toast = useToast()
const store = useMedicalHistoryStore()

const isEditMode = computed(() => Boolean(props.allergy))
const saving = ref(false)
const errors = ref<Record<string, string[]>>({})

function emptyForm() {
  return {
    allergen: '',
    severity: null as AllergySeverity | null,
    reaction: '',
    notes: '',
  }
}

const form = reactive(emptyForm())

watch(
  () => props.visible,
  (visible) => {
    if (!visible) return

    errors.value = {}
    const source = props.allergy

    if (source) {
      Object.assign(form, {
        allergen: source.allergen,
        severity: source.severity,
        reaction: source.reaction ?? '',
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
  if (!form.allergen.trim()) nextErrors.allergen = [t('medicalHistory.dialog.fieldRequired')]
  errors.value = nextErrors
  return Object.keys(nextErrors).length === 0
}

async function submit() {
  if (!validate()) return

  saving.value = true
  errors.value = {}

  const payload = {
    allergen: form.allergen,
    severity: form.severity,
    reaction: form.reaction || null,
    notes: form.notes || null,
  }

  try {
    if (isEditMode.value) {
      await store.updateAllergy(props.allergy!.id, payload)
    } else {
      await store.createAllergy(props.patientId, payload)
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
    :header="isEditMode ? t('medicalHistory.allergies.editTitle') : t('medicalHistory.allergies.createTitle')"
    class="w-full max-w-lg"
    @update:visible="(v) => emit('update:visible', v)"
  >
    <form class="flex flex-col gap-4" @submit.prevent="submit">
      <div class="flex flex-col gap-2">
        <label class="text-sm text-surface-700 dark:text-surface-200">{{
          t('medicalHistory.allergies.allergen')
        }}</label>
        <InputText v-model="form.allergen" :invalid="!!errors.allergen" fluid />
        <Message v-if="errors.allergen" severity="error" size="small">{{ errors.allergen[0] }}</Message>
      </div>

      <div class="flex flex-col gap-2">
        <label class="text-sm text-surface-700 dark:text-surface-200">{{
          t('medicalHistory.allergies.severity')
        }}</label>
        <Select
          v-model="form.severity"
          :options="
            ALLERGY_SEVERITIES.map((s) => ({
              label: t(`medicalHistory.allergies.severities.${s}`),
              value: s,
            }))
          "
          option-label="label"
          option-value="value"
          show-clear
          fluid
        />
      </div>

      <div class="flex flex-col gap-2">
        <label class="text-sm text-surface-700 dark:text-surface-200">{{
          t('medicalHistory.allergies.reaction')
        }}</label>
        <InputText v-model="form.reaction" fluid />
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
