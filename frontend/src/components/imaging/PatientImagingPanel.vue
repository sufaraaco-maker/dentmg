<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import Card from 'primevue/card'
import Button from 'primevue/button'
import Select from 'primevue/select'
import DatePicker from 'primevue/datepicker'
import Paginator from 'primevue/paginator'
import Skeleton from 'primevue/skeleton'
import { Camera, Images } from 'lucide-vue-next'
import ImageThumbnail from './ImageThumbnail.vue'
import UploadImagesDialog from './UploadImagesDialog.vue'
import EditImageDialog from './EditImageDialog.vue'
import ImageLightbox from './ImageLightbox.vue'
import EmptyState from '@/components/common/EmptyState.vue'
import { usePatientImagesStore } from '@/stores/patientImages'
import { useAuthStore } from '@/stores/auth'
import { toLocalDateString } from '@/lib/date'
import { TOOTH_CODES, toothDisplayName } from '@/lib/teeth'
import type { ImageType, PatientImage } from '@/types/imaging'

/**
 * Patient Detail's Imaging tab (design doc §8) — a patient-scoped panel, not a top-level sidebar
 * item (unlike Laboratory/Inventory), since images are always viewed in the context of one patient.
 * Backed by `patientImages.ts` (Phase 2.1, design doc §14.3) — data/loading/error state lives in
 * the store, filter form-state stays local here (it drives the fetch, it isn't fetched data).
 */
const props = defineProps<{ patientId: string }>()

const { t } = useI18n()
const confirm = useConfirm()
const toast = useToast()
const auth = useAuthStore()
const imagesStore = usePatientImagesStore()

// Design doc §5 / Approval Log item 4: admin+dentist+receptionist for create/update, admin-only delete.
const canWrite = computed(() => auth.isAdmin || auth.isDentist || auth.isReceptionist)
const canDelete = computed(() => auth.isAdmin)

const IMAGE_TYPES: ImageType[] = [
  'intraoral_photo',
  'extraoral_photo',
  'xray_periapical',
  'xray_bitewing',
  'xray_panoramic',
  'xray_cephalometric',
  'other',
]

const typeFilterOptions = computed(() => [
  { label: t('imaging.allTypes'), value: null },
  ...IMAGE_TYPES.map((value) => ({ label: t(`imaging.types.${value}`), value })),
])

const toothFilterOptions = [
  { label: t('imaging.allTeeth'), value: null },
  ...TOOTH_CODES.map((code) => ({ label: `${code} — ${toothDisplayName(code)}`, value: code })),
]

const images = computed(() => imagesStore.images)
const totalRecords = computed(() => imagesStore.meta.total)
const perPage = computed(() => imagesStore.meta.perPage)
const loading = computed(() => imagesStore.loading)
const page = ref(1)

const typeFilter = ref<ImageType | null>(null)
const toothFilter = ref<string | null>(null)
const takenFrom = ref<Date | null>(null)
const takenTo = ref<Date | null>(null)

const uploadDialogVisible = ref(false)
const editingImage = ref<PatientImage | null>(null)
const lightboxVisible = ref(false)
const lightboxIndex = ref(0)

async function fetchImages() {
  await imagesStore.fetchForPatient(
    props.patientId,
    {
      image_type: typeFilter.value ?? undefined,
      tooth_number: toothFilter.value ?? undefined,
      taken_from: takenFrom.value ? toLocalDateString(takenFrom.value) : undefined,
      taken_to: takenTo.value ? toLocalDateString(takenTo.value) : undefined,
    },
    page.value,
  )
  if (imagesStore.error) {
    toast.add({ severity: 'error', summary: t('imaging.loadError'), life: 3000 })
  }
}

function onPage(event: { page: number }) {
  page.value = event.page + 1
  fetchImages()
}

watch([typeFilter, toothFilter, takenFrom, takenTo], () => {
  page.value = 1
  fetchImages()
})

watch(
  () => props.patientId,
  () => {
    page.value = 1
    fetchImages()
  },
)

onMounted(fetchImages)

function onUploaded() {
  page.value = 1
  fetchImages()
  toast.add({ severity: 'success', summary: t('imaging.uploaded'), life: 3000 })
}

