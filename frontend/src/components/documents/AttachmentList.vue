<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import Button from 'primevue/button'
import Tag from 'primevue/tag'
import { FileText, LoaderCircle, Pencil, Trash2 } from 'lucide-vue-next'
import { useDocumentObjectUrl } from '@/composables/useDocumentObjectUrl'
import { fetchDocumentObjectUrl } from '@/services/documents'
import type { PatientDocument } from '@/types/documents'

/**
 * One row — generalized from `ImageThumbnail.vue`'s grid-cell pattern (design doc §4.5), but a row
 * rather than a grid cell: unlike Imaging's uniformly-visual photos, a document's title/category/
 * filename metadata is the primary identifying information, so a row list (mirroring Laboratory's
 * `PatientLabCasesPanel.vue` card-row convention) reads better than a photo-grid thumbnail. A small,
 * deliberate deviation from the generalization source, not an oversight — no thumbnail generation
 * exists for documents (design doc §1.3/§15), so an image-type document previews via its own full
 * file rather than a separate thumbnail.
 */
const props = defineProps<{ document: PatientDocument; canEdit: boolean; canDelete: boolean }>()

defineEmits<{ edit: []; delete: [] }>()

const { t } = useI18n()

const isImage = computed(() => props.document.mime_type.startsWith('image/'))
const previewUrl = computed(() => (isImage.value ? props.document.file_url : null))
const { objectUrl, loading } = useDocumentObjectUrl(previewUrl)

const downloading = ref(false)

async function download() {
  downloading.value = true
  try {
    const url = await fetchDocumentObjectUrl(props.document.file_url)
    const link = document.createElement('a')
    link.href = url
    link.download = props.document.original_filename
    link.click()
    URL.revokeObjectURL(url)
  } finally {
    downloading.value = false
  }
}
</script>

<template>
  <div class="flex items-center gap-3 rounded-border border border-surface-200 p-3 dark:border-surface-700">
    <button
      type="button"
      class="flex h-12 w-12 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-surface-100 focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:outline-none dark:bg-surface-800"
      :aria-label="t('documents.download')"
      @click="download"
    >
      <LoaderCircle
        v-if="downloading || (isImage && loading)"
        :size="18"
        class="animate-spin text-surface-400"
      />
      <img v-else-if="isImage && objectUrl" :src="objectUrl" alt="" class="h-full w-full object-cover" />
      <FileText v-else :size="20" class="text-surface-400" />
    </button>

    <button type="button" class="flex min-w-0 flex-1 flex-col gap-1 text-start" @click="download">
      <span class="flex items-center gap-2">
        <span class="truncate font-medium text-surface-900 dark:text-surface-0">{{ document.title }}</span>
        <Tag :value="t(`documents.categories.${document.category}`)" severity="secondary" />
      </span>
      <span class="truncate text-sm text-surface-500 dark:text-surface-400">
        {{ document.original_filename }}
      </span>
    </button>

    <div class="flex shrink-0 gap-1">
      <Button v-if="canEdit" size="small" text rounded :aria-label="t('common.edit')" @click="$emit('edit')">
        <template #icon="{ class: iconClass }">
          <Pencil :size="16" :class="iconClass" />
        </template>
      </Button>
      <Button
        v-if="canDelete"
        size="small"
        text
        rounded
        severity="danger"
        :aria-label="t('common.delete')"
        @click="$emit('delete')"
      >
        <template #icon="{ class: iconClass }">
          <Trash2 :size="16" :class="iconClass" />
        </template>
      </Button>
    </div>
  </div>
</template>
