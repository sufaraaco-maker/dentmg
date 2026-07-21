import { api } from '@/lib/api'
import { rethrowDentalChartEntryError } from './errors'
import type {
  CreateDentalChartEntryPayload,
  DentalChartEntry,
  IndexDentalChartEntryParams,
  UpdateDentalChartEntryPayload,
} from '@/types/dentalChart'

export const dentalChartEntriesApi = {
  // GET /api/patients/{patient}/dental-chart-entries is deliberately not paginated — a per-patient
  // chart is a small, naturally-bounded dataset (backend plan §1.9/§14).
  async list(patientId: string, params: IndexDentalChartEntryParams = {}): Promise<DentalChartEntry[]> {
    const { data } = await api.get<DentalChartEntry[]>(`/patients/${patientId}/dental-chart-entries`, {
      params,
    })
    return data
  },

  async create(patientId: string, payload: CreateDentalChartEntryPayload): Promise<DentalChartEntry> {
    try {
      const { data } = await api.post<DentalChartEntry>(
        `/patients/${patientId}/dental-chart-entries`,
        payload,
      )
      return data
    } catch (error) {
      rethrowDentalChartEntryError(error)
    }
  },

  async update(id: string, payload: UpdateDentalChartEntryPayload): Promise<DentalChartEntry> {
    try {
      const { data } = await api.put<DentalChartEntry>(`/dental-chart-entries/${id}`, payload)
      return data
    } catch (error) {
      rethrowDentalChartEntryError(error)
    }
  },

  async complete(id: string): Promise<DentalChartEntry> {
    try {
      const { data } = await api.post<DentalChartEntry>(`/dental-chart-entries/${id}/complete`)
      return data
    } catch (error) {
      rethrowDentalChartEntryError(error)
    }
  },

  async cancel(id: string): Promise<DentalChartEntry> {
    try {
      const { data } = await api.post<DentalChartEntry>(`/dental-chart-entries/${id}/cancel`)
      return data
    } catch (error) {
      rethrowDentalChartEntryError(error)
    }
  },

  async remove(id: string): Promise<void> {
    await api.delete(`/dental-chart-entries/${id}`)
  },
}
