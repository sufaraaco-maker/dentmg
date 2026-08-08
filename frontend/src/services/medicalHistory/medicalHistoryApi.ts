import { api } from '@/lib/api'
import type {
  CreateAllergyPayload,
  CreateMedicalConditionPayload,
  CreateMedicationPayload,
  PaginatedMedicalHistory,
  PatientAllergy,
  PatientMedicalCondition,
  PatientMedication,
  UpdateAllergyPayload,
  UpdateMedicalConditionPayload,
  UpdateMedicationPayload,
} from '@/types/medicalHistory'

/**
 * One API module for all three Medical History entities, mirroring the backend's single
 * `MedicalHistoryController`/`MedicalHistoryService` shape (design doc §6.3/§6.4) — allergies,
 * conditions, and medications are one logical feature, not three near-identical modules.
 * Every list endpoint is paginated server-side (design doc §11.2), `page` defaults to 1 when
 * omitted.
 */
export const medicalHistoryApi = {
  async listAllergies(patientId: string, page?: number): Promise<PaginatedMedicalHistory<PatientAllergy>> {
    const { data } = await api.get<PaginatedMedicalHistory<PatientAllergy>>(
      `/patients/${patientId}/allergies`,
      { params: { page } },
    )
    return data
  },

  async createAllergy(patientId: string, payload: CreateAllergyPayload): Promise<PatientAllergy> {
    const { data } = await api.post<PatientAllergy>(`/patients/${patientId}/allergies`, payload)
    return data
  },

  async updateAllergy(id: string, payload: UpdateAllergyPayload): Promise<PatientAllergy> {
    const { data } = await api.put<PatientAllergy>(`/allergies/${id}`, payload)
    return data
  },

  async removeAllergy(id: string): Promise<void> {
    await api.delete(`/allergies/${id}`)
  },

  async listConditions(
    patientId: string,
    page?: number,
  ): Promise<PaginatedMedicalHistory<PatientMedicalCondition>> {
    const { data } = await api.get<PaginatedMedicalHistory<PatientMedicalCondition>>(
      `/patients/${patientId}/medical-conditions`,
      { params: { page } },
    )
    return data
  },

  async createCondition(
    patientId: string,
    payload: CreateMedicalConditionPayload,
  ): Promise<PatientMedicalCondition> {
    const { data } = await api.post<PatientMedicalCondition>(
      `/patients/${patientId}/medical-conditions`,
      payload,
    )
    return data
  },

  async updateCondition(id: string, payload: UpdateMedicalConditionPayload): Promise<PatientMedicalCondition> {
    const { data } = await api.put<PatientMedicalCondition>(`/medical-conditions/${id}`, payload)
    return data
  },

  async removeCondition(id: string): Promise<void> {
    await api.delete(`/medical-conditions/${id}`)
  },

  async listMedications(patientId: string, page?: number): Promise<PaginatedMedicalHistory<PatientMedication>> {
    const { data } = await api.get<PaginatedMedicalHistory<PatientMedication>>(
      `/patients/${patientId}/medications`,
      { params: { page } },
    )
    return data
  },

  async createMedication(patientId: string, payload: CreateMedicationPayload): Promise<PatientMedication> {
    const { data } = await api.post<PatientMedication>(`/patients/${patientId}/medications`, payload)
    return data
  },

  async updateMedication(id: string, payload: UpdateMedicationPayload): Promise<PatientMedication> {
    const { data } = await api.put<PatientMedication>(`/medications/${id}`, payload)
    return data
  },

  async removeMedication(id: string): Promise<void> {
    await api.delete(`/medications/${id}`)
  },
}