function onUpdated() {
  // `EditImageDialog` already writes the updated image into `patientImages.ts`'s store state
  // (§14.3) — `images` here is a computed over that same store, so no local mutation is needed.
  toast.add({ severity: 'success', summary: t('imaging.updated'), life: 3000 })
}

function openLightbox(index: number) {
  lightboxIndex.value = index
  lightboxVisible.value = true
}

function askDelete(image: PatientImage) {
  confirm.require({
    message: t('imaging.deleteConfirmMessage'),
    header: t('imaging.deleteConfirmHeader'),
    icon: 'pi pi-exclamation-triangle',
    acceptClass: 'p-button-danger',
    acceptLabel: t('common.delete'),
    rejectLabel: t('common.cancel'),
    accept: async () => {
      try {
        await imagesStore.remove(image.id)
        toast.add({ severity: 'success', summary: t('imaging.deleted'), life: 3000 })
      } catch {
        toast.add({ severity: 'error', summary: t('imaging.deleteError'), life: 3000 })
      }
    },
  })
}
</script>

<template>
  <Card v-bind="$attrs">
    <template #title>
      <div class="flex flex-wrap items-center justify-between gap-2">
        <span>{{ t('imaging.title') }}</span>
        <Button v-if="canWrite" :label="t('imaging.upload')" size="small" @click="uploadDialogVisible = true">
          <template #icon="{ class: iconClass }">
            <Camera :size="16" :class="iconClass" />
          </template>
        </Button>
      </div>
    </template>
    <template #content>
      <div class="mb-4 flex flex-wrap items-end gap-3">
        <div class="flex min-w-40 flex-col gap-1">
          <label for="imaging-filter-type" class="text-xs text-surface-500">{{
            t('imaging.imageType')
          }}</label>
          <Select
            v-model="typeFilter"
            input-id="imaging-filter-type"
            :options="typeFilterOptions"
            option-label="label"
            option-value="value"
          />
        </div>
        <div class="flex min-w-40 flex-col gap-1">
          <label for="imaging-filter-tooth" class="text-xs text-surface-500">{{ t('imaging.tooth') }}</label>
          <Select
            v-model="toothFilter"
            input-id="imaging-filter-tooth"
            :options="toothFilterOptions"
            option-label="label"
            option-value="value"
            filter
          />
        </div>
        <div class="flex flex-col gap-1">
          <label class="text-xs text-surface-500">{{ t('imaging.takenFrom') }}</label>
          <DatePicker v-model="takenFrom" date-format="yy-mm-dd" show-icon show-button-bar />
        </div>
        <div class="flex flex-col gap-1">
          <label class="text-xs text-surface-500">{{ t('imaging.takenTo') }}</label>
          <DatePicker v-model="takenTo" date-format="yy-mm-dd" show-icon show-button-bar />
        </div>
      </div>

      <div
        v-if="loading && !images.length"
        class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6"
      >
        <Skeleton v-for="n in 6" :key="n" class="aspect-square w-full" />
      </div>

      <EmptyState v-else-if="!images.length" :icon="Images" :title="t('imaging.empty')" />

      <div v-else class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6">
        <ImageThumbnail
          v-for="(image, index) in images"
          :key="image.id"
          :image="image"
          :can-edit="canWrite"
          :can-delete="canDelete"
          @click="openLightbox(index)"
          @edit="editingImage = image"
          @delete="askDelete(image)"
        />
      </div>

      <Paginator
        v-if="totalRecords > perPage"
        class="mt-4"
        :rows="perPage"
        :total-records="totalRecords"
        :first="(page - 1) * perPage"
        @page="onPage"
      />
    </template>
  </Card>

  <UploadImagesDialog v-model:visible="uploadDialogVisible" :patient-id="patientId" @uploaded="onUploaded" />

  <EditImageDialog
    :visible="!!editingImage"
    :image="editingImage"
    @update:visible="(v) => !v && (editingImage = null)"
    @updated="onUpdated"
  />

  <ImageLightbox
    v-if="lightboxVisible"
    v-model:visible="lightboxVisible"
    :images="images"
    :initial-index="lightboxIndex"
  />
</template>
