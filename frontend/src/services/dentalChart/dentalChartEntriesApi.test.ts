import { beforeEach, describe, expect, it, vi } from 'vitest'
import { api } from '@/lib/api'
import { dentalChartEntriesApi } from './dentalChartEntriesApi'
import type { DentalChartEntry } from '@/types/dentalChart'

vi.mock('@/lib/api', () => ({
  api: { get: vi.fn(), post: vi.fn(), put: vi.fn(), delete: vi.fn() },
}))

const mockedApi = vi.mocked(api)

function makeEntry(overrides: Partial<DentalChartEntry> = {}): DentalChartEntry {
  return {
    id: 'entry-1',
    patient_id: 'patient-1',
    tooth_number: '16',
    dentition_type: 'permanent',
    surfaces: null,
    status: 'active',
    notes: null,
    recorded_at: '2026-07-20T09:00:00+00:00',
    completed_at: null,
    cancelled_at: null,
    created_at: '2026-07-20T09:00:00+00:00',
    updated_at: '2026-07-20T09:00:00+00:00',
    ...overrides,
  }
}

beforeEach(() => {
  vi.clearAllMocks()
})

describe('dentalChartEntriesApi.list', () => {
  it('returns the bare array (deliberately not paginated) and forwards filters as query params', async () => {
    const entries = [makeEntry()]
    mockedApi.get.mockResolvedValueOnce({ data: entries })

    const result = await dentalChartEntriesApi.list('patient-1', { status: 'planned', tooth_number: '16' })

    expect(result).toEqual(entries)
    expect(mockedApi.get).toHaveBeenCalledWith('/patients/patient-1/dental-chart-entries', {
      params: { status: 'planned', tooth_number: '16' },
    })
  })

  it('defaults to no filters', async () => {
    mockedApi.get.mockResolvedValueOnce({ data: [] })

    await dentalChartEntriesApi.list('patient-1')

    expect(mockedApi.get).toHaveBeenCalledWith('/patients/patient-1/dental-chart-entries', { params: {} })
  })
})

describe('dentalChartEntriesApi.create', () => {
  it('posts to the patient-nested route', async () => {
    const created = makeEntry()
    mockedApi.post.mockResolvedValueOnce({ data: created })

    const result = await dentalChartEntriesApi.create('patient-1', {
      dental_condition_id: 'condition-1',
      dentist_id: 'dentist-1',
      tooth_number: '16',
      status: 'active',
    })

    expect(result).toEqual(created)
    expect(mockedApi.post).toHaveBeenCalledWith('/patients/patient-1/dental-chart-entries', {
      dental_condition_id: 'condition-1',
      dentist_id: 'dentist-1',
      tooth_number: '16',
      status: 'active',
    })
  })

  it('rethrows a typed error for a 422 with a recognized code', async () => {
    mockedApi.post.mockRejectedValueOnce({
      response: { data: { message: 'locked', code: 'dental_chart_entry_locked' } },
    })

    await expect(
      dentalChartEntriesApi.create('patient-1', {
        dental_condition_id: 'condition-1',
        dentist_id: 'dentist-1',
        tooth_number: '16',
        status: 'active',
      }),
    ).rejects.toEqual(expect.objectContaining({ code: 'dental_chart_entry_locked' }))
  })
})

describe('dentalChartEntriesApi.update', () => {
  it('puts to the entry-level route (not nested under patient)', async () => {
    const updated = makeEntry({ notes: 'Follow-up needed' })
    mockedApi.put.mockResolvedValueOnce({ data: updated })

    const result = await dentalChartEntriesApi.update('entry-1', { notes: 'Follow-up needed' })

    expect(result).toEqual(updated)
    expect(mockedApi.put).toHaveBeenCalledWith('/dental-chart-entries/entry-1', { notes: 'Follow-up needed' })
  })
})

describe('dentalChartEntriesApi.complete / cancel', () => {
  it('complete() posts to the /complete transition endpoint', async () => {
    const completed = makeEntry({ status: 'completed' })
    mockedApi.post.mockResolvedValueOnce({ data: completed })

    const result = await dentalChartEntriesApi.complete('entry-1')

    expect(result).toEqual(completed)
    expect(mockedApi.post).toHaveBeenCalledWith('/dental-chart-entries/entry-1/complete')
  })

  it('cancel() posts to the /cancel transition endpoint', async () => {
    const cancelled = makeEntry({ status: 'cancelled' })
    mockedApi.post.mockResolvedValueOnce({ data: cancelled })

    const result = await dentalChartEntriesApi.cancel('entry-1')

    expect(result).toEqual(cancelled)
    expect(mockedApi.post).toHaveBeenCalledWith('/dental-chart-entries/entry-1/cancel')
  })

  it('rethrows a typed error when a transition is invalid', async () => {
    mockedApi.post.mockRejectedValueOnce({
      response: { data: { message: 'invalid', code: 'invalid_status_transition' } },
    })

    await expect(dentalChartEntriesApi.complete('entry-1')).rejects.toEqual(
      expect.objectContaining({ code: 'invalid_status_transition' }),
    )
  })
})

describe('dentalChartEntriesApi.remove', () => {
  it('deletes the correct id', async () => {
    mockedApi.delete.mockResolvedValueOnce({ data: undefined })

    await dentalChartEntriesApi.remove('entry-1')

    expect(mockedApi.delete).toHaveBeenCalledWith('/dental-chart-entries/entry-1')
  })
})
