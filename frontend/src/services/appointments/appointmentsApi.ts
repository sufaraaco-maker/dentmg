import { api } from '@/lib/api'
import { rethrowAppointmentError } from './errors'
import type {
  Appointment,
  AvailableSlotsParams,
  CancelAppointmentPayload,
  CreateAppointmentPayload,
  IndexAppointmentParams,
  MarkNoShowPayload,
  UpdateAppointmentPayload,
} from '@/types/appointment'

export const appointmentsApi = {
  async list(params: IndexAppointmentParams): Promise<Appointment[]> {
    const { data } = await api.get<{ data: Appointment[] }>('/appointments', { params })
    return data.data
  },

  async get(id: string): Promise<Appointment> {
    const { data } = await api.get<Appointment>(`/appointments/${id}`)
    return data
  },

  async create(payload: CreateAppointmentPayload): Promise<Appointment> {
    try {
      const { data } = await api.post<Appointment>('/appointments', payload)
      return data
    } catch (error) {
      rethrowAppointmentError(error)
    }
  },

  async update(id: string, payload: UpdateAppointmentPayload): Promise<Appointment> {
    try {
      const { data } = await api.put<Appointment>(`/appointments/${id}`, payload)
      return data
    } catch (error) {
      rethrowAppointmentError(error)
    }
  },

  async remove(id: string): Promise<void> {
    await api.delete(`/appointments/${id}`)
  },

  async confirm(id: string): Promise<Appointment> {
    try {
      const { data } = await api.post<Appointment>(`/appointments/${id}/confirm`)
      return data
    } catch (error) {
      rethrowAppointmentError(error)
    }
  },

  async checkIn(id: string): Promise<Appointment> {
    try {
      const { data } = await api.post<Appointment>(`/appointments/${id}/check-in`)
      return data
    } catch (error) {
      rethrowAppointmentError(error)
    }
  },

  async start(id: string): Promise<Appointment> {
    try {
      const { data } = await api.post<Appointment>(`/appointments/${id}/start`)
      return data
    } catch (error) {
      rethrowAppointmentError(error)
    }
  },

  async complete(id: string): Promise<Appointment> {
    try {
      const { data } = await api.post<Appointment>(`/appointments/${id}/complete`)
      return data
    } catch (error) {
      rethrowAppointmentError(error)
    }
  },

  async cancel(id: string, payload: CancelAppointmentPayload = {}): Promise<Appointment> {
    try {
      const { data } = await api.post<Appointment>(`/appointments/${id}/cancel`, payload)
      return data
    } catch (error) {
      rethrowAppointmentError(error)
    }
  },

  async noShow(id: string, payload: MarkNoShowPayload = {}): Promise<Appointment> {
    try {
      const { data } = await api.post<Appointment>(`/appointments/${id}/no-show`, payload)
      return data
    } catch (error) {
      rethrowAppointmentError(error)
    }
  },

  async availableSlots(params: AvailableSlotsParams): Promise<string[]> {
    const { data } = await api.get<{ slots: string[] }>('/available-slots', { params })
    return data.slots
  },
}
