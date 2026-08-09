import { api } from '@/lib/api'
import type { PaginatedPatientActivities, PatientActivityCategory } from '@/types/patientActivity'

export const patientActivitiesApi = {
  async list(
    patientId: string,
    page: number,
    category: PatientActivityCategory | null,
    perPage: number,
  ): Promise<PaginatedPatientActivities> {
    const { data } = await api.get<PaginatedPatientActivities>(`/patients/${patientId}/activities`, {
      params: { page, per_page: perPage, category: category || undefined },
    })
    return data
  },
}
