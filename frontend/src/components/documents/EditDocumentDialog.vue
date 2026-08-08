<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from 'primevue/usetoast'
import Dialog from 'primevue/dialog'
import Select from 'primevue/select'
import InputText from 'primevue/inputtext'
import Textarea from 'primevue/textarea'
import Button from 'primevue/button'
import Message from 'primevue/message'
import { usePatientDocumentsStore } from '@/stores/patientDocuments'
import { useDialogFocusRestore } from '@/composables/useDialogFocusRestore'
import type { DocumentCategory, PatientDocument } from '@/types/documents'

/** Metadata-only edit — the file itself is immutable once uploaded, mirrors `EditImageDialog.vue`. */
const props = defineProps<{ visible: boolean; document: PatientDocument | null }>()

const emit = defineEmits<{
  'update:visible': [value: boolean]
  updated: [document: PatientDocument]
}>()

const { t } = useI18n()
const toast = useToast()
const documentsStore = usePatientDocumentsStore()

const CATEGORIES: DocumentCategory[] = [
  'consent_form',
  'insurance',
  'referral',
  'clinical_summary',
  'correspondence',
  'other',
]

const saving = ref(false)
const errors = ref<Record<string, string[]>>({})

function emptyForm() {
  return {
    category: 'other' as DocumentCategory,
    title: '',
    notes: '',
  }
}

const form = reactive(emptyForm())

watch(
  () => props.document,
  (document) => {
    errors.value = {}
    if (!document) return
    form.category = document.category
    form.title = document.title
    form.notes = document.notes ?? ''
  },
  { immediate: true },
)

useDialogFocusRestore(() => props.visible)

const categoryOptions = computed(() =>
  CATEGORIES.map((value) => ({ label: t(`documents.categories.${value}`), value })),
)

async function submit() {
  if (!props.document) return

  saving.value = true
  errors.value = {}

  try {
    const updated = await documentsStore.update(props.document.id, {
      category: form.category,
      title: form.title.trim(),
      notes: form.notes.trim() || null,
    })

    emit('updated', updated)
    emit('update:visible', false)
  } catch (err: unknown) {
    const response = (err as { response?: { status?: number; data?: { errors?: Record<string, string[]> } } })
      ?.response

    if (response?.status === 422) {
      errors.value = response.data?.errors ?? {}
    } else {
      toast.add({ severity: 'error', summary: t('documents.updateError'), life: 3000 })
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
    :header="t('documents.editTitle')"
    class="w-full max-w-lg"
    @update:visible="(v) => emit('update:visible', v)"
  >
    <form v-if="document" class="flex flex-col gap-4" @submit.prevent="submit">
      <div class="flex flex-col gap-2">
        <label for="edit-document-title" class="text-sm text-surface-700 dark:text-surface-200">
          {{ t('documents.title') }}
        </label>
        <InputText id="edit-document-title" v-model="form.title" fluid :invalid="!!errors.title" />
        <Message v-if="errors.title" severity="error" size="small">{{ errors.title[0] }}</Message>
      </div>

      <div class="flex flex-col gap-2">
        <label for="edit-document-category" class="text-sm text-surface-700 dark:text-surface-200">
          {{ t('documents.category') }}
        </label>
        <Select
          v-model="form.category"
          input-id="edit-document-category"
          :options="categoryOptions"
          option-label="label"
          option-value="value"
          fluid
        />
      </div>

      <div class="flex flex-col gap-2">
        <label for="edit-document-notes" class="text-sm text-surface-700 dark:text-surface-200">
          {{ t('documents.notes') }}
        </label>
        <Textarea id="edit-document-notes" v-model="form.notes" auto-resize rows="2" fluid />
      </div>

      <div class="flex justify-end gap-2 pt-2">
        <Button type="button" :label="t('common.cancel')" text @click="emit('update:visible', false)" />
        <Button type="submit" :label="t('common.save')" :loading="saving" />
      </div>
    </form>
  </Dialog>
</template>
