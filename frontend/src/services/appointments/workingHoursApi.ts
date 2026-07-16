import { api } from '@/lib/api'
import type { CreateWorkingHourPayload, DentistWorkingHour } from '@/types/appointment'

export const workingHoursApi = {
  async listForDentist(userId: string): Promise<DentistWorkingHour[]> {
    const { data } = await api.get<DentistWorkingHour[]>(`/dentists/${userId}/working-hours`)
    return data
  },

  async create(userId: string, payload: CreateWorkingHourPayload): Promise<DentistWorkingHour> {
    const { data } = await api.post<DentistWorkingHour>(`/dentists/${userId}/working-hours`, payload)
    return data
  },

  async remove(userId: string, id: string): Promise<void> {
    await api.delete(`/dentists/${userId}/working-hours/${id}`)
  },
}
