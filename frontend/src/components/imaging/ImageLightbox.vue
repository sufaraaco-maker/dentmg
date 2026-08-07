<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import Button from 'primevue/button'
import Slider from 'primevue/slider'
import ToggleSwitch from 'primevue/toggleswitch'
import Select from 'primevue/select'
import { ChevronLeft, ChevronRight, LoaderCircle, RefreshCw, Sun, X, ZoomIn } from 'lucide-vue-next'
import { useImageObjectUrl } from '@/composables/useImageObjectUrl'
import { parseLocalDate } from '@/lib/date'
import type { PatientImage } from '@/types/imaging'

/**
 * Full-screen viewer (design doc §8). Non-destructive only (Approval Log item 2): brightness/
 * contrast/invert are CSS filters applied live, never written back to the file or the database, and
 * there is no "save adjusted view" action anywhere in this component. Zoom uses a CSS transform plus
 * native `overflow: auto` scrolling for panning once zoomed — touch-usable without a gesture library.
 */
const props = defineProps<{ visible: boolean; images: PatientImage[]; initialIndex: number }>()

const emit = defineEmits<{ 'update:visible': [value: boolean] }>()

const { t, locale } = useI18n()

const currentIndex = ref(props.initialIndex)
const brightness = ref(100)
const contrast = ref(100)
const inverted = ref(false)
const zoom = ref(1)
const compareMode = ref(false)
const compareIndex = ref<number | null>(null)

watch(
  () => props.visible,
  (visible) => {
    if (!visible) return
    currentIndex.value = props.initialIndex
    resetAdjustments()
    compareMode.value = false
    compareIndex.value = null
  },
)

function resetAdjustments() {
  brightness.value = 100
  contrast.value = 100
  inverted.value = false
  zoom.value = 1
}

// Defensive against `props.images` itself (not just an index) being unset — the parent always
// binds a real array in practice, but this component is also mounted eagerly by the caller's own
// `v-if`, so a stray render before props settle should degrade to "no image" rather than throw.
const current = computed(() => (props.images ?? [])[currentIndex.value] ?? null)
const compareImage = computed(() =>
  compareIndex.value !== null ? ((props.images ?? [])[compareIndex.value] ?? null) : null,
)

const currentUrl = computed(() => current.value?.file_url)
const { objectUrl: currentObjectUrl, loading: currentLoading } = useImageObjectUrl(currentUrl)

const compareUrl = computed(() => compareImage.value?.file_url)
const { objectUrl: compareObjectUrl, loading: compareLoading } = useImageObjectUrl(compareUrl)

const filterStyle = computed(
  () => `brightness(${brightness.value}%) contrast(${contrast.value}%) ${inverted.value ? 'invert(1)' : ''}`,
)
const transformStyle = computed(() => `scale(${zoom.value})`)

const compareOptions = computed(() =>
  (props.images ?? [])
    .map((image, index) => ({ label: formatLabel(image), value: index }))
    .filter((option) => option.value !== currentIndex.value),
)

function formatLabel(image: PatientImage): string {
  return `${t(`imaging.types.${image.image_type}`)} — ${parseLocalDate(image.taken_at).toLocaleDateString(locale.value)}`
}

function goPrev() {
  if (currentIndex.value > 0) {
    currentIndex.value -= 1
    resetAdjustments()
  }
}

function goNext() {
  if (currentIndex.value < props.images.length - 1) {
    currentIndex.value += 1
    resetAdjustments()
  }
}

function onKeydown(event: KeyboardEvent) {
  if (event.key === 'ArrowLeft') goPrev()
  else if (event.key === 'ArrowRight') goNext()
  else if (event.key === 'Escape') emit('update:visible', false)
}

function close() {
  emit('update:visible', false)
}
</script>

