import { beforeEach, describe, expect, it, vi } from 'vitest'
import { api } from '@/lib/api'
import { timeOffApi } from './timeOffApi'
import type { DentistTimeOff } from '@/types/appointment'

vi.mock('@/lib/api', () => ({
  api: { get: vi.fn(), post: vi.fn(), delete: vi.fn() },
}))

const mockedApi = vi.mocked(api)

function makeEntry(overrides: Partial<DentistTimeOff> = {}): DentistTimeOff {
  return {
    id: 'to-1',
    user_id: 'dentist-1',
    start_at: '2026-08-01T00:00:00+00:00',
    end_at: '2026-08-05T00:00:00+00:00',
    reason: 'Vacation',
    ...overrides,
  }
}

beforeEach(() => {
  vi.clearAllMocks()
})

describe('timeOffApi', () => {
  it('listForDentist() requests the correct sub-resource', async () => {
    mockedApi.get.mockResolvedValueOnce({ data: [makeEntry()] })

    const result = await timeOffApi.listForDentist('dentist-1')

    expect(result).toEqual([makeEntry()])
    expect(mockedApi.get).toHaveBeenCalledWith('/dentists/dentist-1/time-off')
  })

  it('create() posts to the dentist-scoped endpoint', async () => {
    mockedApi.post.mockResolvedValueOnce({ data: makeEntry() })

    await timeOffApi.create('dentist-1', {
      start_at: '2026-08-01T00:00:00+00:00',
      end_at: '2026-08-05T00:00:00+00:00',
      reason: 'Vacation',
    })

    expect(mockedApi.post).toHaveBeenCalledWith('/dentists/dentist-1/time-off', {
      start_at: '2026-08-01T00:00:00+00:00',
      end_at: '2026-08-05T00:00:00+00:00',
      reason: 'Vacation',
    })
  })

  it('remove() deletes the specific entry for that dentist', async () => {
    mockedApi.delete.mockResolvedValueOnce({ data: undefined })

    await timeOffApi.remove('dentist-1', 'to-1')

    expect(mockedApi.delete).toHaveBeenCalledWith('/dentists/dentist-1/time-off/to-1')
  })
})
