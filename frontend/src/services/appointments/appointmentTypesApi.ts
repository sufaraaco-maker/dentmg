import { api } from '@/lib/api'
import type {
  AppointmentType,
  CreateAppointmentTypePayload,
  UpdateAppointmentTypePayload,
} from '@/types/appointment'

export const appointmentTypesApi = {
  async list(): Promise<AppointmentType[]> {
    const { data } = await api.get<AppointmentType[]>('/appointment-types')
    return data
  },

  async create(payload: CreateAppointmentTypePayload): Promise<AppointmentType> {
    const { data } = await api.post<AppointmentType>('/appointment-types', payload)
    return data
  },

  async update(id: string, payload: UpdateAppointmentTypePayload): Promise<AppointmentType> {
    const { data } = await api.put<AppointmentType>(`/appointment-types/${id}`, payload)
    return data
  },

  async remove(id: string): Promise<void> {
    await api.delete(`/appointment-types/${id}`)
  },
}
