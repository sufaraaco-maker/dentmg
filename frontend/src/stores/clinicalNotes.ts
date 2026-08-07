import { defineStore } from 'pinia'
import { reactive, ref } from 'vue'
import { clinicalNotesApi } from '@/services/clinicalNotes'
import type { ClinicalNote, CreateClinicalNotePayload, UpdateClinicalNotePayload } from '@/types/clinicalNote'

interface PatientPageMeta {
  currentPage: number
  lastPage: number
  perPage: number
  total: number
}

/**
 * A single id-keyed cache serving both the Patient Clinical Notes tab's list (`notesForPatient`)
 * and the dedicated Note Detail route's single-resource fetch (`fetchOne`) — mirrors
 * `stores/treatmentPlans.ts`'s identical shape/reasoning exactly (a real
 * `GET /clinical-notes/{note}` detail endpoint reachable independently of any patient's list ever
 * having been fetched, e.g. a direct/bookmarked navigation to Note Detail).
 *
 * Every mutation below upserts the response directly with no follow-up re-fetch, because the
 * backend deliberately returns the full updated `ClinicalNote` (addendums eager-loaded) from every
 * mutation endpoint (design doc §9), same as Treatment Plans/Invoices/Payments.
 *
 * Phase 2.1 (design doc §11/§14.4): the patient-scoped list is now paginated server-side — see
 * `treatmentPlans.ts`'s identical comment for why `notesForPatient` reads `patientPageIds`
 * (the most recently fetched page's ids) rather than filtering the whole cache.
 */
export const useClinicalNotesStore = defineStore('clinicalNotes', () => {
  const cache = reactive(new Map<string, ClinicalNote>())
  const patientPageIds = reactive(new Map<string, string[]>())
  const patientPageMeta = reactive(new Map<string, PatientPageMeta>())
  const loadedPatientPage = reactive(new Map<string, number>())
  const loading = ref(false)
  const error = ref<string | null>(null)

  function upsert(note: ClinicalNote) {
    cache.set(note.id, note)
  }

  /** The most recently fetched page's notes for one patient, most recently created first. */
  function notesForPatient(patientId: string): ClinicalNote[] {
    const ids = patientPageIds.get(patientId) ?? []
    return ids
      .map((id) => cache.get(id))
      .filter((note): note is ClinicalNote => !!note)
      .sort((a, b) => b.created_at.localeCompare(a.created_at))
  }

  /** Pagination metadata for the currently loaded page, for the panel's `Paginator`. */
  function pageMetaForPatient(patientId: string): PatientPageMeta {
    return patientPageMeta.get(patientId) ?? { currentPage: 1, lastPage: 1, perPage: 15, total: 0 }
  }

  async function fetchForPatient(patientId: string, page = 1, force = false): Promise<void> {
    if (loadedPatientPage.get(patientId) === page && !force) return

    loading.value = true
    error.value = null

    try {
      const result = await clinicalNotesApi.list(patientId, page)
      result.data.forEach(upsert)
      patientPageIds.set(
        patientId,
        result.data.map((note) => note.id),
      )
      patientPageMeta.set(patientId, {
        currentPage: result.meta.current_page,
        lastPage: result.meta.last_page,
        perPage: result.meta.per_page,
        total: result.meta.total,
      })
      loadedPatientPage.set(patientId, page)
    } catch {
      error.value = 'clinicalNotes.loadError'
    } finally {
      loading.value = false
    }
  }

  async function fetchOne(id: string): Promise<ClinicalNote> {
    const note = await clinicalNotesApi.get(id)
    upsert(note)
    return note
  }

  /** A new note always sorts to the top (most recently created) — refreshes page 1 so it's
   *  immediately visible regardless of which page the patient's list happened to be on. */
  async function create(patientId: string, payload: CreateClinicalNotePayload): Promise<ClinicalNote> {
    const created = await clinicalNotesApi.create(patientId, payload)
    upsert(created)
    await fetchForPatient(patientId, 1, true)
    return created
  }

  async function update(id: string, payload: UpdateClinicalNotePayload): Promise<ClinicalNote> {
    const updated = await clinicalNotesApi.update(id, payload)
    upsert(updated)
    return updated
  }

  /** Idempotent server-side (design doc §8) — safe to call even if the cached copy is already signed. */
  async function sign(id: string): Promise<ClinicalNote> {
    const note = await clinicalNotesApi.sign(id)
    upsert(note)
    return note
  }

  async function addAddendum(id: string, body: string): Promise<ClinicalNote> {
    const note = await clinicalNotesApi.addAddendum(id, body)
    upsert(note)
    return note
  }

  async function remove(id: string): Promise<void> {
    await clinicalNotesApi.remove(id)
    cache.delete(id)
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
    notesForPatient,
    pageMetaForPatient,
    fetchForPatient,
    fetchOne,
    create,
    update,
    sign,
    addAddendum,
    remove,
    $reset,
  }
})
