<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import Button from 'primevue/button'
import { LoaderCircle, Pencil, Trash2 } from 'lucide-vue-next'
import { useImageObjectUrl } from '@/composables/useImageObjectUrl'
import type { PatientImage } from '@/types/imaging'

/**
 * One grid cell — a separate component (not inline in a v-for) because `useImageObjectUrl` is a
 * composable and must run at each cell's own setup scope, not once for the whole list.
 */
const props = defineProps<{ image: PatientImage; canEdit: boolean; canDelete: boolean }>()

defineEmits<{ click: []; edit: []; delete: [] }>()

const { t } = useI18n()

const source = computed(() => props.image.thumbnail_url ?? props.image.file_url)
const { objectUrl, loading } = useImageObjectUrl(source)
</script>

<template>
  <div
    class="group relative aspect-square w-full overflow-hidden rounded-lg bg-surface-100 dark:bg-surface-800"
  >
    <button
      type="button"
      class="absolute inset-0 h-full w-full focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:outline-none"
      @click="$emit('click')"
    >
      <LoaderCircle
        v-if="loading || !objectUrl"
        :size="24"
        class="absolute inset-0 m-auto animate-spin text-surface-400"
      />
      <img
        v-else
        :src="objectUrl"
        :alt="image.notes || image.image_type"
        class="h-full w-full object-cover transition-transform group-hover:scale-105"
        loading="lazy"
      />
      <span
        v-if="image.tooth_number"
        class="absolute top-1 rtl:right-1 ltr:left-1 rounded bg-surface-900/70 px-1.5 py-0.5 text-xs text-white"
      >
        {{ image.tooth_number }}
      </span>
    </button>

    <div
      v-if="canEdit || canDelete"
      class="absolute bottom-1 rtl:left-1 ltr:right-1 flex gap-1 opacity-0 transition-opacity focus-within:opacity-100 group-hover:opacity-100"
    >
      <Button
        v-if="canEdit"
        size="small"
        rounded
        severity="contrast"
        :aria-label="t('common.edit')"
        @click.stop="$emit('edit')"
      >
        <template #icon="{ class: iconClass }">
          <Pencil :size="14" :class="iconClass" />
        </template>
      </Button>
      <Button
        v-if="canDelete"
        size="small"
        rounded
        severity="danger"
        :aria-label="t('common.delete')"
        @click.stop="$emit('delete')"
      >
        <template #icon="{ class: iconClass }">
          <Trash2 :size="14" :class="iconClass" />
        </template>
      </Button>
    </div>
  </div>
</template>
