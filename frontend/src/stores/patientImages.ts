import { defineStore } from 'pinia'
import { reactive, ref } from 'vue'
import { deletePatientImage, fetchPatientImages, updatePatientImage, uploadImages } from '@/services/imaging'
import type {
  PatientImage,
  PatientImageFilters,
  UpdatePatientImagePayload,
  UploadImagesPayload,
} from '@/types/imaging'

/**
 * Phase 2.1 architecture cleanup (design doc §14.3): wraps the existing `services/imaging`
 * functions in a store, giving Imaging the same caching/reactivity shape as every other tab.
 * Backend is untouched — pure frontend refactor.
 *
 * Deliberately does *not* follow the `fetchForPatient(patientId, force)` convention the other
 * patient-scoped stores use (design doc §14.2): a gallery is filtered *and* paginated, so every
 * filter/page change is a genuine new fetch, not a cache-hit check — the same reasoning the
 * pre-store version of this panel already documented for why Imaging never cached app-wide.
 */
export const usePatientImagesStore = defineStore('patientImages', () => {
  const images = ref<PatientImage[]>([])
  const meta = reactive({ currentPage: 1, lastPage: 1, perPage: 30, total: 0 })
  const loading = ref(false)
  const error = ref<string | null>(null)

  async function fetchForPatient(
    patientId: string,
    filters: PatientImageFilters = {},
    page = 1,
  ): Promise<void> {
    loading.value = true
    error.value = null

    try {
      const response = await fetchPatientImages(patientId, filters, page)
      // Defensive against an unexpected response shape (e.g. a proxy/cache serving stale
      // content) — never let a malformed response corrupt the gallery to a non-array value.
      images.value = response.data ?? []
      meta.currentPage = response.meta?.current_page ?? page
      meta.lastPage = response.meta?.last_page ?? 1
      meta.perPage = response.meta?.per_page ?? meta.perPage
      meta.total = response.meta?.total ?? 0
    } catch {
      error.value = 'imaging.loadError'
    } finally {
      loading.value = false
    }
  }

  async function upload(patientId: string, payload: UploadImagesPayload): Promise<PatientImage[]> {
    return uploadImages(patientId, payload)
  }

  async function update(imageId: string, payload: UpdatePatientImagePayload): Promise<PatientImage> {
    const updated = await updatePatientImage(imageId, payload)
    images.value = images.value.map((image) => (image.id === updated.id ? updated : image))
    return updated
  }

  async function remove(imageId: string): Promise<void> {
    await deletePatientImage(imageId)
    images.value = images.value.filter((image) => image.id !== imageId)
    meta.total = Math.max(0, meta.total - 1)
  }

  function $reset() {
    images.value = []
    meta.currentPage = 1
    meta.lastPage = 1
    meta.perPage = 30
    meta.total = 0
    loading.value = false
    error.value = null
  }

  return { images, meta, loading, error, fetchForPatient, upload, update, remove, $reset }
})
