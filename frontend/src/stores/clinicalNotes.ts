import { defineStore } from 'pinia'
import { reactive, ref } from 'vue'
import { clinicalNotesApi } from '@/services/clinicalNotes'
import type { ClinicalNote, CreateClinicalNotePayload, UpdateClinicalNotePayload } from '@/types/clinicalNote'

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
 */
export const useClinicalNotesStore = defineStore('clinicalNotes', () => {
  const cache = reactive(new Map<string, ClinicalNote>())
  const loadedPatientIds = reactive(new Set<string>())
  const loading = ref(false)
  const error = ref<string | null>(null)

  function upsert(note: ClinicalNote) {
    cache.set(note.id, note)
  }

  /** All cached notes for one patient, most recently created first — backs the Notes List tab. */
  function notesForPatient(patientId: string): ClinicalNote[] {
    return Array.from(cache.values())
      .filter((note) => note.patient_id === patientId)
      .sort((a, b) => b.created_at.localeCompare(a.created_at))
  }

  async function fetchForPatient(patientId: string, force = false): Promise<void> {
    if (loadedPatientIds.has(patientId) && !force) return

    loading.value = true
    error.value = null

    try {
      const notes = await clinicalNotesApi.list(patientId)
      notes.forEach(upsert)
      loadedPatientIds.add(patientId)
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

  async function create(patientId: string, payload: CreateClinicalNotePayload): Promise<ClinicalNote> {
    const created = await clinicalNotesApi.create(patientId, payload)
    upsert(created)
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
    loadedPatientIds.clear()
    loading.value = false
    error.value = null
  }

  return {
    cache,
    loading,
    error,
    notesForPatient,
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
