<script setup lang="ts">
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import Button from 'primevue/button'
import Message from 'primevue/message'
import { ImagePlus, Trash2 } from 'lucide-vue-next'

/**
 * Shared upload control for the clinic logo (Practice Settings) and user avatar (Users edit
 * dialog) — same shape as `AttachmentUpload.vue`'s dropzone (hidden native input + a triggering
 * Button; no PrimeVue `FileUpload` anywhere in this codebase), reduced to a single always-visible
 * image instead of a full dropzone, since both consumers need a persistent current-value preview
 * rather than a one-shot upload dialog. Presentation-only: the parent owns the actual
 * upload/remove API calls and passes `uploading`/`error` back in, matching this app's
 * view-owns-the-service-call convention (no dedicated Pinia store for a single settings field).
 */
withDefaults(
  defineProps<{
    imageUrl: string | null
    label: string
    shape?: 'square' | 'circle'
    size?: number
    uploading?: boolean
    error?: string | null
  }>(),
  { shape: 'square', size: 96, uploading: false, error: null },
)

const emit = defineEmits<{ select: [file: File]; remove: [] }>()

const { t } = useI18n()
const fileInput = ref<HTMLInputElement | null>(null)

const ACCEPT = 'image/jpeg,image/png,image/webp'
const MAX_BYTES = 2 * 1024 * 1024

const clientError = ref<string | null>(null)

function onFileInputChange(event: Event) {
  const input = event.target as HTMLInputElement
  const file = input.files?.[0] ?? null
  input.value = ''
  if (!file) return

  if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
    clientError.value = t('common.imagePicker.invalidType')
    return
  }
  if (file.size > MAX_BYTES) {
    clientError.value = t('common.imagePicker.tooLarge')
    return
  }

  clientError.value = null
  emit('select', file)
}
</script>

<template>
  <div class="flex items-center gap-4">
    <div
      class="flex shrink-0 items-center justify-center overflow-hidden border border-surface-200 bg-surface-100 dark:border-surface-700 dark:bg-surface-800"
      :class="shape === 'circle' ? 'rounded-full' : 'rounded-lg'"
      :style="{ width: `${size}px`, height: `${size}px` }"
    >
      <img v-if="imageUrl" :src="imageUrl" :alt="label" class="h-full w-full object-cover" />
      <ImagePlus v-else :size="size / 3" class="text-surface-400" />
    </div>

    <div class="flex flex-col gap-2">
      <div class="flex gap-2">
        <Button
          type="button"
          size="small"
          outlined
          :label="t('common.imagePicker.upload')"
          :loading="uploading"
          @click="fileInput?.click()"
        />
        <Button
          v-if="imageUrl"
          type="button"
          size="small"
          text
          severity="danger"
          :label="t('common.imagePicker.remove')"
          :disabled="uploading"
          @click="emit('remove')"
        >
          <template #icon="{ class: iconClass }">
            <Trash2 :size="14" :class="iconClass" />
          </template>
        </Button>
        <input ref="fileInput" type="file" :accept="ACCEPT" class="hidden" @change="onFileInputChange" />
      </div>
      <Message v-if="clientError" severity="error" size="small">{{ clientError }}</Message>
      <Message v-else-if="error" severity="error" size="small">{{ error }}</Message>
    </div>
  </div>
</template>
