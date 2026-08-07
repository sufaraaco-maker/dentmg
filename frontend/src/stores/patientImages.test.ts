import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { deletePatientImage, fetchPatientImages, updatePatientImage, uploadImages } from '@/services/imaging'
import { usePatientImagesStore } from './patientImages'
import type { PatientImage } from '@/types/imaging'

vi.mock('@/services/imaging', () => ({
  fetchPatientImages: vi.fn(),
  uploadImages: vi.fn(),
  updatePatientImage: vi.fn(),
  deletePatientImage: vi.fn(),
}))

const mockedFetch = vi.mocked(fetchPatientImages)
const mockedUpload = vi.mocked(uploadImages)
const mockedUpdate = vi.mocked(updatePatientImage)
const mockedDelete = vi.mocked(deletePatientImage)

function makeImage(overrides: Partial<PatientImage> = {}): PatientImage {
  return {
    id: overrides.id ?? 'image-1',
    patient_id: 'patient-1',
    uploaded_by: 'user-1',
    image_type: 'intraoral_photo',
    tooth_number: null,
    surfaces: null,
    taken_at: '2026-08-01',
    treatment_plan_item_id: null,
    appointment_id: null,
    mime_type: 'image/jpeg',
    file_size: 1024,
    width: 800,
    height: 600,
    notes: null,
    file_url: '/images/image-1/file',
    thumbnail_url: '/images/image-1/thumbnail',
    created_at: '2026-08-01T09:00:00+00:00',
    updated_at: '2026-08-01T09:00:00+00:00',
    ...overrides,
  }
}

beforeEach(() => {
  setActivePinia(createPinia())
  vi.clearAllMocks()
})

describe('usePatientImagesStore.fetchForPatient', () => {
  it('populates images and meta from the response', async () => {
    mockedFetch.mockResolvedValueOnce({
      data: [makeImage()],
      meta: { current_page: 1, last_page: 2, per_page: 30, total: 31 },
    })
    const store = usePatientImagesStore()

    await store.fetchForPatient('patient-1', {}, 1)

    expect(store.images).toHaveLength(1)
    expect(store.meta).toEqual({ currentPage: 1, lastPage: 2, perPage: 30, total: 31 })
    expect(mockedFetch).toHaveBeenCalledWith('patient-1', {}, 1)
  })

  it('defensively falls back to an empty array on a malformed response', async () => {
    // @ts-expect-error — deliberately simulating a malformed API response shape
    mockedFetch.mockResolvedValueOnce({ data: null, meta: null })
    const store = usePatientImagesStore()

    await store.fetchForPatient('patient-1')

    expect(store.images).toEqual([])
    expect(store.meta.total).toBe(0)
  })

  it('sets a translation-key error on failure', async () => {
    mockedFetch.mockRejectedValueOnce(new Error('network error'))
    const store = usePatientImagesStore()

    await store.fetchForPatient('patient-1')

    expect(store.error).toBe('imaging.loadError')
    expect(store.loading).toBe(false)
  })
})

describe('usePatientImagesStore.upload', () => {
  it('delegates to the imaging service and returns the uploaded images', async () => {
    const uploaded = [makeImage()]
    mockedUpload.mockResolvedValueOnce(uploaded)
    const store = usePatientImagesStore()

    const result = await store.upload('patient-1', {
      images: [],
      image_type: 'intraoral_photo',
      taken_at: '2026-08-01',
    })

    expect(result).toEqual(uploaded)
    expect(mockedUpload).toHaveBeenCalledWith(
      'patient-1',
      expect.objectContaining({ image_type: 'intraoral_photo' }),
    )
  })
})

describe('usePatientImagesStore.update', () => {
  it('updates the image in place within the currently loaded page', async () => {
    mockedFetch.mockResolvedValueOnce({
      data: [makeImage({ notes: 'Before' })],
      meta: { current_page: 1, last_page: 1, per_page: 30, total: 1 },
    })
    const store = usePatientImagesStore()
    await store.fetchForPatient('patient-1')

    mockedUpdate.mockResolvedValueOnce(makeImage({ notes: 'After' }))
    const updated = await store.update('image-1', { notes: 'After' })

    expect(updated.notes).toBe('After')
    expect(store.images[0].notes).toBe('After')
  })
})

describe('usePatientImagesStore.remove', () => {
  it('removes the image from the list and decrements the total', async () => {
    mockedFetch.mockResolvedValueOnce({
      data: [makeImage()],
      meta: { current_page: 1, last_page: 1, per_page: 30, total: 1 },
    })
    const store = usePatientImagesStore()
    await store.fetchForPatient('patient-1')

    mockedDelete.mockResolvedValueOnce(undefined)
    await store.remove('image-1')

    expect(store.images).toHaveLength(0)
    expect(store.meta.total).toBe(0)
  })

  it('never lets the total go negative', async () => {
    const store = usePatientImagesStore()
    mockedDelete.mockResolvedValueOnce(undefined)

    await store.remove('image-1')

    expect(store.meta.total).toBe(0)
  })
})

describe('usePatientImagesStore.$reset', () => {
  it('clears images and meta back to defaults', async () => {
    mockedFetch.mockResolvedValueOnce({
      data: [makeImage()],
      meta: { current_page: 2, last_page: 2, per_page: 30, total: 31 },
    })
    const store = usePatientImagesStore()
    await store.fetchForPatient('patient-1', {}, 2)

    store.$reset()

    expect(store.images).toEqual([])
    expect(store.meta).toEqual({ currentPage: 1, lastPage: 1, perPage: 30, total: 0 })
  })
})
