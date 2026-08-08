import { defineStore } from 'pinia'
import { reactive, ref } from 'vue'
import {
  deletePatientDocument,
  fetchPatientDocuments,
  updatePatientDocument,
  uploadDocument,
} from '@/services/documents'
import type {
  DocumentCategory,
  PatientDocument,
  UpdateDocumentPayload,
  UploadDocumentPayload,
} from '@/types/documents'

interface PatientPageMeta {
  currentPage: number
  lastPage: number
  perPage: number
  total: number
}

/**
 * Patient Profile's Documents tab (Phase 2.5, patient-documents-redesign-design.md §4.4) — same
 * id-keyed cache + per-patient page-tracking shape as `stores/patientLabCases.ts`, the standard
 * convention every patient-scoped tab uses (Documents has no filter/pagination complexity that would
 * push it toward `patientImages.ts`'s non-standard shape).
 */
export const usePatientDocumentsStore = defineStore('patientDocuments', () => {
  const cache = reactive(new Map<string, PatientDocument>())
  const patientPageIds = reactive(new Map<string, string[]>())
  const patientPageMeta = reactive(new Map<string, PatientPageMeta>())
  const loadedPatientPage = reactive(new Map<string, number>())
  const loading = ref(false)
  const error = ref<string | null>(null)

  function upsert(document: PatientDocument) {
    cache.set(document.id, document)
  }

  /** The most recently fetched page's documents for one patient, most recently created first. */
  function documentsForPatient(patientId: string): PatientDocument[] {
    const ids = patientPageIds.get(patientId) ?? []
    return ids
      .map((id) => cache.get(id))
      .filter((document): document is PatientDocument => !!document)
      .sort((a, b) => b.created_at.localeCompare(a.created_at))
  }

  /** Pagination metadata for the currently loaded page, for the panel's `Paginator`. */
  function pageMetaForPatient(patientId: string): PatientPageMeta {
    return patientPageMeta.get(patientId) ?? { currentPage: 1, lastPage: 1, perPage: 15, total: 0 }
  }

  async function fetchForPatient(
    patientId: string,
    page = 1,
    category: DocumentCategory | null = null,
    force = false,
  ): Promise<void> {
    if (loadedPatientPage.get(patientId) === page && !force) return

    loading.value = true
    error.value = null

    try {
      const result = await fetchPatientDocuments(patientId, { category }, page)
      result.data.forEach(upsert)
      patientPageIds.set(
        patientId,
        result.data.map((document) => document.id),
      )
      patientPageMeta.set(patientId, {
        currentPage: result.meta.current_page,
        lastPage: result.meta.last_page,
        perPage: result.meta.per_page,
        total: result.meta.total,
      })
      loadedPatientPage.set(patientId, page)
    } catch {
      error.value = 'documents.loadError'
    } finally {
      loading.value = false
    }
  }

  /** A new document always sorts to the top — refreshes page 1 so it's immediately visible
   *  regardless of which page the patient's list happened to be on. */
  async function upload(patientId: string, payload: UploadDocumentPayload): Promise<PatientDocument> {
    const created = await uploadDocument(patientId, payload)
    upsert(created)
    await fetchForPatient(patientId, 1, null, true)
    return created
  }

  async function update(documentId: string, payload: UpdateDocumentPayload): Promise<PatientDocument> {
    const updated = await updatePatientDocument(documentId, payload)
    upsert(updated)
    return updated
  }

  async function remove(documentId: string): Promise<void> {
    await deletePatientDocument(documentId)
    cache.delete(documentId)
    for (const ids of patientPageIds.values()) {
      const index = ids.indexOf(documentId)
      if (index !== -1) ids.splice(index, 1)
    }
  }

  function $reset() {
    cache.clear()
    patientPageIds.clear()
    patientPageMeta.clear()
    loadedPatientPage.clear()
    loading.value = false
    error.value = null
  }

  return {
    cache,
    loading,
    error,
    documentsForPatient,
    pageMetaForPatient,
    fetchForPatient,
    upsert,
    upload,
    update,
    remove,
    $reset,
  }
})
