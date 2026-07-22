import { api } from '@/lib/api'
import type {
  CreateDentalConditionPayload,
  DentalCondition,
  UpdateDentalConditionPayload,
} from '@/types/dentalChart'

export const dentalConditionsApi = {
  async list(): Promise<DentalCondition[]> {
    const { data } = await api.get<DentalCondition[]>('/dental-conditions')
    return data
  },

  async create(payload: CreateDentalConditionPayload): Promise<DentalCondition> {
    const { data } = await api.post<DentalCondition>('/dental-conditions', payload)
    return data
  },

  async update(id: string, payload: UpdateDentalConditionPayload): Promise<DentalCondition> {
    const { data } = await api.put<DentalCondition>(`/dental-conditions/${id}`, payload)
    return data
  },

  async remove(id: string): Promise<void> {
    await api.delete(`/dental-conditions/${id}`)
  },
}
