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
import ImageThumbnail from './ImageThumbnail.vue'
import UploadImagesDialog from './UploadImagesDialog.vue'
import EditImageDialog from './EditImageDialog.vue'
import ImageLightbox from './ImageLightbox.vue'
import { deletePatientImage, fetchPatientImages } from '@/services/imaging'
import { useAuthStore } from '@/stores/auth'
import { toLocalDateString } from '@/lib/date'
import { TOOTH_CODES, toothDisplayName } from '@/lib/teeth'
import type { ImageType, PatientImage } from '@/types/imaging'

/**
 * Patient Detail's Imaging tab (design doc §8) — a patient-scoped panel, not a top-level sidebar
 * item (unlike Laboratory/Inventory), since images are always viewed in the context of one patient.
 * No Pinia store: each patient's gallery is fetched directly, mirroring `LabCasesView.vue`'s
 * direct-`api`-call pattern rather than a globally-cached store, since a gallery is inherently
 * paginated per-patient data, not small reference data worth caching app-wide.
 */
const props = defineProps<{ patientId: string }>()

const { t } = useI18n()
const confirm = useConfirm()
const toast = useToast()
const auth = useAuthStore()

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

const images = ref<PatientImage[]>([])
const totalRecords = ref(0)
const perPage = ref(30)
const page = ref(1)
const loading = ref(false)

const typeFilter = ref<ImageType | null>(null)
const toothFilter = ref<string | null>(null)
const takenFrom = ref<Date | null>(null)
const takenTo = ref<Date | null>(null)

const uploadDialogVisible = ref(false)
const editingImage = ref<PatientImage | null>(null)
const lightboxVisible = ref(false)
const lightboxIndex = ref(0)

async function fetchImages() {
  loading.value = true
  try {
    const { data, meta } = await fetchPatientImages(
      props.patientId,
      {
        image_type: typeFilter.value ?? undefined,
        tooth_number: toothFilter.value ?? undefined,
        taken_from: takenFrom.value ? toLocalDateString(takenFrom.value) : undefined,
        taken_to: takenTo.value ? toLocalDateString(takenTo.value) : undefined,
      },
      page.value,
    )
    // Defensive against an unexpected response shape (e.g. a proxy/cache serving stale content) —
    // never let a malformed response corrupt the gallery to a non-array `images.value`.
    images.value = data ?? []
    totalRecords.value = meta?.total ?? 0
    perPage.value = meta?.per_page ?? perPage.value
  } catch {
    toast.add({ severity: 'error', summary: t('imaging.loadError'), life: 3000 })
  } finally {
    loading.value = false
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

function onUpdated(updated: PatientImage) {
  images.value = images.value.map((image) => (image.id === updated.id ? updated : image))
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
        await deletePatientImage(image.id)
        images.value = images.value.filter((item) => item.id !== image.id)
        totalRecords.value -= 1
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
        <Button
          v-if="canWrite"
          :label="t('imaging.upload')"
          icon="pi pi-camera"
          size="small"
          @click="uploadDialogVisible = true"
        />
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

      <p v-else-if="!images.length" class="text-sm text-surface-500">{{ t('imaging.empty') }}</p>

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
