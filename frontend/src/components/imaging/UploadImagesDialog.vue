<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from 'primevue/usetoast'
import Dialog from 'primevue/dialog'
import Select from 'primevue/select'
import DatePicker from 'primevue/datepicker'
import Textarea from 'primevue/textarea'
import Button from 'primevue/button'
import Message from 'primevue/message'
import { Images, X } from 'lucide-vue-next'
import { usePatientImagesStore } from '@/stores/patientImages'
import { useDialogFocusRestore } from '@/composables/useDialogFocusRestore'
import { toLocalDateString } from '@/lib/date'
import { TOOTH_CODES, toothDisplayName } from '@/lib/teeth'
import type { ImageType, PatientImage } from '@/types/imaging'

/**
 * Upload one or more images in a single batch, all sharing the same metadata (design doc §6) — the
 * common case is a set of exposures taken in the same visit. File picker or, on a mobile browser, the
 * device camera directly via the standard HTML5 `capture` attribute (design doc's PWA/mobile-first
 * check — no native app or extra library needed).
 */
const props = defineProps<{ visible: boolean; patientId: string }>()

const emit = defineEmits<{
  'update:visible': [value: boolean]
  uploaded: [images: PatientImage[]]
}>()

const { t } = useI18n()
const toast = useToast()
const imagesStore = usePatientImagesStore()

const IMAGE_TYPES: ImageType[] = [
  'intraoral_photo',
  'extraoral_photo',
  'xray_periapical',
  'xray_bitewing',
  'xray_panoramic',
  'xray_cephalometric',
  'other',
]

const toothOptions = [
  { label: t('imaging.noToothTag'), value: null },
  ...TOOTH_CODES.map((code) => ({ label: `${code} — ${toothDisplayName(code)}`, value: code })),
]

const today = new Date()
const saving = ref(false)
const errors = ref<Record<string, string[]>>({})
const fileInput = ref<HTMLInputElement | null>(null)
const isDraggingOver = ref(false)

function emptyForm() {
  return {
    files: [] as File[],
    image_type: 'intraoral_photo' as ImageType,
    tooth_number: null as string | null,
    taken_at: today,
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

const typeOptions = computed(() =>
  IMAGE_TYPES.map((value) => ({ label: t(`imaging.types.${value}`), value })),
)

function addFiles(fileList: FileList | File[] | null) {
  if (!fileList) return
  form.files = [...form.files, ...Array.from(fileList)]
}

function onFileInputChange(event: Event) {
  addFiles((event.target as HTMLInputElement).files)
  if (fileInput.value) fileInput.value.value = ''
}

function onDrop(event: DragEvent) {
  isDraggingOver.value = false
  addFiles(event.dataTransfer?.files ?? null)
}

function removeFile(index: number) {
  form.files = form.files.filter((_, i) => i !== index)
}

function validate(): boolean {
  const nextErrors: Record<string, string[]> = {}

  if (!form.files.length) nextErrors.images = [t('imaging.fieldRequired')]
  if (!form.taken_at) nextErrors.taken_at = [t('imaging.fieldRequired')]

  errors.value = nextErrors
  return Object.keys(nextErrors).length === 0
}

async function submit() {
  if (!validate()) return

  saving.value = true
  errors.value = {}

  try {
    const images = await imagesStore.upload(props.patientId, {
      images: form.files,
      image_type: form.image_type,
      tooth_number: form.tooth_number,
      taken_at: toLocalDateString(form.taken_at),
      notes: form.notes.trim() || null,
    })

    emit('uploaded', images)
    emit('update:visible', false)
  } catch (err: unknown) {
    const response = (err as { response?: { status?: number; data?: { errors?: Record<string, string[]> } } })
      ?.response

    if (response?.status === 422) {
      errors.value = response.data?.errors ?? {}
    } else {
      toast.add({ severity: 'error', summary: t('imaging.uploadError'), life: 3000 })
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
    :header="t('imaging.uploadTitle')"
    class="w-full max-w-lg"
    @update:visible="(v) => emit('update:visible', v)"
  >
    <form class="flex flex-col gap-4" @submit.prevent="submit">
      <div class="flex flex-col gap-2">
        <label class="text-sm text-surface-700 dark:text-surface-200">{{ t('imaging.files') }}</label>
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
          <Images :size="32" class="text-surface-400" />
          <p class="text-sm text-surface-500">{{ t('imaging.dropHint') }}</p>
          <div class="flex gap-2">
            <Button
              type="button"
              :label="t('imaging.chooseFiles')"
              size="small"
              outlined
              @click="fileInput?.click()"
            />
          </div>
          <input
            ref="fileInput"
            type="file"
            accept="image/jpeg,image/png,image/webp"
            multiple
            capture="environment"
            class="hidden"
            @change="onFileInputChange"
          />
        </div>
        <Message v-if="errors.images" severity="error" size="small">{{ errors.images[0] }}</Message>

        <ul v-if="form.files.length" class="flex flex-col gap-1">
          <li
            v-for="(file, index) in form.files"
            :key="`${file.name}-${index}`"
            class="flex items-center justify-between rounded bg-surface-100 px-2 py-1 text-sm dark:bg-surface-800"
          >
            <span class="truncate">{{ file.name }}</span>
            <Button
              type="button"
              text
              rounded
              size="small"
              :aria-label="t('common.remove')"
              @click="removeFile(index)"
            >
              <template #icon="{ class: iconClass }">
                <X :size="14" :class="iconClass" />
              </template>
            </Button>
          </li>
        </ul>
      </div>

      <div class="grid grid-cols-2 gap-4">
        <div class="flex flex-col gap-2">
          <label for="image-type" class="text-sm text-surface-700 dark:text-surface-200">
            {{ t('imaging.imageType') }}
          </label>
          <Select
            v-model="form.image_type"
            input-id="image-type"
            :options="typeOptions"
            option-label="label"
            option-value="value"
            fluid
          />
        </div>
        <div class="flex flex-col gap-2">
          <label class="text-sm text-surface-700 dark:text-surface-200">{{ t('imaging.takenAt') }}</label>
          <DatePicker
            v-model="form.taken_at"
            date-format="yy-mm-dd"
            :max-date="today"
            show-icon
            fluid
            :invalid="!!errors.taken_at"
          />
          <Message v-if="errors.taken_at" severity="error" size="small">{{ errors.taken_at[0] }}</Message>
        </div>
      </div>

      <div class="flex flex-col gap-2">
        <label for="image-tooth" class="text-sm text-surface-700 dark:text-surface-200">
          {{ t('imaging.tooth') }}
        </label>
        <Select
          v-model="form.tooth_number"
          input-id="image-tooth"
          :options="toothOptions"
          option-label="label"
          option-value="value"
          filter
          fluid
        />
      </div>

      <div class="flex flex-col gap-2">
        <label for="image-notes" class="text-sm text-surface-700 dark:text-surface-200">
          {{ t('imaging.notes') }}
        </label>
        <Textarea id="image-notes" v-model="form.notes" auto-resize rows="2" fluid />
      </div>

      <div class="flex justify-end gap-2 pt-2">
        <Button type="button" :label="t('common.cancel')" text @click="emit('update:visible', false)" />
        <Button type="submit" :label="t('imaging.confirmUpload')" :loading="saving" />
      </div>
    </form>
  </Dialog>
</template>
