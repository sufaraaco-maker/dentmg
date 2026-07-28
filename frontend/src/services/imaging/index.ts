import { api } from '@/lib/api'
import type {
  PaginatedResponse,
  PatientImage,
  PatientImageFilters,
  UpdatePatientImagePayload,
  UploadImagesPayload,
} from '@/types/imaging'

export async function fetchPatientImages(
  patientId: string,
  filters: PatientImageFilters,
  page: number,
): Promise<PaginatedResponse<PatientImage>> {
  const { data } = await api.get<PaginatedResponse<PatientImage>>(`/patients/${patientId}/images`, {
    params: {
      image_type: filters.image_type || undefined,
      tooth_number: filters.tooth_number || undefined,
      taken_from: filters.taken_from || undefined,
      taken_to: filters.taken_to || undefined,
      page,
    },
  })
  return data
}

export async function uploadImages(patientId: string, payload: UploadImagesPayload): Promise<PatientImage[]> {
  const form = new FormData()
  payload.images.forEach((file) => form.append('images[]', file))
  form.append('image_type', payload.image_type)
  form.append('taken_at', payload.taken_at)
  if (payload.tooth_number) form.append('tooth_number', payload.tooth_number)
  if (payload.surfaces) payload.surfaces.forEach((surface) => form.append('surfaces[]', surface))
  if (payload.treatment_plan_item_id) form.append('treatment_plan_item_id', payload.treatment_plan_item_id)
  if (payload.appointment_id) form.append('appointment_id', payload.appointment_id)
  if (payload.notes) form.append('notes', payload.notes)

  const { data } = await api.post<PatientImage[]>(`/patients/${patientId}/images`, form, {
    headers: { 'Content-Type': 'multipart/form-data' },
  })
  return data
}

export async function updatePatientImage(
  imageId: string,
  payload: UpdatePatientImagePayload,
): Promise<PatientImage> {
  const { data } = await api.put<PatientImage>(`/images/${imageId}`, payload)
  return data
}

export async function deletePatientImage(imageId: string): Promise<void> {
  await api.delete(`/images/${imageId}`)
}

/**
 * Images are served through an authenticated, policy-checked route (backend design doc §9), never a
 * public/static URL — a plain `<img src>` can't reliably carry Sanctum's session cookie across the
 * frontend/backend origins the way every other `api.*` call already does via `withCredentials`. Fetch
 * through `api` like any other request and hand the caller an object URL to bind to `<img>`/CSS
 * instead. Callers must `URL.revokeObjectURL(...)` when done (see `useImageObjectUrl` composable).
 */
export async function fetchImageObjectUrl(url: string): Promise<string> {
  const { data } = await api.get<Blob>(url, { responseType: 'blob' })
  return URL.createObjectURL(data)
}
