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
import { FileUp, X } from 'lucide-vue-next'
import { usePatientDocumentsStore } from '@/stores/patientDocuments'
import { useDialogFocusRestore } from '@/composables/useDialogFocusRestore'
import type { DocumentCategory, PatientDocument } from '@/types/documents'

/**
 * Generalized from `UploadImagesDialog.vue`'s dropzone (drag-drop + native input + progress +
 * `capture="environment"`), design doc §4.5/§16 decision. One file per upload — see
 * `StorePatientDocumentRequest`'s comment for why this doesn't batch the way Imaging's upload does:
 * a document's title/category is naturally per-file, unlike a shared-metadata batch of exposures.
 */
const props = defineProps<{ visible: boolean; patientId: string }>()

const emit = defineEmits<{
  'update:visible': [value: boolean]
  uploaded: [document: PatientDocument]
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
const fileInput = ref<HTMLInputElement | null>(null)
const isDraggingOver = ref(false)

function emptyForm() {
  return {
    file: null as File | null,
    category: 'other' as DocumentCategory,
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

const categoryOptions = computed(() =>
  CATEGORIES.map((value) => ({ label: t(`documents.categories.${value}`), value })),
)

function setFile(file: File | null) {
  form.file = file
  if (file && !form.title) form.title = file.name.replace(/\.[^.]+$/, '')
}

function onFileInputChange(event: Event) {
  const file = (event.target as HTMLInputElement).files?.[0] ?? null
  setFile(file)
  if (fileInput.value) fileInput.value.value = ''
}

function onDrop(event: DragEvent) {
  isDraggingOver.value = false
  setFile(event.dataTransfer?.files?.[0] ?? null)
}

function validate(): boolean {
  const nextErrors: Record<string, string[]> = {}

  if (!form.file) nextErrors.file = [t('documents.fieldRequired')]
  if (!form.title.trim()) nextErrors.title = [t('documents.fieldRequired')]

  errors.value = nextErrors
  return Object.keys(nextErrors).length === 0
}

async function submit() {
  if (!validate()) return

  saving.value = true
  errors.value = {}

  try {
    const document = await documentsStore.upload(props.patientId, {
      // Guaranteed non-null past validate() above.
      file: form.file!,
      category: form.category,
      title: form.title.trim(),
      notes: form.notes.trim() || null,
    })

    emit('uploaded', document)
    emit('update:visible', false)
  } catch (err: unknown) {
    const response = (err as { response?: { status?: number; data?: { errors?: Record<string, string[]> } } })
      ?.response

    if (response?.status === 422) {
      errors.value = response.data?.errors ?? {}
    } else {
      toast.add({ severity: 'error', summary: t('documents.uploadError'), life: 3000 })
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
    :header="t('documents.uploadTitle')"
    class="w-full max-w-lg"
    @update:visible="(v) => emit('update:visible', v)"
  >
    <form class="flex flex-col gap-4" @submit.prevent="submit">
      <div class="flex flex-col gap-2">
        <label class="text-sm text-surface-700 dark:text-surface-200">{{ t('documents.file') }}</label>
        <div
          class="flex flex-col items-center justify-center gap-2 rounded-lg border-2 border-dashed p-6 text-center transition-colors"
          :class="
            isDraggingOver
              ? 'border-primary-500 bg-primary-50 dark:bg-primary-950/30'
              : 'border-surface-300 dark:border-surface-600'
          "
          @dragover.prevent="isDraggingOver = true"
          @dragleave.prevent="isDraggingOver = false"
          @drop.prevent="onDrop"
        >
          <FileUp :size="32" class="text-surface-400" />
          <p class="text-sm text-surface-500">{{ t('documents.dropHint') }}</p>
          <Button
            type="button"
            :label="t('documents.chooseFile')"
            size="small"
            outlined
            @click="fileInput?.click()"
          />
          <input
            ref="fileInput"
            type="file"
            capture="environment"
            class="hidden"
            @change="onFileInputChange"
          />
        </div>
        <Message v-if="errors.file" severity="error" size="small">{{ errors.file[0] }}</Message>

        <div
          v-if="form.file"
          class="flex items-center justify-between rounded bg-surface-100 px-2 py-1 text-sm dark:bg-surface-800"
        >
          <span class="truncate">{{ form.file.name }}</span>
          <Button
            type="button"
            text
            rounded
            size="small"
            :aria-label="t('common.remove')"
            @click="setFile(null)"
          >
            <template #icon="{ class: iconClass }">
              <X :size="14" :class="iconClass" />
            </template>
          </Button>
        </div>
      </div>

      <div class="flex flex-col gap-2">
        <label for="document-title" class="text-sm text-surface-700 dark:text-surface-200">
          {{ t('documents.title') }}
        </label>
        <InputText id="document-title" v-model="form.title" fluid :invalid="!!errors.title" />
        <Message v-if="errors.title" severity="error" size="small">{{ errors.title[0] }}</Message>
      </div>

      <div class="flex flex-col gap-2">
        <label for="document-category" class="text-sm text-surface-700 dark:text-surface-200">
          {{ t('documents.category') }}
        </label>
        <Select
          v-model="form.category"
          input-id="document-category"
          :options="categoryOptions"
          option-label="label"
          option-value="value"
          fluid
        />
      </div>

      <div class="flex flex-col gap-2">
        <label for="document-notes" class="text-sm text-surface-700 dark:text-surface-200">
          {{ t('documents.notes') }}
        </label>
        <Textarea id="document-notes" v-model="form.notes" auto-resize rows="2" fluid />
      </div>

      <div class="flex justify-end gap-2 pt-2">
        <Button type="button" :label="t('common.cancel')" text @click="emit('update:visible', false)" />
        <Button type="submit" :label="t('documents.confirmUpload')" :loading="saving" />
      </div>
    </form>
  </Dialog>
</template>
