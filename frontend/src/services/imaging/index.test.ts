import { beforeEach, describe, expect, it, vi } from 'vitest'
import { api } from '@/lib/api'
import {
  deletePatientImage,
  fetchImageObjectUrl,
  fetchPatientImages,
  updatePatientImage,
  uploadImages,
} from './index'
import type { PatientImage } from '@/types/imaging'

vi.mock('@/lib/api', () => ({
  api: { get: vi.fn(), post: vi.fn(), put: vi.fn(), delete: vi.fn() },
}))

const mockedApi = vi.mocked(api)

function makeImage(overrides: Partial<PatientImage> = {}): PatientImage {
  return {
    id: 'img-1',
    patient_id: 'patient-1',
    uploaded_by: 'user-1',
    image_type: 'xray_periapical',
    tooth_number: '16',
    surfaces: null,
    taken_at: '2026-07-27',
    treatment_plan_item_id: null,
    appointment_id: null,
    mime_type: 'image/jpeg',
    file_size: 12345,
    width: 800,
    height: 600,
    notes: null,
    file_url: '/api/images/img-1/file',
    thumbnail_url: '/api/images/img-1/thumbnail',
    created_at: '2026-07-27T00:00:00+00:00',
    updated_at: '2026-07-27T00:00:00+00:00',
    ...overrides,
  }
}

beforeEach(() => {
  vi.clearAllMocks()
})

describe('fetchPatientImages', () => {
  it('passes filters and page as query params', async () => {
    mockedApi.get.mockResolvedValue({
      data: { data: [makeImage()], meta: { current_page: 1, last_page: 1, per_page: 30, total: 1 } },
    })

    const result = await fetchPatientImages(
      'patient-1',
      { image_type: 'xray_periapical', tooth_number: '16' },
      2,
    )

    expect(mockedApi.get).toHaveBeenCalledWith('/patients/patient-1/images', {
      params: {
        image_type: 'xray_periapical',
        tooth_number: '16',
        taken_from: undefined,
        taken_to: undefined,
        page: 2,
      },
    })
    expect(result.data).toHaveLength(1)
  })
})

describe('uploadImages', () => {
  it('builds a multipart FormData with shared metadata applied to every file', async () => {
    mockedApi.post.mockResolvedValue({ data: [makeImage()] })
    const file = new File(['x'], 'xray.jpg', { type: 'image/jpeg' })

    await uploadImages('patient-1', {
      images: [file],
      image_type: 'xray_periapical',
      tooth_number: '16',
      taken_at: '2026-07-27',
      notes: 'note',
    })

    expect(mockedApi.post).toHaveBeenCalledTimes(1)
    const [url, form, config] = mockedApi.post.mock.calls[0]
    expect(url).toBe('/patients/patient-1/images')
    expect(form).toBeInstanceOf(FormData)
    expect((form as FormData).get('image_type')).toBe('xray_periapical')
    expect((form as FormData).get('tooth_number')).toBe('16')
    expect((form as FormData).get('taken_at')).toBe('2026-07-27')
    expect((form as FormData).get('notes')).toBe('note')
    expect((form as FormData).getAll('images[]')).toHaveLength(1)
    expect(config).toEqual({ headers: { 'Content-Type': 'multipart/form-data' } })
  })

  it('omits optional fields entirely when not provided', async () => {
    mockedApi.post.mockResolvedValue({ data: [makeImage()] })
    const file = new File(['x'], 'xray.jpg', { type: 'image/jpeg' })

    await uploadImages('patient-1', {
      images: [file],
      image_type: 'other',
      taken_at: '2026-07-27',
    })

    const form = mockedApi.post.mock.calls[0][1] as FormData
    expect(form.get('tooth_number')).toBeNull()
    expect(form.get('notes')).toBeNull()
  })
})

describe('updatePatientImage / deletePatientImage', () => {
  it('updatePatientImage PUTs to the image endpoint', async () => {
    mockedApi.put.mockResolvedValue({ data: makeImage({ notes: 'updated' }) })

    const result = await updatePatientImage('img-1', { notes: 'updated' })

    expect(mockedApi.put).toHaveBeenCalledWith('/images/img-1', { notes: 'updated' })
    expect(result.notes).toBe('updated')
  })

  it('deletePatientImage DELETEs the image endpoint', async () => {
    mockedApi.delete.mockResolvedValue({ data: null })

    await deletePatientImage('img-1')

    expect(mockedApi.delete).toHaveBeenCalledWith('/images/img-1')
  })
})

describe('fetchImageObjectUrl', () => {
  it('fetches the URL as a blob and returns an object URL', async () => {
    const blob = new Blob(['fake image bytes'], { type: 'image/jpeg' })
    mockedApi.get.mockResolvedValue({ data: blob })
    const createObjectURL = vi.fn(() => 'blob:mock-url')
    vi.stubGlobal('URL', { ...URL, createObjectURL, revokeObjectURL: vi.fn() })

    const url = await fetchImageObjectUrl('/api/images/img-1/file')

    expect(mockedApi.get).toHaveBeenCalledWith('/api/images/img-1/file', { responseType: 'blob' })
    expect(createObjectURL).toHaveBeenCalledWith(blob)
    expect(url).toBe('blob:mock-url')

    vi.unstubAllGlobals()
  })
})
