import { api } from '@/lib/api'
import type {
  PaginatedResponse,
  PatientDocument,
  PatientDocumentFilters,
  UpdateDocumentPayload,
  UploadDocumentPayload,
} from '@/types/documents'

export async function fetchPatientDocuments(
  patientId: string,
  filters: PatientDocumentFilters,
  page: number,
): Promise<PaginatedResponse<PatientDocument>> {
  const { data } = await api.get<PaginatedResponse<PatientDocument>>(`/patients/${patientId}/documents`, {
    params: { category: filters.category || undefined, page },
  })
  return data
}

export async function uploadDocument(
  patientId: string,
  payload: UploadDocumentPayload,
): Promise<PatientDocument> {
  const form = new FormData()
  form.append('file', payload.file)
  form.append('category', payload.category)
  form.append('title', payload.title)
  if (payload.notes) form.append('notes', payload.notes)

  const { data } = await api.post<PatientDocument>(`/patients/${patientId}/documents`, form, {
    headers: { 'Content-Type': 'multipart/form-data' },
  })
  return data
}

export async function updatePatientDocument(
  documentId: string,
  payload: UpdateDocumentPayload,
): Promise<PatientDocument> {
  const { data } = await api.put<PatientDocument>(`/documents/${documentId}`, payload)
  return data
}

export async function deletePatientDocument(documentId: string): Promise<void> {
  await api.delete(`/documents/${documentId}`)
}

/**
 * Documents are served through an authenticated, policy-checked route, never a public/static URL —
 * mirrors `fetchImageObjectUrl` (a plain `<a href>`/`<img src>` can't reliably carry Sanctum's session
 * cookie cross-origin). Callers must `URL.revokeObjectURL(...)` when done.
 */
export async function fetchDocumentObjectUrl(url: string): Promise<string> {
  const { data } = await api.get<Blob>(url, { responseType: 'blob' })
  return URL.createObjectURL(data)
}
