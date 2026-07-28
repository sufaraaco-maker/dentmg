export type ImageType =
  | 'intraoral_photo'
  | 'extraoral_photo'
  | 'xray_periapical'
  | 'xray_bitewing'
  | 'xray_panoramic'
  | 'xray_cephalometric'
  | 'other'

export type ToothSurface = 'M' | 'D' | 'F' | 'L' | 'O' | 'I'

export interface PatientImage {
  id: string
  patient_id: string
  uploaded_by: string | null
  image_type: ImageType
  tooth_number: string | null
  surfaces: ToothSurface[] | null
  taken_at: string
  treatment_plan_item_id: string | null
  appointment_id: string | null
  mime_type: string
  file_size: number
  width: number | null
  height: number | null
  notes: string | null
  file_url: string
  thumbnail_url: string | null
  created_at: string
  updated_at: string
  uploaded_by_user?: { id: string; name: string } | null
}

/** Shared metadata applied to every file in a single upload batch (design doc §6). */
export interface UploadImagesPayload {
  images: File[]
  image_type: ImageType
  tooth_number?: string | null
  surfaces?: ToothSurface[] | null
  taken_at: string
  treatment_plan_item_id?: string | null
  appointment_id?: string | null
  notes?: string | null
}

export interface UpdatePatientImagePayload {
  image_type?: ImageType
  tooth_number?: string | null
  surfaces?: ToothSurface[] | null
  taken_at?: string
  treatment_plan_item_id?: string | null
  appointment_id?: string | null
  notes?: string | null
}

export interface PatientImageFilters {
  image_type?: ImageType
  tooth_number?: string
  taken_from?: string
  taken_to?: string
}

export interface PaginatedResponse<T> {
  data: T[]
  meta: { current_page: number; last_page: number; total: number; per_page: number }
}