<template>
  <Teleport to="body">
    <div
      v-if="visible && current"
      class="fixed inset-0 z-[1100] flex flex-col bg-surface-950/95"
      role="dialog"
      aria-modal="true"
      :aria-label="t('imaging.lightbox.label')"
      tabindex="-1"
      @keydown="onKeydown"
    >
      <div class="flex items-center justify-between gap-2 p-3 text-surface-0">
        <div class="flex items-center gap-2 text-sm">
          <span>{{ formatLabel(current) }}</span>
          <span v-if="current.tooth_number" class="rounded bg-surface-0/10 px-2 py-0.5">
            {{ t('imaging.tooth') }}: {{ current.tooth_number }}
          </span>
        </div>
        <Button text rounded severity="contrast" :aria-label="t('common.close')" @click="close">
          <template #icon="{ class: iconClass }">
            <X :size="18" :class="iconClass" />
          </template>
        </Button>
      </div>

      <div class="relative flex flex-1 items-center justify-center gap-4 overflow-hidden px-2">
        <Button
          text
          rounded
          severity="contrast"
          class="rtl:rotate-180"
          :disabled="currentIndex === 0"
          :aria-label="t('imaging.lightbox.previous')"
          @click="goPrev"
        >
          <template #icon="{ class: iconClass }">
            <ChevronLeft :size="18" :class="iconClass" />
          </template>
        </Button>

        <div class="flex h-full min-w-0 flex-1 gap-4">
          <div
            class="flex flex-1 items-center justify-center overflow-auto"
            style="touch-action: pan-x pan-y"
          >
            <LoaderCircle
              v-if="currentLoading || !currentObjectUrl"
              :size="32"
              class="animate-spin text-surface-0"
            />
            <img
              v-else
              :src="currentObjectUrl"
              :alt="current.notes || current.image_type"
              class="max-h-full max-w-full origin-center"
              :style="{ filter: filterStyle, transform: transformStyle }"
            />
          </div>

          <div
            v-if="compareMode && compareImage"
            class="flex flex-1 items-center justify-center overflow-auto"
          >
            <LoaderCircle
              v-if="compareLoading || !compareObjectUrl"
              :size="32"
              class="animate-spin text-surface-0"
            />
            <img
              v-else
              :src="compareObjectUrl"
              :alt="compareImage.notes || compareImage.image_type"
              class="max-h-full max-w-full"
            />
          </div>
        </div>

        <Button
          text
          rounded
          severity="contrast"
          class="rtl:rotate-180"
          :disabled="currentIndex === images.length - 1"
          :aria-label="t('imaging.lightbox.next')"
          @click="goNext"
        >
          <template #icon="{ class: iconClass }">
            <ChevronRight :size="18" :class="iconClass" />
          </template>
        </Button>
      </div>

      <div class="flex flex-wrap items-center gap-4 border-t border-surface-0/10 p-3 text-surface-0">
        <div class="flex min-w-40 flex-1 items-center gap-2">
          <Sun :size="14" />
          <Slider v-model="brightness" :min="50" :max="150" class="flex-1" />
        </div>
        <div class="flex min-w-40 flex-1 items-center gap-2">
          <span class="inline-block size-2.5 rounded-full bg-current" aria-hidden="true"></span>
          <Slider v-model="contrast" :min="50" :max="150" class="flex-1" />
        </div>
        <div class="flex min-w-32 flex-1 items-center gap-2">
          <ZoomIn :size="14" />
          <Slider v-model="zoom" :min="1" :max="4" :step="0.1" class="flex-1" />
        </div>
        <Button
          text
          rounded
          size="small"
          severity="contrast"
          :aria-label="t('imaging.lightbox.resetAdjustments')"
          @click="resetAdjustments"
        >
          <template #icon="{ class: iconClass }">
            <RefreshCw :size="16" :class="iconClass" />
          </template>
        </Button>
        <div class="flex items-center gap-2">
          <ToggleSwitch v-model="inverted" input-id="lightbox-invert" />
          <label for="lightbox-invert" class="text-sm">{{ t('imaging.lightbox.invert') }}</label>
        </div>
        <div v-if="images.length > 1" class="flex items-center gap-2">
          <ToggleSwitch v-model="compareMode" input-id="lightbox-compare" />
          <label for="lightbox-compare" class="text-sm">{{ t('imaging.lightbox.compare') }}</label>
          <Select
            v-if="compareMode"
            v-model="compareIndex"
            :options="compareOptions"
            option-label="label"
            option-value="value"
            :placeholder="t('imaging.lightbox.selectCompare')"
            class="w-56"
          />
        </div>
      </div>
    </div>
  </Teleport>
</template>
