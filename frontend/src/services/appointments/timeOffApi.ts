import { api } from '@/lib/api'
import type { CreateTimeOffPayload, DentistTimeOff } from '@/types/appointment'

export const timeOffApi = {
  async listForDentist(userId: string): Promise<DentistTimeOff[]> {
    const { data } = await api.get<DentistTimeOff[]>(`/dentists/${userId}/time-off`)
    return data
  },

  async create(userId: string, payload: CreateTimeOffPayload): Promise<DentistTimeOff> {
    const { data } = await api.post<DentistTimeOff>(`/dentists/${userId}/time-off`, payload)
    return data
  },

  async remove(userId: string, id: string): Promise<void> {
    await api.delete(`/dentists/${userId}/time-off/${id}`)
  },
}
