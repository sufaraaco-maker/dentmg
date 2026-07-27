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
import { updatePatientImage } from '@/services/imaging'
import { useDialogFocusRestore } from '@/composables/useDialogFocusRestore'
import { parseLocalDate, toLocalDateString } from '@/lib/date'
import { TOOTH_CODES, toothDisplayName } from '@/lib/teeth'
import type { ImageType, PatientImage } from '@/types/imaging'

/** Metadata-only edit — the file itself is immutable once uploaded (design doc §4/§12). */
const props = defineProps<{ visible: boolean; image: PatientImage | null }>()

const emit = defineEmits<{
  'update:visible': [value: boolean]
  updated: [image: PatientImage]
}>()

const { t } = useI18n()
const toast = useToast()

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

function emptyForm() {
  return {
    image_type: 'intraoral_photo' as ImageType,
    tooth_number: null as string | null,
    taken_at: today,
    notes: '',
  }
}

const form = reactive(emptyForm())

watch(
  () => props.image,
  (image) => {
    errors.value = {}
    if (!image) return
    form.image_type = image.image_type
    form.tooth_number = image.tooth_number
    form.taken_at = parseLocalDate(image.taken_at)
    form.notes = image.notes ?? ''
  },
  { immediate: true },
)

useDialogFocusRestore(() => props.visible)

const typeOptions = computed(() =>
  IMAGE_TYPES.map((value) => ({ label: t(`imaging.types.${value}`), value })),
)

async function submit() {
  if (!props.image) return

  saving.value = true
  errors.value = {}

  try {
    const updated = await updatePatientImage(props.image.id, {
      image_type: form.image_type,
      tooth_number: form.tooth_number,
      taken_at: toLocalDateString(form.taken_at),
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
      toast.add({ severity: 'error', summary: t('imaging.updateError'), life: 3000 })
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
    :header="t('imaging.editTitle')"
    class="w-full max-w-lg"
    @update:visible="(v) => emit('update:visible', v)"
  >
    <form v-if="image" class="flex flex-col gap-4" @submit.prevent="submit">
      <div class="grid grid-cols-2 gap-4">
        <div class="flex flex-col gap-2">
          <label for="edit-image-type" class="text-sm text-surface-700 dark:text-surface-200">
            {{ t('imaging.imageType') }}
          </label>
          <Select
            v-model="form.image_type"
            input-id="edit-image-type"
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
        <label for="edit-image-tooth" class="text-sm text-surface-700 dark:text-surface-200">
          {{ t('imaging.tooth') }}
        </label>
        <Select
          v-model="form.tooth_number"
          input-id="edit-image-tooth"
          :options="toothOptions"
          option-label="label"
          option-value="value"
          filter
          fluid
        />
      </div>

      <div class="flex flex-col gap-2">
        <label for="edit-image-notes" class="text-sm text-surface-700 dark:text-surface-200">
          {{ t('imaging.notes') }}
        </label>
        <Textarea id="edit-image-notes" v-model="form.notes" auto-resize rows="2" fluid />
      </div>

      <div class="flex justify-end gap-2 pt-2">
        <Button type="button" :label="t('common.cancel')" text @click="emit('update:visible', false)" />
        <Button type="submit" :label="t('common.save')" :loading="saving" />
      </div>
    </form>
  </Dialog>
</template>
