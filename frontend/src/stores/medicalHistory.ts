import { defineStore } from 'pinia'
import { reactive, ref } from 'vue'
import { medicalHistoryApi } from '@/services/medicalHistory'
import type {
  CreateAllergyPayload,
  CreateMedicalConditionPayload,
  CreateMedicationPayload,
  MedicalHistoryPageMeta,
  PatientAllergy,
  PatientMedicalCondition,
  PatientMedication,
  UpdateAllergyPayload,
  UpdateMedicalConditionPayload,
  UpdateMedicationPayload,
} from '@/types/medicalHistory'

const EMPTY_META: MedicalHistoryPageMeta = { current_page: 1, last_page: 1, per_page: 15, total: 0 }

/**
 * One store for all three Medical History sections (design doc §6.3's "one logical module" shape,
 * mirrored on the frontend) — there is no `GET /allergies/{id}`-style detail endpoint for any of
 * the three entities (unlike Clinical Notes' `stores/clinicalNotes.ts`), so this follows
 * `dentalChartEntries.ts`'s simpler "one active patient's current page" shape rather than an
 * id-keyed cache, with per-section pagination state layered on top (design doc §11.2 — every new
 * list endpoint is paginated).
 */
export const useMedicalHistoryStore = defineStore('medicalHistory', () => {
  const patientId = ref<string | null>(null)

  const allergies = ref<PatientAllergy[]>([])
  const allergiesMeta = reactive({ ...EMPTY_META })
  const allergiesLoading = ref(false)

  const conditions = ref<PatientMedicalCondition[]>([])
  const conditionsMeta = reactive({ ...EMPTY_META })
  const conditionsLoading = ref(false)

  const medications = ref<PatientMedication[]>([])
  const medicationsMeta = reactive({ ...EMPTY_META })
  const medicationsLoading = ref(false)

  const error = ref<string | null>(null)

  async function fetchAllergies(id: string, page = 1): Promise<void> {
    allergiesLoading.value = true
    error.value = null
    try {
      const result = await medicalHistoryApi.listAllergies(id, page)
      allergies.value = result.data
      Object.assign(allergiesMeta, result.meta)
    } catch {
      error.value = 'medicalHistory.loadError'
    } finally {
      allergiesLoading.value = false
    }
  }

  async function fetchConditions(id: string, page = 1): Promise<void> {
    conditionsLoading.value = true
    error.value = null
    try {
      const result = await medicalHistoryApi.listConditions(id, page)
      conditions.value = result.data
      Object.assign(conditionsMeta, result.meta)
    } catch {
      error.value = 'medicalHistory.loadError'
    } finally {
      conditionsLoading.value = false
    }
  }

  async function fetchMedications(id: string, page = 1): Promise<void> {
    medicationsLoading.value = true
    error.value = null
    try {
      const result = await medicalHistoryApi.listMedications(id, page)
      medications.value = result.data
      Object.assign(medicationsMeta, result.meta)
    } catch {
      error.value = 'medicalHistory.loadError'
    } finally {
      medicationsLoading.value = false
    }
  }

  /** Fetches page 1 of all three sections in parallel — the panel's initial load. */
  async function fetchForPatient(id: string, force = false): Promise<void> {
    if (patientId.value === id && !force) return

    patientId.value = id
    await Promise.all([fetchAllergies(id), fetchConditions(id), fetchMedications(id)])
  }

  // ---- Allergies ------------------------------------------------------------------------

  async function createAllergy(id: string, payload: CreateAllergyPayload): Promise<PatientAllergy> {
    const created = await medicalHistoryApi.createAllergy(id, payload)
    await fetchAllergies(id, 1)
    return created
  }

  async function updateAllergy(id: string, payload: UpdateAllergyPayload): Promise<PatientAllergy> {
    const updated = await medicalHistoryApi.updateAllergy(id, payload)
    const index = allergies.value.findIndex((a) => a.id === id)
    if (index !== -1) allergies.value[index] = updated
    return updated
  }

  /** Filters the cached row out directly rather than re-fetching — mirrors
   *  `dentalChartEntries.ts`'s `remove()` and avoids depending on this store's shared
   *  `patientId` ref, which only `fetchForPatient` (not the section-level fetchers) sets. */
  async function removeAllergy(id: string): Promise<void> {
    await medicalHistoryApi.removeAllergy(id)
    allergies.value = allergies.value.filter((a) => a.id !== id)
    allergiesMeta.total = Math.max(0, allergiesMeta.total - 1)
  }

  // ---- Medical Conditions -----------------------------------------------------------------

  async function createCondition(
    id: string,
    payload: CreateMedicalConditionPayload,
  ): Promise<PatientMedicalCondition> {
    const created = await medicalHistoryApi.createCondition(id, payload)
    await fetchConditions(id, 1)
    return created
  }

  async function updateCondition(
    id: string,
    payload: UpdateMedicalConditionPayload,
  ): Promise<PatientMedicalCondition> {
    const updated = await medicalHistoryApi.updateCondition(id, payload)
    const index = conditions.value.findIndex((c) => c.id === id)
    if (index !== -1) conditions.value[index] = updated
    return updated
  }

  async function removeCondition(id: string): Promise<void> {
    await medicalHistoryApi.removeCondition(id)
    conditions.value = conditions.value.filter((c) => c.id !== id)
    conditionsMeta.total = Math.max(0, conditionsMeta.total - 1)
  }

  // ---- Medications ------------------------------------------------------------------------

  async function createMedication(id: string, payload: CreateMedicationPayload): Promise<PatientMedication> {
    const created = await medicalHistoryApi.createMedication(id, payload)
    await fetchMedications(id, 1)
    return created
  }

  async function updateMedication(id: string, payload: UpdateMedicationPayload): Promise<PatientMedication> {
    const updated = await medicalHistoryApi.updateMedication(id, payload)
    const index = medications.value.findIndex((m) => m.id === id)
    if (index !== -1) medications.value[index] = updated
    return updated
  }

  async function removeMedication(id: string): Promise<void> {
    await medicalHistoryApi.removeMedication(id)
    medications.value = medications.value.filter((m) => m.id !== id)
    medicationsMeta.total = Math.max(0, medicationsMeta.total - 1)
  }

  function $reset() {
    patientId.value = null
    allergies.value = []
    Object.assign(allergiesMeta, EMPTY_META)
    allergiesLoading.value = false
    conditions.value = []
    Object.assign(conditionsMeta, EMPTY_META)
    conditionsLoading.value = false
    medications.value = []
    Object.assign(medicationsMeta, EMPTY_META)
    medicationsLoading.value = false
    error.value = null
  }

  return {
    patientId,
    allergies,
    allergiesMeta,
    allergiesLoading,
    conditions,
    conditionsMeta,
    conditionsLoading,
    medications,
    medicationsMeta,
    medicationsLoading,
    error,
    fetchForPatient,
    fetchAllergies,
    fetchConditions,
    fetchMedications,
    createAllergy,
    updateAllergy,
    removeAllergy,
    createCondition,
    updateCondition,
    removeCondition,
    createMedication,
    updateMedication,
    removeMedication,
    $reset,
  }
})
